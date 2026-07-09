# Phase 5 Gate A Default-On Fresh Install Design

Date: 2026-07-09

## Goal

Make accepted Phase 5 Gate A behavior default-on for fresh installs by changing
the relevant feature defaults from `false` to `true`, while preserving rollback
through the `features` override.

## Scope

Flip these accepted Gate A and B2 support flags to default-on:

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

Keep these flags default-off:

- Phase 3/4 carryovers whose deploy-dark inventory still lists missing evidence:
  `custom_css`, `group_dms`, `community_memory`, `link_previews`,
  `expanded_files`, and `automated_context`.
- Gate B or reserved Phase 5 workstreams: `server_extensions`, `governance`,
  `service_principals`, and `verified_links`.

## Rationale

`docs/adr/0017-phase-5-gate-a-closeout.md` records Phase 5 Gate A acceptance on
2026-07-09 and explicitly keeps Gate B out of scope. The default-on change should
therefore track the accepted Gate A boundary, not every current deploy-dark flag.

The selected flags have closeout evidence in `docs/evidence/phase5/` and
rollback entries in `docs/phase5/requirement-ledger.json`. The remaining Phase
3/4 dark flags still list missing browser, runbook, operational, load, or policy
evidence in `docs/evidence/deploy-dark-features.md`, so they should not be
silently graduated in this change.

## Implementation Shape

Update `src/Core/FeatureFlags.php` so the selected Phase 5 Gate A and B2 flags
default to `true`. Keep comments clear that these flags remain reversible via the
`features` settings override and that package execution has the separate
`PACKAGE_EXECUTION_DISABLED` / `package_execution_disabled` brake.

Update tests that assert the default split and default posture. The expected
split changes from 37 default-on / 20 default-off to 47 default-on / 10
default-off. Existing tests that assert off-flag routes 404 should be revised to
use explicit `features` overrides for rollback coverage instead of relying on
defaults.

Update documentation:

- `docs/evidence/deploy-dark-features.md`: re-run the source audit summary,
  move the ten flipped flags into retained default-on traceability language, and
  keep the ten still-dark flags visible.
- `PHASE_5_STATUS.md`: record that Gate A fresh-install defaults are now on,
  while Gate B remains reserved.
- `docs/phase5/requirement-ledger.json`: update rollback/default-posture notes
  where they still say the selected flags are deploy-dark by default.
- Any admin feature-inventory docs or canaries that mention the old 37/20 split.

## Testing

Use test-first implementation for changed behavior. Add or update focused tests
before changing production defaults, then verify they fail for the old posture
and pass after the flip.

Minimum verification:

- Focused feature-flag/default-posture tests.
- Route rollback tests for selected flags with explicit `features.<flag>=false`.
- Phase 5 evidence-map or ledger guard tests if documentation evidence mappings
  change.
- A final focused PHPUnit sweep covering the touched feature-flag and Phase 5
  posture tests.

Full browser evidence is not required for this default flip because the accepted
Gate A evidence package already captured those surfaces. If a test update reveals
that any route only works when seeded flags are manually enabled, keep the test
fixture explicit rather than adding new behavior outside the approved default
posture.

## Rollback

Operators can still disable any flipped flag through the `features` settings
override. The Gate B flags stay default-off, and package execution remains
independently stoppable through the package execution brake.
