# Runbook — Account Lifecycle (`account_lifecycle`)

Release/operations runbook for the **account_lifecycle** feature (the member
self-serve slice from ADR 0006: JSON **export**, reversible **deactivate /
reactivate**, and a **deletion request** with a 30-day grace window that a
scheduled worker later turns into an anonymizing **purge**). **Default-ON as of
2026-07-02** (the `account_lifecycle` flag graduated out of deploy-dark); fully
reversible via the `features` override. Follows the same conventions as
`docs/runbooks/operations.md` §2 and mirrors `docs/runbooks/server_drafts.md` /
`docs/runbooks/badge_rules.md`.

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
   no longer `pending_deletion` (reactivated, cancelled, or a status desync) — a
   non-`pending_deletion` account is **never** anonymized.
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
  action stay reachable in-session (they are not `WriteGate`-guarded), and
  deactivating **revokes all other sessions** but keeps the current one.
- **Deletion is a scheduled purge, not a hard delete.** A request starts a
  **30-day grace** window during which the account is `pending_deletion` (also
  write-blocked, other sessions revoked) and the member — or an admin — can
  **cancel** and return to `active`. Public post bodies are **preserved** under
  the Deleted-user identity (thread integrity + accepted-answer state survive);
  only PII is purged. Re-requesting after a cancel is allowed (a fresh
  `pending` row); requesting while one is already pending is a no-op.
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

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppAccountLifecycleTest.php` — exercises the
  shipped default (no override): export-without-secrets (+ `405` on GET),
  reversible deactivate/reactivate, grace-period cancel, final-admin guard,
  anonymizing purge with PII removal, and the not-`pending_deletion` skip;
  `tests/Integration/Core/AppFeatureFlagTest.php` —
  `test_account_lifecycle_carryover_defaults_on_and_is_operator_reversible`
  (default-on plus operator rollback: every lifecycle route 404 when disabled,
  core profile editing stays up; its still-dark cross-check now uses `group_dms`
  since appeals graduated to default-on on 2026-07-02).
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
