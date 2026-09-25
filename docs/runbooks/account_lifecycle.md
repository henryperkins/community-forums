# Runbook — Account Lifecycle (`account_lifecycle`)

Release/operations runbook for the **account_lifecycle** feature (the member
self-serve slice from ADR 0006: JSON **export**, reversible **deactivate /
reactivate**, and a **deletion request** with a 30-day grace window that a
scheduled worker later turns into an anonymizing **purge**). **Default-ON as of
2026-07-02** (the `account_lifecycle` flag graduated out of deploy-dark); fully
reversible via the `features` override. Follows the same conventions as
`docs/runbooks/operations.md` §2. The 2026-09-20 lifecycle-restriction repair's
release diagnostics and moderation precedence are recorded here too.

> **Golden rule:** for any defect in the member-facing flows (export leak, a
> bad deactivate/delete transition), **disable the `account_lifecycle` flag
> first** (all six `/settings/account/{export,lifecycle,deactivate,reactivate,delete/*}`
> routes 404; the rest of `/settings/account` keeps serving), then investigate.
> Disabling is non-destructive — it never touches `users.status` or the
> `account_deletion_requests` ledger. **Note the one exception below: the
> `worker:purge-accounts` cron does _not_ read the flag**, so if you are rolling
> back to stop *purges*, also pause that cron.

## What the flag gates

`account_lifecycle` gates the member self-serve surface only. Every action lives
on `AccountController`, gated **in-controller** via `requireAccountLifecycle()`
(404 when the flag is off) **before** `requireUser()`, so a disabled flag returns
404 to everyone (guest and member alike) rather than bouncing guests to `/login`.
The settings rail (`templates/partials/settings_nav.php`) only renders the
**Account** link when the flag is live. Schema (`account_deletion_requests` plus
the `deactivated`/`pending_deletion`/`deleted` values on `users.status`) ships in
migration `0059_account_lifecycle.php`; disabling the flag never touches it.

Routes (all member-scoped; every POST is CSRF-protected):

- `GET  /settings/account/lifecycle` — the single state-dependent lifecycle page.
- `POST /settings/account/export` — stream the account JSON archive (a download;
  audited — never reachable as a GET, which is a `405`).
- `POST /settings/account/deactivate` — reversible deactivation (requires the
  current password).
- `POST /settings/account/reactivate` — restore a deactivated account.
- `POST /settings/account/delete/request` — start the 30-day deletion grace
  (requires the current password).
- `POST /settings/account/delete/cancel` — cancel during the grace window.

**Not gated by this flag** (they stay live when it is off): the core profile
editor at `GET/POST /settings/account`, and the scheduled purge worker.

## Pre-release restriction reconciliation

Before releasing a lifecycle or suspension repair, run these **read-only**
diagnostics against the deployment database. Save the counts and reviewed IDs
with the release record. An empty test schema is not evidence about existing
member accounts.

```sql
-- Accounts whose cached active state disagrees with durable restrictions.
SELECT u.id, u.status, u.suspended_until,
       EXISTS (
           SELECT 1 FROM bans b
           WHERE b.user_id = u.id AND b.scope = 'site' AND b.lifted_at IS NULL
             AND (b.expires_at IS NULL OR b.expires_at > UTC_TIMESTAMP())
       ) AS live_site_restriction,
       EXISTS (
           SELECT 1 FROM account_deletion_requests d
           WHERE d.user_id = u.id AND d.status = 'pending'
       ) AS pending_deletion
FROM users u
WHERE (u.status = 'active' OR (
           u.status = 'suspended' AND u.suspended_until <= UTC_TIMESTAMP()
      ))
  AND (
      EXISTS (
          SELECT 1 FROM bans b
          WHERE b.user_id = u.id AND b.scope = 'site' AND b.lifted_at IS NULL
            AND (b.expires_at IS NULL OR b.expires_at > UTC_TIMESTAMP())
      )
      OR EXISTS (
          SELECT 1 FROM account_deletion_requests d
          WHERE d.user_id = u.id AND d.status = 'pending'
      )
  )
ORDER BY u.id;

-- Suspended accounts whose cached expiry ends before a live site post hold.
-- NULL required_until means an indefinite hold is still in force.
SELECT u.id, u.suspended_until,
       CASE WHEN SUM(b.expires_at IS NULL) > 0 THEN NULL
            ELSE MAX(b.expires_at) END AS required_until,
       COUNT(*) AS live_post_restrictions
FROM users u
JOIN bans b ON b.user_id = u.id
WHERE u.status = 'suspended' AND b.scope = 'site' AND b.type = 'post'
  AND b.lifted_at IS NULL
  AND (b.expires_at IS NULL OR b.expires_at > UTC_TIMESTAMP())
GROUP BY u.id, u.suspended_until
HAVING (SUM(b.expires_at IS NULL) > 0 AND u.suspended_until IS NOT NULL)
    OR (SUM(b.expires_at IS NULL) = 0 AND u.suspended_until < MAX(b.expires_at))
ORDER BY u.id;

-- Pending requests outside the ordinary deletion/moderation states need review.
SELECT d.id AS request_id, d.user_id, d.purge_after, u.status
FROM account_deletion_requests d
JOIN users u ON u.id = d.user_id
WHERE d.status = 'pending'
  AND u.status NOT IN ('pending_deletion', 'banned', 'suspended')
ORDER BY d.user_id, d.id;
```

