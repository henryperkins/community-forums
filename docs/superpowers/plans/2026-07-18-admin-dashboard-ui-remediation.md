# Admin Dashboard UI Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `/admin` an operational landing page with domain-owned settings pages, a grouped responsive admin workspace, and complete server/browser evidence.

**Architecture:** A new `AdminSettingsService` owns all general/moderation setting models and mutations. Existing server-rendered templates continue to share one grouped navigation partial; CSS promotes the three-node admin structure into a desktop grid, and `app.js` progressively enhances that same navigation into a mobile drawer. `AdminDashboardService` is reduced to operational queue/activity data.

**Tech Stack:** PHP 8.2, vanilla server-rendered templates, MySQL/MariaDB, PHPUnit, progressive vanilla JavaScript, CSS Grid, Playwright Chromium, axe-core.

## Global Constraints

- Preserve the authority chain in `AGENTS.md` and the completion-evidence rule in `DESIGN.md` §13.
- Preserve no-JavaScript operation, strict CSP, CSRF on every POST, admin authorization, `WriteGate`, feature-gate 404 behavior, transactions, and audits.
- Preserve `POST /admin/site` and every existing custom-emoji mutation URL.
- Remove `POST /admin/settings`; do not add a compatibility alias.
- Do not add a migration, schema change, dependency, JSON API, or feature-default change.
- Preserve the current Imladris runtime, member navigation, colors, typography, density, and Admin mode indicator.
- Do not touch `.github/prompts/` or `output/admin-dashboard-audit-2026-07-18/`.

---

### Task 1: Red integration contract

**Files:**
- Create: `tests/Integration/Core/AppAdminDashboardRemediationTest.php`
- Modify: `tests/Integration/Core/AppAdminModerationTest.php`
- Modify: `tests/Integration/Core/AppCustomEmojiGiphyTest.php`

**Interfaces:**
- Consumes: current in-process HTTP client and real test DB.
- Produces: failing behavior assertions for the new routes, models, ownership boundaries, and 422 pages.

- [ ] Add tests for guest redirect, non-admin 403, admin 200, `anti_abuse`/`custom_emoji` 404 gates, and obsolete `POST /admin/settings` 404.
- [ ] Add tests that assert all eight navigation group labels, exact destinations, `aria-current`, and disabled explanations.
- [ ] Seed settings with sentinel values and prove registration, anti-abuse, and site-name POSTs mutate only owned keys.
- [ ] Assert owning-page redirects and precise `moderation_log.before_json`/`after_json` payloads.
- [ ] Assert site, registration, anti-abuse, and emoji validation returns 422 with field errors and drafts intact.
- [ ] Assert dashboard queue/activity labels, `attention|clear|unavailable` classes, section order, audit heading link, and absence of settings/emoji forms.
- [ ] Run `php vendor/bin/phpunit tests/Integration/Core/AppAdminDashboardRemediationTest.php tests/Integration/Core/AppAdminModerationTest.php tests/Integration/Core/AppCustomEmojiGiphyTest.php` and confirm failures are caused by the missing routes/service/UI.

### Task 2: Settings and emoji ownership

**Files:**
- Create: `src/Service/AdminSettingsService.php`
- Create: `src/Controller/AdminSettingsController.php`
- Create: `templates/admin/settings.php`
- Create: `templates/admin/moderation.php`
- Create: `templates/admin/custom_emoji.php`
- Modify: `src/Service/AdminService.php`
- Modify: `src/Service/CustomEmojiService.php`
- Modify: `src/Controller/AdminController.php`
- Modify: `src/Controller/AdminCustomEmojiController.php`
- Modify: `src/Core/App.php`
- Modify: direct `new AdminService(...)` test fixtures if constructor arguments change.

**Interfaces:**
- Produces: the five required `AdminSettingsService` methods and `CustomEmojiService::pageModel(array $overlay = []): array`.
- Consumes: `Database`, `SettingRepository`, `ModerationLogRepository`, `WriteGate`, `FeatureFlags`, and existing View/controller helpers.

- [ ] Bind `AdminSettingsService`, remove setting dependencies from `AdminService`/`AdminDashboardService`, and register the new GET/POST routes in specific-before-generic order.
- [ ] Implement general/moderation base models with `array_replace` overlays.
- [ ] Implement isolated settings mutations, validation, transaction boundaries, and precise audit payloads.
- [ ] Render owning templates and route every 422/success back to its owner.
- [ ] Implement emoji page model/index and change every emoji redirect/422 target to `/admin/custom-emoji`.
- [ ] Run the focused PHP command until green, then run directly affected constructor/service suites.

