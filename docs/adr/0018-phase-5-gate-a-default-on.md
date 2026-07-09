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

## Verification and DESIGN §13

The flip changes only each flag's default *value*, not any rendered behavior: the
same server render pipeline serves these surfaces whether the flag is on by
default or enabled via an operator override. The Gate A closeout browser/a11y
evidence (README status line: browser 71 passed / 1 skipped, a11y 26 / 2) already
exercised every UI-visible surface in the ON state. Graduation is therefore
pinned by server-side tests — the default-posture PHPUnit test
(`test_phase5_gate_a_defaults_on_and_gate_b_stays_dark`), the admin
feature-inventory canary (47/10), and a green full `composer test` run — rather
than by re-capturing Playwright. This matches the established graduation practice
(prior flags graduated via PHPUnit default-on/rollback pins that cite existing
browser artifacts). No new fresh-install browser capture is required; a future
operator-facing smoke may add a zero-override pass if desired.

## Evidence

- `docs/evidence/phase5/gate-a-closeout.md`
- `docs/evidence/deploy-dark-features.md`
- `docs/superpowers/specs/2026-07-09-phase5-gate-a-default-on-design.md`
