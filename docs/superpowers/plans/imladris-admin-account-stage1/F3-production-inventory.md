# F3 — Production surface inventory (admin + account)

Analyst: production surface inventory. Repo `C:/Users/htper/community-forums` @ `main` (working tree, 2026-08-03).
Sources read in full: `src/Core/App.php::buildRouter()` (lines 1994–2414), `src/Core/FeatureFlags.php`,
`templates/admin/_nav.php`, `templates/partials/settings_nav.php`, `templates/layout.php`, all 41 files in
`templates/admin/`, all 13 in `templates/account/`, plus `templates/mod/*` and `templates/appeals/index.php`
(reachable from the two navs). Controller guards confirmed by opening each controller — no guard is inferred.

---

## 0. Two facts that change the brief

### 0.1 There are **11** admin/account design screens, not 7

`docs/design-system/imladris/templates/` gained **four more screens mid-session** (mtime 2026-08-03 20:20–20:21,
still untracked — `git status` shows them as `??`):

| dir | file | added |
|---|---|---|
| `admin-members/` | `AdminMembers.dc.html` (88 KB) | 2026-08-03 20:20 |
| `admin-features/` | `AdminFeatures.dc.html` (51 KB) | 2026-08-03 20:21 |
| `admin-integrations/` | `AdminIntegrations.dc.html` (63 KB) | 2026-08-03 20:21 |
| `admin-packages/` | `AdminPackages.dc.html` (75 KB) | 2026-08-03 20:21 |

They carry exactly the surfaces that were about to be reported as "no design representation": Directory +
Invitations; Feature flags + Badge rules + Custom emoji; API tokens + Webhooks + Sign-in providers; Packages +
Registry trust + Extensions. **Question B must be answered against 11 screens, not 7**, or ~20 production pages
would be wrongly classified as `feature-added`. Note `admin-packages/` ships *only* the `.dc.html` — no
`ds-base.js`/`support.js` — so it may still be mid-sync.

### 0.2 The design screens carry **zero** class attributes and **zero** real hrefs

| screen | `class="` | `style="` | `style-hover="` | non-`#` hrefs |
|---|---|---|---|---|
| AdminOverview | 0 | 142 | 16 | 0 (8× `href="#"`) |
| AdminPeople | 0 | 203 | 14 | 0 |
| AdminContent | 0 | 160 | 24 | 0 |
| AdminAppearance | 0 | 138 | 16 | 0 |
| AdminNotifications | 0 | 142 | 13 | 0 |
| AdminSettings | 0 | 120 | 9 | 0 |
| AdminMembers | 0 | 286 | 14 | 0 |
| AdminFeatures | 0 | 155 | 9 | 0 |
| AdminIntegrations | 0 | 201 | 12 | 0 |
| AdminPackages | 0 | 267 | 13 | 0 |
| AccountSettings | 0 | 360 | 53 | 0 |

2174 inline `style` + 193 `style-hover` attributes across the 11 screens; **every** `href` is the placeholder
`"#"`. Consequences for the brief's constraints:
- **Constraint 1 (CSP)** is total, not partial: there is no class vocabulary to import. Every rule must be
  authored into `public/assets/*.css` and named by production, then mapped back to the design's rendered pixels.
- **Constraint 4 (route names are fiction)** is *inverted*: there are no fictional route names to reject —
  there are no routes at all. Navigation must be re-derived wholesale from `buildRouter()`; the design gives
  zero navigation evidence.

---

## 1. Route table — every `/admin`, `/settings`, `/mod`, `/drafts`, `/appeals` route

All `/admin/*` routes go through `Controller::requireAdmin()` → `ForbiddenException` (403) for non-admins.
`/settings/*` and `/drafts` and `/appeals` go through `requireUser()` → redirecting `HttpException(302, …,
'/login?next=…')`. `/mod/*` follows ADR 0023 D1: **404** for zero-authority browsing, **403** for an
unauthorised action.

### 1.1 `/admin` (39 GET + 96 POST)

