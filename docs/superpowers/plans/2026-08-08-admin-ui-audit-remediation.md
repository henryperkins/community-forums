# Admin UI Audit Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Admin Console header compact before it becomes cramped and make the Members directory practical, discoverable, and fully usable on mobile without JavaScript.

**Architecture:** Console-chrome selectors owned by Imladris are changed only in `docs/design-system/imladris/components.css` and rebuilt into generated assets. The Members directory keeps one server-rendered GET form: common controls stay visible, advanced controls move into native `<details>`, and the existing generic overflow-cue enhancer attaches to a new server-rendered table shell.

**Tech Stack:** PHP 8.2 server-rendered templates, CSS, existing progressive-enhancement JavaScript, PHPUnit 11, Playwright, Imladris asset builder.

## Execution record

Implemented 2026-08-08. Review remediation added explicit coverage for a valid 80-character site name at 900px, the 800px 44px disclosure target, zero-valued advanced post filters, and the tier's 900px overflow signal. The build and Imladris verification used the documented local application-surface digest allowance; its sole baseline hunk was restored after the verified full-suite run and before commit. Final browser evidence is under `docs/evidence/admin-ui-audit-remediation-2026-08-08/`.

## Global Constraints

- Preserve every `/admin/users` GET parameter, sort/direction value, result count, bulk-action field, route, authorization rule, and CSRF behavior.
- Preserve `ADMIN.md` §9.2/§9.4: ordinary links, no drawer, no JavaScript requirement, 44px touch targets below 860px, and visible area-tier overflow signal.
- Change `.admin-bar` and `.admin-tier` only in `docs/design-system/imladris/components.css`; run `composer build:imladris`; never hand-edit generated output.
- Do not retain a change to `config/imladris-runtime-baseline.json`, migrations, feature defaults, or dependencies. A local-only digest allowance may be used to build and verify, then its exact hunk must be restored before commit.
- Keep the application-wide 860px one-column breakpoint; add only a console-header compact state at 900px.
- Use `apply_patch` for source edits; stage only files owned by this slice; do not push, deploy, merge, or alter production data.

---

### Task 1: Add observable regression coverage for the audit findings

**Files:**
- Modify: `tests/Integration/Admin/AdminUserBulkTest.php:320-363`
- Modify: `tests/browser/admin-dashboard.spec.ts:289-355`
- Modify: `tests/browser/members-console.spec.ts:126-228`

**Interfaces:**
- Consumes: the server-rendered Members route at `GET /admin/users`, browser test projects `desktop` (1280×800) and `mobile` (390×844), and the existing `[data-overflow-cue]` JavaScript enhancer.
- Produces: regression coverage for 900px header compaction, native advanced filters, active-filter preservation, and a mobile member-table scroll cue.

- [x] **Step 1: Write the failing PHPUnit assertions for native advanced filters and the table cue**

  Add a focused test that loads `/admin/users` with no advanced filters and asserts this structure:

  ```php
  self::assertStringContainsString('<details class="member-directory-advanced-filters">', $body);
  self::assertStringContainsString('<summary>More filters</summary>', $body);
  self::assertStringContainsString('class="member-directory-table-shell" data-overflow-cue', $body);
  self::assertStringContainsString('aria-label="User directory" data-overflow-region', $body);
  ```

  Add a second GET with `last_seen=7` that asserts the same disclosure is rendered as `<details class="member-directory-advanced-filters" open>` and the Past 7 days option stays selected.

- [x] **Step 2: Run the focused PHPUnit file and verify the new assertions fail for absent markup**

  Run: `php vendor/bin/phpunit tests/Integration/Admin/AdminUserBulkTest.php`

  Expected: FAIL because the directory currently renders every filter in one grid and has no member table overflow cue shell.

- [x] **Step 3: Write the failing browser contracts**

  In `admin-dashboard.spec.ts`, add a desktop-only 900px test that sets the viewport to 900×800, visits `/admin`, and asserts:

  ```ts
  await expect(page.locator('.admin-bar-search')).toBeHidden();
  await expect(page.locator('.admin-bar-username')).toBeHidden();
  await expect(page.locator('.admin-bar-action-label')).toBeHidden();
  await expect(page.getByRole('button', { name: 'Log out' })).toBeVisible();
  await expectNoDocumentOverflow(page);
  await shot(page, info, 'admin-header-900px');
  ```

  In `members-console.spec.ts`, assert that the native `details.member-directory-advanced-filters` starts closed, opens by clicking `More filters`, submits `last_seen=7` through the GET form, and remains open after the response. Add a mobile-only test that asserts the member table shell/cue/scroll region, scrolls the region to its end, and verifies the generic cue/fade becomes hidden.

