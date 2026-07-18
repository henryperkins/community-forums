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

## PR #44 review remediation — 2026-07-18

The written review of PR #44 (spec:
`docs/superpowers/specs/2026-07-18-pr44-safety-remediation-design.md`, plan:
`docs/superpowers/plans/2026-07-18-pr44-safety-remediation.md`) found seven
release-blocking issues in the remediation above. All seven are closed on the
`pr44-remediation` branch; every fix is red-test-first PHPUnit plus, where
UI-visible, Playwright evidence. No migration shipped (slot 0078 remains
free); `SCHEMA.md` is untouched at v1.38.

### Finding → disposition

| # | Finding | Disposition | Evidence |
|---|---|---|---|
| 1 | Moderation failure re-renders disclosed private/pending threads (`rerenderThread`, `PostController` reply/edit catches); `moveThread` validated the destination before source authority (422/302 existence + content oracle); pin/lock/restore/reveal 403'd on unreadable threads | One shared read gate: `Security\BoardAuthority` + `Service\ThreadReadService`; read (404) → authority (403) → validation (422) everywhere; merge targets resolve from the actor's view with missing/unreadable identical 422s; assignment ⇒ readable (spec §1 decision) | `AppModerationReadGateTest` (13 cases, 11 red-first); scope/split-merge/draft-loss/private-board/approval suites green unmodified |
| 2 | `/mod/u/{id}` let any single-board moderator read every member's full record (site-wide warnings, private notes, bans, audit trail) and warn with an arbitrary unaudited `board_id` | `UserModerationService::panelFor` actor-aware model — participation-scoped admission (404 byte-identical to missing), whitelisted identity keys (no email), overlap-scoped warnings only; warn requires an assigned-AND-participated board, audits it in `after_json`, and adopts the `submission_idempotency` seam (`mod_warn`; duplicate replays the success redirect); notes are admin-only (ADR 0021 post-review decision) | `AppModUserPanelScopeTest` (10 cases, 8 red-first); panel/user-moderation/bulk/record suites green (participation fixtures updated — scoped-behaviour edits, commented in-diff) |
| 3 | Dashboard 422s dropped `settings_errors`/`settings_old` (`[defaults] + $extra` union); `structureView` same shape | `AdminDashboardService::dashboardModel(overlay)` with `array_replace`; `structureView` likewise; `reorder()`'s inline catch collapsed. Present-but-invalid registration/anti-abuse modes now refuse at 422 instead of silently clamping (the clamp reset an enforced `block` posture to `observe` on a bogus POST — the two pinned clamp assertions were updated to the stricter contract, recorded here) | `AppAdminModerationTest` new 422 cases red-first; `AppAdminTest`/`AppAdminStructureReorderTest` unmodified; browser journey `remediation-dashboard-422-draft` (desktop+mobile) |
| 4 | Board delete previewed the denormalised `thread_count` while the POST gate counted raw rows (hidden-content boards previewed deletable then dead-ended); all validation ran before the transaction; flash used the stale count | `boardDeleteImpact()` preview from the authoritative unfiltered count, labelled "(including hidden, held, and deleted)"; `deleteBoard` validates INSIDE the transaction against `FOR UPDATE` rows (lock-then-read, ascending ids; the count itself locks rows — the Task 1 MariaDB-1020 learning), returns the moved count for the flash; `recountContent` relocated verbatim to `BoardRecountService` | `AdminBoardSettingsTest` hidden/pending red-first cases; `AdminBoardDeleteConcurrencyTest` (two connections, committed fixtures, archived-after-preview refused with nothing mutated); browser journey `remediation-board-delete-authoritative-count` |
| 5 | Pagination `has_next = count(rows) === PER_PAGE` on `/admin/audit`, `/admin/users`, `/admin/email`, `/mod/reports`; audit dates filtered as `'banana 00:00:00'`; IP samples ordered by packed bytes; tag-merge impact under-counted; assorted controller-side read models | Totals-based `has_next` everywhere (0-based `(page+1)*per < total`; email 1-based `page*per < total`); `AuditQueryService` with strict `Y-m-d` round-trip dates → 422 with zero rows, actor resolution via `UserRepository::idsMatchingName`, single-table `ModerationLogRepository` + batched handle enrichment; `revealPii` orders by `MAX(last_seen_at)`/`MAX(created_at)`; `TagRepository::countAssociationsForTag` + `TagService` (preview == moved set); read models moved behind `UserModerationService` (directory/bulk), `EmailOpsService::dashboardModel` (+ honest non-failed-requeue test seeding a real `sent` row), `ReportService::queueModel`, `AppealService` view models, `WebhookService::detailModel` | Tasks 4 + 7a–7g commits, each red-first; the four pagination surfaces have exact-multiple no-Next tests with unique filter markers |
| 6 | Refreshing the mint POST minted a second live credential (template documented the wart) | `ApiTokenService::mint` adopts the `submission_idempotency` seam (`api_token_mint`, checked post-reauth so state is not probeable pre-auth); duplicates are refused — never replayed (plaintext is not stored) — and the controller renders a 409 conflict view; hidden per-render key (composer precedent); the reload warning is deleted, "will not be shown again" retained | `ApiTokenServiceTest` + `AdminApiTokenTest` red-first (service throw, HTTP 409, 422 key round-trip, fresh keys per GET); both Playwright token specs now reload → 409 with one row |
| 7 | Red verification gates: two Thread Intelligence tests failing (MariaDB errno 1020) and 14 CI browser failures | TI: prune absorbs 1020 outside its own transaction (nothing pruned this pass); the queue's visibility lock converts 1020 to the documented bounded-transient busy contract; the resume test's read view now forms after the competitor's commit, with the stale-view abort pinned by a new companion test. Browser: one product CSS line (`.table-scroll { position: relative }` — sr-only headers escaped the clip and zoomed the mobile layout viewport out) fixed the seven mobile timeouts; honest selector/flow updates for deliberately renamed UI (Record install, no `data-board-id`, reauthed "Delete webhook"); the composer Escape handler now honors the peel-outermost-first contract (fixed the pre-existing `:502` on both projects) | Full suite 2322+ green; `npm run evidence` 21 + 108 + 15 passed, zero failures; `npm run a11y` 32 passed |

