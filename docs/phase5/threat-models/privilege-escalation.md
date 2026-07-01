# Phase 5 threat model - Privilege escalation

**Status:** Recorded 2026-07-01 - pending owner review
**Sources:** PHASE_5_PLAN section 9 built-in role parity, custom role, grantor authority, state precedence, private read gate, temporary grant, role edit, simulator, and last-owner scenarios; section 12 role risks; `docs/phase5/capability-taxonomy.md`.
**Fixture index:** `fixtures.json`, enforced by `tests/Unit/Core/ThreatModelIndexTest.php`.

## Scope and assets

The database-backed authorization spine: capability catalogue, role definitions,
scoped assignments, resolver caches, protected owner state, and the legacy
authority model kept as rollback. No route, simulator, grant, cache, or package
may yield authority beyond the accepted model.

## Threats

| ID | Threat | Required negative fixture | Owner |
|---|---|---|---|
| TM-PE-01 | Custom role obtains a protected capability. | Role create/edit/clone with protected key rejected; seed pins zero protected mappings. | Inc1 |
| TM-PE-02 | Grantor assigns beyond held scope or capability. | Board-scoped grantor cannot assign site scope via direct POST. | Inc6 |
| TM-PE-03 | Expired temporary grant remains honored through stale cache. | Direct request after ends_at denied despite warm cache. | Inc6 |
| TM-PE-04 | Suspended account keeps write authority through custom role. | Suspended user with active custom role denied on every write. | Inc1 |
| TM-PE-05 | Private-board read leaks through simulator, search, or counts. | Simulator/search/counts leak nothing for a non-member role. | Inc1 |
| TM-PE-06 | Migration parity gives broader authority than legacy model. | Parity corpus proves no migrated grant exceeds legacy scope. | Inc6 |
| TM-PE-07 | Last recoverable owner is removed through a secondary path. | Every owner-loss path blocks at the last recoverable owner. | Inc6 |
| TM-PE-08 | Role edit leaves removed capability active for assignees. | Capability removed from role denies all assignees on next direct request. | Inc6 |
| TM-PE-09 | Governance approval allows self-approval. | Requester counted as approver leaves request pending. | GateB |

## Residual risk

The operator can still grant themselves power directly through database access.
That is an accepted self-host boundary. Application controls focus on all web
and worker entry points, rollback, audit, cache invalidation, and simulator
accuracy.