- [x] **Step 4: Run the affected browser specs and verify they fail for the missing responsive/filter/cue contracts**

  Run: `cd tests/browser; bash prepare.sh; npx playwright test admin-dashboard.spec.ts members-console.spec.ts --project=desktop --project=mobile`

  Expected: FAIL only at the newly added 900px header and Members-directory assertions; existing browser contracts remain green.

### Task 2: Move compact console-chrome ownership into the Imladris source and rebuild it

**Files:**
- Modify: `docs/design-system/imladris/components.css:328-342`
- Modify: `public/assets/app.css:2963-2989,5597-5611`
- Modify (generated): `resources/imladris/components.css`
- Modify (generated): `public/assets/imladris.css`

**Interfaces:**
- Consumes: `.admin-bar-id`, `.admin-tier`, and the application-owned `.admin-bar-right`, `.admin-bar-search`, `.admin-bar-username`, and `.admin-bar-action-label` selectors.
- Produces: a 900px compact identity row, transparent scrollbar track plus token-colored thumb, and the unchanged 860px console content layout.

- [x] **Step 1: Implement the minimum 900px header contract in the design source**

  Add an `@media (max-width: 900px)` block to `docs/design-system/imladris/components.css` that keeps `.admin-bar-id` on one line with compact `10px 16px` padding and a 58px minimum height, and gives `.admin-tier` its compact horizontal padding plus `scrollbar-color: var(--border-hair) transparent`. Add a transparent `::-webkit-scrollbar-track` next to the existing thumb rule.

  Move no `.admin-bar` or `.admin-tier` declaration into `app.css`. Put the app-owned search/name/sign-out-label hiding and `admin-bar-right` gap reduction in a separate `@media (max-width: 900px)` app rule. Remove those duplicate header/tier declarations from the 860px app rule while retaining the one-column console, touch-target, and section-tab rules there.

- [x] **Step 2: Rebuild generated Imladris assets**

  Run: `composer build:imladris`

  Expected: the generated component copy, bundled public Imladris asset, and runtime manifest change in addition to the design source.

- [x] **Step 3: Run the 900px browser regression and Imladris verifier**

  Run: `cd tests/browser; bash prepare.sh; npx playwright test admin-dashboard.spec.ts --project=desktop`

  Run: `composer verify:imladris`

  Expected: the header contract is green at 900px; the generated assets exactly match the design source and the runtime test passes under the local-only digest allowance.

### Task 3: Make Members filters and table scrolling progressively disclosed and discoverable

**Files:**
- Modify: `templates/admin/users.php:6-119`
- Modify: `public/assets/app.css:3257-3264,5687-5718,10032-10120,10477-10505`

**Interfaces:**
- Consumes: `$filters` with `q`, `role`, `status`, `last_seen`, `joined_from`, `joined_to`, `min_posts`, and `max_posts`; existing `.filter-grid`, `.table-scroll`, `.table-scroll-cue`, and `[data-overflow-cue]` contracts.
- Produces: `$hasAdvancedFilters` for render-time disclosure state, `details.member-directory-advanced-filters`, and `.member-directory-table-shell[data-overflow-cue]` whose child region has `data-overflow-region`.

- [x] **Step 1: Implement render-time advanced-filter state and semantic markup**

  In `templates/admin/users.php`, calculate `$hasAdvancedFilters` by checking `last_seen`, `joined_from`, `joined_to`, `min_posts`, and `max_posts` with strict empty-string comparison so `0` is active. Keep Search, Role, and State in the existing visible filter grid. Wrap the remaining five controls in:

  ```php
  <details class="member-directory-advanced-filters"<?= $hasAdvancedFilters ? ' open' : '' ?>>
      <summary>More filters</summary>
      <div class="filter-grid member-directory-filter-grid member-directory-advanced-filter-grid">
          <!-- existing advanced controls -->
      </div>
  </details>
  ```

  Wrap the existing table region in:

  ```php
  <div class="member-directory-table-shell" data-overflow-cue>
      <p class="table-scroll-cue" data-overflow-cue-label>Scroll for state, activity, and dates <span aria-hidden="true">→</span></p>
      <div class="table-scroll" tabindex="0" role="region" aria-label="User directory" data-overflow-region>
  ```

  Preserve the existing table, region label, `tabindex`, bulk form, and closing tag nesting.