### Measured browser baseline (Task 0) and final state

- Local `main` (084ed0c): exactly **2** failures — `composer-shell.spec.ts:502` desktop + mobile (both fixed product-side here).
- Local PR head: **13** of CI's 14 reproduced; the 14th (desktop `composer-shell:576` emoji, CI's only non-timeout) passes locally — CI-environment-linked, and plausibly the same Escape race the `:502` fix closes (a dialog-open Escape reaching the document collapsed the composer).
- Segment 3 (`admin-remediation.spec.ts`) had **never run in CI** (segment 2's non-zero exit short-circuits the `&&` chain); it was green when finally run, and now also carries the four new spec-required journeys (dashboard 422, scoped panel + out-of-scope 404, authoritative delete count, mint-refresh 409).
- Final: all three segments zero failures; a11y green; refreshed screenshots inspected (no overlays, no clipped controls).

### No-behaviour dispositions and observations

- Redirect-helper style comments from the review: no change — pure consistency notes.
- `BoardRepository::recomputeLastPost` stays in the repository (its other caller is `ModerationService::moveThread`, out of scope); only `recountContent` moved.
- Tag merge still writes no audit row — recorded gap, unchanged (see finding 5 disposition).
- The redundant staff check in the panel render path fell out naturally with `panelFor()`.
- The plan's Task 1 parenthetical ("1020 ⇒ the reconcile path") assumed in-place continuation; probing MariaDB 11.8 showed errno 1020 rolls back the ENTIRE transaction server-side (an uncommitted in-transaction write vanishes; binary-protocol PDO's client flag goes stale-true), so the queue-side treatment maps 1020 to the existing busy/transient contract instead — the invariant ("resume never acts on stale visibility") holds via abort-then-retry and is pinned by the companion test.
- Console sweep of the touched admin routes is clean except two pre-existing 404s for `/emoji/a11y_desktop.png`/`/emoji/a11y_mobile.png` — a11y-spec custom-emoji fixture rows whose image files are never stored; fixture artifact, not a product defect, noted for the a11y spec's owner.
