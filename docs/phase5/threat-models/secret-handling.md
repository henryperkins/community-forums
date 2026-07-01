# Phase 5 threat model - Secret handling

**Status:** Recorded 2026-07-01 - pending owner review
**Sources:** PHASE_5_PLAN section 9 secret, provider, webhook, backup/restore, and telemetry scenarios; section 12 secret-handling risks.
**Fixture index:** `fixtures.json`, enforced by `tests/Unit/Core/ThreatModelIndexTest.php`.

## Scope and assets

Service-secret references, encrypted secret versions, webhook signing secrets,
provider client secrets, API tokens, telemetry/log context, backup/restore, and
operational test flows. Plaintext secrets should only exist at input time or in
bounded in-memory use.

## Threats

| ID | Threat | Required negative fixture | Owner |
|---|---|---|---|
| TM-SE-01 | Telemetry/logging emits live token, password, secret, PII, or private content. | Telemetry line with live secret carries [redacted], never the value. | Foundation |
| TM-SE-02 | Admin read/list surfaces return plaintext secret after save. | Reading a webhook/token/provider config after save returns no plaintext. | Inc5 |
| TM-SE-03 | Restored backup revives revoked or stale secret version. | Post-restore, a revoked svcsec version cannot decrypt/sign. | Inc10 |
| TM-SE-04 | Non-owning consumer reveals or rotates another service's secret. | Non-owning consumer reveal attempt fails and audits. | Inc5 |
| TM-SE-05 | Vault failure path logs or returns plaintext fallback. | Forced vault failure yields svcsec reference only, no plaintext. | Inc5 |
| TM-SE-06 | Provider test-flow response leaks client secret. | Provider test-flow response and logs free of client secret. | Inc8 |
| TM-SE-07 | Disaster recovery cannot re-key secrets safely. | Vault-unreadable DR rehearsal re-keys webhooks/providers without data loss. | Inc10 |

## Residual risk

Operators with filesystem and database access can recover encrypted material if
they also control the application key. That is accepted for self-hosted single
VPS deployment. Application controls prevent accidental exposure through logs,
admin readbacks, cross-consumer access, and rollback/restore flows.