Every returned row requires targeted reconciliation before release. Inspect
the member's moderation history, live site restrictions, and deletion-request
audit history. Preserve independent restrictions: full site bans remain
banned; post restrictions retain their actual expiry (NULL means indefinite).
Restore a deletion hold only when its durable request is confirmed intended;
a canceled request stays canceled. Record reviewed IDs, justification, and
resulting status in an operator audit entry. Perform corrections in a
transaction, locking shared protected-owner and active-admin rowsets first
when an owner could be lost, then the target user, pending requests, and site
restrictions, each in ascending ID order. Never blanket-reactivate accounts.

The repair prevents new self-service bypasses but does not rewrite legacy
inconsistencies automatically. Normal writes still use cached account state;
recovery refuses a live site restriction even when that cache says active, and
cancellation removes only the deletion request.

## Roll back / re-enable

The flag lives in the `features` setting (JSON `flag => bool`); see
`docs/runbooks/operations.md` §2 for the inspect/set snippets. Disabling is the
**first response** to any member-flow defect and is non-destructive (account
statuses and the deletion ledger are retained and the surface reappears on
re-enable):

```bash
# Roll back: take the member lifecycle surface offline (merge — do not clobber other flags)
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["account_lifecycle"]=false; $r->set("features",$f);'
```

Re-enable by setting `account_lifecycle` back to `true` or removing the key (the
default is now `true`).

> **Rollback caveat — the purge worker ignores the flag.** `worker:purge-accounts`
> constructs `AccountLifecycleService` directly and does **not** consult
> `FeatureFlags`. Disabling the flag stops members from *starting* new deletions,
> but any request already in `pending_deletion` will still be purged once its
> grace elapses. To fully halt purges (e.g. while investigating), **pause the
> cron entry** in addition to flipping the flag. Members whose grace has not yet
> elapsed can still self-cancel only while the flag is on — so if you must both
> stop purges and let members bail out, keep the flag on and pause only the cron.

## Scheduled purge worker

```bash
php bin/console worker:purge-accounts [limit]   # default limit 100
```

Run it on the **same cron cadence as the other purge workers** (`worker:purge-ips`,
`worker:attachments`). It selects deletion requests whose `purge_after` has
elapsed (up to `limit`, default 100), and for each one, **inside its own
transaction**:

1. **Defence in depth:** re-reads `users.status` and **skips** any account that is
   no longer in `pending_deletion`, `banned`, or `suspended`. It locks and
   rechecks the **same pending request ID and its current deadline** before
   purging. A later ban/suspension cannot strand a due deletion, and a canceled
   request cannot be purged even if the cached user status still says
   `pending_deletion`. Legacy cached-active or deactivated accounts with a
   pending request are skipped for targeted reconciliation, not anonymized.
2. Marks the request purged, deletes PII/linkage rows (sessions, verifications,
   OAuth identities, preferences, board/bookmark folders, profile fields, saved
   feeds, subscriptions, notifications, TOTP/recovery/MFA, server drafts, follows,
   blocks, conversation participation, email suppressions; email-delivery rows are
   detached), and anonymizes the account to a **Deleted user** identity
   (`username=deleted-user-{id}`, `email=deleted-user-{id}@deleted.invalid`,
   `password_hash=NULL`, profile fields nulled).
3. Writes an `account_purged` `moderation_log` row with **`actor_id = NULL`** (the
   system actor).

It prints `Account purge: anonymised N due deletion(s).` and exits 0.

## Operating semantics (what to tell operators)

- **Export excludes secrets.** The archive covers profile, preferences, sessions
  *metadata*, subscriptions, notifications, reports filed, visible posts, DMs the
  requester participates in, server drafts, and audit rows — but **never**
  `password_hash` or recovery secrets. Export is a POST (it writes an
  `account_exported` audit row) and streams as
  `retroboards-account-export.json`; it is not forgeable via a GET (`405`).
- **Deactivation is reversible.** A deactivated account can still sign in and read
  but is **write-blocked** by `WriteGate` and is hidden from
  presence/leaderboards/follow-suggestions. The lifecycle page and the reactivate
  action stay reachable in-session (they are not `WriteGate`-guarded); reactivation
  requires no pending deletion or live site restriction. Deactivating **revokes
  all other sessions** but keeps the current one.
- **Deletion is a scheduled purge, not a hard delete.** A request starts a
  **30-day grace** window during which the account is `pending_deletion` (also
  write-blocked, other sessions revoked) and the member — or an admin — can
  **cancel**. Cancellation removes the deletion hold, but preserves any live
  ban/suspension instead of unconditionally returning to `active`. Public post
  bodies are **preserved** under the Deleted-user identity (thread integrity +
  accepted-answer state survive); only PII is purged. Re-requesting after a
  cancel is allowed (a fresh `pending` row) if the account has no live site
  restriction; requesting while one is already pending is a no-op.
