# Admin UX Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remediate the `/admin` UX audit findings from `/tmp/retroboards-admin-audit/findings.md` so the admin console matches `ADMIN.md` for operator workflow, safety, anti-draft-loss, no-JS support, responsive behavior, and basic accessibility.

**Architecture:** Keep the admin console server-rendered and progressively enhanced. Add shared admin chrome, a small dashboard summary service, and focused confirmation/422-render paths around existing controllers and services instead of changing domain rules. Treat default-dark Phase 5+ surfaces as explanatory disabled states, not active remediation scope.

**Tech Stack:** PHP 8.2, plain PHP templates, `App\Core\View::partial()`, PHPUnit integration tests, Playwright browser evidence, axe accessibility checks, existing CSS in `public/assets/app.css`.

---

## Priority Order

- [ ] **P0:** User record actions: expose suspend, ban, lift, warn, and note from admin.
- [ ] **P0:** Destructive-action safety: confirmation and impact copy for structure, badge-rule, and API-token actions.
- [ ] **P1:** Shared admin navigation and operational dashboard.
- [ ] **P1:** Anti-draft-loss: failed admin writes re-render at `422` with input preserved.
- [ ] **P1:** User directory filters, sorting, and bulk-action foundation.
- [ ] **P2:** Responsive tables/action rows, labels, contrast, email table polish, and tour suppression.

## Global Constraints

- [ ] Keep every mutating admin action behind `requireAdmin()` or the existing service authorization path.
- [ ] Keep no-JS form posts working; JavaScript may decorate but cannot be required.
- [ ] Preserve CSP discipline: no inline `<script>`, `<style>`, or `on*=` handlers in `templates/admin`, `templates/mod`, or `templates/layout.php`.
- [ ] Failed validation from admin forms must render the same task context at `422` with field errors and old input, except irreversible actions that have no typed input and can safely redirect with flash after a server-side refusal.
- [ ] Do not change the default-dark status of `api_tokens`, `webhooks`, `package_registry`, `package_themes`, `capabilities`, or `server_extensions`.
- [ ] Do not implement public plugin marketplace/sandbox, granular custom roles, passkeys/OIDC/invitations, server extensions, package themes/registry activation, Phase 6 scale, or Phase 7 expansion in this remediation.
- [ ] After each task, run the targeted PHPUnit file named in that task before moving on.

---

## Task 1: Shared Admin Navigation And Disabled-State Messaging

**Files:**
- Create: `templates/admin/_nav.php`
- Modify: every `templates/admin/*.php` file that currently hand-rolls `<nav class="subnav">`
- Modify: `tests/Integration/Core/AppAdminTest.php`
- Modify or add browser checks in `tests/browser/gate-a.spec.ts`

**Remediation Instructions:**

- [ ] Create `templates/admin/_nav.php` as the single admin nav partial.
- [ ] Make the partial accept `active` and `features` data.
- [ ] Include default-on links: Dashboard, Boards & categories, Users, Branding, Tags, Badge rules, Email, Announcements.
- [ ] Include route links for default-dark surfaces only when their flag is enabled: API tokens, Webhooks, Packages, Registry trust, Themes, Roles, Extensions.
- [ ] When a default-dark flag is disabled, render a non-link disabled nav item with short copy such as `Disabled until the feature flag is enabled`; do not point operators at a 404.
- [ ] Replace per-template hard-coded subnavs with `<?= $this->partial('admin/_nav', ['active' => '<key>', 'features' => $features ?? []]) ?>`.
- [ ] Ensure every controller rendering an admin template passes `features` or that the partial safely falls back to `$this->shared('features', [])`.

**Acceptance Criteria:**

