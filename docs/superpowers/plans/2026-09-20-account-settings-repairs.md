# Account Settings Repairs Implementation Plan

> **For agentic workers:** Use `superpowers:executing-plans` to implement these tasks. Follow the combined delivery order in the [main plan](2026-09-20-unified-notifications.md). This is a planning artifact; no application changes are claimed complete.

**Goal:** Close all nine account-settings findings, sharing the private-subscription and saved-feed delivery fixes with the unified notification system.

**Architecture:** Repair the existing lifecycle, authentication, profile, organization, and settings rendering paths. Keep established routes and services where possible; use the shared notification visibility and digest contracts for overlapping behavior. Preserve every workflow as ordinary HTML forms and links.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB, existing account services and Imladris components, PHPUnit, Playwright/axe.

**Spec:** [Unified notifications and account-settings repair design](../specs/2026-09-20-unified-notifications-and-account-settings-design.md).

## Global Constraints

- PHP 8.2+, existing MySQL/MariaDB schema and prepared statements; no new application framework or runtime dependency.
- Server-rendered forms and GET navigation work without JavaScript; progressive enhancement and short polling only.
- Strict CSP; no inline scripts/styles. Escape all new member-controlled names, labels, and drafts.
- Existing flags keep their defaults; disabling a feature hides its controls and gates its routes/worker source.
- Services own policy, repositories own queries, and new services are hand-bound in `App::buildContainer()` and explicit worker construction in `bin/console`.
- Shell lookups tolerate missing tables/unreachable DB and preserve pre-setup/health behavior.
- Account state takes precedence over role. Reactivation/cancellation and delivery-reducing opt-out are explicit permitted operations, not general exemptions from restrictions. N2 permits owned subscription/channel disablement, digest Off, and global pause in every authenticated account state; enabling delivery remains gated.
- Never replay passwords, OTP codes, or recovery secrets after validation errors. Retain ordinary form drafts and selected options.
- A2 from the supplied audit (private subscription disclosure) is implemented by tasks N1/N2 in the main plan; do not create a second inconsistent fix here.

## Review Focus

1. A restriction or deletion request changes between GET and POST: lock/reread it and never restore forbidden write access. Tests: A1.
2. An enrollment is pending after a wrong code/reload, or an account has no password: present a usable next action without exposing secrets or weakening reauthentication. Tests: A2.
3. An avatar operation succeeds or fails while other profile fields are dirty: preserve all drafts, including custom fields, and save only the intended data. Tests: A3.
4. A saved filter loses its only readable board or matches several sources: stay empty when appropriate, prevent IDOR, and deduplicate digest content. Tests: A4.
5. Mobile/no-JS clients, long section/device names, and spoofed user agents: keep controls reachable, labels escaped, and session revocation intact. Tests: A5.

## Task A1: Prevent lifecycle transitions from clearing restrictions

**Finding:** A1, critical. **Dependencies:** None; execute first and review independently of UI consolidation.

**Files:** Modify `src/Service/AccountLifecycleService.php`, `src/Service/UserModerationService.php`, `src/Repository/UserRepository.php`, `src/Repository/AccountDeletionRepository.php`, `src/Controller/AccountController.php`, `templates/account/lifecycle.php`, `src/Core/App.php`. Create `src/Repository/BanRepository.php` for the narrow live site-restriction read. Test `tests/Integration/Core/AppAccountLifecycleTest.php`, `AppUserModerationTest.php`, `AppProtectedOwnerTest.php`; add lifecycle cases to new `tests/browser/account-settings-repairs.spec.ts`.

**Interfaces:**

```php
// UserRepository; called inside a service-owned transaction.
public function findForUpdate(int $id): ?array;
// AccountDeletionRepository
public function pendingForUserForUpdate(int $userId): ?array;
// BanRepository: scope='site', lifted_at IS NULL, not expired.
public function activeSiteRestrictionsForUpdate(int $userId): array;
// AccountLifecycleService: UI availability; each write repeats checks under lock.
// Keys deactivate, reactivate, request_deletion, cancel_deletion are booleans.
public function availableActions(\App\Domain\User $user): array;
```