| Method | Path | Controller::method | Flag guard (default) |
|---|---|---|---|
| GET | `/admin` | `AdminController::dashboard` (AdminController.php:31) | — (core) |
| GET | `/admin/audit` | `AdminController::audit` (:45) | — |
| GET | `/admin/structure` | `AdminController::structure` (:80) | — |
| POST | `/admin/site` | `AdminSettingsController::updateSite` | — |
| POST | `/admin/categories` | `AdminController::createCategory` (:87) | — |
| POST | `/admin/categories/{id}` | `AdminController::updateCategory` (:102) | — |
| GET | `/admin/categories/{id}/delete` | `AdminController::confirmDeleteCategory` (:124) | — |
| POST | `/admin/categories/{id}/delete` | `AdminController::deleteCategory` (:131) | — |
| POST | `/admin/categories/{id}/move` | `AdminController::moveCategory` (:363) | — |
| GET | `/admin/boards/{id}/edit` | `AdminController::editBoard` (:147) | — |
| POST | `/admin/boards` | `AdminController::createBoard` (:159) | — |
| POST | `/admin/boards/{id}` | `AdminController::updateBoard` (:174) | — |
| GET/POST | `/admin/boards/{id}/delete` | `AdminController::confirmDeleteBoard` (:330) / `deleteBoard` (:337) | — |
| GET/POST | `/admin/boards/{id}/archive` | `confirmArchiveBoard` (:419) / `archiveBoard` | — |
| GET/POST | `/admin/boards/{id}/unarchive` | `confirmUnarchiveBoard` / `unarchiveBoard` | — |
| POST | `/admin/boards/{id}/move` | `AdminController::moveBoard` (:376) | — |
| POST | `/admin/structure/reorder` | `AdminController::reorder` (:395) | — |
| POST | `/admin/boards/{id}/moderators` `…/remove` | `assignModerator` (:213) / `unassignModerator` (:227) | — |
| POST | `/admin/boards/{id}/members` `…/remove` | `addMember` (:239) / `removeMember` (:253) | — |
| GET | `/admin/settings` | `AdminSettingsController::general` (:97) | — |
| POST | `/admin/settings` | `AdminSettingsController::obsoleteCombinedUpdate` | — |
| POST | `/admin/settings/registration` | `AdminSettingsController::updateRegistration` (:54) | — |
| GET | `/admin/moderation` | `AdminSettingsController::moderation` (:107) | `anti_abuse` (**ON**) at :116 |
| POST | `/admin/moderation` | `AdminSettingsController::updateAntiAbuse` (:90) | `anti_abuse` |
| GET | `/admin/features` | `AdminFeatureController::index` (:168; guard :120) | — |
| GET | `/admin/users` | `AdminUserController::index` (:364) | — |
| POST | `/admin/users/bulk` | `AdminUserController::bulkConfirm` (:378) | — |
| POST | `/admin/users/bulk/apply` | `AdminUserController::bulkApply` | — |
| GET | `/admin/users/{id}` | `AdminUserController::show` (:435) | — (sub-gate `profile_media` **ON** at :449/:465) |
| POST | `/admin/users/{id}/…` `pii` `title` `avatar/remove` `signature/remove` `badges/grant` `badges/revoke` `warn` `note` `suspend` `ban` `lift` `role` | `AdminUserController::*` | — (`role` is deliberately flag-**independent**, App.php:2377-2379) |
| GET | `/admin/roles` | `AdminRoleController::index` (:261) | `capabilities` (**ON**) at :29 |
| POST | `/admin/roles` | `AdminRoleController::create` | `capabilities` |
| GET | `/admin/roles/simulator` | `AdminRoleController::simulator` (:93) | `capabilities` |
| GET/POST | `/admin/roles/{id}` | `edit` (:278) / `update` | `capabilities` |
| POST | `/admin/roles/{id}/clone` `…/assignments` | `clone` / `assign` | `capabilities` |
| POST | `/admin/role-assignments/{id}/revoke` `…/renew` | `revokeAssignment` / `renewAssignment` | `capabilities` |
| GET/POST | `/admin/invitations` | `AdminInvitationController::index` (:102) / `create` (:55) | `invitations` (**ON**) at :78 |
| POST | `/admin/invitations/{id}/revoke` | `AdminInvitationController::revoke` | `invitations` |
| GET/POST | `/admin/badge-rules` | `AdminBadgeRuleController::index` (:23) / `create` (:39) | `badge_rules` (**ON**) via `requireEnabled()` :88 |
| GET | `/admin/badge-rules/{id}/preview` | `AdminBadgeRuleController::preview` (:57) | `badge_rules` |
| POST | `/admin/badge-rules/{id}/{enable\|disable\|backfill\|revoke}` | `AdminBadgeRuleController::*` | `badge_rules` |
| GET/POST | `/admin/branding` | `BrandingController::form` (:77) / `update` (:93) | `branding` (**ON**) at :34; sub-gate `custom_css` (**OFF**) at :59/:146 |
| GET | `/admin/themes` | `AdminThemeController::index` (:128) | `package_themes` (**ON**) at :120 |
| GET/POST | `/admin/themes/safe-mode` | `safeModeForm` (:136) / `safeMode` | `package_themes` |
| POST | `/admin/themes/preview/clear` `…/rollback` `…/{id}/preview` `…/{id}/activate` | `AdminThemeController::*` | `package_themes` |
| GET | `/admin/packages` | `AdminPackagesController::index` (:33) | `package_registry` (**ON**) at :22 |
| GET | `/admin/packages/security` | `AdminPackageSecurityController::index` (:80) | `package_registry` (:255) |
| POST | `/admin/packages/security/execution` | `emergencyDisable` | `package_registry` |
| GET | `/admin/packages/publishers/{id}` | `AdminPackageSecurityController::publisher` (:287) | `package_registry` |
| POST | `/admin/packages/publishers/{id}/{verify\|suspend\|reinstate\|keys\|rotate}` | `AdminPackageSecurityController::*` | `package_registry` |
| POST | `/admin/publisher-keys/{id}/revoke` | `revokePublisherKey` | `package_registry` |
| GET | `/admin/packages/{id}` | `AdminPackagesController::show` (:62) | `package_registry` |
| POST | `/admin/packages/{id}/plan` | `AdminPackageLifecycleController::plan` (:40) | `package_registry` (:297) |
| GET/POST | `/admin/packages/{id}/consent` | `consentForm` (:425) / `consent` | `package_registry` |
| POST | `/admin/packages/{id}/{install\|enable\|disable\|pin\|update-policy\|update\|update/cancel\|rollback\|uninstall\|export\|reverify}` | `AdminPackageLifecycleController::*` | `package_registry` |
| POST | `/admin/packages/{id}/review` | `AdminPackageSecurityController::recordReview` (:250) | `package_registry` |
| POST | `/admin/packages/{id}/integration/{settings\|provision\|disable\|export}` `…/credentials/{credentialId}/{rotate\|revoke}` | `AdminPackageIntegrationController::*` (:156 guard) | `package_registry` |
| GET/POST | `/admin/registries` | `AdminRegistryController::index` (:252) / `create` | `package_registry` (:31) |
| POST | `/admin/registries/{id}/{enabled\|keys\|rotate\|advisories}` | `AdminRegistryController::*` | `package_registry` |
| POST | `/admin/registry-keys/{id}/revoke`, `/admin/advisories/{id}/ack`, `/admin/blocklist`, `/admin/blocklist/{id}/remove` | `AdminRegistryController::*` | `package_registry` |
| GET/POST | `/admin/webhooks` | `AdminWebhookController::index` (:35) / `create` | `webhooks` (**ON**) at :20 |
| GET/POST | `/admin/webhooks/{id}` | `show` (:90) / `update` (:112) | `webhooks` |
| POST | `/admin/webhooks/{id}/{toggle\|rotate\|test\|delete}`, `…/deliveries/{deliveryId}/replay` | `AdminWebhookController::*` | `webhooks` |
| GET/POST | `/admin/api-tokens` | `AdminApiTokenController::index` (:26) / `mint` (:44) | `api_tokens` (**ON**) at :16 |
| POST | `/admin/api-tokens/{id}/revoke` | `revoke` | `api_tokens` |
| GET/POST | `/admin/providers` | `AdminProviderController::index` (:156) / `create` | `provider_registry` (**ON**) at :134 |
| POST | `/admin/providers/{id}/test` `…/enable` | `test` / `enable` | `provider_registry` |
| GET/POST | `/admin/providers/{id}/disable` | `disableConfirm` (:173) / `disable` | `provider_registry` |
| GET | `/admin/email` | `AdminEmailController::index` (:54) | `email` (**ON**) at :25 |
| GET | `/admin/email/export` | `AdminEmailController::export` | `email` — **CSV, no template** |
| POST | `/admin/email/{test\|domain/verify\|suppressions\|suppressions/remove}`, `…/deliveries/{id}/requeue` | `AdminEmailController::*` | `email` |
| GET/POST | `/admin/announcements` | `AdminAnnouncementController::form` (:37) / `save` (:47) | `announcements` (**ON**) at :22 |
| GET/POST | `/admin/custom-emoji` | `AdminCustomEmojiController::index` (:60) / `create` (:33) | `custom_emoji` (**ON**) via `requireEnabled()` :69 |
| POST | `/admin/custom-emoji/{shortcode}/{enable\|disable}` | `AdminCustomEmojiController::*` | `custom_emoji` |
| GET/POST | `/admin/tags` | `TagController::admin` (:229) / `create` | `tags` (**ON**) via `requireTags()` :218 |
| POST | `/admin/tags/{id}` | `TagController::update` | `tags` |
| GET/POST | `/admin/tags/{id}/merge` | `mergeConfirm` (:147) / `merge` | `tags` |
| GET | `/admin/thread-intelligence` | `AdminThreadIntelligenceController::index` (:14) | **NONE** — see §7.2 |
| POST | `/admin/thread-intelligence/generation/{pause\|resume}`, `…/provider/retry`, `…/threads/{id}/{retry\|reconcile\|pause\|resume}` | `AdminThreadIntelligenceController::*` | **NONE** |
| GET | `/admin/extensions` | `AdminExtensionController::index` (:24) | `server_extensions` (**OFF**) at :20 |
| POST | `/admin/link-previews/{id}/{refresh\|purge}` | `AdminLinkPreviewController::*` (:34) | `link_previews` (**OFF**) — **POST-only, no page** |

### 1.2 `/settings` + `/drafts` + `/appeals`