- [ ] Every admin page shows the same top-level admin navigation.
- [ ] Default-on pages are discoverable from the console.
- [ ] Disabled Phase 5+ surfaces are explained without linking to dark 404s.
- [ ] The active nav item is correct on dashboard, structure, users, email, tags, badge rules, API tokens, webhooks, packages, themes, roles, registries, and extensions.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Core/AppAdminTest.php`
- [ ] `cd tests/browser && npm run a11y`
- [ ] Browser evidence: desktop and mobile screenshots for `/admin`, `/admin/users`, and `/admin/structure` show the same nav set.

---

## Task 2: Operational Admin Dashboard

**Files:**
- Create: `src/Service/AdminDashboardService.php`
- Modify: `src/Core/App.php`
- Modify: `src/Controller/AdminController.php`
- Modify: `templates/admin/dashboard.php`
- Modify: `tests/Integration/Core/AppAdminTest.php`
- Modify: `tests/browser/gate-a.spec.ts`

**Remediation Instructions:**

- [ ] Add `AdminDashboardService` and bind it in `App::buildContainer()`.
- [ ] Compute dashboard metrics from existing tables and repositories:
  - open or triaged report count from `reports.status IN ('open','triaged')`
  - pending approval thread count from `threads.is_pending = 1`
  - pending approval reply count from `posts.is_pending = 1 AND posts.is_op = 0`
  - new users today from `users.created_at >= UTC_DATE()`
  - active users from `users.last_seen_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE`
  - failed email deliveries from `EmailDeliveryRepository::statusCounts()`
  - recent audit rows from `ModerationLogRepository::recent(10)`
- [ ] Keep the existing site-name and trust/safety settings reachable, but move them below the operational summary or behind a clearly labelled Settings/Trust section.
- [ ] Render linked queue cards for Reports, Approval hold, Users, Email failures, and Audit.
- [ ] Add a compact needs-attention list that only shows non-zero queues or risky system states.
- [ ] Keep every card a normal anchor or form-safe link; do not require JavaScript.

**Acceptance Criteria:**

- [ ] `/admin` first viewport prioritizes queue counts and health flags over long-form settings.
- [ ] Each count links to the route where the operator resolves the issue.
- [ ] Empty-state dashboard still explains that there is no pending operator work.
- [ ] Dashboard keeps existing settings forms functional and audited.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Core/AppAdminTest.php`
- [ ] `vendor/bin/phpunit tests/Integration/Core/AppAdminModerationTest.php tests/Integration/Admin/AppAdminEmailTest.php`
- [ ] `cd tests/browser && npm run evidence`

---

## Task 3: Admin User Record Actions

**Files:**
- Modify: `src/Controller/AdminUserController.php`
- Modify: `src/Core/App.php`
- Modify: `templates/admin/user_record.php`
- Modify: `tests/Integration/Admin/AppAdminUserRecordTest.php`
- Reference existing service: `src/Service/UserModerationService.php`

**Remediation Instructions:**

- [ ] Add admin-scoped POST routes:
  - `POST /admin/users/{id}/warn`
  - `POST /admin/users/{id}/note`
  - `POST /admin/users/{id}/suspend`
  - `POST /admin/users/{id}/ban`
  - `POST /admin/users/{id}/lift`
- [ ] Implement matching methods in `AdminUserController` that call the existing `UserModerationService` methods.
- [ ] On `ValidationException`, re-render `AdminUserController::record()` at `422` with old input and field errors instead of redirecting to the public profile.
- [ ] Add a status panel to `templates/admin/user_record.php` showing current `role`, `status`, and `suspended_until`.
- [ ] Add forms for warning, private staff note, suspension with optional UTC until timestamp, permanent ban, and lift.
- [ ] Hide or disable suspend/ban controls for the current admin and for other admins, matching `UserModerationService::requireGovernable()`.
- [ ] Show recent warnings, notes, bans, and `moderation_log` rows for the subject so actions are auditable in-context.
- [ ] Keep the existing manual badge and title controls in the same record view.
- [ ] Do not implement granular custom role assignment here; `capabilities` remains a default-dark Phase 5+ surface.

**Acceptance Criteria:**

- [ ] Playwright finds visible Warn, Note, Suspend, Ban, and Lift controls where allowed.
- [ ] Successful actions return to `/admin/users/{id}`, not `/u/{username}`.
- [ ] Empty reason/body submissions return `422` and preserve typed fields.
- [ ] Service-level safeguards still prevent self-moderation and admin-on-admin suspension/ban.
- [ ] Audit rows are written for warn, suspend, ban, and lift; notes are visible only to staff.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Admin/AppAdminUserRecordTest.php`
- [ ] `vendor/bin/phpunit tests/Integration/Core/AppUserModerationTest.php`
- [ ] `cd tests/browser && npm run evidence`

---

## Task 4: User Directory Filters, Sorting, Bulk Foundation

**Files:**
- Modify: `src/Repository/UserRepository.php`
- Modify: `src/Controller/AdminUserController.php`
- Modify: `templates/admin/users.php`
- Modify: `tests/Integration/Admin/AppAdminUserRecordTest.php`
- Modify: `tests/browser/gate-a.spec.ts`

**Remediation Instructions:**

- [ ] Extend `UserRepository::directory()` to accept a typed filter array: `q`, `role`, `status`, `joined_from`, `joined_to`, `last_seen`, `min_posts`, `max_posts`, `sort`, `direction`, `limit`, and `offset`.
- [ ] Keep `LIMIT` and `OFFSET` clamped and inlined; do not bind them as placeholders.
- [ ] Use distinct named placeholders for every repeated query value.
- [ ] Add GET controls for role, state, join date, last seen, post-count range, sort, and direction.
- [ ] Add sortable table headings that preserve the current filters.
- [ ] Add row checkboxes and a disabled bulk-action bar as a foundation, with copy indicating bulk moderation requires a separate confirmation step.
- [ ] Do not wire bulk destructive actions in this task; this task only makes selection and filtering discoverable.
- [ ] For moderator-scoped users, add a read-mostly variant only if there is already an admin/mod route gate available in the current branch; otherwise document it as a follow-up tied to moderator console IA.

**Acceptance Criteria:**

- [ ] Directory supports role and status filtering.
- [ ] Directory supports sorting by username, role, status, created date, last seen, post count, and reputation.
- [ ] Filter submissions are shareable GET URLs.
- [ ] Mobile does not fragment table headers into unreadable two-letter chunks.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Admin/AppAdminUserRecordTest.php`
- [ ] `vendor/bin/phpunit tests/Integration/Repository/UserRepositoryDirectoryTest.php`
- [ ] `cd tests/browser && npm run a11y`

