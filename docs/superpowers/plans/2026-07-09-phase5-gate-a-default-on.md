# Phase 5 Gate A Default-On Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make accepted Phase 5 Gate A and B2 support features default-on for fresh installs while preserving per-flag rollback through the `features` override.

**Architecture:** `src/Core/FeatureFlags.php` remains the runtime source of truth. Tests first convert old "default dark" route checks into explicit rollback checks, then pin the new fresh-install default posture and flip only the accepted Gate A/B2 flags. Documentation records a new default-on authorization boundary while Gate B and unfinished Phase 3/4 carryovers stay dark.

**Tech Stack:** PHP 8.2+, PHPUnit integration/unit suites, JSON requirement ledger, Markdown ADR/evidence docs.

## Global Constraints

- Flip exactly these flags to default-on: `package_registry`, `package_themes`, `capabilities`, `passkeys`, `provider_registry`, `invitations`, `service_secrets`, `api_tokens`, `webhooks`, and `first_party_hooks`.
- Keep exactly these flags default-off: `custom_css`, `group_dms`, `community_memory`, `link_previews`, `expanded_files`, `automated_context`, `server_extensions`, `governance`, `service_principals`, and `verified_links`.
- Preserve rollback through the `features` settings override for every flipped flag.
- Preserve the Gate B boundary: `server_extensions`, `governance`, `service_principals`, and `verified_links` stay reserved and default-off.
- Preserve the package execution brake: `PACKAGE_EXECUTION_DISABLED` / `package_execution_disabled` remains independent of `package_registry`.
- Do not include unrelated pre-existing working-tree changes in any commit.

---

## File Structure

- Modify `tests/Integration/Core/AppFeatureFlagTest.php`: convert dark route tests to explicit rollback tests and add the new Phase 5 default-posture assertion.
- Modify `tests/Integration/Admin/AppAdminFeaturesTest.php`: update the admin inventory count canary.
- Modify `tests/Integration/Security/RegistrationPolicyTest.php`, `tests/Integration/Core/AppOAuthTest.php`, `tests/Integration/Core/AppAdminModerationTest.php`, and `tests/Integration/Core/AppInvitationsTest.php`: make invite-mode fail-closed coverage explicitly disable `invitations`.
- Modify `tests/Integration/Core/AppPackageIntegrationTest.php` and `tests/Integration/Core/AppAdminBoardRosterTest.php`: update stale comments that mention old shipped defaults.
- Modify `src/Core/FeatureFlags.php`: change the selected Phase 5 defaults and comments.
- Create `docs/adr/0018-phase-5-gate-a-default-on.md`: record the fresh-install default-on authorization.
- Modify `docs/evidence/deploy-dark-features.md`: update source-audit counts and Phase 5 rows.
- Modify `PHASE_5_STATUS.md`, `README.md`, `CHANGELOG.md`, `SCHEMA.md`, and `docs/phase5/requirement-ledger.json`: reconcile default posture language.

---

### Task 1: Convert Dark-Route Tests to Explicit Rollback Tests

**Files:**
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php`
- Modify: `tests/Integration/Security/RegistrationPolicyTest.php`
- Modify: `tests/Integration/Core/AppOAuthTest.php`
- Modify: `tests/Integration/Core/AppAdminModerationTest.php`
- Modify: `tests/Integration/Core/AppInvitationsTest.php`
- Modify: `tests/Integration/Core/AppPackageIntegrationTest.php`
- Modify: `tests/Integration/Core/AppAdminBoardRosterTest.php`

**Interfaces:**
- Consumes: `SettingRepository::set('features', array<string,bool>)`.
- Produces: Rollback tests that still prove routes fail closed when an operator disables a now-default-on flag.

- [ ] **Step 1: Update `AppFeatureFlagTest` route gates to set explicit false overrides**

At the start of each listed test, before any route assertion that expects `404`, add the shown line:

```php
// test_provider_registry_flag_gates_generic_oidc_routes()
$this->setFlags(['provider_registry' => false]);

