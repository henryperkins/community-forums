# ADR 0018: Phase 5 Gate A Defaults On

**Date:** 2026-07-09
**Status:** Accepted

## Context

ADR 0017 accepted Phase 5 Gate A closeout evidence but did not itself authorize
broad feature-flag enablement. After that closeout, Henry approved making the
accepted Gate A and B2 support surfaces default-on while preserving per-flag
rollback.

## Decision

The following flags default to `true`:

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

`FeatureFlags::DEFAULTS` is the base for every install, so the new defaults
apply to **any install without an explicit `features.<flag>` override — fresh
and upgraded alike** (the same semantics as every prior graduation, e.g.
`topic_workflow`, `wysiwyg_composer`, `appeals`). Operators can still set any
of these to `false` in the `features` setting; a stored override always beats
the default in both directions. The package execution brake remains
independent of the `package_registry` flag.

This ADR also supersedes the per-subsystem staged-enablement / staff-pilot
steps that PHASE_5_PLAN §13.1 scheduled between acceptance and release (the
cohort rollouts in steps 3 and 9): the default-on flip is the enablement
event, and the affected requirement-ledger rows advance to R5 on this ADR's
authority. The one artifact those steps still owed — a measured
`registry.fetch_p95` against a live registry endpoint — remains **PENDING**
and is excluded from Gate A (GA-DOD-18); it is to be measured when the first
production-representative registry is enabled.

## Upgrade notes

Operators upgrading an existing install past this change should know:

1. **These ten surfaces go live on upgrade** unless a `features.<flag>=false`
   override exists. To keep any of them dark, set the override *before or
   after* upgrading — see `docs/runbooks/operations.md` §2 for the
   merge-preserving recipe (never write a fresh `features` object; it clobbers
   other overrides).
2. **A lingering `CAPABILITIES_MODE=enforce` environment value becomes live
   resolver enforcement.** While `capabilities` defaulted dark the mode was
   never read; with the flag on by default, `enforce` activates DB-backed
   authorization (fail-closed, including the ADR 0016 suspended-staff delta)
   across every gated site. Unset it (shadow is the default) or roll the flag
   back until you have run the shadow soak in
   `docs/runbooks/capabilities.md` §"Staged rollout".
3. **Passkeys require a canonical HTTPS `APP_URL` in production.** The login
   affordance renders only where the ceremony can succeed (it is hidden when
   the relying-party policy is unsatisfiable), but installs terminating TLS at
   a proxy should correct a stale `http://` `APP_URL` — or set
   `features.passkeys=false` — before advertising passkeys.

## Non-Goals

This decision does not graduate unfinished Phase 3/4 carryovers:
`custom_css`, `group_dms`, `community_memory`, `link_previews`,
`expanded_files`, or `automated_context`.

This decision does not accept Gate B. `server_extensions`, `governance`,
`service_principals`, and `verified_links` remain default-off and reserved
until each Gate B workstream lands its own release evidence.

## Verification and DESIGN §13

The flip does change one piece of default rendered output: with `passkeys` on
by default, `/login` gains the passkey sign-in affordance and every page loads
`/assets/passkeys.js` on a zero-override install. The browser-evidence seed
(`tests/browser/seed.php`) therefore pins `passkeys` explicitly alongside the
other nine flags (all pinned ON, the posture the Gate A closeout browser/a11y
evidence — browser 71 passed / 1 skipped, a11y 26 / 2 — already exercised),
and the login-page captures (`05-login`, `67-login-generic-provider-button`,
desktop + mobile) are refreshed to the default-on baseline; the dark-posture
shot stays owned by `passkeys.spec.ts`, which sets the flag per test.

Server-side, graduation is pinned by:

- the default-posture PHPUnit test
  (`test_phase5_gate_a_defaults_on_and_gate_b_stays_dark`),
- a **zero-override live-route pin**
  (`test_phase5_gate_a_surfaces_are_live_with_no_features_override`) proving
  the surfaces answer on a pristine install, matching the
  `*_is_available_by_default` pins every prior graduation carried,
- a **rollback write smoke**
  (`test_capabilities_rollback_keeps_legacy_authorization_writes_live`)
  driving authorization writes with `features.capabilities=false`,
- the admin feature-inventory canary (47/10), and
- a green full `composer test` run recorded in `PHASE_5_STATUS.md` (Suite).

## Evidence

- `docs/evidence/phase5/gate-a-closeout.md`
- `docs/evidence/deploy-dark-features.md`
- `docs/superpowers/specs/2026-07-09-phase5-gate-a-default-on-design.md`
- `docs/evidence/browser/desktop/05-login.png` /
  `docs/evidence/browser/mobile/05-login.png` (default-on login baseline)