---

## Task 5: Structure Confirmation, Impact Copy, And 422 Preservation

**Files:**
- Modify: `src/Core/App.php`
- Modify: `src/Controller/AdminController.php`
- Modify: `src/Service/AdminService.php`
- Modify: `templates/admin/structure.php`
- Create as needed: `templates/admin/structure_confirm.php`
- Modify: `tests/Integration/Core/AppAdminTest.php`
- Modify: `tests/Integration/Core/AppAdminArchiveTest.php`
- Modify: `tests/Integration/Core/AppAdminStructureReorderTest.php`

**Remediation Instructions:**

- [ ] Replace one-click Delete category, Delete board, Archive, and Unarchive row controls with confirmation entry points.
- [ ] Add GET confirmation routes for no-JS operators:
  - `GET /admin/categories/{id}/delete`
  - `GET /admin/boards/{id}/delete`
  - `GET /admin/boards/{id}/archive`
  - `GET /admin/boards/{id}/unarchive`
- [ ] Keep the existing POST routes as the only mutating endpoints.
- [ ] Show impact copy on confirmation pages:
  - category name, board count, and whether deletion is blocked by non-empty contents
  - board name, thread count, post count, visibility, and archive/read-only impact
  - unarchive copy explaining posting will be re-enabled
- [ ] Require typed confirmation for category delete and board delete. Use the category or board slug/name as the typed value and reject mismatch with `422`.
- [ ] For archive/unarchive, require an explicit checkbox or typed board slug; prefer typed board slug for consistency with `ADMIN.md`.
- [ ] Update `AdminService` delete/archive methods or controller guards so direct POST without matching confirmation cannot mutate.
- [ ] Convert create/update category, create board, assign/remove moderator, add/remove member, move, archive, delete, and unarchive validation failures to `422` re-renders in the same context.
- [ ] Keep the existing board edit `422` behavior intact.

**Acceptance Criteria:**

- [ ] Destructive actions are not one-click from `/admin/structure`.
- [ ] A direct POST without confirmation returns `422` or a non-mutating refusal.
- [ ] Confirmation pages work with JavaScript disabled.
- [ ] Failed forms preserve old input and field errors.
- [ ] The board archive/unarchive read-only contract remains unchanged.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Core/AppAdminTest.php`
- [ ] `vendor/bin/phpunit tests/Integration/Core/AppAdminArchiveTest.php`
- [ ] `vendor/bin/phpunit tests/Integration/Core/AppAdminStructureReorderTest.php`
- [ ] No-JS browser pass: archive and unarchive a board through confirmation pages.

---

## Task 6: Tag Admin Anti-Draft-Loss, Labels, And Merge Safety

**Files:**
- Modify: `src/Controller/TagController.php`
- Modify: `templates/admin/tags.php`
- Modify or create: `tests/Integration/Core/AppTagAdminTest.php`
- Modify: `tests/browser/a11y.spec.ts`

**Remediation Instructions:**

- [ ] Add a private admin-render helper in `TagController` that loads all tag rows and accepts `errors`, `old`, and optional row id context.
- [ ] Change create and update validation failures from redirect-with-flash to `422` re-render with old values.
- [ ] Add programmatic labels to inline name, slug, description, enabled, visibility, and merge controls.
- [ ] Add a no-JS merge confirmation path before `mergeInto()` runs.
- [ ] Show source tag name, target tag name, and affected thread count on the merge confirmation page.
- [ ] Require typed confirmation matching the source tag slug before merge.

**Acceptance Criteria:**

- [ ] Invalid tag create/update preserves typed values.
- [ ] Inline edit controls have accessible labels.
- [ ] Merge cannot be fired from the listing with one click.
- [ ] Merge impact is visible before the operator confirms.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Core/AppTagAdminTest.php`
- [ ] `cd tests/browser && npm run a11y`