| Method | Path | Controller::method | Flag (default) |
|---|---|---|---|
| GET | `/settings` | `AccountController::index` (:27) → **302 `/settings/account`** | — |
| GET/POST | `/settings/account` | `accountForm` (:34) / `updateAccount` (:85 → 422) | — (sub-gates `custom_profile_fields`, `profile_media`, both **ON**) |
| POST | `/settings/account/export` | `exportAccount` | `account_lifecycle` (**ON**) at :323 |
| GET | `/settings/account/lifecycle` | `lifecycleForm` (:307) | `account_lifecycle` |
| POST | `/settings/account/{deactivate\|reactivate\|delete/request\|delete/cancel}` | `AccountController::*` (422 at :130/143/160/173) | `account_lifecycle` |
| POST | `/settings/avatar` `…/remove` | `uploadAvatar` / `removeAvatar` (:316) | `profile_media` (**ON**) |
| GET/POST | `/settings/security` | `securityForm` (:292) / `updateSecurity` (422 :212) | — |
| POST | `/settings/security/totp/{enroll\|confirm\|recovery/rotate\|disable}` | `AccountController::*` (422 :228/246/263/281) | — |
| POST | `/settings/security/passkeys` `…/challenge` `…/step-up-challenge` `…/{id}/rename` `…/{id}/revoke` | `PasskeyController::*` (:177) | `passkeys` (**ON**) |
| GET/POST | `/settings/privacy` | `SettingsController::privacyForm` (:38) / `updatePrivacy` | — |
| GET/POST | `/settings/appearance` | `appearanceForm` (:57) / `updateAppearance` | — |
| GET/POST | `/settings/preferences` | `preferencesForm` (:72) / `updatePreferences` | — |
| POST | `/settings/preferences/reset` | `resetPreferences` | — (form lives on **appearance.php:66**) |
| GET | `/settings/preferences/export` | `exportPreferences` | — (download, no template) |
| GET/POST | `/settings/composing` | `composingForm` (:87) / `updateComposing` | — |
| GET/POST | `/settings/notifications` | `notificationsForm` (:134) / `updateNotifications` | — |
| GET | `/settings/sessions` | `sessions` (:163) | — |
| POST | `/settings/sessions/revoke` `…/revoke-others` | `revokeSession` / `revokeOtherSessions` | — |
| GET | `/settings/blocks` | `BlockController::index` (:26) | — |
| GET | `/settings/boards` | `SettingsController::boards` (:226) | — (sub-gates `board_folders`/`saved_feeds`/`bookmark_folders`, all **ON**, :212-214) |
| POST | `/settings/boards/toggle` | `toggleBoardPref` | — |
| POST | `/settings/board-folders` `…/{id}/boards` | `PersonalOrganizationController::*` | `board_folders` |
| POST | `/settings/bookmark-folders` `…/add-thread` `…/{id}/threads` | `PersonalOrganizationController::*` | `bookmark_folders` |
| POST | `/settings/saved-feeds` | `createSavedFeed` | `saved_feeds` |
| GET | `/settings/connections` | `OAuthController::connections` (:118) | `oauth` (**ON**) at :95/128 |
| POST | `/settings/connections/unlink` `…/set-password` | `unlink` (:144) / `setPassword` (:163) | `oauth` |
| GET | `/drafts` | `DraftController::index` (:31) | `drafts` (**ON**) at :23; sub-gate `server_drafts` (**ON**) at :97 |
| POST | `/drafts/{id}/discard` | `DraftController::discardPage` | `drafts` |
| GET | `/appeals` | `AppealController::index` | `appeals` (**ON**) at :93 |
| POST | `/appeals/posts/{id}` `…/modlog/{id}` | `openPost` / `openModerationLog` | `appeals` |

### 1.3 `/mod` (linked from the admin nav)

| Method | Path | Controller::method | Flag |
|---|---|---|---|
| GET | `/mod/reports` | `ReportController::queue` | `moderation_queue` (**ON**) |
| POST | `/mod/reports/{id}/{claim\|resolve\|dismiss}` | `ReportController::*` | `moderation_queue` |
| GET | `/mod/approvals` | `ApprovalController::queue` | `moderation_queue` (gate added by ADR 0023 D1) |
| POST | `/mod/approvals/{thread\|post}/{id}/{approve\|reject}` | `ApprovalController::*` | `moderation_queue` |
| GET | `/mod/appeals` | `AppealController::queue` | `appeals` |
| POST | `/mod/appeals/{id}/resolve` | `AppealController::resolve` | `appeals` |
| GET | `/mod/u/{id}` | `UserModerationController::show` | — |
| POST | `/mod/u/{id}/{warn\|note\|suspend\|ban\|lift}` | `UserModerationController::*` | — |
| POST | `/mod/t/{id}/{pin\|lock\|move\|split\|merge}`, `/mod/p/{id}/{restore\|reveal}` | `ModerationController::*` | `split_merge` for split/merge |

---

## 2. Template inventory

### 2.1 `templates/admin/` — 41 files (38 full pages, 3 partials)

Every full page except `theme_safe_mode.php` is `<div class="admin">` → `<header class="admin-head">` →
`partial('admin/_nav')` → `<div class="admin-pane">` → N × `<section class="card">`.
Shared vocabulary across nearly all: `card`, `stacked` (forms), `table-scroll`, `audit` (dense tables),
`btn` / `btn-small` / `btn-ghost` / `linkbtn` / `danger`, `field` / `input` / `field-error`, `muted`,
`pill pill-admin`, `form-actions`, `inline-form`, `sr-only`, `state` / `state-*`.

