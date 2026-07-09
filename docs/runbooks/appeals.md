# Runbook — Moderation Appeals (`appeals`)

Release/operations runbook for the **appeals** feature (self-service moderation
appeals: a member appeals a moderator-removed post or a user-target moderation
action through no-JS forms on `/appeals`, and staff resolve those appeals from a
board-scoped queue on `/mod/appeals`, with reversal delegated to the owning
moderation service and an append-only appeal-event ledger). **Default-ON as of
2026-07-02** (the `appeals` flag graduated out of deploy-dark); fully reversible
via the `features` override. Policy is locked by ADR 0007. Follows the same
conventions as `docs/runbooks/operations.md` §2 and mirrors
`docs/runbooks/badge_rules.md` / `docs/runbooks/account_lifecycle.md`.

> **Golden rule:** for any logic defect (wrong eligibility, a bad reversal, a
> notification storm), **disable the `appeals` flag first** (all five
> `/appeals*` and `/mod/appeals*` routes 404; the rest of the app keeps
> serving), then investigate. Disabling is non-destructive — the
> `moderation_appeals` rows and their event history are retained and reappear
> when the flag is re-enabled.

## What the flag gates

`appeals` gates the entire appeals surface. There is **one** controller
(`AppealController`), gated **in-controller** via `requireAppeals()` (404 when
the flag is off) **before** the auth check, so a disabled flag returns 404 to
everyone (guest, member, staff, admin). The schema (`moderation_appeals` +
`moderation_appeal_events`) is additive and inert while the flag is off;
disabling the flag never touches those rows.

Routes (every POST is CSRF-protected):

- `GET  /appeals` — member landing: lists appealable actions (each with an
  inline form) and the member's own appeals with status.
- `POST /appeals/posts/{id}` — open an appeal for a moderator-deleted post
  (field `reason`).
- `POST /appeals/modlog/{id}` — open an appeal against a user-target moderation
  action — `warn` / `suspend` / `ban` / `clear_signature` (field `reason`).
- `GET  /mod/appeals` — the board-scoped staff resolution queue.
- `POST /mod/appeals/{id}/resolve` — resolve an open appeal (fields `outcome`,
  `note`).

Member navigation: the settings rail only renders the **Appeals** link when the
flag is live (`templates/partials/settings_nav.php`). Staff navigation: the
moderation subnav's **Appeals** tab (`templates/mod/reports.php`,
`templates/mod/approvals.php`) is not itself flag-gated, so while the flag is off
that tab 404s on click; graduation makes the tab resolve.

## Roll back / re-enable

The flag lives in the `features` setting (JSON `flag => bool`); see
`docs/runbooks/operations.md` §2 for the inspect/set snippets. Disabling is the
**first response** to any defect and is non-destructive (appeals and their event
history are retained and reappear on re-enable):

```bash
# Roll back: take the appeals surface offline (merge — do not clobber other flags)
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["appeals"]=false; $r->set("features",$f);'
```

Re-enable by setting `appeals` back to `true` or removing the key (the default
is now `true`). Toggling the flag never opens, resolves, or reverses anything —
those happen only on an explicit member or staff form submit — so a flag
round-trip is safe and the appeal/event history is preserved intact. Reversals
that were already applied (post restored, ban lifted) are **not** re-reverted by
disabling the flag; they were carried out by the owning moderation service and
persist independently.

## Operating semantics (what to tell operators)

- **Two appealable target types, both time-boxed.** A member may appeal (a) a
  post of theirs that a **moderator** removed — there must be a `delete_post`
  `moderation_log` row; a self-delete is not appealable — or (b) a user-target
  moderation action (`warn` / `suspend` / `ban` / `clear_signature`). Both must
  be opened **within 30 days** of the action; older targets are rejected with a
  `422`.
- **One active appeal per target; one open user-level appeal at a time.** A
  second open appeal for the same post is refused; while a member has any open
  user-target appeal, the whole moderation-log list is suppressed. `reason` is
  required and capped at 2000 characters.
- **Board-scoped staff queue (mirrors the report queue).** Admins see **every**
  open appeal. A board moderator sees only **post** appeals in boards they
  moderate; a user who moderates nothing gets a `403`. User-target appeals
  (ban/suspend/warn/clear_signature) are **admin-only** to resolve. Authority
  comes from `board_moderators`, never a bare `users.role`.
- **Four outcomes; `reversed` actually reverses.** `outcome` ∈ `upheld` |
  `modified` | `reversed` | `dismissed` (an unknown value is a `422`). Only
  `reversed` mutates the target, and the reversal is **delegated to the owning
  service** so counters, read gates, notifications, and audit stay consistent:
  a post appeal calls `ModerationService::restorePost`; a `suspend`/`ban`
  user appeal calls `UserModerationService::lift`. The other three outcomes only
  record the decision.
- **Notification.** Resolving an appeal sends the appellant the standard
  moderation notification (in-app; email per their prefs).
- **Audit + event ledger.** Every open and every resolution writes a
  `moderation_log` row (`appeal_opened` / `appeal_resolved`, keyed
  `reason=appeal:{id}`) **and** appends a row to `moderation_appeal_events`
  (the append-only per-appeal history). Appeal records are not physically
  removed by ordinary cleanup.

## Monitoring & known limits

- **No worker, no rate limit, no counters.** Nothing runs on cron; there is no
  dedicated `RateLimitService` policy for appeals. Appeals do not feed any
  denormalized counter, so `RepairService` has nothing to reconcile for this
  feature. (Reversals reuse the moderation services, which maintain their own
  counters transactionally.)
- **Eligibility queries are bounded.** The member landing lists up to 20
  appealable posts and up to 20 appealable moderation-log actions.
- **Restore from backup with the flag disabled.** The `moderation_appeals` /
  `moderation_appeal_events` tables are authoritative operator/member content
  with no reconstructable derivation; on corruption, disable the flag and
  restore from backup.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppModerationAppealsTest.php` — the full
  member-open → one-active-appeal → board-scoped-queue → resolve/reverse flow
  (including the 30-day window, admin-only user-target resolution, and the
  board-moderator scope check); `tests/Integration/Core/AppFeatureFlagTest.php`
  — `test_appeals_carryover_defaults_on_and_is_operator_reversible` asserts the
  flag is declared **default-on** and that disabling it re-gates every appeals
  route to `404` while the core forum stays up.
- **Browser:** `docs/evidence/browser/{desktop,mobile}/44-appeals-member.png`
  (member submits an appeal) and `45-appeals-staff-queue.png` (staff resolves it
  from the board-scoped queue), driven by the appeals journey in
  `tests/browser/appeals.spec.ts` (member open → staff resolve, all through the
  no-JS forms).
- **Accessibility:** `tests/browser/a11y.spec.ts` — the member `/appeals` axe
  scan (`Submit appeal` form) plus the staff `/mod/appeals` queue scan, desktop
  + mobile, no serious/critical violations.
