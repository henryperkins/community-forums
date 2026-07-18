# Admin console remediation — 2026-07-18

Companion to `docs/history/admin-ux-review-2026-07.md` (the 48-finding review)
and its 2026-07-18 re-verification. This records the finding → disposition map
for the remediation delivered on 2026-07-18. Deferrals live in ADR 0021; this
file is the evidence trail.

**Verification caveat:** the change set was authored in a session whose
execution permissions denied `php`/`phpunit`/browser tooling, so the PHPUnit
files below were written but **not executed** by the author. Run
`composer test` before claiming green, and capture Playwright evidence for the
UI-visible items per DESIGN §13.

## Finding → disposition (numbers from the 2026-07 review)

| # | Finding | Disposition |
|---|---|---|
| 1 | No feature-flag UI | Already resolved by design pre-remediation (read-only `/admin/features`); unchanged |
| 2 | Nav 404s on overridden-off flags | Already fixed pre-remediation; unchanged |
| 3 | Settings surface vs §9.2 | Deferred with enforcement rationale — ADR 0021 #3 |
| 4 | "New users today" card | Already fixed pre-remediation |
| 5 | Tour floats over console | **Fixed** — `tour.js` auto-start skips `/admin*` + `/mod*` |
| 6 | Phantom bulk UI | **Built** — `/admin/users/bulk` confirm → per-member audited apply (warn/suspend), live select-all JS |
| 7 | Board-mod status invisible in directory | **Fixed** — `moderated_boards` count + "board mod" tag |
| 8 | Users table scroll/a11y + sort arrows | **Fixed** — scroll region, `th scope`, aria-hidden arrows |
| 9 | Suspend 500 | Already fixed pre-remediation; regression-covered in new tests |
| 10 | No role assignment | Already fixed pre-remediation |
| 11 | Record gaps (PII, joined/last-seen, ban shape) | **Partially built** — joined/last-seen rows + audited email/IP reveal; ban type/scope/duration deferred (ADR 0021 #4) |
| 12 | Badge draft-loss | Already fixed pre-remediation |
| 13 | One-click ban | **Fixed** — typed-username confirmation, server-enforced |
| 14 | `post_min_role` unconfigurable | **Fixed** — "Who can post" on create+edit; **new regression also fixed**: UI saves no longer reset it to `user` (existing-value fallback in `validateBoard`), and the update audit now records it |
| 15 | Non-empty board delete dead end | **Built** — move-threads-to destination on the confirm page; authoritative recount of the destination |
| 16 | §4.2 board settings missing | **Edit window built + enforced** (staff exempt); icon/locked/prefixes/collapse/bulk-archive deferred (ADR 0021 #6) |
| 17 | "Private (admins only)" label | **Fixed** — "Private (members only)" on create+edit |
| 18 | Dead reorder scaffolding | **Fixed** — dead `data-reorder-*` attrs removed; tested POST target retained for the future enhancement (ADR 0021 #8) |
| 19 | Unlabeled category inputs | **Fixed** — aria-label + sr-only label |
| 20 | "1 threads" | **Fixed** |
| 21 | Tag create/update draft-loss | Already fixed pre-remediation |
| 22 | Tag merge posture | **Fixed** — impact-count confirmation page + danger styling |
| 23 | Tag row labels | Already fixed pre-remediation |
| 24 | Email status copy | Already fixed pre-remediation |
| 25 | Email log pager / requeue flash / suppress label | **Fixed** — pager UI, honest no-op requeue message, sr-only label, scroll regions |
| 26 | Email templates + staff matrix untracked | **Recorded** — ADR 0021 #1/#2 |
| 27 | Announce 429 wipes message | **Fixed** — 429 re-renders the form with the typed banner |
| 28 | Announcements history | **Built** — publish/clear history from the audit trail (message now captured in the audit payload) |
| 29 | Token mint scope loss | Already fixed pre-remediation |
| 30 | Tokens empty state / reload warning | **Fixed** — empty state, don't-refresh hint, scroll region |
| 31 | Webhook test stale-id 500 | Already fixed pre-remediation; covered in tests |
| 32 | One-click webhook delete | **Fixed** — password-reauthed delete (service + form), 422 on wrong password |
| 33 | Generic pause/resume flash | **Fixed** — distinct paused/resumed messages |
| 34 | Silent package lifecycle | **Fixed** — success flashes on every lifecycle handler |
| 35 | Unstyled package/theme buttons | **Fixed** — `.btn` classes, danger on Uninstall, plan-label honesty, consent Cancel link |
| 36 | Registries placeholder-only inputs | **Fixed** — sr-only labels (incl. package-security brake), empty states |
| 37 | Flat capability list + clone flash-loss | **Fixed** — grouped fieldsets by namespace; clone 422-re-renders with the typed name |
| 38 | Extensions matrix drift | Already fixed pre-remediation |
| 39 | Mod queue discoverability | Already fixed pre-remediation |
| 40 | Approvals false-success | Already fixed pre-remediation |
| 41 | `/mod` draft-loss family | **Fixed structurally** — new `/mod/u/{id}` staff panel with 422 re-renders (the POST routes were UI-orphaned after the Inbox redesign); split/merge/move re-render the thread via `renderThread` with input preserved; the orphaned **Move** control itself was restored in Topic tools |
| 42 | Reports queue vs §3.2 | **Largely built** — board/reason/status filters, pagination, >24 h aging cue, per-item Warn author; single `h1` in all three queues |
| 43 | No audit-log screen | **Built** — `/admin/audit` (filters: actor, action, target, dates; paginated), nav entry, dashboard card links to it; mod-scoped view deferred (ADR 0021 #5) |
| 44 | Systemic a11y | **Swept** — `th scope="col"`, sr-only action headers, differentiated per-row button labels, scroll regions on every operator table |
| 45 | Destructive grammar / branding reset | **Fixed** — typed `RESET` confirmation + danger styling; badge revoke danger; merge danger |
| 46 | Branding silent asset failure | **Fixed** — per-asset rejection reported in the flash, old asset kept |
| 47 | Terminology drift | **Partially fixed** — invitations table class, revoke labels; broader wording left as-is deliberately (churn > value) |
| 48 | Orphan endpoints | Custom emoji already fixed pre-remediation; link-previews stays tracked (ADR 0021 #7) |

New findings from the 2026-07-18 re-verification, all fixed here: the
`post_min_role` UI-save reset (N1), backend-only email pager (N2), appeal-note
draft-loss (N3), unwrapped tables (N4), always-both badge-rule buttons (N5),
suspension rows recorded as `type='full'` (N6), TI nav vanishing instead of a
disabled span (N7), dashboard settings flash-loss (N8), placeholder-only brake
inputs (N9).

## Evidence

- PHPUnit (written this change): `tests/Integration/Admin/AdminBoardSettingsTest.php`,
  `AdminUserBulkTest.php`, `AdminAuditPageTest.php`, updated `AdminWebhookTest.php`;
  `tests/Integration/Core/AppModUserPanelTest.php`, `AppModerationDraftLossTest.php`.
- **Verified 2026-07-18 (application session):** full suite green apart from two
  pre-existing Thread Intelligence concurrency failures already red on `main`
  (MariaDB error 1020 in the fork-a-second-connection tests; unrelated to this
  change). Application-session fixes folded in:
  - the kit's `apply_anchored.php` clobbered same-file edits (each edit was
    planned from the original bytes, so only the last write per file survived) —
    the five anchored-target files were rebuilt from `git show HEAD:` + all 15
    edits;
  - `PackageIntegrationService::revokeCredential` still called the old 2-arg
    `WebhookService::delete`; the service now exposes `deleteWithoutReauth()`
    for the deliberately friction-free package-credential revocation path while
    operator-UI deletion keeps the password reauth;
  - seven new tests seeded no admin account, so the first-run setup gate
    (`SetupService::isInitialized` = adminCount > 0) redirected their requests
    to `/setup`; they now seed one, and the appeal draft-loss test appeals a
    deleted **reply** (removing an OP retracts the topic, which is not
    appealable via `/appeals/posts`);
  - pre-existing tests asserting the replaced behaviours were updated (ban
    typed-username confirmation, branding `reset_confirm=RESET`, board delete
    destination message, split failure now a 422 re-render, users directory
    bulk markup);
  - `config/imladris-runtime-baseline.json` application-surface digest was
    re-reviewed and reconciled (+ regenerated `resources/imladris/manifest.json`).
- Browser evidence (`tests/browser/admin-remediation.spec.ts`, wired into
  `npm run evidence`): `docs/evidence/browser/desktop/remediation-*.png` —
  board-edit settings (who-can-post + edit window), move-topic control,
  board-delete destination picker, users bulk confirm, audit log (filtered),
  user-record PII reveal, ban typed-username 422 with rationale preserved,
  `/mod/u/{id}` staff panel + preserved failed warn, split-failure draft
  preservation, announcement 429 with banner text kept + history table, webhook
  pause/resume flashes + reauthed delete — and
  `docs/evidence/browser/mobile/remediation-390-*.png` for the 390 px scroll
  regions over users/email/providers/roles/invitations. `php bin/console
  repair` after the browser-driven move + delete confirms zero counter drift
  (the console's "1 rows" is the method's constant return, not affected rows).