---

## Task 7: Badge Rule Backfill/Revoke Safety

**Files:**
- Modify: `src/Controller/AdminBadgeRuleController.php`
- Modify: `templates/admin/badge_rules.php`
- Modify: `templates/admin/badge_rule_preview.php`
- Modify: `tests/Integration/Admin/AppAdminBadgeRulesTest.php`
- Modify: `tests/browser/gate-a.spec.ts`

**Remediation Instructions:**

- [ ] Remove direct backfill and revoke forms from the badge-rule listing.
- [ ] Make the Preview page the required entry point for Backfill.
- [ ] Show eligible-user count and sample users before Backfill.
- [ ] Add a Revoke confirmation page or mode that shows active award count and affected sample users.
- [ ] Require typed confirmation for Revoke awards.
- [ ] Require typed confirmation for Backfill when affected count is greater than zero.
- [ ] Keep Enable and Disable as normal POST actions if they remain reversible and audited.

**Acceptance Criteria:**

- [ ] Backfill and Revoke cannot be executed as one-click actions from `/admin/badge-rules`.
- [ ] The operator sees affected counts before mutation.
- [ ] Direct POST without confirmation does not mutate awards.
- [ ] All successful mutations remain audited.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Admin/AppAdminBadgeRulesTest.php`
- [ ] `cd tests/browser && npm run evidence`

---

## Task 8: API Token Revoke Safety

**Files:**
- Modify: `src/Controller/AdminApiTokenController.php`
- Modify: `src/Service/ApiTokenService.php` if token lookup details are missing
- Modify: `templates/admin/api_tokens.php`
- Create as needed: `templates/admin/api_token_revoke.php`
- Modify: `tests/Integration/Api/AdminApiTokenTest.php`
- Modify: `tests/browser/gate-a.spec.ts`

**Remediation Instructions:**

- [ ] Add `GET /admin/api-tokens/{id}/revoke` confirmation route gated by `api_tokens`.
- [ ] Keep `POST /admin/api-tokens/{id}/revoke` as the only mutating route.
- [ ] Show token name, scopes, creation timestamp, expiration timestamp, last-used timestamp if stored, and whether the token is active.
- [ ] Require typed confirmation matching the token name.
- [ ] Require password reauth for revoking an active token, using the same reauth mechanism minting already uses.
- [ ] Preserve the existing one-time plaintext token display behavior on mint.

**Acceptance Criteria:**

- [ ] Revoke is not one-click from the token list.
- [ ] Direct POST without confirmation and password does not revoke an active token.
- [ ] Successful revoke still redirects to `/admin/api-tokens` with flash and audit.
- [ ] The route remains 404 when `api_tokens` is dark.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Api/AdminApiTokenTest.php`
- [ ] `cd tests/browser && npm run evidence`

---

## Task 9: Responsive And Accessibility Pass

**Files:**
- Modify: `public/assets/app.css`
- Modify: `templates/admin/users.php`
- Modify: `templates/admin/user_record.php`
- Modify: `templates/admin/structure.php`
- Modify: `templates/admin/tags.php`
- Modify: `templates/admin/email.php`
- Modify: `tests/browser/a11y.spec.ts`

**Remediation Instructions:**

- [ ] Use the shared `.table-scroll` pattern for dense admin tables or convert mobile rows into stacked cards with stable labels.
- [ ] Fix `/admin/users` mobile fragments by giving columns minimum widths or card labels for User, Role, Status, Reputation, Created, and Actions.
- [ ] Fix `/admin/structure` mobile clipping by rendering board/category row actions as stacked groups or a stable action menu on small screens.
- [ ] Add accessible labels or `aria-label` values to inline category rename inputs.
- [ ] Add labels to tag row inputs.
- [ ] Add a visible or screen-reader-only label to the email suppression address input.
- [ ] Wrap the email delivery table in a scroll region or convert delivery rows to cards.
- [ ] Increase `.role-admin` contrast and make user links visually distinguishable without relying only on color.

**Acceptance Criteria:**

- [ ] Axe has no critical violations on `/admin/structure`.
- [ ] Axe has no serious contrast or link-distinguishability violations on `/admin/users`.
- [ ] Mobile screenshots do not clip Delete, Archive, Requeue, Suspend, Ban, or Revoke controls.
- [ ] Text does not wrap into unreadable two-letter fragments.