**Transition contract:**

| Action | Permitted starting state | Result / refusal |
|---|---|---|
| Deactivate | Effectively active, no pending deletion, no live site restriction | Self-deactivated; otherwise 422 with explanation |
| Reactivate | Self-deactivated, no pending deletion, no live restriction | Active; otherwise 422; never clear a suspension timestamp implicitly |
| Request deletion | Active or self-deactivated; no live restriction | Pending deletion with the existing 30-day grace; repeat request is idempotent |
| Cancel deletion | Durable pending request exists | Cancel request; restore active only if no independent restriction; preserve current ban/suspension |
| Lift moderation | Authorized moderator/admin action | Clear only moderation restriction; an existing pending deletion stays write-blocked |

An expired timed suspension remains effectively active as today. A live `bans` record prevents a misleading cached `users.status='active'` from authorizing lifecycle recovery. Keep final-admin/protected-owner checks, password reauthentication, revocation, and audit logging.

- [ ] Add the direct suspension regression in `AppAccountLifecycleTest`:

```php
public function test_self_service_lifecycle_cannot_clear_a_suspension(): void
{
    $this->makeAdmin();
    $until = gmdate('Y-m-d H:i:s', time() + 7 * 86400);
    $user = $this->makeUser(['status' => 'suspended', 'suspended_until' => $until]);
    $this->actingAs($user);
    $this->assertStatus(422, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));
    $this->assertStatus(422, $this->post('/settings/account/reactivate'));
    $row = $this->users()->find((int) $user['id']);
    self::assertSame('suspended', $row['status']);
    self::assertSame($until, $row['suspended_until']);
    $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still blocked']));
}
```

- [ ] Add request deletion → deactivate → reactivate, suspended → request deletion → cancel, moderation during deletion → cancel, and moderation lift during deletion regressions. Verify refusal through HTTP and a subsequent profile/post write, not only the cached status. Also prove those restrictions do not block N2's owned opt-out routes. Run `vendor/bin/phpunit --filter 'AppAccountLifecycleTest|AppUserModerationTest|AppProtectedOwnerTest'` to observe the new failures.
- [ ] Add the three locking repository reads. Reread state inside the transaction, including reauthentication against the current password hash. Owner-loss operations acquire the shared protected-owner/active-admin rowsets before locking an individual target; then lock target user, pending deletion, and site restrictions in that order. Within each rowset use ascending IDs. Align the touched lifecycle/moderation guard calls with that order; do not first lock each admin's own row and then the other active admins. Operations that need no owner rowset start with the target user and never acquire an owner rowset afterward.
- [ ] Enforce the table above in services. Do not call unrestricted `setStatus($id, 'active')` from recovery paths without the live checks. Pass a deliberate suspension value to status writes rather than relying on the nullable default to erase it. Make moderation lift consult the pending request. Test the final persisted outcome when the moderator wins a concurrent race.
- [ ] Make purge/cancel consult the durable request under lock. A ban imposed during the deletion grace must not erase the request or silently strand its eventual purge; cancelled requests must never purge. Add both scenarios to the existing purge tests, preserving the 30-day policy and anonymization behavior. Pair an actual purge (which sets `email_deliveries.user_id=NULL`) with N3's drain test: that row is suppressed as `recipient_missing` and a later valid row still sends to the shared `ArrayMailer`.
- [ ] Feed the same permitted-action model to the lifecycle template, including stale-tab 422 feedback even if its original form is no longer present. Hide invalid actions; keep pending-deletion cancellation and ordinary self-reactivation reachable. Do not treat hidden buttons as the server fix.
- [ ] Add a two-connection race test on an isolated schema, outside the ordinary per-test transaction: concurrent deactivate/moderation and cancel/moderation must not lose the restriction. Capture process exit codes and clean committed fixture rows. Add a runbook diagnostic for active accounts with live site restrictions or pending requests; require targeted reconciliation of any found rows before release, never blanket reactivation.
- [ ] Rerun the focused tests and no-JS browser lifecycle sequences. Record the critical defect closed only after the original bypass sequence still returns 403 on a normal write.