// test_invitations_flag_gates_invitation_routes_and_redemption()
$this->setFlags(['invitations' => false]);

// test_capabilities_flag_gates_role_routes()
$this->setFlags(['capabilities' => false]);

// test_passkeys_flag_gates_ceremony_routes()
$this->setFlags(['passkeys' => false]);

// test_package_registry_flag_gates_catalog_and_registry_routes()
$this->setFlags(['package_registry' => false]);

// test_package_registry_gates_publisher_console_routes()
$this->setFlags(['package_registry' => false]);
```

For `test_provider_registry_flag_gates_generic_oidc_routes()`, replace the leading comment with:

```php
// An ENABLED registry row must stay invisible when an operator rolls back the
// P5-12 flag: no /auth routes, no sign-in button. Full-flow coverage lives
// in AppOidcProviderTest; this is the canonical rollback pin.
```

For `test_invitations_flag_gates_invitation_routes_and_redemption()`, replace the leading comment with:

```php
// Canonical rollback pin for P5-13: routes 404 and a planted VALID invitation
// stays inert while features.invitations=false.
```

- [ ] **Step 2: Update invitation fail-closed tests outside `AppFeatureFlagTest`**

In `tests/Integration/Security/RegistrationPolicyTest.php`, change the start of `test_invite_is_effective_only_while_the_invitations_flag_is_on()` to:

```php
(new SettingRepository($this->db))->set('registration_mode', 'invite');
(new SettingRepository($this->db))->set('features', ['invitations' => false]);

// Explicit rollback: fail closed, but the configured value survives so the
// console keeps showing what the operator chose.
self::assertSame('invite', $this->policy()->configuredMode());
self::assertSame('closed', $this->policy()->effectiveMode());
```

In `tests/Integration/Core/AppOAuthTest.php`, change the start of `test_invite_mode_with_dark_flag_degrades_to_closed_for_oauth()` to:

```php
// Explicit invitation rollback must fail closed for OAuth provisioning.
$this->settings()->set('registration_mode', 'invite');
$this->settings()->set('features', ['invitations' => false]);
```

In `tests/Integration/Core/AppAdminModerationTest.php`, change the start of `test_registration_mode_invite_persists_and_dashboard_warns_while_dark()` to:

```php
$this->settings()->set('features', ['invitations' => false]);
$this->actingAs($this->admin);
$this->get('/admin'); // seed CSRF
```

In `tests/Integration/Core/AppInvitationsTest.php`, change `test_token_bearing_renders_stay_noindex_while_dark()` to start with:

```php
(new SettingRepository($this->db))->set('features', ['invitations' => false]);
$this->setMode('open');
```

In `tests/Integration/Core/AppInvitationsTest.php`, change `test_invite_mode_with_a_dark_flag_fails_closed()` to start with:

```php
// Explicit invitation rollback must not reopen registration.
(new SettingRepository($this->db))->set('features', ['invitations' => false]);
$this->setMode('invite');
```

- [ ] **Step 3: Update stale test comments**

In `tests/Integration/Core/AppPackageIntegrationTest.php`, change:

```php
// package_registry stays default-off.
```

to:

```php
// package_registry rollback keeps integration export dark.
```

In `tests/Integration/Core/AppAdminBoardRosterTest.php`, change:

```php
* `capabilities` flag OFF (the shipped default), so the test runs unflagged;
```

to:

```php
* the capabilities runtime posture, so the test runs without forcing a mode;
```

- [ ] **Step 4: Run rollback tests while defaults are still old**

Run:

```bash
vendor/bin/phpunit --filter 'test_provider_registry_flag_gates_generic_oidc_routes|test_invitations_flag_gates_invitation_routes_and_redemption|test_capabilities_flag_gates_role_routes|test_passkeys_flag_gates_ceremony_routes|test_package_registry_flag_gates_catalog_and_registry_routes|test_package_registry_gates_publisher_console_routes|test_invite_is_effective_only_while_the_invitations_flag_is_on|test_invite_mode_with_dark_flag_degrades_to_closed_for_oauth|test_registration_mode_invite_persists_and_dashboard_warns_while_dark|test_token_bearing_renders_stay_noindex_while_dark|test_invite_mode_with_a_dark_flag_fails_closed|test_export_settings_is_dark_without_flag' tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Security/RegistrationPolicyTest.php tests/Integration/Core/AppOAuthTest.php tests/Integration/Core/AppAdminModerationTest.php tests/Integration/Core/AppInvitationsTest.php tests/Integration/Core/AppPackageIntegrationTest.php
```

Expected: PASS. These tests use explicit false overrides, so they are independent of the production default.

- [ ] **Step 5: Commit the rollback-test conversion**

```bash
git add tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Security/RegistrationPolicyTest.php tests/Integration/Core/AppOAuthTest.php tests/Integration/Core/AppAdminModerationTest.php tests/Integration/Core/AppInvitationsTest.php tests/Integration/Core/AppPackageIntegrationTest.php tests/Integration/Core/AppAdminBoardRosterTest.php
git commit -m "test(phase5): make rollback gates explicit"
```

---

### Task 2: Pin and Implement the New Default Posture

**Files:**
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php`
- Modify: `tests/Integration/Admin/AppAdminFeaturesTest.php`
- Modify: `src/Core/FeatureFlags.php`