- **Moderation and lifecycle are independent.** A suspension imposed during
  deletion grace or self-deactivation retains the `pending_deletion` or
  `deactivated` cached hold; the site restriction and its expiry still block
  early recovery. Expiry or a moderation lift does not restore ordinary writes
  through those lifecycle holds. The member must explicitly cancel deletion or
  reactivate when eligible. An existing full site ban takes precedence over a
  new suspension, including when only its durable restriction record survives
  and the cached status is stale; the ban is not downgraded to a timed
  suspension. Accounts with only a timed suspension still auto-expire normally.
- **Final-admin guard.** The last remaining active admin cannot deactivate or
  request deletion until another active admin exists (`422` with "Add another
  active admin…"). This mirrors the owner/last-admin protection elsewhere.
- **Audit trail.** Export, deactivate, reactivate, deletion request, cancel, and
  purge each write a `moderation_log` row. Self-service rows use the member as
  actor; scheduled purges use `actor_id = NULL`.

## Monitoring & known limits

- **No rate-limit policy, no denormalized counters.** These are per-account
  self-serve actions; there is no dedicated `RateLimitService` policy and nothing
  for `RepairService` to reconcile (anonymization edits authoritative rows
  directly).
- **Purge is bounded + idempotent.** `worker:purge-accounts` processes up to
  `limit` (default 100) due requests per run, each in its own transaction; a
  crash mid-run leaves already-purged accounts done and the rest for the next run.
  For a large backlog, raise the limit or run repeatedly until it reports 0.
- **Restore from backup.** `account_deletion_requests` is authoritative operator
  content with no reconstructable derivation; a purge is irreversible (PII is
  gone). On corruption, disable the flag, pause the purge cron, and restore from
  backup.

## Two-connection rehearsal

Use an empty, explicitly provisioned schema named
`retroboards_unified_lifecycle_race` accessible by the configured DB user:

```bash
DB_LIFECYCLE_RACE_DATABASE=retroboards_unified_lifecycle_race \
  MAIL_DRIVER=sendmail MAIL_FROM='' php tests/concurrency/account-lifecycle.php
```

The standalone test applies additive migrations only to that fixed schema,
creates committed uniquely named members, starts a separate PHP process for
the competing service call, observes its `FOR UPDATE` query before committing
the first transaction, and records child exit codes and final write-gate
outcomes. It runs both commit orders for deactivate/ban and cancellation/ban,
plus two owner deactivations. Cleanup deletes only IDs created by that
invocation. The ordinary PHPUnit transaction harness cannot provide this
concurrency evidence.

The combined notification repair also checks that restricted signed-in members
can reduce their own delivery preferences (N2), and that a real purge's
NULL-recipient outbox row is suppressed as `recipient_missing` while a later
valid row reaches the captured `ArrayMailer` (N3). See the
[combined evidence index](../evidence/unified-notifications-and-settings/README.md);
do not drain these fixtures into a real mail transport.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppAccountLifecycleTest.php` — exercises the
  shipped default (no override): export-without-secrets (+ `405` on GET),
  reversible deactivate/reactivate, grace-period cancel, final-admin guard,
  anonymizing purge with PII removal, and the legacy cached-active skip. The
  2026-09-20 repair additionally covers restriction precedence, cancellation
  after moderation, timed expiry, canceled-request safety and ban-surviving
  purge; the two-connection rehearsal above covers the competing writes;
  `tests/Integration/Core/AppFeatureFlagTest.php` —
  `test_account_lifecycle_carryover_defaults_on_and_is_operator_reversible`
  (default-on plus operator rollback: every lifecycle route 404 when disabled,
  core profile editing stays up; its still-dark cross-check now uses
  `expanded_files` — appeals, `group_dms`, and `link_previews` have since
  graduated).
- **Browser:** `docs/evidence/browser/{desktop,mobile}/35-account-lifecycle.png`
  (the active-state lifecycle page: export + deactivate + delete sections) and
  `36-account-deletion-scheduled.png` (the danger-zone grace/cancel state), driven
  by the `phase 4 account lifecycle` journey in `tests/browser/gate-a.spec.ts`
  (export download → deactivate → reactivate → request deletion → cancel, all
  through the no-JS forms, on a dedicated `dana` account so the destructive steps
  never touch other fixtures).
- **Accessibility:** `tests/browser/a11y.spec.ts` — the member axe scan now
  renders `/settings/account/lifecycle`, desktop + mobile, with no
  serious/critical violations.
- **Worker smoke:** `php bin/console worker:purge-accounts` →
  `Account purge: anonymised 0 due deletion(s).`, exit 0 (re-verified after the
  `ReauthGate` wiring fix; see `docs/evidence/deploy-dark-features.md` Tier 2 #4).