## Task A2: Keep TOTP enrollment recoverable and render passwordless states

**Findings:** A3 and A7. **Dependencies:** A1 for lifecycle availability.

**Files:** Modify `src/Controller/AccountController.php`, `src/Controller/OAuthController.php`, `src/Service/MfaService.php`, `src/Service/AccountLifecycleService.php`, `src/Service/AccountService.php` where first-password concurrency requires it, `src/Core/App.php`, `templates/account/security.php`, `templates/account/lifecycle.php`, `templates/account/connections.php`. Create `templates/partials/set_password_form.php` to share existing first-password form markup. Test `tests/Integration/Core/AppMfaTest.php`, `AppAccountConsoleTest.php`, existing OAuth tests, `AppSessionManagementTest.php`, `tests/browser/totp.spec.ts`, and `account-settings-repairs.spec.ts`.

**Interfaces:** Keep `MfaService::status(int): array{enabled:bool,pending:bool,unused_recovery_codes:int}` and `confirmEnrollment(User,string,string): array`. Add `AccountController::setInitialPassword(Request,array): Response`, POST `/settings/security/set-password`, using existing `AccountService::setInitialPassword(User,array): void`. Retain the Connections POST alias and its return context. Security view data includes `has_password:bool` and distinct password/set-password error contexts.

- [ ] Add the enrollment retry test using the existing `extractAuthenticatorSecret()` helper in `AppMfaTest`:

```php
public function test_pending_enrollment_survives_an_invalid_code_and_reload(): void
{
    $this->makeAdmin();
    $user = $this->makeUser();
    $this->actingAs($user);
    $start = $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
    $secret = $this->extractAuthenticatorSecret($start);
    $bad = $this->post('/settings/security/totp/confirm', ['current_password' => 'password123', 'totp_code' => 'invalid']);
    $this->assertStatus(422, $bad);
    self::assertStringContainsString('action="/settings/security/totp/confirm"', $bad->body());
    $reload = $this->get('/settings/security');
    self::assertStringContainsString('action="/settings/security/totp/confirm"', $reload->body());
    self::assertStringNotContainsString($secret, $reload->body());
    $good = $this->post('/settings/security/totp/confirm', [
        'current_password' => 'password123', 'totp_code' => (new \App\Security\Totp())->code($secret),
    ]);
    $this->assertStatus(200, $good);
    self::assertNotEmpty($this->extractRecoveryCodes($good));
}
```

- [ ] Add a real passwordless fixture by setting `password_hash=NULL` after `makeUser()` and refetching it; the helper's `password` default does not create such a user. Check Security, lifecycle, and TOTP entry states, first-password mismatch/success, existing-password refusal, and other-session invalidation. Run `vendor/bin/phpunit --filter 'AppMfaTest|AppAccountConsoleTest|AppOAuth|AppSessionManagementTest'`.
- [ ] Split the setup-secret display condition from the confirmation-form condition: show the QR/secret only when fresh setup data was returned, but show confirmation whenever `totp.pending` is true. An explicit Restart setup POST still requires the current password and rate limit. Reject confirmation of disabled/invalid enrollment; retain the same pending encrypted secret on invalid code/password.
- [ ] Render Set a password for passwordless users in Security, with an anchor used by lifecycle/TOTP guidance. Reuse the current password policy and revocation behavior; add the existing `mfa_settings` rate limit to the new route. Keep first-password setting available when OAuth is dark, since a local credential is an existing account capability. Connections remains feature-gated.
- [ ] Have both routes call the same account service and field partial. Make first-password creation race-safe so a stale passwordless session cannot replace a password already set by another request. Return 422 with blank secret fields and field-linked errors. Keep the normal Change password and lifecycle confirmation requirements for password-bearing accounts.
- [ ] Use `ReauthGate::requirePassword(..., missingPasswordError: ...)` in password-required service paths to give a useful missing-password explanation on direct/stale POSTs. Do not bypass `WriteGate` to set credentials for a restricted account; lifecycle cancellation/reactivation remain available according to A1 without adding an impossible password step.
- [ ] Verify invalid/retry/reload/success and explicit restart without JavaScript; recovery codes remain one-time-visible, consumed OTP/recovery codes cannot replay, and enrollment success still revokes other sessions. Run the focused PHP/browser checks with a passwordless fixture that fails setup if missing rather than silently skipping the case.