**Interfaces:**
- Consumes: `App\Core\FeatureFlags::all()`, `FeatureFlags::enabled(string): bool`, `SettingRepository::set('features', array)`.
- Produces: `FeatureFlags::defaults()` returns 47 `true` values and 10 `false` values; selected Phase 5 flags are enabled on a fresh install.

- [ ] **Step 1: Write the failing default-posture tests**

In `tests/Integration/Core/AppFeatureFlagTest.php`, replace `test_phase5_foundation_flags_default_dark()` with:

```php
public function test_phase5_gate_a_defaults_on_and_gate_b_stays_dark(): void
{
    $flags = new FeatureFlags(new SettingRepository($this->db));
    $phase5DefaultOn = [
        'package_registry',
        'package_themes',
        'capabilities',
        'passkeys',
        'provider_registry',
        'invitations',
        'service_secrets',
        'api_tokens',
        'webhooks',
        'first_party_hooks',
    ];
    $phase5DefaultDark = [
        'server_extensions',
        'governance',
        'service_principals',
        'verified_links',
    ];

    foreach ($phase5DefaultOn as $flag) {
        self::assertArrayHasKey($flag, $flags->all(), "$flag should be declared in FeatureFlags::DEFAULTS");
        self::assertTrue($flags->enabled($flag), "$flag should be default-on after Phase 5 Gate A acceptance");
    }
    foreach ($phase5DefaultDark as $flag) {
        self::assertArrayHasKey($flag, $flags->all(), "$flag should be declared in FeatureFlags::DEFAULTS");
        self::assertFalse($flags->enabled($flag), "$flag should stay default-dark for Gate B");
    }

    $defaults = $flags->all();
    self::assertSame(47, count(array_filter($defaults)), 'fresh installs should now have 47 default-on flags');
    self::assertSame(10, count($defaults) - count(array_filter($defaults)), 'fresh installs should keep 10 default-dark flags');

    $this->setFlags(['capabilities' => false, 'passkeys' => false]);
    $overridden = new FeatureFlags(new SettingRepository($this->db));
    self::assertFalse($overridden->enabled('capabilities'), 'operator rollback should disable capabilities');
    self::assertFalse($overridden->enabled('passkeys'), 'operator rollback should disable passkeys');
    self::assertTrue($overridden->enabled('provider_registry'), 'rolling back one Phase 5 flag must not disable its neighbours');
    self::assertFalse($overridden->enabled('server_extensions'), 'Gate B stays dark without an override');
}
```

In `tests/Integration/Admin/AppAdminFeaturesTest.php`, change the inventory count assertions to:

