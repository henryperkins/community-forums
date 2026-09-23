# Performance evidence — 2026-09-20

This directory preserves historical measurements and the subsequent fixes from
the 2026-09-20 performance work. Production state, build identifiers, timings
and source line references describe those runs; they are not a current
production assessment. The original loose files were organized on 2026-09-23.

| Evidence | Purpose |
| --- | --- |
| [Diagnosis](diagnosis.md) | Measurement-based attribution and ranked recommendations |
| [Browser analysis](browser-analysis.md) | Guest production and isolated local-member timing, images and layout |
| [Staff browser analysis](browser-staff-analysis.md) | Authenticated staff measurements and their limits |
| [Server attribution](server-analysis.md) | Query counts, call-site attribution and proposed reductions |
| [Staff server attribution](server-staff-analysis.md) | Role and read-state comparisons on local fixtures |
| [Query fixes](query-fix/README.md) | Implemented request-read and unread-position improvements |
| [Layout fixes](cls-fix/README.md) | Implemented layout-stability repairs and before/after evidence |
| [Production release](production-release/README.md) | Deployment, live asset checks, browser measurements and session cleanup |
| [Session brief](session-brief.md) | Historical instructions and assumptions that guided the original diagnosis |

Named summary, attribution, validation, cleanup and deployment JSON files remain
beside the reports. Screenshots and the image benchmark remain as visual
evidence. Raw per-request traces and one-off helper scripts are in the local
archive; report links identify those artifacts and point to the
[86-file inventory](../../workspace-cleanup/2026-09-23/README.md), which records
their original paths and checksums.

The staff-browser report corrects the older local screenshot label
`cold-browser-first-unread`: that fixture measured a first render with no saved
cursor, not a proven first-unread browser journey. Preserving the filename does
not change that limitation. Laboratory samples are not field percentiles.
