# P5-16 Defect Sweep

**Date:** 2026-07-09
**Requirement:** GA-DOD-21
**Status:** Captured.

## Scope

This sweep covers critical/high release blockers across security, privacy, accessibility, authorization, supply chain, identity, privilege escalation, data integrity, and release operability.

## Search Methodology

| Search | Result count | Classification |
|---|---:|---|
| `rg -n '\b(CRITICAL\|Critical\|critical\|HIGH\|High\|high\|blocker\|BLOCKER\|P0\|P1)\b' PHASE_5_STATUS.md PHASE_5_PLAN.md docs/evidence docs/phase5 docs/runbooks src tests` | 180 lines | Acceptance criteria, risk taxonomy, high-impact capability vocabulary, axe assertion text, advisory/test fixtures, historical status notes, and the open GA-DOD-21 ledger row pending Task 6 reconciliation. No live critical/high Gate A blocker identified. |
| `rg -n '\b(failed\|failure\|pre-existing\|deferred\|follow-up\|blocked\|not chased\|pending owner\|reserved)\b' PHASE_5_STATUS.md docs/evidence/phase5 docs/evidence/deploy-dark-features.md docs/phase5 docs/adr` | 107 lines | Explicit Gate B reserves, historical pre-P5-16 notes, known non-blocking deferrals, pending product-owner acceptance markers, expected refusal/blocked behavior in runbooks/tests, and resolved browser issues. No hidden live Gate A blocker identified. |

## Search Hit Disposition

| Category | Examples | Disposition |
|---|---|---|
| Gate criteria and status history | `PHASE_5_PLAN.md`, `PHASE_5_STATUS.md` | Historical/criteria text. Current P5-16 evidence supersedes the previously recorded badge-rules mobile issue and records green browser/regression results. |
| Risk taxonomy and high-impact vocabulary | `docs/phase5/capability-taxonomy.md`, `src/Security/CapabilityCatalog.php`, `src/Security/DataClasses.php` | Expected security model vocabulary, not defect markers. |
| Axe and browser assertions | `tests/browser/*.spec.ts`, runbook a11y notes | Positive assertions that serious/critical axe violations are absent in covered surfaces. |
| Advisory/severity fixtures | package registry, update, health, and advisory tests | Test data for high/critical package advisory behavior; covered by Task 3, Task 4, and security regression. |
| Explicit Gate B reserves | governance, service principals, verified links, public sandbox/server extensions | Out of Gate A; listed below as non-blocking deferrals. |
| Threat-model "pending owner" markers | `docs/phase5/threat-models/*.md` | Owner-review status markers, not evidence of live implementation defects; GA-DOD-23 remains separate product-owner acceptance. |

## Security Regression

| Command | Result |
|---|---:|
| `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 vendor/bin/phpunit --no-progress tests/Unit/Security tests/Integration/Security tests/Unit/Core/ThreatModelIndexTest.php tests/Integration/Service/SecretVaultTest.php tests/Integration/Service/SecretVaultRedactionTest.php tests/Integration/Service/PackageCredentialAuthGuardTest.php tests/Integration/Service/PublisherTrustServiceTest.php tests/Integration/Service/InvitationServiceTest.php tests/Integration/Core/AppInvitationsTest.php tests/Integration/Core/AppOidcProviderTest.php tests/Integration/Core/AppPasskeyRecoveryTest.php` | OK (330 tests, 2150 assertions) |

## Known Non-Blocking Deferrals

| Item | Source | Disposition |
|---|---|---|
| Gate B sandbox / server extensions | `PHASE_5_PLAN.md` Gate B; `docs/adr/0011-public-plugin-runtime-scope.md` | Explicit Gate B reserve; not a Gate A blocker. |
| Governance / access review | `PHASE_5_PLAN.md` Gate B; `docs/evidence/deploy-dark-features.md` | Explicit Gate B reserve; not a Gate A blocker. |
| Service principals | `PHASE_5_PLAN.md` Gate B; `docs/evidence/deploy-dark-features.md` | Explicit Gate B reserve; not a Gate A blocker. |
| Verified links / richer profile fields | `PHASE_5_PLAN.md` Gate B; `docs/evidence/deploy-dark-features.md` | Explicit Gate B reserve; not a Gate A blocker. |
| Inc 5 hardening nits | `docs/evidence/phase5/inc5-closeout-readiness-audit.md` | Deferred as non-critical/non-high while deploy-dark; no broad enablement claim depends on them. |

## Release Blocker Disposition

No open critical/high Gate A defects remain after the P5-16 browser sweep, runbook rehearsals, full regression/route matrix, and security-focused regression listed above.

This statement does not accept Gate B scope. Gate B reserved items remain outside Gate A until accepted separately or explicitly deferred by the product owner.