```php
self::assertStringContainsString('57 declared', $page->body());
self::assertStringContainsString('47 default-on', $page->body());
self::assertStringContainsString('10 default-dark', $page->body());
```

- [ ] **Step 2: Run tests to verify they fail before the production flip**

Run:

```bash
vendor/bin/phpunit --filter 'test_phase5_gate_a_defaults_on_and_gate_b_stays_dark|test_admin_can_review_declared_feature_flag_inventory' tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Admin/AppAdminFeaturesTest.php
```

Expected: FAIL. `package_registry should be default-on after Phase 5 Gate A acceptance` fails, and the admin inventory still renders `37 default-on` / `20 default-dark`.

- [ ] **Step 3: Flip the selected defaults**

In `src/Core/FeatureFlags.php`, replace the Phase 5 Gate A and B2 defaults block with:

```php
// -- Phase 5 Gate A (accepted; default-on, independently reversible) -----
// Gate A closed on 2026-07-09. Fresh installs expose these surfaces by
// default; operators can still roll each one back through the `features`
// setting. Package execution also has a flag-independent emergency brake
// (`PACKAGE_EXECUTION_DISABLED` / `package_execution_disabled`).
'package_registry' => true,   // signed registry, package catalogue/install/update (P5-01/02/04)
'package_themes' => true,     // declarative theme packages + preview/safe-mode (P5-03)
'capabilities' => true,       // DB-backed roles/capability resolver, scoped grants (P5-08/09)
'passkeys' => true,           // WebAuthn registration/sign-in/step-up (P5-11)
'provider_registry' => true,  // generic OIDC + provider registry expansion (P5-12)
'invitations' => true,        // invitation lifecycle / invite-based registration (P5-13)

// -- Phase 5 Gate A - B2 trusted-extension support (default-on) ----------
// Encrypted service-secret registry (SecretVault). Operators can roll writes
// back via `features.service_secrets=false`; reveal/revoke/prune stay available.
'service_secrets' => true,    // reversible secret vault for providers/webhooks (B2 sub-project 1)
'api_tokens' => true,         // admin/service Bearer API tokens + read-only /api/v1 (B2 sub-project 2)
'webhooks' => true,           // outbound webhook delivery engine + admin UI (B2 sub-project 3)
'first_party_hooks' => true,  // code-only first-party hooks + domain webhook producers (B2 sub-project 4)
```

Leave the Gate B block default-off.

- [ ] **Step 4: Run default-posture and rollback tests**

Run:

```bash
vendor/bin/phpunit --filter 'test_phase5_gate_a_defaults_on_and_gate_b_stays_dark|test_admin_can_review_declared_feature_flag_inventory' tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Admin/AppAdminFeaturesTest.php
```

Expected: PASS.

Run:

```bash
vendor/bin/phpunit --filter 'test_provider_registry_flag_gates_generic_oidc_routes|test_invitations_flag_gates_invitation_routes_and_redemption|test_capabilities_flag_gates_role_routes|test_passkeys_flag_gates_ceremony_routes|test_package_registry_flag_gates_catalog_and_registry_routes|test_package_registry_gates_publisher_console_routes|test_invite_is_effective_only_while_the_invitations_flag_is_on|test_invite_mode_with_dark_flag_degrades_to_closed_for_oauth|test_registration_mode_invite_persists_and_dashboard_warns_while_dark|test_token_bearing_renders_stay_noindex_while_dark|test_invite_mode_with_a_dark_flag_fails_closed|test_export_settings_is_dark_without_flag' tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Security/RegistrationPolicyTest.php tests/Integration/Core/AppOAuthTest.php tests/Integration/Core/AppAdminModerationTest.php tests/Integration/Core/AppInvitationsTest.php tests/Integration/Core/AppPackageIntegrationTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit the default flip**

```bash
git add src/Core/FeatureFlags.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Admin/AppAdminFeaturesTest.php
git commit -m "feat(phase5): default Gate A features on"
```

---

### Task 3: Reconcile Default-On Authorization and Deployment Docs

**Files:**
- Create: `docs/adr/0018-phase-5-gate-a-default-on.md`
- Modify: `docs/evidence/deploy-dark-features.md`
- Modify: `PHASE_5_STATUS.md`
- Modify: `README.md`
- Modify: `CHANGELOG.md`
- Modify: `SCHEMA.md`
- Modify: `docs/phase5/requirement-ledger.json`

**Interfaces:**
- Consumes: Existing Gate A evidence index at `docs/evidence/phase5/gate-a-closeout.md`.
- Produces: Docs that state selected Gate A/B2 flags are default-on, Gate B remains reserved, and current default-dark count is 10.

- [ ] **Step 1: Add ADR 0018**

Create `docs/adr/0018-phase-5-gate-a-default-on.md` with:

```markdown
# ADR 0018: Phase 5 Gate A Fresh-Install Defaults On

