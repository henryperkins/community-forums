# Runbook — Badge Rules (`badge_rules`)

Release/operations runbook for the **badge_rules** feature (an admin-only surface
for defining threshold-based badge award rules over a constrained metric
vocabulary, previewing who would qualify, and **manually** backfilling or
revoking the awards, with an append-only award/revoke ledger). **Default-ON as of
2026-07-02** (the `badge_rules` flag graduated out of deploy-dark); fully
reversible via the `features` override. Follows the same conventions as
`docs/runbooks/operations.md` §2 and mirrors `docs/runbooks/topic_workflow.md` /
`docs/runbooks/slash_giphy.md`.

> **Golden rule:** for any logic defect (bad eligibility SQL, wrong awards,
> notification storm), **disable the `badge_rules` flag first** (all nine
> `/admin/badge-rules*` routes 404; the rest of the app keeps serving), then
> investigate. Disabling is non-destructive — the `badge_rules` and
> `badge_award_history` rows are retained and reappear when the flag is
> re-enabled.

## What the flag gates

`badge_rules` gates the entire admin badge-rules surface. There is **one**
controller (`AdminBadgeRuleController`), gated **in-controller** via
`requireEnabled()` (404 when the flag is off) **before** `requireAdmin()`, so a
disabled flag returns 404 to everyone (guest, member, admin). Schema
(`badge_rules` + the append-only `badge_award_history` ledger) ships in migration
`0048_phase4_gate_a.php`; disabling the flag never touches those rows.

Routes (all admin-only; every POST is CSRF-protected):

- `GET  /admin/badge-rules` — the rules list + create-rule form.
- `POST /admin/badge-rules` — create a rule (starts **disabled**).
- `GET  /admin/badge-rules/{id}/preview` — list the users who currently qualify.
- `POST /admin/badge-rules/{id}/enable` — set `is_enabled=1` (a label; see below).
- `POST /admin/badge-rules/{id}/disable` — set `is_enabled=0`.
- `GET  /admin/badge-rules/{id}/backfill` — review the current award batch and typed confirmation.
- `POST /admin/badge-rules/{id}/backfill` — confirm and **award** up to 1000 current
  qualifiers (the only action that grants badges).
- `GET  /admin/badge-rules/{id}/revoke` — review active rule awards and typed confirmation.
- `POST /admin/badge-rules/{id}/revoke` — confirm revocation of the awards this rule owns.

The surface is linked from **Features → Badge rules** in the admin console.

## Roll back / re-enable

The flag lives in the `features` setting (JSON `flag => bool`); see
`docs/runbooks/operations.md` §2 for the inspect/set snippets. Disabling is the
**first response** to any defect and is non-destructive (rules and the award
ledger are retained and reappear on re-enable):

```bash
# Roll back: take the badge-rules admin surface offline (merge — do not clobber other flags)
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["badge_rules"]=false; $r->set("features",$f);'
```

Re-enable by setting `badge_rules` back to `true` or removing the key (the default
is now `true`). Toggling the flag never awards or revokes anything — awards change
**only** after an explicit backfill/revoke confirmation — so a flag round-trip is safe and
the `badge_award_history` ledger is preserved intact.

## Operating semantics (what to tell operators)

- **Constrained vocabulary (4 types).** A rule is `rule_type >= threshold`,
  optionally scoped to one board. `rule_type` ∈
  `post_count` | `thread_count` | `reputation` | `solved_count` (enforced in the
  service **and** as a DB `ENUM`; an unknown type is a `422`). `threshold` is
  `1..1000000`. `board_id` empty = all boards; board-scoped rules count only that
  board's posts/threads/reputation/accepted answers.
- **Enable/Disable is a label, not automation.** There is **no cron/worker** that
  awards badges. `is_enabled` records operator intent only; **awards happen solely
  when an operator reviews and confirms Backfill** (and Backfill works even on a *disabled*
  rule, as does Preview). Plan to Enable → Backfill as an explicit two-step, and
  re-run Backfill after the community grows to catch newly-qualifying members.
- **Idempotent.** Awards use `INSERT IGNORE`, so re-running Backfill (or toggling
  the flag) never double-awards. Preview lists only users who qualify **and do
  not already hold the badge**.