| # | Template | Type | Rendering route(s) | Controller::method | Flag (default) | Nav key | Distinctive top-level classes |
|---|---|---|---|---|---|---|---|
| 1 | `dashboard.php` | full page | GET `/admin` | `AdminController::dashboard` | — | `dashboard` | `admin` · `admin-head` · `admin-pane` · `eyebrow` "Operator desk" · `pane-intro` · `admin-dashboard-section` · `section-heading-row` · `admin-dashboard-grid` · `card queue-card queue-status-*` · `card attention-panel` · `attention-list` · `attention-total` · `activity-card-grid` · `card activity-card` · `card recent-activity-card` · `audit audit-recent` · `status-legend` · `table-scroll-cue` |
| 2 | `audit.php` | full page | GET `/admin/audit` (+422 :76) | `AdminController::audit` | — | `audit` | `admin` · `eyebrow` "Accountability" · `pane-intro` · `filter-form` `filter-grid` · `card` · `audit` · `audit-change` · `action-cell` · `pager` |
| 3 | `structure.php` | full page | GET `/admin/structure`; 422 re-render from `/admin/categories`, `/admin/categories/{id}`, `/admin/boards`, `/admin/structure/reorder`, `/admin/{categories,boards}/{id}/move` | `AdminController::structure` (+`structureView` :489) | — | `structure` | `admin-structure` · `card admin-cat` · `admin-cat-head` · `admin-cat-actions` · `admin-board-list` · `admin-board-row` · `admin-board-actions` · `tag tag-archived` · `hash` · `checkline` |
| 4 | `board_edit.php` | full page | GET `/admin/boards/{id}/edit`; 422 from POST `/admin/boards/{id}` (:186) and the 4 roster POSTs (:282) | `AdminController::editBoard` (+`boardEditView` :462) | — | `structure` | `admin` · `form.stacked.card` · `card` · `admin-board-list` · `admin-board-row` · `inline-form` · `checkline` · `flash flash-error` |
| 5 | `structure_confirm.php` | **confirmation interstitial** | GET `/admin/categories/{id}/delete`, `/admin/boards/{id}/delete`, `…/archive`, `…/unarchive`; 422 from each matching POST | `confirmDeleteCategory` :124, `confirmDeleteBoard` :330, `confirmArchiveBoard` :419, `confirmUnarchiveBoard` | — | `structure` | `card confirm-card` · `form.stacked.confirm-form` · `impact-list` · `danger` |
| 6 | `settings.php` | full page | GET `/admin/settings` | `AdminSettingsController::general` | — | `settings` | `eyebrow` "Operator desk" · `pane-intro` · 2× `card settings-card` (one form each) |
| 7 | `moderation.php` | full page | GET `/admin/moderation` | `AdminSettingsController::moderation` | `anti_abuse` (ON) | `moderation` | `eyebrow` "Moderation" · `pane-intro` · `card settings-card` |
| 8 | `features.php` | full page | GET `/admin/features` | `AdminFeatureController::index` | — | `features` | `eyebrow` "Runtime controls" · `pane-intro` (very long) · `admin-dashboard-grid` + 4× `card queue-card is-static` · `card` · `audit audit-flags` |
| 9 | `thread_intelligence.php` | full page | GET `/admin/thread-intelligence` | `AdminThreadIntelligenceController::index` | **none in controller** (nav uses `community_memory`\|`automated_context`, both ON) | `thread_intelligence` | `admin thread-intelligence-admin` · `eyebrow` "Operations" · `card ti-attention` · `admin-dashboard-grid` + `card queue-card is-static` · `card ti-controls` · `card ti-budget` · `ti-evidence` · `ti-metadata` · `ti-actions` |
| 10 | `users.php` | full page | GET `/admin/users` | `AdminUserController::index` | — | `users` | `card` · `filter-form filter-grid` · `user-directory` · `user-link` · `bulk-bar` · `col-select` · `role-pill role-*` · `state state-*` · `pager` |
| 11 | `users_bulk_confirm.php` | **confirmation interstitial (POST-rendered)** | POST `/admin/users/bulk` | `AdminUserController::bulkConfirm` :378 | — | `users` | `card confirm-card` · `link-list` · `role-pill` · `state` |
| 12 | `user_record.php` | full page (drill-in) | GET `/admin/users/{id}` | `AdminUserController::show` :435 | — (`profile_media` ON sub-gate) | `users` | `card` ×7 · `profile-media-card` · `avatar-row` `avatar-img` · `monogram monogram-gilt` · `profile-stats` · `record-body` `record-list` `record-when` · `role-pill` · `badge-icon` |
| 13 | `roles.php` | full page | GET `/admin/roles` | `AdminRoleController::index` :261 | `capabilities` (ON) | `roles` | `card` · `audit` · `stacked` |
| 14 | `role_edit.php` | full page (drill-in) | GET `/admin/roles/{id}`; 422 from `update`/`clone`/`assign` | `AdminRoleController::edit` :278 | `capabilities` | `roles` | 5× `card` · `audit` · `inline-form` · `state state-*` · `field-error` (ADR 0023 deferral #4: not wired to inputs) |
| 15 | `role_simulator.php` | full page | GET `/admin/roles/simulator` | `AdminRoleController::simulator` :93 | `capabilities` | `roles` | `card` · **`<form method="get">`** · `field-error` |
| 16 | `invitations.php` | full page | GET `/admin/invitations`; 422 from POST `/admin/invitations` (:55) | `AdminInvitationController::index` :102 | `invitations` (ON) | `invitations` | `card` ×2 · `audit` · `flash` · `stacked` |
| 17 | `badge_rules.php` | full page | GET `/admin/badge-rules`; 422 from POST (:45) | `AdminBadgeRuleController::index` :23 | `badge_rules` (ON) | `badge_rules` | `card` ×2 · `link-list` · `stacked` |
| 18 | `badge_rule_preview.php` | interstitial (read-only preview) | GET `/admin/badge-rules/{id}/preview` | `AdminBadgeRuleController::preview` :57 | `badge_rules` | `badge_rules` | `card` · `link-list` |
| 19 | `branding.php` | full page | GET `/admin/branding`; 422 from POST (:93/:160) | `BrandingController::form` :77 | `branding` (ON); `custom_css` (**OFF**) sub-gate | `branding` | `eyebrow` "Operator desk" · `pane-intro` · `card brand-cols` · `brand-preview*` (5 classes) · `code-area` (custom CSS) · `form.stacked.card` |
| 20 | `themes.php` | full page | GET `/admin/themes` | `AdminThemeController::index` :128 | `package_themes` (ON) | `themes` | `card` ×4 · `audit` · `inline-form` · `action-cell` |
| 21 | `theme_safe_mode.php` | full page, **`variant=plain`** | GET `/admin/themes/safe-mode` | `AdminThemeController::safeModeForm` :136 | `package_themes` | `themes` | **`container`** (not `admin`), `admin-head`, `card` ×3, `pill pill-admin` "Recovery" — **no `admin-pane`**, yet still renders `admin/_nav` (§7.3) |
| 22 | `packages.php` | full page | GET `/admin/packages` | `AdminPackagesController::index` :33 | `package_registry` (ON) | `packages` | `card` · `audit` · `action-cell` · `field-error` |
| 23 | `package_detail.php` | full page (drill-in) | GET `/admin/packages/{id}`; re-rendered by lifecycle (:384), integration (:224), security review (:250) | `AdminPackagesController::show` :62 | `package_registry` | `packages` | `card` ×N · `audit` · `form-grid` · `inline-form` · embeds `_package_review_form` + `_package_integration` |
| 24 | `package_plan.php` | **interstitial (POST-rendered)** | POST `/admin/packages/{id}/plan` | `AdminPackageLifecycleController::plan` :40 | `package_registry` | `packages` | `card` ×3 · `audit` · `stacked` · `linkbtn` |
| 25 | `package_consent.php` | **confirmation interstitial** | GET `/admin/packages/{id}/consent` | `consentForm` :425 | `package_registry` | `packages` | `card` ×2 · `audit` · `field-error` |
| 26 | `package_security.php` | full page | GET `/admin/packages/security` | `AdminPackageSecurityController::index` :80 | `package_registry` | `packages` | `card` ×4 · `audit` · `inline-form` · `danger` |
| 27 | `package_publisher.php` | full page (drill-in) | GET `/admin/packages/publishers/{id}` | `publisher` :287 | `package_registry` | **`registries`** (inconsistent with #26's `packages`) | `card` ×3 · `audit` · `table-scroll-wide` · `form-cell` |
| 28 | `registries.php` | full page | GET `/admin/registries` | `AdminRegistryController::index` :252 | `package_registry` | `registries` | `card` ×4 · `audit` · `table-scroll-wide` · `form-cell` · `field-error` (ADR 0023 deferral #4) |
| 29 | `webhooks.php` | full page | GET `/admin/webhooks`; 422 (:58/:66) | `AdminWebhookController::index` :35 | `webhooks` (ON) | `webhooks` | `card` ×2 · `audit` · `flash` · `state` |
| 30 | `webhook_detail.php` | full page (drill-in) | GET `/admin/webhooks/{id}`; 422 (:112/:153/:189) | `show` :90 | `webhooks` | `webhooks` | `card` ×3 · `audit` · `flash` · `danger` |
| 31 | `api_tokens.php` | full page | GET `/admin/api-tokens`; 422 (:44) | `AdminApiTokenController::index` :26 | `api_tokens` (ON) | `api_tokens` | `card` ×2 · `audit` · `flash flash-error` · `state` |
| 32 | `providers.php` | full page | GET `/admin/providers` | `AdminProviderController::index` :156 | `provider_registry` (ON) | `providers` | `card` ×2 · `audit` · `inline-form` (carries `data-sole-count`, retained per ADR 0023 "Reclassified") |
| 33 | `provider_disable.php` | **confirmation interstitial** | GET `/admin/providers/{id}/disable` | `disableConfirm` :173 | `provider_registry` | `providers` | `card` · `audit` · `btn-secondary` · `field-error` |
| 34 | `email.php` | full page | GET `/admin/email`; 422 (:82/:95) | `AdminEmailController::index` :54 | `email` (ON) | `email` | `card` ×5 · `email-status-facts` (ADR 0023 shipped #3: one fact per line) · `stat-cards` `stat-card` `stat-num` `stat-label` · `audit` · `pager` |
| 35 | `announcements.php` | full page | GET `/admin/announcements`; 422 (:47) | `AdminAnnouncementController::form` :37 | `announcements` (ON) | `announcements` | `card` ×3 · `site-announcement-current` · `audit` |
| 36 | `custom_emoji.php` | full page | GET `/admin/custom-emoji`; 422 (:33) | `AdminCustomEmojiController::index` :60 | `custom_emoji` (ON) | `custom_emoji` | `eyebrow` "Appearance" · `pane-intro` · `custom-emoji-panel` · `card` ×2 · `form-grid` · `checkline` |
| 37 | `tags.php` | full page | GET `/admin/tags`; 422 via `renderAdminTags` :229 | `TagController::admin` | `tags` (ON) | `tags` | `card` ×2 · `admin-board-list` `admin-board-row` (reused from structure) · `input-small` · `error-list` · `empty` |
| 38 | `tag_merge_confirm.php` | **confirmation interstitial** | GET `/admin/tags/{id}/merge` | `TagController::mergeConfirm` :147 | `tags` | `tags` | `card confirm-card` · `confirm-form` · `impact-list` |
| 39 | `extensions.php` | full page | GET `/admin/extensions` | `AdminExtensionController::index` :24 | **`server_extensions` (OFF)** — the only default-dark admin page | `extensions` | `card` ×4 · `audit` |
| P1 | `_nav.php` | **partial** | included by all 38 above | — | reads `$features` | — | `admin-sections-toggle` · `subnav admin-subnav` · `admin-nav-drawer-head` · `admin-nav-close` · `admin-nav-group` · `admin-nav-group-title` · `admin-nav-group-list` · `admin-nav-link` (`.active`, `.is-disabled`) · `subnav-item-label` · `subnav-item-note` · `admin-nav-scrim` |
| P2 | `_package_integration.php` | **partial** | inside `package_detail.php:289` | `AdminPackageIntegrationController` POSTs | `package_registry` | — | `section.card#integration` · `card reveal` · `integration-actions` · `audit` · `field-error` |
| P3 | `_package_review_form.php` | **partial** | inside `package_detail.php:75` (per release row) | POST `/admin/packages/{id}/review` | `package_registry` | — | `review-decision-form` |

### 2.2 `templates/account/` — 13 files, all full pages

All 13 are `<div class="settings-screen">` → `<header class="settings-head">` (with
`<span class="eyebrow">Account</span>` + `<h1>`) → `<div class="settings">` → `partial('partials/settings_nav')`
→ `<div class="settings-pane">`. Two body idioms: **`scribe-panel`** (preference forms —
`form.stacked.scribe-panel` + `scribe-panel-head`) and **`card`** (list/record surfaces).

| # | Template | Rendering route(s) | Controller::method | Flag (default) | Nav label | Distinctive classes |
|---|---|---|---|---|---|---|
| 1 | `settings.php` | GET `/settings/account` (and 302 target of `/settings`); 422 from POST `/settings/account` (:92) | `AccountController::accountForm` :34 | — (`custom_profile_fields` ON, `profile_media` ON) | Profile | `card notice` · `profile-media-panel` · `avatar-row` `avatar-img` `avatar-actions` · `monogram monogram-gilt` · `form.stacked.scribe-panel` · `field-grid` `field-row` · `input-engraved` `textarea-engraved` · `custom-profile-fields` `row-bullet` `row-input` `row-mark` |
| 2 | `security.php` | GET `/settings/security`; 422 ×5 (:212/228/246/263/281) | `AccountController::securityForm` :292 | — (`passkeys` ON) | Security | 3× `scribe-panel` (password, TOTP, `[data-passkey-panel]`) · `code-list` · `btn-secondary` · `field-error` |
| 3 | `privacy.php` | GET `/settings/privacy` | `SettingsController::privacyForm` :38 | — | Privacy | `form.stacked.scribe-panel` · `toggle-stack` · `gem-field` `gem-check` `gem-leaf` `gem-river` `gem-gold` `gem-sub` |
| 4 | `appearance.php` | GET `/settings/appearance` | `SettingsController::appearanceForm` :57 | — | Appearance | `scribe-panel` · `choice-cards` `choice-card` `choice-card-title` `choice-card-desc` · `theme-swatch` `swatch-parchment` `swatch-twilight` `swatch-system` `sw-bg` `sw-card` `sw-accent` · `density-prev` `is-compact` · `switchline` `switch` `switch-text` · `stacked card` + a second form posting to `/settings/preferences/reset` |
| 5 | `preferences.php` | GET `/settings/preferences` | `preferencesForm` :72 | — | Reading | `scribe-panel` · `toggle-stack` · `gem-field` `gem-check` `gem-leaf` |
| 6 | `composing.php` | GET `/settings/composing` | `composingForm` :87 | — | Composing | `scribe-panel` · `switchline` `switch` `switch-text` |
| 7 | `drafts.php` | GET `/drafts` | `DraftController::index` :31 | `drafts` (ON); `server_drafts` (ON) sub-gate | Drafts | `card[data-drafts-list]` · `local-drafts` · `report-list` `report-row` `report-head` `report-excerpt` · `badge` · `inline-form` |
| 8 | `notifications.php` | GET `/settings/notifications` | `notificationsForm` :134 | — | Notifications | `scribe-panel` · `checkline` · `card` · `sub-list` `sub-row` |
| 9 | `connections.php` | GET `/settings/connections` | `OAuthController::connections` :118 | `oauth` (ON) | Connections | `card` ×2 · `connections-list` `connection-row` `connection-name` · `pill` · `field-error` |
| 10 | `sessions.php` | GET `/settings/sessions` | `SettingsController::sessions` :163 | — | Sessions | `card` · `sessions-head` · `session-list` `session-row` `session-meta` `session-ua` · `pill` |
| 11 | `blocks.php` | GET `/settings/blocks` | `BlockController::index` :26 | — | Blocks | `card` · `people-list` `person-row` `person-name` |
| 12 | `boards.php` | GET `/settings/boards` | `SettingsController::boards` :226 | — (`board_folders`/`bookmark_folders`/`saved_feeds` all ON, :212-214) | Boards | `card` · `board-pref-list` `board-pref-row` `board-pref-name` `board-cat` · `personal-org-grid` · `org-card` `org-card-head` `org-folder` `org-folder-list` `org-items` `org-count` `org-empty` `org-star` `org-icon` `org-form` · `board-folder-card` `bookmark-folder-card` `saved-feed-card` |
| 13 | `lifecycle.php` | GET `/settings/account/lifecycle`; 422 ×4 (:130/143/160/173) | `AccountController::lifecycleForm` :307 | `account_lifecycle` (ON) | Account | `card error-list` · `card stacked` ×2 · `card stacked danger-zone` · `inline-form` |

**Also inside the settings rail but not in `templates/account/`:** `templates/appeals/index.php`
(GET `/appeals`, `AppealController::index`, flag `appeals` ON) — `settings-screen` / `settings-head` /
`settings-pane mod-pane`, eyebrow **"Council record"**.

---

## 3. Nav structures and how `layout.php` frames both surfaces

### 3.1 Admin nav — `templates/admin/_nav.php` (8 groups, 20 items)

```
Dashboard      dashboard  Dashboard              /admin                     —
Moderation     reports    Reports                /mod/reports               moderation_queue (ON)
               approvals  Approvals              /mod/approvals             moderation_queue (ON)
               appeals    Appeals                /mod/appeals               appeals (ON)
               audit      Audit log              /admin/audit               —
               moderation Anti-abuse             /admin/moderation          anti_abuse (ON)
Content        structure  Boards & categories    /admin/structure           —
               tags       Tags                   /admin/tags                tags (ON)
People         users      Users                  /admin/users               —
               roles      Roles                  /admin/roles               capabilities (ON)
               invitations Invitations           /admin/invitations         invitations (ON)
               badge_rules Badge rules           /admin/badge-rules         badge_rules (ON)
Appearance     branding   Branding               /admin/branding            branding (ON)
               themes     Themes                 /admin/themes              package_themes (ON)
               custom_emoji Custom emoji         /admin/custom-emoji        custom_emoji (ON)
Notifications  email      Email                  /admin/email               email (ON)
               announcements Announcements       /admin/announcements       announcements (ON)
Integrations   packages   Packages               /admin/packages            package_registry (ON)
               registries Registry trust         /admin/registries          package_registry (ON)
               webhooks   Webhooks               /admin/webhooks            webhooks (ON)
               api_tokens API tokens             /admin/api-tokens          api_tokens (ON)
               providers  Sign-in providers      /admin/providers           provider_registry (ON)
               extensions Extensions             /admin/extensions          server_extensions (OFF)
Settings       settings   General & registration /admin/settings            —
               features   Feature flags          /admin/features            —
               thread_intelligence Thread Intelligence /admin/thread-intelligence  flags_any: community_memory|automated_context (both ON)
```

Grouping matches ADR 0023 shipped item #6 (ADMIN §9.2 IA) — **do not "simplify" it**.
A flag-off item renders as `<span class="admin-nav-link is-disabled" aria-disabled="true"
data-destination="…">` with `<span class="subnav-item-note">Disabled until the feature flag is enabled</span>`
(`_nav.php:80-84`). Today only **Extensions** ever renders disabled. Mobile: `.admin-sections-toggle`
(`hidden` by default, unhidden by JS) + `.admin-nav-close` + `.admin-nav-scrim`, wired via
`data-admin-nav-toggle` / `data-admin-nav` / `data-admin-nav-close` / `data-admin-nav-scrim`.

**Eight consoles have no nav entry** and are reachable only by drill-in: `/admin/roles/simulator` (linked from
`roles.php`), `/admin/packages/security` (linked from `packages.php`) — both inbound links added by ADR 0023 #6
— plus `/admin/users/{id}`, `/admin/roles/{id}`, `/admin/packages/{id}`, `/admin/packages/publishers/{id}`,
`/admin/webhooks/{id}`, `/admin/boards/{id}/edit`, `/admin/themes/safe-mode`, `/admin/badge-rules/{id}/preview`.

### 3.2 Account nav — `templates/partials/settings_nav.php` (flat rail, 13-15 items)

```
/settings/account            Profile        —
/settings/security           Security       —
/settings/privacy            Privacy        —
/settings/appearance         Appearance     —
/settings/preferences        Reading        —
/settings/composing          Composing      —
/drafts                      Drafts         drafts (ON)          ← conditional
/settings/notifications      Notifications  —
/settings/connections        Connections    oauth (ON)           ← conditional
/settings/sessions           Sessions       —
/settings/blocks             Blocks         —
/settings/boards             Boards         —
/settings/account/lifecycle  Account        account_lifecycle (ON)  ← conditional
/appeals                     Appeals        appeals (ON)         ← conditional
[button.linkbtn.subnav-action data-tour-replay] "Replay tour"     product_tour (ON)
```

`<nav class="subnav settings-rail">`, active = `class="active"` matched on `$request_path` exact string.
Flag-off items are **omitted entirely** — the opposite of the admin nav's disabled-with-note treatment.

### 3.3 `layout.php` framing

`$variant = $this->block('variant', 'app')` — three variants:

- **`app`** (default; every admin page except `theme_safe_mode.php`, and every account page):
  `body.variant-app` → `partial('partials/topbar')` → optional `partial('partials/announcement_banner')` →
  `div.app-shell` [ `div.nav-scrim[data-nav-scrim]`, `partial('partials/sidebar')`,
  `main.main#main` [ `partial('partials/flash')`, `$content` ] ].
- **`plain`** (`theme_safe_mode.php` only): topbar still renders; `main.container#main` [ flash, content ].
  No sidebar, no app-shell.
- **`auth`** (login/register/etc.): no topbar; `main.auth-stage#main` with the star SVG, `.auth-brand`, flash,
  content, and a hard-coded `<p class="auth-colophon">Et Eärello Endorenna utúlien.</p>` (layout.php:74).

**Where things render:**
- **Page title** — `$this->section('title', …)` → `<title>` **and** `og:title` only. It is **not** rendered on
  the page; the visible `<h1>` is written independently inside each template's `admin-head` / `settings-head`.
- **Breadcrumb** — **there is none.** No breadcrumb partial exists; no admin/account template renders one, and
  no drill-in page (`user_record`, `role_edit`, `package_detail`, `webhook_detail`, `board_edit`,
  `package_publisher`) renders a back link. `grep -rn "back-link\|admin-back\|&larr;" templates/admin
  templates/account` returns nothing but the ↑/↓ reorder buttons in `structure.php`.
- **Flash** — `partials/flash.php`, one `<div class="flash" role="status">`, rendered by the **layout** above
  `$content` (inside `main.main` for app, inside `main.container` for plain). Several templates additionally
  render their own local `.flash` / `.flash-error` blocks inside the pane (`api_tokens`, `board_edit`,
  `invitations`, `structure`, `structure_confirm`, `webhooks`, `webhook_detail`).
- **Subnav** — rendered by the **leaf template**, not the layout: admin templates call
  `partial('admin/_nav', ['active' => …, 'features' => …])` between `<header class="admin-head">` and
  `<div class="admin-pane">`; account templates call `partial('partials/settings_nav')` as the first child of
  `<div class="settings">`, sibling to `<div class="settings-pane">`.
- **Assets** — `/assets/imladris.css` then `/assets/app.css` (layout.php:42-43); JS is `/assets/app.js` (defer)
  plus conditionals. Theme/density/font-size/reduced-motion are stamped on `<html>` server-side (flash-free).

---

## 4. Question A — which design screen governs which production page

`PRODUCTION_PARITY.md` is the nominated starting authority but **cannot be used for this mapping** (see §6).
The mapping below was derived by reading the 11 `.dc.html` screens directly (tab state variables + section
headings) and matching them to the route table above.

Design-screen tab sets, read from the `<nav aria-label="…">` blocks:

| screen | nav label | tabs |
|---|---|---|
| AdminOverview | "Admin sections" | Dashboard · Audit log |
| AdminContent | "Content sections" | Boards & categories · Tags |
| AdminPeople | "People sections" | Roles · Permission simulator |
| AdminMembers | "Member sections" | Directory · Invitations |
| AdminFeatures | "Capability sections" | Feature flags · Badge rules · Custom emoji |
| AdminAppearance | "Appearance sections" | Branding · Themes |
| AdminNotifications | "Notification sections" | Email · Announcements |
| AdminIntegrations | "Integration sections" | API tokens · Webhooks · Sign-in providers |
| AdminPackages | "Supply chain sections" | Packages · Registry trust · Extensions |
| AdminSettings | "Settings sections" | General & registration · Thread Intelligence |
| AccountSettings | "Settings sections" | Profile · Security · Privacy · **Regard** · Appearance · Reading · Drafts · Boards · Notifications · Connections · Blocks · Sessions · Account |

Resulting governance map (35 production templates governed):

| Design screen | Production templates | Production routes |
|---|---|---|
| **admin-overview** | `admin/dashboard.php`, `admin/audit.php` | GET `/admin`, GET `/admin/audit` |
| **admin-content** | `admin/structure.php`, `admin/structure_confirm.php`, `admin/tags.php`, `admin/tag_merge_confirm.php` | GET `/admin/structure`, GET `/admin/categories/{id}/delete`, GET `/admin/boards/{id}/{delete,archive,unarchive}`, GET/POST `/admin/tags`, GET/POST `/admin/tags/{id}/merge` |
| **admin-people** | `admin/roles.php`, `admin/role_edit.php`, `admin/role_simulator.php` | GET/POST `/admin/roles`, GET `/admin/roles/{id}`, GET `/admin/roles/simulator` |
| **admin-members** | `admin/users.php`, `admin/user_record.php`, `admin/users_bulk_confirm.php`, `admin/invitations.php` | GET `/admin/users`, GET `/admin/users/{id}`, POST `/admin/users/bulk`, GET/POST `/admin/invitations` |
| **admin-features** | `admin/features.php`, `admin/badge_rules.php`, `admin/badge_rule_preview.php`, `admin/custom_emoji.php` | GET `/admin/features`, GET/POST `/admin/badge-rules`, GET `/admin/badge-rules/{id}/preview`, GET/POST `/admin/custom-emoji` |
| **admin-appearance** | `admin/branding.php`, `admin/themes.php`, `admin/theme_safe_mode.php` | GET/POST `/admin/branding`, GET `/admin/themes`, GET/POST `/admin/themes/safe-mode` |
| **admin-notifications** | `admin/email.php`, `admin/announcements.php` | GET `/admin/email`, GET/POST `/admin/announcements` |
| **admin-integrations** | `admin/api_tokens.php`, `admin/webhooks.php`, `admin/webhook_detail.php`, `admin/providers.php`, `admin/provider_disable.php` | GET/POST `/admin/api-tokens`, GET/POST `/admin/webhooks`, GET `/admin/webhooks/{id}`, GET/POST `/admin/providers`, GET `/admin/providers/{id}/disable` |
| **admin-packages** | `admin/packages.php`, `admin/package_detail.php`, `admin/package_plan.php`, `admin/package_consent.php`, `admin/package_security.php`, `admin/package_publisher.php`, `admin/registries.php`, `admin/extensions.php`, partials `_package_integration.php` + `_package_review_form.php` | GET `/admin/packages`, GET `/admin/packages/{id}`, POST `/admin/packages/{id}/plan`, GET `/admin/packages/{id}/consent`, GET `/admin/packages/security`, GET `/admin/packages/publishers/{id}`, GET `/admin/registries`, GET `/admin/extensions` |
| **admin-settings** | `admin/settings.php`, `admin/thread_intelligence.php` | GET `/admin/settings`, GET `/admin/thread-intelligence` |
| **account-settings** | all 13 `templates/account/*.php` **except** `composing.php` (no tab) — i.e. `settings`, `security`, `privacy`, `appearance`, `preferences`, `drafts`, `boards`, `notifications`, `connections`, `blocks`, `sessions`, `lifecycle` | GET `/settings/account`, `/settings/security`, `/settings/privacy`, `/settings/appearance`, `/settings/preferences`, `/drafts`, `/settings/boards`, `/settings/notifications`, `/settings/connections`, `/settings/blocks`, `/settings/sessions`, `/settings/account/lifecycle` |

Design-side sections with **no production counterpart** (`feature-removed` candidates — do not build, do not
ship dead chrome):
- **AccountSettings "Regard" tab** — a reputation/commends ledger inside account settings. There is no
  `/settings/regard` route; production surfaces reputation on the public profile (`/u/{username}?tab=commends`,
  `templates/profile/show.php:127`) and the leaderboard. Also a **lexicon** violation ("Regard"/"Commend").
- **AdminContent "Edit" link on board rows** — the design shows the affordance but models no board-edit screen;
  production has a full one (`admin/board_edit.php`, §5).
- **AdminOverview's static breadcrumb-ish string** `Moderation · Content · People · Appearance ·
  Notifications · Integrations · Settings` — a non-interactive label standing in for the real grouped nav.

---

## 5. Question B — production admin pages with NO design representation

Exhaustive, against all **11** screens. (Against only the 7 named in the brief, add every row from
admin-members / admin-features / admin-integrations / admin-packages above — 20 further templates.)

| Production template | Route(s) | Flag (default) | Note |
|---|---|---|---|
| `admin/moderation.php` | GET `/admin/moderation`, POST `/admin/moderation` | `anti_abuse` (**ON**) | Anti-abuse posture + blocked-phrase list. Nav group "Moderation". No design screen models it; "anti-abuse" appears in AdminOverview/AdminPackages only inside `<script type="text/x-dc">` sample data, never as anatomy. |
| `admin/board_edit.php` | GET `/admin/boards/{id}/edit`; 422 from POST `/admin/boards/{id}` + 4 roster POSTs | — (core) | Board settings + board-moderator roster + board-member roster. AdminContent renders an "Edit" link and an *add-board* form with visibility / edit-window / self-assign / wiki fields, but no edit screen and no roster anatomy. |
| `templates/mod/reports.php` | GET `/mod/reports` | `moderation_queue` (**ON**) | Linked from admin nav group "Moderation". AdminOverview shows only a "Reports open" dashboard queue card. |
| `templates/mod/approvals.php` | GET `/mod/approvals` | `moderation_queue` (**ON**) | Linked from admin nav. AdminOverview shows only an "Approval hold" card. |
| `templates/mod/appeals.php` | GET `/mod/appeals` | `appeals` (**ON**) | Linked from admin nav. AdminOverview shows only an "Appeals" card. |
| `templates/mod/user.php` | GET `/mod/u/{id}` | — (ADR 0023 D1: 404 without authority) | Moderator-scoped user record. No design screen. |
| `templates/appeals/index.php` | GET `/appeals` | `appeals` (**ON**) | Member appeals surface; it is an **item in the settings rail** but has no AccountSettings tab. |
| `templates/account/composing.php` | GET/POST `/settings/composing` | — (core) | AccountSettings has no Composing tab; the enter-to-send / preview / smart-list switches live nowhere in the design. |
| `admin/email.php` "Export" | GET `/admin/email/export` | `email` (**ON**) | CSV download; no template, no design anatomy (behavior-only). |
| `/settings/preferences/export` | GET | — | Download; no template. |
| `/admin/link-previews/{id}/{refresh,purge}` | POST only | `link_previews` (**OFF**) | No page anywhere — ADR 0021 deferral #7 ("Missing admin operations"). Do **not** invent a console. |

Additional production affordances present in no design screen (not whole pages, listed so they are not lost):
- Admin nav **mobile drawer** (`admin-sections-toggle` / `admin-nav-close` / `admin-nav-scrim`).
- Admin nav **disabled-with-note** treatment (`is-disabled` + "Disabled until the feature flag is enabled").
- Settings rail **"Replay tour"** button (`product_tour`, `data-tour-replay`).
- `admin/features.php` **readiness column** vocabulary (Missing user UI / Missing admin operations /
  Safety-blocked / Operational configuration required / Reserved (ADR 0018)) — AdminFeatures mentions
  "Readiness" in prose but does not enumerate the five states.

---

## 6. `PRODUCTION_PARITY.md` staleness

`docs/design-system/imladris/PRODUCTION_PARITY.md` header: *"Production parity matrix — RetroBoards @
`4efe4e33` (main, 2026-07-14)"*; `manifest.json` agrees (`inspected_commit: 4efe4e33…`, `inspected_at:
2026-07-14`, `unresolved_gaps: []`).

1. **It points at the retired kit, not the screens under adoption.** Its admin row reads:
   `| Admin: dashboard, features, TI, structure, users, branding, tags, badges, email, announcements |
   admin/* | core/GA | **ui_kits/admin** — all sections … |`, and the platform row likewise
   `**ui_kits/admin** packages, themes, registry trust, API tokens, webhooks`. Per the brief's reading rules
   `ui_kits/*` is retired and superseded by `templates/*`. The doc therefore certifies parity against markup
   that no longer governs.
2. **It cannot mention the screens at all.** `grep -n "admin-overview\|admin-people\|admin-content\|
   admin-appearance\|admin-notifications\|admin-settings\|account-settings"` over `PRODUCTION_PARITY.md`,
   `README.md`, `ACTIVATED_FEATURES.md`, `CHANGELOG.md` and `manifest.json` returns **zero hits**. The seven
   original screens landed 2026-08-03 06:01 (`git log`: `44bfd8a docs: sync Imladris references and composer
   contract`, 2026-08-03); the four new ones landed 2026-08-03 20:20-20:21 and are still untracked.
3. **`unresolved_gaps: []` is false.** §5 lists 11 unrepresented production surfaces, and §4 lists three
   design-side inventions with no production counterpart.
4. **Its account row is imprecise about scope**: `Profiles (+gated), preferences, account lifecycle |
   profile/*, account/* (13 templates)` conflates the public profile with the 13 settings templates and does
   not record that `composing.php` has no design tab.
5. **Its `ui_kits/settings` and `ui_kits/admin` claims post-date nothing**: `git log -1 -- ui_kits/admin` →
   `e0db34e … 2026-07-16 feat: adopt Imladris design system at runtime`. The kit has not been touched since;
   the `templates/` screens are ~2.5 weeks newer.
6. **Its "Extensions · governance · service principals · verified links → *reserved* — disabled nav entry
   only — by rule"** row is still correct and binding: `server_extensions` is the one default-OFF flag in the
   admin nav (`FeatureFlags.php:100`), and `admin/extensions.php` must keep rendering as a disabled nav entry.
   AdminPackages' third tab is literally "Extensions", so adopting it verbatim would violate this rule.
7. **CHANGELOG.md's latest entry (2026-08-02, "Thread-view template reconciled to production") covers
   `templates/thread-view/` only.** No admin/account reconciliation entry exists — nothing has yet been
   audited on the admin side the way thread-view was.

**Verdict: `PRODUCTION_PARITY.md` and `manifest.json` are stale for this pass. Do not treat them as the
mapping authority; the map in §4 supersedes them and both files need a rewrite as part of this work.**

---

## 7. Headline findings

### 7.1 Fiction lexicon has ALREADY leaked into shipped production templates
This is not a future risk — it is live code. Inside my scope and adjacent to it:

| File:line | Shipped string | Proposed production string |
|---|---|---|
| `templates/admin/branding.php:20` | `Tune the public name, colour accents, assets, and preview before **the council sees the updated hall**.` | `…and preview the result before members see it.` |
| `templates/appeals/index.php:5` | `<span class="eyebrow">**Council record**</span>` | `Moderation` |
| `templates/appeals/index.php:28` | `Explain what should be reviewed. **The council record** keeps the original action and your reason together.` | `The moderation record keeps…` |
| `templates/mod/reports.php:18`, `mod/approvals.php:12`, `mod/appeals.php:12`, `mod/user.php:27` | `<span class="eyebrow">**Warden's table**</span>` | `Moderation` |
| `templates/layout.php:74` | `<p class="auth-colophon">**Et Eärello Endorenna utúlien.**</p>` | delete, or a plain tagline |
| `templates/partials/post_deleted.php:13` | `Removed by a **warden**` | `Removed by a moderator` |
| `templates/partials/post_toolbar.php:55,101` | `Remove topic (**warden**)` / `Remove (**warden**)` | `Remove topic (moderator)` / `Remove (moderator)` |
| `templates/leaderboard.php:5` | `<p class="eyebrow">**The council**</p>` | `Community` |
| `templates/auth/login.php:4` | `Welcome back to **the council**` | `Welcome back` |
| `templates/auth/verify.php:7` | `Your **seat at the council** is ready.` | `Your account is ready.` |
| `templates/dm/*`, `partials/dm_list.php`, `partials/dm_rail.php` (7 sites) | `Private **counsel**`, `Choose a thread of **counsel**`, `N in **counsel**` | `Private message`, `Choose a conversation`, `N in conversation` |
| `templates/partials/post.php:27,33`, `profile/show.php:38,100,127,169,221`, `partials/badges.php`, `partials/icon.php:45-46`, `leaderboard.php:32,36` | `**Commends**` / `commend-star` / `Most commended` / `regard-plinth` | `Reputation` / `reputation-star` / `Most upvoted` (the icon id `commend-star` is also a code identifier, not just copy) |

Every one is a **constraint** deviation (design fiction → plain-English equivalent). Because it is already
shipped, adopting the design "verbatim" would *entrench* it rather than introduce it. The remediation set is
larger than the design-adoption diff.

### 7.2 `/admin/thread-intelligence` has no feature-flag guard
`AdminThreadIntelligenceController::index` (`src/Controller/AdminThreadIntelligenceController.php:14-20`) and
all seven of its POST handlers call only `requireAdmin()`. `templates/admin/_nav.php:48` gates the nav entry on
`flags_any: ['community_memory','automated_context']`, so with both flags rolled back the link disappears but
the route still answers 200 and the POSTs still mutate. Every other flagged admin console throws
`NotFoundException` (`AdminPackagesController:22`, `AdminWebhookController:20`, `AdminRoleController:29`, …).
This violates brief constraint 6 and the CLAUDE.md rule "New subsystems ship … route-gated, with a regression
test asserting they're dark". Independent of design adoption — fix regardless.

### 7.3 `admin/theme_safe_mode.php` is structurally inconsistent with the other 37 admin pages
It sets `$this->section('variant', 'plain')` (layout.php:76-80 → `main.container`, no `app-shell`, no
sidebar) and uses `<div class="container">` as its wrapper — no `.admin`, no `.admin-pane` — yet still renders
the full `admin/_nav` drawer at line 13. Result: the grouped admin subnav renders inside a centred narrow
plain shell it was never styled for. Whatever the design says about safe mode (AdminAppearance models it as a
*card inside the Themes tab*, not a standalone page), this page's shell choice needs an explicit decision.

### 7.4 Nav dead-ends: 4 of 5 admin "Moderation" entries leave the admin shell
`/mod/reports`, `/mod/approvals`, `/mod/appeals`, and `/mod/u/{id}` render `templates/mod/*.php`, which use
`<div class="mod reports-view">` + `<header class="mod-head">` and **never include `admin/_nav`**. A user who
enters via the admin console's Moderation group loses the console navigation entirely and has no way back
except the browser. ADR 0023 #6 deliberately added these entries to the admin IA; it did not give the
destinations the console chrome.

### 7.5 Two independent, incompatible flag-off idioms
Admin nav: render a disabled `<span aria-disabled="true" data-destination="…">` with the note "Disabled until
the feature flag is enabled" (`_nav.php:80-84`). Settings rail: omit the item silently
(`settings_nav.php:12-27`). Both surfaces should not diverge; the design screens model neither (they have no
flags at all), so this is a **feature-added** production concern that the design idiom must be extended to
cover, not dropped.

### 7.6 Anti-draft-loss coverage is broad and must survive restructuring
32 distinct 422 re-render paths across the inventory (AccountController ×10, AdminController ×11,
AdminWebhookController ×3, AdminPackage* ×5, plus badge rules, custom emoji, email, invitations). Notably
`AdminController::structureView` (:497) uses `array_replace`, **not `+`**, with an explicit comment: "the 422
context must WIN over base keys". Any restructuring of `structure.php`, `board_edit.php`, `settings.php`,
`user_record.php`, or the account forms must carry `->errors` + `->old` through the new markup unchanged.

### 7.7 Two POST-rendered interstitials will not survive a naive "make it a GET page" rewrite
`admin/users_bulk_confirm.php` is rendered by **POST** `/admin/users/bulk` (`AdminUserController::bulkConfirm`
:378) and `admin/package_plan.php` by **POST** `/admin/packages/{id}/plan`
(`AdminPackageLifecycleController::plan` :40). They carry forward selection state that a GET has no way to
express (ADR 0023 shipped #4, "bulk-selection preservation"). The other five interstitials
(`structure_confirm`, `tag_merge_confirm`, `package_consent`, `provider_disable`, `badge_rule_preview`) are
GET, per the App.php:2332-2334 comment "Destructive structure actions are two-step: a GET confirmation page
(works with JS disabled…)".

### 7.8 Eyebrow coverage is 8/38 on admin, 13/13 on account
Only `dashboard` ("Operator desk"), `settings` ("Operator desk"), `branding` ("Operator desk"), `audit`
("Accountability"), `custom_emoji` ("Appearance"), `features` ("Runtime controls"), `moderation`
("Moderation"), `thread_intelligence` ("Operations") carry a `<span class="eyebrow">`; only 7 carry a
`.pane-intro`. All 11 design screens give every screen an eyebrow (`Admin console` / `Roles & capabilities` /
`Boards & tags` / `Branding & themes` / `Email & announcements` / `General & intelligence` / `Members &
invitations` / `Features & badges` / `Tokens, webhooks & sign-in` / `Packages & registries`) plus an intro
paragraph. The 30 admin pages without one are plain **copy** differences — production must add them.

### 7.9 One nav-key inconsistency
`admin/package_publisher.php:14` highlights nav key `'registries'`; `admin/package_security.php:11` (reached
from the same Packages surface) highlights `'packages'`. Both are drill-ins off `/admin/packages`. Copy
difference — pick one.