## Task A3: Preserve profile drafts during avatar operations

**Finding:** A5. **Dependencies:** None.

**Files:** Modify `templates/account/settings.php`, `src/Controller/AccountController.php`, `src/Service/ProfileMediaService.php` only if its error shape needs normalization, and profile-specific CSS. Test `tests/Integration/Core/AppProfileMediaTest.php`, `AppUserSettingsTest.php`, `AppProfileFidelityTest.php`, `tests/browser/profile-surface.spec.ts`, and `account-settings-repairs.spec.ts`.

**Interface:** Extract `AccountController::accountView(User $user, array $data = [], int $status = 200): Response` to build the common profile data. Preserve trusted stored `avatar_path` while merging only allowed profile draft fields. Existing POST `/settings/avatar` and `/settings/avatar/remove` receive the same ordinary draft fields as `/settings/account`.

- [ ] Add a regression using the current multipart test helper:

```php
public function test_invalid_avatar_preserves_the_unsaved_profile_draft(): void
{
    $this->makeAdmin();
    $user = $this->makeUser(['display_name' => 'Stored name']);
    $this->actingAs($user);
    $file = $this->fakeUpload('not an image', 'bad.txt', 'text/plain');
    $response = $this->postFile('/settings/avatar', 'avatar', $file, [
        'display_name' => 'Unsaved name', 'bio' => 'Unsaved biography', 'location' => 'Unsaved place',
    ]);
    $this->assertStatus(422, $response);
    self::assertStringContainsString('Unsaved name', $response->body());
    self::assertStringContainsString('Unsaved biography', $response->body());
    self::assertStringContainsString('Unsaved place', $response->body());
    self::assertSame('Stored name', $this->users()->find((int) $user['id'])['display_name']);
}
```

- [ ] Add upload success and remove cases carrying bio, pronouns, location, website, signature, and all custom-field pairs. Run `vendor/bin/phpunit --filter 'AppProfileMediaTest|AppUserSettingsTest|AppProfileFidelityTest'` and observe current draft-loss failures.
- [ ] Use a single multipart form for profile fields and avatar controls; use submit-button `formaction` for the existing avatar endpoints. Keep the file input optional for Save profile and Remove; the upload service validates file presence/type/size. Avatar buttons use `formnovalidate` so an unrelated incomplete profile field does not block the avatar-only action; the normal profile save still validates all profile fields.

```html
<form method="post" action="/settings/account" enctype="multipart/form-data">
  <!-- Existing escaped profile inputs and CSRF field live in this same form. -->
  <input type="file" name="avatar" accept="image/png,image/jpeg,image/gif,image/webp">
  <button type="submit" formaction="/settings/avatar" formnovalidate>Upload avatar</button>
  <button type="submit" formaction="/settings/avatar/remove" formnovalidate>Remove avatar</button>
  <button type="submit">Save profile</button>
</form>
```

- [ ] On avatar validation failure, render `accountView` at 422 with the posted draft and an avatar-linked error. On upload/remove success, render at 200 with the draft, trusted updated avatar, and “Avatar updated. Other profile edits are not saved.” (or removal equivalent). Do not call `updateProfile` as a side effect. Normal Save profile retains its current redirect on success.
- [ ] Keep avatar paths server-owned; ignore posted `avatar_path`, user IDs, and other hidden privileged fields. Escape drafts without truncating ordinary validation failures. Explain that a rejected file must be selected again; use error styling/semantics rather than a green success flash.
- [ ] Update existing tests that expected an avatar redirect to assert the deliberately changed render contract, not just a generic 200. Check missing/oversized/invalid files, feature off, unauthorized writes, and cleanup of temporary upload records/files. Browser tests must prove edits survive upload/remove with JavaScript disabled and that Save profile eventually persists them.