- **Revoke removes this rule's ownership, then checks other enabled rules.**
  Revoke iterates the users *this rule* awarded (per the ledger) and records a
  revoke event for this rule. The visible `(user_id, badge_id)` row may be
  preserved when another **enabled** rule qualifies that active user and can
  retain ownership. Ownership transfers to that rule with a `badge_rule_overlap`
  award-history row where permitted. Previous explicit revocations take
  precedence: eligibility alone does not revive an explicitly revoked ownership
  cycle. If no other rule can retain ownership, Revoke removes the visible badge
  row. Manual grants are not represented
  in `badge_award_history`, so a rule Revoke can still remove a badge that was
  also manually granted. **Disable** changes the label and overlap-preservation
  intent without removing existing awards; it does not block a later manual
  Backfill. Use **Revoke** when you intend to remove this rule's award ownership.
- **Review and typed confirmation.** Backfill and Revoke open read-only confirmation
  pages; GET never grants or revokes awards. Backfill shows the actual processing
  batch (up to 1000), with a notice when more members qualify. Revoke shows all
  active rule awards and the number of those members currently holding the
  visible badge; overlap rules can preserve badges as described above. Type
  `BACKFILL {rule id}` or `REVOKE {rule id}` exactly, then submit the CSRF-protected
  form. The server recomputes the affected batch inside the action transaction;
  if it changed since review, the request returns `422` with refreshed impact
  and the typed text preserved, before any award, notification, or audit write.
  Missing or incorrect confirmation also returns `422` without effects.
  Admin authorization, the feature flag, and the account write gate apply to
  both the confirmation pages and the action submissions.
- **Create-once.** There is no edit or delete for a rule — only enable/disable and
  backfill/revoke of its awards. To change a threshold, create a new rule (and
  revoke the old one's awards if desired).
- **Audit + ledger.** Every action writes a `moderation_log` row
  (`badge_rule.create` / `.enable` / `.disable` / `.backfill` (`awarded=N`) /
  `.revoke` (`revoked=N`)). Award/revoke events are double-booked in the
  append-only `badge_award_history` table (`action='award'|'revoke'`,
  `achievement_key` embeds the rule id + version so award/revoke pair up).
- **Notifications.** Backfill sends each newly-awarded member the standard badge
  notification (in-app; email per their prefs). A large first Backfill therefore
  fans out notifications — run it deliberately.

## Monitoring & known limits

- **No worker, no rate limit, no counters.** Nothing runs on cron; there is no
  dedicated `RateLimitService` policy (the surface is admin-only). Badge rules do
  not feed any denormalized counter, so `RepairService` has nothing to reconcile
  for this feature.
- **Eligibility query is bounded.** Preview lists up to 100 qualifiers; Backfill
  processes up to 1000 per run inside a single transaction. Both limits are
  disclosed in the UI; the Backfill confirmation uses the 1000-member processing
  batch rather than the shorter preview. For very large
  communities, run Backfill repeatedly until the awarded count reaches 0.
- **Restore from backup with the flag disabled.** The `badge_rules` /
  `badge_award_history` tables are authoritative operator content with no
  reconstructable derivation; on corruption, disable the flag and restore from
  backup.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Admin/AppAdminBadgeRulesTest.php` —
  `test_badge_rule_admin_routes_are_available_by_default_and_can_be_disabled`
  (default-on plus operator rollback: routes 404 when disabled),
  `test_admin_previews_backfills_disables_and_revokes_post_count_rule` (the full
  operator flow with the `badge_award_history` award/revoke assertions),
  `test_revoking_one_rule_preserves_badge_when_another_enabled_rule_still_qualifies_user`
  (overlapping enabled rules preserve the visible badge and transfer active award
  ownership),
  `test_badge_rules_flag_rollback_preserves_award_history` (disable/re-enable the
  flag with the award ledger intact), and
  `test_rule_creation_rejects_unknown_vocabulary` (`422` vocabulary enforcement);
  typed-confirmation regression coverage also rejects bare/wrong submissions
  without effects, rejects stale Backfill/Revoke batches, proves the 1000-member
  processing impact is not understated by the 100-member preview, and checks
  feature/admin/account gates on the confirmation pages and actions;
  `tests/Integration/Core/AppFeatureFlagTest.php` asserts `badge_rules` is
  declared **default-on** after graduation.
- **Browser:** `docs/evidence/browser/{desktop,mobile}/32-badge-rules.png`
  (create form + rule list), `33-badge-rule-preview.png` (eligible users), and
  `34-badge-rule-backfilled.png` (enabled rule + backfill flash), driven by the
  `phase 4 badge rules` journey in `tests/browser/gate-a.spec.ts` (create →
  preview → enable → review/confirm Backfill → disable → review/confirm Revoke,
  all through the no-JS forms).
- **Accessibility:** `tests/browser/a11y.spec.ts` — the admin dark-surface axe
  scan now includes `/admin/badge-rules` (create form + rule list), desktop +
  mobile, no serious/critical violations.