**Verification:**

- [ ] `cd tests/browser && npm run a11y`
- [ ] Manual screenshots at `390x844`: `/admin`, `/admin/users`, `/admin/users/{id}`, `/admin/structure`, `/admin/email`.

---

## Task 10: Suppress Member Tour On Operator Routes

**Files:**
- Modify: `src/Core/App.php`
- Modify: `templates/layout.php` only if the data contract needs clarification
- Modify: `tests/Integration/Core/AppProductTourTest.php`
- Modify: `tests/browser/gate-a.spec.ts`

**Remediation Instructions:**

- [ ] Change the `needs_tour` calculation so routes beginning with `/admin` or `/mod` do not load the member onboarding overlay.
- [ ] Keep the tour behavior unchanged for normal member-facing pages.
- [ ] Do not remove `tour.js`; only suppress it for operator contexts.

**Acceptance Criteria:**

- [ ] First admin login to `/admin` does not show the member tour.
- [ ] First member login to a normal content route still shows the tour when expected.
- [ ] Operator screenshots are not obscured by onboarding UI.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Core/AppProductTourTest.php`
- [ ] `cd tests/browser && npm run evidence`

---

## Task 11: Email Operations Polish

**Files:**
- Modify: `templates/admin/email.php`
- Modify: `tests/Integration/Admin/AppAdminEmailTest.php`
- Modify: `tests/browser/a11y.spec.ts`

**Remediation Instructions:**

- [ ] Add a label to the suppression email input.
- [ ] Wrap the delivery log in `.table-scroll` or convert it to compact delivery cards on small screens.
- [ ] Keep Requeue as a plain POST form, since the audit confirmed it works.
- [ ] Preserve current filters for status, kind, and email after requeue where possible.

**Acceptance Criteria:**

- [ ] Failed delivery requeue still works through the UI.
- [ ] Suppression input is labelled.
- [ ] Delivery status, kind, email, and Requeue action remain readable at desktop and mobile widths.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Admin/AppAdminEmailTest.php`
- [ ] `cd tests/browser && npm run a11y`

---

## Task 12: Final Regression And Evidence

**Files:**
- Modify: `tests/browser/gate-a.spec.ts`
- Modify: `tests/browser/a11y.spec.ts`
- Add screenshots under the existing evidence convention only after implementation is complete.

**Remediation Instructions:**

- [ ] Run focused PHPUnit suites from Tasks 1-11.
- [ ] Run the full PHP suite.
- [ ] Run Playwright evidence and a11y in the browser harness.
- [ ] Run the CSP scan.
- [ ] Capture desktop and mobile screenshots for Dashboard, Structure, Users, User record, Tags, Badge rules, API tokens when enabled, and Email.
- [ ] Confirm no-JS archive/unarchive, user warning, failed form preservation, and email requeue still work.

**Verification Commands:**

```bash
composer test
cd tests/browser && npm run evidence
cd tests/browser && npm run a11y
rg -n "<script|<style| on[a-z]+=" templates/admin templates/mod templates/layout.php -S
```

**Final Acceptance Criteria:**

- [ ] All P0 and P1 audit findings are either fixed or explicitly recorded as Phase 5+ deferrals.
- [ ] Browser console is clean on audited admin routes.
- [ ] No critical axe violations remain on audited admin routes.
- [ ] Admin actions work with JavaScript disabled.
- [ ] The final evidence notes link back to `/tmp/retroboards-admin-audit/findings.md` and this remediation plan.

---

## Finding-To-Task Map

- [ ] Finding 1 dashboard operations gap: Task 2.
- [ ] Finding 2 inconsistent nav: Task 1.
- [ ] Finding 3 tour overlay: Task 10.
- [ ] Finding 4 missing user actions: Task 3.
- [ ] Finding 5 user directory filters/sorting/bulk: Task 4.
- [ ] Finding 6 user table responsive/contrast: Task 9.
- [ ] Finding 7 structure confirmation/impact: Task 5.
- [ ] Finding 8 structure anti-draft-loss: Task 5.
- [ ] Finding 9 structure mobile clipping: Task 9.
- [ ] Finding 10 category input labels: Task 9.
- [ ] Finding 11 tag anti-draft-loss/labelling/merge: Task 6.
- [ ] Finding 12 badge backfill/revoke safety: Task 7.
- [ ] Finding 13 API token revoke safety: Task 8.
- [ ] Finding 14 email table pattern: Task 11.
- [ ] Finding 15 suppression input label: Task 11.