## Task A4: Complete saved-feed/folder workflows and preserve organization forms

**Findings:** A4 and A6. **Dependencies:** N1 eligibility and N3's digest job contract. This completes the exposed feature; hiding the Digest label alone is not acceptance.

**Files:** Create `src/Repository/SavedFeedRepository.php`, `src/Repository/BoardFolderRepository.php`, `src/Service/SavedFeedService.php`, `src/Controller/SavedFeedController.php`. Modify `src/Service/PersonalOrganizationService.php`, `src/Controller/PersonalOrganizationController.php`, `src/Controller/SettingsController.php`, `src/Service/FeedService.php`, `src/Service/NavigationService.php`, `src/Service/DigestService.php`, `src/Repository/DigestActivityRepository.php`, `src/Core/App.php`, `templates/account/boards.php`, `templates/feed.php`, `templates/partials/sidebar.php`, and required shared styles. Test `tests/Integration/Core/AppBoardFoldersSavedFeedsTest.php`, new `AppSavedFeedReadTest.php`, digest worker tests, and `account-settings-repairs.spec.ts`.

**Interfaces and routes:**

```php
// SavedFeedService; ownership/flags checked before loading labels/results.
public function open(\App\Domain\User $viewer, int $feedId, int $page = 1): array;
// Result: id:int, name:string, items:list<array>, page:int, has_more:bool,
// unavailable:bool, digest_enabled:bool. Item shape matches FeedService::latest.
// FeedService extension; preserve original IDs, including IDs now inaccessible.
public function forSavedFeed(\App\Domain\User $viewer, array $filter, int $page = 1, int $perPage = 20): array;
// SavedFeedRepository
public function findOwned(int $userId, int $id): ?array;
public function enabledForDigest(int $userId): array;
// PersonalOrganizationService extensions
public function updateSavedFeed(\App\Domain\User $user, int $id, array $input): void;
public function deleteSavedFeed(\App\Domain\User $user, int $id): void;
public function renameFolder(\App\Domain\User $user, int $id, string $name): void;
public function deleteFolder(\App\Domain\User $user, int $id): void;
public function removeBoardFromFolder(\App\Domain\User $user, int $folderId, int $boardId): void;
// SettingsController's reusable renderer for validation errors.
public function boardsView(\App\Domain\User $user, array $data = [], int $status = 200): \App\Core\Response;
```

New GET `/feeds/saved/{id}` opens an owned saved feed. New POST routes: `/settings/saved-feeds/{id}` (rename/filter/digest update), `/settings/saved-feeds/{id}/delete`, `/settings/board-folders/{id}/rename`, `/settings/board-folders/{id}/delete`, `/settings/board-folders/{id}/boards/{board_id}/remove`. The router only special-cases `{id}` as numeric: parse/validate `{board_id}` explicitly. Keep all current create/add routes and feature gates.

- [ ] Add the preserved-selection regression to `AppBoardFoldersSavedFeedsTest`:

```php
public function test_invalid_saved_feed_keeps_selected_board_and_digest(): void
{
    $this->makeAdmin();
    $user = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory());
    $this->actingAs($user);
    $response = $this->post('/settings/saved-feeds', [
        'name' => '   ', 'board_id' => (string) $board['id'], 'digest_enabled' => '1',
    ]);
    $this->assertStatus(422, $response);
    $dom = new \DOMDocument();
    @$dom->loadHTML($response->body());
    $xpath = new \DOMXPath($dom);
    self::assertSame(1, $xpath->query('//form[@action="/settings/saved-feeds"]//option[@value="' . $board['id'] . '" and @selected]')->length);
    self::assertSame(1, $xpath->query('//form[@action="/settings/saved-feeds"]//input[@name="digest_enabled" and @checked]')->length);
}
```