**Date:** 2026-07-09
**Status:** Accepted

## Context

ADR 0017 accepted Phase 5 Gate A closeout evidence but did not itself authorize
broad feature-flag enablement. After that closeout, Henry approved making the
accepted Gate A and B2 support surfaces default-on for fresh installs while
preserving per-flag rollback.

## Decision

The following flags default to `true` for fresh installs:

- `package_registry`
- `package_themes`
- `capabilities`
- `passkeys`
- `provider_registry`
- `invitations`
- `service_secrets`
- `api_tokens`
- `webhooks`
- `first_party_hooks`

Operators can still set any of these to `false` in the `features` setting.
The package execution brake remains independent of the `package_registry` flag.

## Non-Goals

This decision does not graduate unfinished Phase 3/4 carryovers:
`custom_css`, `group_dms`, `community_memory`, `link_previews`,
`expanded_files`, or `automated_context`.

This decision does not accept Gate B. `server_extensions`, `governance`,
`service_principals`, and `verified_links` remain default-off and reserved.

## Evidence

- `docs/evidence/phase5/gate-a-closeout.md`
- `docs/evidence/deploy-dark-features.md`
- `docs/superpowers/specs/2026-07-09-phase5-gate-a-default-on-design.md`
```

- [ ] **Step 2: Update `docs/evidence/deploy-dark-features.md` source audit**

Update the Source Code Audit section so it says:

```markdown
- `FeatureFlags::DEFAULTS` declares 57 flags: 47 default `true`, 10 default
  `false`. (Phase 5 Gate A/B2 support flipped `false`->`true` on 2026-07-09;
  the admin feature-inventory canary in
  `tests/Integration/Admin/AppAdminFeaturesTest.php` enforces the `47`/`10`
  split.)
- This deploy-dark inventory has 39 table rows: all 10 current default-dark
  flags, plus 29 retained graduated flags that are default-ON and
  operator-reversible.
```

Update the Phase 5 reconciliation paragraph so it says the 2026-07-09 default
flip changed the split from `37`/`20` to `47`/`10`, and that Gate B plus the six
unfinished Phase 3/4 carryovers remain default-dark.

In the Phase 5 Gate A table, change each selected row to begin with
`**Graduated 2026-07-09 - now default-ON**` and preserve the existing evidence,
rollback, sequencing, and brake details. The `invitations` row must retain
`registration_mode = invite`, fail-closed semantics under
`features.invitations=false`, browser evidence `69`-`74`, and the runbook link.

- [ ] **Step 3: Update status and top-level docs**

In `PHASE_5_STATUS.md`, update the top status paragraph and P5-16 closeout
section to state that Gate A fresh-install defaults are on as of 2026-07-09,
with Gate B reserved. Preserve existing suite counts and evidence links.

In `README.md`, change the status callout from `Gate A accepted; everything deploy-dark` to:

```markdown
> **Status: Phase 5 (ecosystem, identity & governance) - Gate A accepted and default-on for fresh installs; Gate B reserved.**
```

Then summarize that the selected Gate A/B2 flags are default-on and operator
reversible, instead of saying live behavior is unchanged until an operator
enables them.

In `CHANGELOG.md`, add a new Unreleased subsection above the P5-16 evidence entry:

```markdown
## [Unreleased] - Phase 5 Gate A defaults on for fresh installs