- [x] **Step 2: Add scoped disclosure and cue styles without duplicating JavaScript behavior**

  Style the summary as a labelled, keyboard-focusable disclosure control and its open state with existing tokens. Use a 44px minimum summary target at mobile widths. Extend the existing activity-table-shell base, mobile fade, and end/unneeded selectors to also target `.member-directory-table-shell`; do not change the generic enhancer in `app.js`, because the new data attributes already opt into it.

  At the existing 760px Members breakpoint, keep the filter card/table card padding and make filter actions stack cleanly below the closed disclosure. Do not hide active values or alter desktop table widths.

- [x] **Step 3: Run the red tests again and verify the directory contracts are green**

  Run: `php vendor/bin/phpunit tests/Integration/Admin/AdminUserBulkTest.php`

  Run: `cd tests/browser; bash prepare.sh; npx playwright test members-console.spec.ts --project=desktop --project=mobile`

  Expected: PHP markup assertions and both JavaScript-enabled/no-JavaScript directory flows pass; desktop hides the cue while mobile shows it until the table reaches its end.

### Task 4: Capture complete evidence and create the single remediation commit

**Files:**
- Create: `docs/evidence/admin-ui-audit-remediation-2026-08-08/` browser screenshots generated by the focused specs
- Modify: `docs/superpowers/plans/2026-08-08-admin-ui-audit-remediation.md` checklist state only
- Commit: owned source, generated assets, tests, plan, and evidence only

**Interfaces:**
- Consumes: the completed responsive header, member filters, table cue, focused test suite, Imladris builder, and evidence scripts.
- Produces: reproducible desktop/mobile/no-JavaScript evidence and one independently reviewable slice commit.

- [x] **Step 1: Run the required security and source checks**

  Run: `rg -n "<script|<style| on[a-z]+=" templates/ -S`

  Run: `composer verify:imladris`

  Run: `git diff --check`

  Expected: no newly introduced inline execution/style violations; generated assets are current; diff has no whitespace errors.

- [x] **Step 2: Run the full server-side regression suite**

  Run: `php vendor/bin/phpunit`

  Expected: all PHPUnit tests pass with no warnings or risky tests.

- [x] **Step 3: Capture focused browser evidence with JavaScript enabled and disabled**

  Run: `cd tests/browser; RB_EVIDENCE_DIR=docs/evidence/admin-ui-audit-remediation-2026-08-08 bash prepare.sh; RB_EVIDENCE_DIR=docs/evidence/admin-ui-audit-remediation-2026-08-08 npx playwright test admin-dashboard.spec.ts members-console.spec.ts --project=desktop --project=mobile`

  Inspect the generated desktop/mobile screenshots for the 900px header state, the compact Members filter surface, visible table cue, and no-JavaScript routes.

- [x] **Step 4: Review the final tree and commit only owned files**

  Run: `git status --short` and `git diff --check`.

  Stage the explicit source, generated assets, tests, plan, and `docs/evidence/admin-ui-audit-remediation-2026-08-08/` allowlist. Commit with:

  ```bash
  git commit -m "fix: remediate admin UI audit findings"
  ```

  Do not stage audit artifacts or unrelated files from other worktrees. Do not push.

## Plan self-review

- **Spec coverage:** Task 2 covers the earlier compact header and honest tier-scroll signal; Task 3 covers the Members filter disclosure and table cue; Task 4 covers browser/server/CSP/asset evidence and the required isolated slice commit.
- **Placeholders:** The plan contains no deferred implementation markers; each source, observable behavior, command, and expected outcome is explicit.
- **Consistency:** The template’s `data-overflow-cue` and `data-overflow-region` names match the existing generic JavaScript contract, and the only Imladris-generated files listed are the builder outputs.