- [ ] Add create → open → rail → rename → disable digest → delete and folder create → add → rail → remove → rename/delete flows. Include foreign IDs and a feed whose sole board becomes private. Run `vendor/bin/phpunit --filter 'AppBoardFoldersSavedFeedsTest|AppSavedFeedReadTest|DailyDigestWorkerTest'` and record the current route/render/delivery failures.
- [ ] Move touched organization storage queries into the two narrow repositories and retain service-owned validation/transactions. Creation, rename, filter changes, and digest enablement use `WriteGate`. A digest-only change from enabled to disabled is an owner-scoped opt-out and remains available in restricted states; reject mixed requests that also change a name/filter. Include an explicit digest-disable button that submits only that reduction. Creation and update validation returns `ValidationException($errors, $input)`; controllers call `boardsView` at 422 with a form-specific error/old-values bag. Retain board, digest, folder, and thread selections for the originating form; avoid duplicate error IDs. Catch invalid add-to-folder submissions as well as create failures.
- [ ] Implement `SavedFeedService::open` using existing feed item rendering and topic links, current board read/listing semantics, blocks, and deleted/pending/anonymous exclusions. An empty original `board_ids` means the existing Latest feed's eligible discovery set; a nonempty original array stays constrained even when every board becomes unreadable. Validate stored JSON shape; corrupt filters return a neutral unavailable state, never an unrestricted feed or 500.
- [ ] Add folder/saved-feed navigation through a lazy, guarded shell lookup. Folder board links and saved-feed names are owner-scoped; unread counters do not count duplicate shortcuts twice. Render custom folders and saved feeds as additional groups before normal categories; retain existing category links. In composer destination-picker mode keep board destinations only and preserve locked-board explanations. Use the existing rail drawer and presence footer.
- [ ] Finish owned rename/delete/remove controls with normal POST forms. Removing a stale owned folder association does not require access to its board. Deleting a folder removes its associations, never the board. Saved-feed deletion does not delete topics, subscriptions, or other feeds. Reject foreign IDs with 404 and translate duplicate-name uniqueness conflicts into field-specific 422 errors rather than silently overwriting another saved record.
- [ ] Extend N3's `snapshot()` to include enabled saved-feed IDs and their original validated filters. At delivery intersect the original source scope with the still-enabled current source and N1 eligibility. Combine it with daily subscriptions; effective thread-over-board Off/email-disabled excludes the target, Instant remains instant-only, and Daily participates. A feed supplies daily activity when no explicit subscription controls the target. Deduplicate post IDs and aggregate each topic once. Global digest Off/pause/suppression wins. Explain “Daily digest is off” with a settings link instead of reporting active delivery for a disabled global schedule.
- [ ] Test overlapping feeds/subscriptions, saved feed only, Off overrides, disable/delete/change filter between queue and retry, malformed filters, multiple selected IDs in stored legacy JSON, and revoked access. Verify there is one recipient/day outbox job and no duplicate thread in its captured email. Check original all-boards versus revoked-selected-board behavior in both page and digest tests. Include suspended/deactivated/deletion-pending/banned fixtures that can disable saved-feed digest delivery but cannot enable or reconfigure it. If no source remains eligible, suppression is terminal; if another source remains eligible, its activity may still deliver without the disabled source's content.
- [ ] Capture no-JS create/error/open/manage/digest settings flows and mobile rail placement. Update ADR 0032 and the phase closeout ledger to describe this completed behavior accurately. Rerun focused PHP/browser tests and profile the feed query with representative data; no per-item membership queries.

## Task A5: Make mobile settings and session labels usable

**Findings:** A8 and A9. **Dependencies:** Previous account forms; N5/shared chrome changes must be included in the final combined check.

**Files:** Modify `templates/partials/settings_nav.php`, `templates/account/sessions.php`, `src/Controller/SettingsController.php`, `public/assets/app.css`, relevant Imladris source/mirror. Create `templates/partials/settings_nav_links.php`, `src/Support/UserAgentLabel.php`, `tests/Unit/Support/UserAgentLabelTest.php`. Test `AppAccountConsoleTest.php`, `AppSessionManagementTest.php`, existing `tests/browser/account-console.spec.ts`, and `account-settings-repairs.spec.ts`.