Accepted Phase 5 Gate A and B2 support flags now default on for fresh installs:
`package_registry`, `package_themes`, `capabilities`, `passkeys`,
`provider_registry`, `invitations`, `service_secrets`, `api_tokens`, `webhooks`,
and `first_party_hooks`. Each remains reversible through the `features` setting;
Gate B and unfinished Phase 3/4 carryovers stay default-off.
```

- [ ] **Step 4: Update schema and ledger notes**

In `SCHEMA.md`, update the Phase 5 foundation note and Section 5A introduction
so they say the schema was originally additive/deploy-dark, is now animated by
accepted Gate A defaults, and remains reversible through `features` overrides.
Keep Gate B tables `101`-`104` described as deploy-dark.

In `docs/phase5/requirement-ledger.json`, update notes that say the selected
flags are deploy-dark or that R5 waits for staged enablement. Do not change the
JSON shape. Add `docs/adr/0018-phase-5-gate-a-default-on.md` to `GA-DOD-23`
evidence. Keep Gate B rollback text unchanged. Keep rollback strings for
selected flags phrased as `Set features.FLAG=false`.

- [ ] **Step 5: Run doc guard tests**

Run:

```bash
vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php tests/Integration/Admin/AppAdminFeaturesTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit docs**

```bash
git add docs/adr/0018-phase-5-gate-a-default-on.md docs/evidence/deploy-dark-features.md PHASE_5_STATUS.md README.md CHANGELOG.md SCHEMA.md docs/phase5/requirement-ledger.json
git commit -m "docs(phase5): record Gate A default-on posture"
```

---

### Task 4: Final Verification Sweep

**Files:**
- Test only.

**Interfaces:**
- Produces: Verification evidence for the default flip.

- [ ] **Step 1: Run focused feature and rollout tests**

Run:

```bash
vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Admin/AppAdminFeaturesTest.php tests/Integration/Security/RegistrationPolicyTest.php tests/Integration/Core/AppOAuthTest.php tests/Integration/Core/AppAdminModerationTest.php tests/Integration/Core/AppInvitationsTest.php tests/Integration/Core/AppPackageIntegrationTest.php tests/Unit/Core/Phase5EvidenceMapTest.php
```

Expected: PASS.

- [ ] **Step 2: Run API/webhook rollback-focused tests affected by B2 defaults**

Run:

```bash
vendor/bin/phpunit tests/Integration/Api/AdminApiTokenTest.php tests/Integration/Api/ApiAuthorizationMatrixTest.php tests/Integration/Admin/AdminWebhookTest.php tests/Integration/Worker/WebhookIdempotencyTest.php tests/Integration/Worker/WebhookDeliveryWorkerTest.php tests/Integration/Core/AppPackageSecurityConsoleTest.php
```

Expected: PASS.

- [ ] **Step 3: Run final grep checks for stale posture text**

Run:

```bash
rg -n "37 default-on|20 default-dark|everything deploy-dark|Gate A has landed behind default-off|features\\.invitations stays at its dark default|package_registry stays default-off|every Phase 5 flag defaults dark|R5 waits for staged enablement|R5 at staged enablement" README.md CHANGELOG.md PHASE_5_STATUS.md SCHEMA.md docs/evidence/deploy-dark-features.md docs/phase5/requirement-ledger.json tests
```

Expected: no matches.

- [ ] **Step 4: Check worktree scope**

Run:

```bash
git status --short
```

Expected: only intended files are modified or the tree is clean after commits. Pre-existing unrelated changes from the starting workspace must not be included in implementation commits.

- [ ] **Step 5: Leave verification runs as command evidence**

Do not create a verification-only commit. Report the command outputs in the
handoff summary. If verification changes a generated artifact unexpectedly,
restore that artifact before the final worktree-scope check.