### Task 3: Operational dashboard model and template

**Files:**
- Modify: `src/Service/AdminDashboardService.php`
- Modify: `templates/admin/dashboard.php`
- Modify: `src/Core/App.php`

**Interfaces:**
- Produces: `summary(): array{queue_cards:list<array>,activity_cards:list<array>,attention:list<array>,audit:list<array>}`.
- Queue card status is exactly `attention`, `clear`, or `unavailable`.

- [ ] Replace `cards` with `queue_cards` and `activity_cards`; remove settings, emoji, audit-total, and dashboard-form data.
- [ ] Compute queue status from feature availability, counts, mailer/domain posture, and Thread Intelligence health.
- [ ] Render Introduction → Queue health → Needs attention → Community today → Recent activity.
- [ ] Put `/admin/audit` in the Recent activity heading and remove all configuration markup.
- [ ] Add the mobile scroll cue/fade wrapper without changing table semantics or focusability.
- [ ] Run the focused PHP tests and confirm the required ordering/absence assertions pass.

### Task 4: Grouped admin workspace and progressive enhancement

**Files:**
- Modify: `templates/admin/_nav.php`
- Modify: `public/assets/app.css`
- Modify: `public/assets/app.js`

**Interfaces:**
- Produces data hooks: `data-admin-nav-toggle`, `data-admin-nav`, `data-admin-nav-close`, `data-admin-nav-scrim`, and `data-overflow-cue`.

- [ ] Render one grouped navigation tree with feature-aware links/disabled spans and a hidden-by-default mobile toggle/close/scrim.
- [ ] Apply the 224px desktop grid above 860px while keeping `.admin-head`, navigation, and `.admin-pane` as the layout sequence.
- [ ] Keep no-JS grouped navigation expanded at 860px and below.
- [ ] Enhance mobile open/close, Escape, scrim, link close, focus trap/restore, body scroll lock, inert state, and breakpoint cleanup in external `app.js`.
- [ ] Add table overflow measurement that removes the cue/fade at the horizontal end and on resize.
- [ ] Re-run focused PHP tests and `composer verify:imladris` after the application-surface changes.

### Task 5: Browser contract and evidence note

**Files:**
- Create: `tests/browser/admin-dashboard.spec.ts`
- Modify: `tests/browser/admin-remediation.spec.ts`
- Modify: `tests/browser/package.json`
- Modify: `tests/browser/gate-a.spec.ts` only if selectors/capture need adjustment.
- Create: `docs/history/admin-dashboard-ui-remediation-2026-07-18.md`

**Interfaces:**
- Consumes: seeded admin login, 1280×800 and 390×844 Playwright projects, `@axe-core/playwright`.
- Produces: named desktop/mobile evidence screenshots and a finding-to-fix evidence ledger.

- [ ] Cover desktop rail dimensions/groups/destinations and operational hierarchy/metrics.
- [ ] Cover mobile 44px control, drawer open, Tab containment, Escape/scrim/link close, focus restoration, and resize cleanup.
- [ ] Disable JavaScript in a dedicated context and prove grouped navigation reaches `/admin/settings`.
- [ ] Horizontally scroll Recent activity, assert cue/fade end state, inspect console messages, and run axe with serious/critical impact filtering.
- [ ] Move the existing site-name 422 journey to `/admin/settings` and rename its screenshot.
- [ ] Wire the focused suite into `npm run evidence` and `npm run a11y`.
- [ ] Record each original audit finding, implementation fix, PHPUnit/Playwright proof, and screenshot path under `docs/history/`.

### Task 6: Final verification and fidelity

**Files:**
- Refresh: relevant `docs/evidence/browser/desktop/*.png`
- Refresh: relevant `docs/evidence/browser/mobile/*.png`
- Review: all changed source/test/docs files.

- [ ] Run focused PHPUnit tests.
- [ ] Run `composer test` and read the complete result.
- [ ] Run `composer verify:imladris` and reconcile only reviewed runtime-baseline drift.
- [ ] From `tests/browser`, run `npm run evidence` and `npm run a11y`.
- [ ] Inspect browser console output and desktop/mobile screenshots.
- [ ] Use `view_image` on the accepted desktop/mobile concepts and latest implementation screenshots in the same QA pass.
- [ ] Write a fidelity ledger covering copy, hierarchy, rail/drawer geometry, typography/palette, queue state treatment, table behavior, and responsive interaction; fix every actionable mismatch.
- [ ] Run `git diff --check`, inspect `git status --short`, and confirm the two pre-existing untracked artifact areas are untouched.