**Interfaces:** `UserAgentLabel::for(?string $userAgent): string` returns a concise browser/OS description or “Unknown device.” The settings navigation uses one existing groups/flags/active model, rendered into mutually exclusive desktop and mobile containers without shared element IDs.

- [ ] Add an initial-viewport assertion to the authenticated mobile browser fixture and unit cases for representative user agents:

```typescript
await page.goto('/settings/security');
const summary = page.locator('[data-settings-mobile-nav] > summary');
await expect(summary).toHaveText('Settings: Security');
await expect(page.locator('[data-settings-mobile-nav]')).not.toHaveAttribute('open', '');
const control = page.locator('.settings-pane input:not([type="hidden"]):visible').first();
const box = await control.boundingBox();
expect(box).not.toBeNull();
expect(box!.y + box!.height).toBeLessThan(844);
```

```php
public function test_unknown_and_spoofed_user_agents_have_safe_fallback_labels(): void
{
    self::assertSame('Unknown device', \App\Support\UserAgentLabel::for(null));
    self::assertSame('Unknown device', \App\Support\UserAgentLabel::for('<script>alert(1)</script>'));
    self::assertSame('Chrome on Windows', \App\Support\UserAgentLabel::for(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/149.0.0.0 Safari/537.36'
    ));
}
```

- [ ] Run the new unit test and the focused mobile spec to observe current failures. Make viewport assertions use the dedicated 390×844 case rather than running that exact numeric threshold against every project size.
- [ ] Render desktop grouped navigation and a mobile native closed `<details data-settings-mobile-nav>` with the active section in its summary. Both call the same links partial with the same flag filtering. Use existing responsive breakpoints, real links, `aria-current`, and no JavaScript requirement. Keep the nav heading/summary short enough that the first actionable control remains visible.
- [ ] Scope browser assertions to the visible nav rather than counting hidden duplicate links. Verify keyboard open/close, tab order, route changes, 200% zoom, long labels, flag-off entries, guest routing, and no-JS navigation. Keep Replay tour behavior and all 13 account destinations accounted for; Appeals remains an intentional exit from the settings shell.
- [ ] Implement bounded deterministic browser/OS recognition without a new dependency. Match specific overlapping browsers before generic Chrome/Safari (Edge/Opera/Firefox iOS included); recognize Windows/macOS/iOS/Android/Linux and use readable unknown fallbacks. Do not trust the UA as an authorization signal or display an invented device model.
- [ ] Render the label as the primary session text, retain current-device marker and timestamps/revoke forms, and escape the raw stored string in secondary `<details>`. Test blank, malformed, very long, and HTML-looking agents. Existing session IDs/ownership rules and revoke-current/other behavior remain unchanged.
- [ ] Run `vendor/bin/phpunit --filter 'UserAgentLabelTest|AppAccountConsoleTest|AppSessionManagementTest'` and focused browser tests at desktop/390/320 widths in light/dark and no-JS modes. Open screenshots of initial Security/Profile/Notifications viewports and Sessions; record the results for N6.

## Workstream completion and handoff

- [ ] A1 has server, race, and no-JS evidence; A2 retains TOTP verification and supports a real passwordless persona; A3 preserves every draft field; A4 supplies working routes/rail/digest/manage behavior and 422 retention; A5 passes mobile/task-access and session-label checks.
- [ ] N1/N2 close the overlapping private-subscription finding on settings as well as bell/list/email paths.
- [ ] Join the main plan's N6 full PHPUnit, browser, Imladris, documentation, and cleanup gate. Every affected browser capture uses its supplied `RB_EVIDENCE_DIR`; `profile-surface`, `chamfer-removal`, and the actual `board-index-remediation` pane coverage are included alongside account tests. Worker rehearsals always pin `MAIL_DRIVER=array`. A previously passing baseline suite or a skipped passwordless fixture does not count as proof of these repairs.
- [ ] Record external OAuth ceremony and real email delivery as untested unless they are explicitly exercised. No commits, deployment, or unrelated feature work are part of creating this plan.
