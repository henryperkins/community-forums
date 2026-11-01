# Verified email-change deployment

Published once on 2026-11-01 as
[`a187ae4f`](https://github.com/henryperkins/community-forums/commit/a187ae4fbc063954731b306c39e2012bedecf8db).
Primary `main` is clean and synchronized with `origin/main`. It includes the
previous asset-retention bookkeeping commit `a75eee98`.

| Check | Verified result | Receipt |
| --- | --- | --- |
| Cloud Build | `6409eb92-81e9-49a4-97c4-f8c7bd315257`, SUCCESS for the published SHA | [Build](cloud-build.json) |
| Cloud Run | `retroboards-00071-hl7`, Ready, 100% traffic, exact published image | [Service](cloud-run-service.json) |
| Cron jobs | All four Ready and using the published image | [Readiness](cron-readiness.json), [images](cron-images.json) |
| Worker | `8eb74d31-9da6-492a-8fa3-4782cebb5bc4`, 100%; successful build `8fb7757b` maps the exact version to the published SHA | [Worker](worker.json) |
| Assets | 57/57 SHA-256 values match; current version `67ba8434286ff7e6`; retained prior release files are present | [Asset bytes](live-assets-and-pages.json) |
| Final public smoke | Health/database OK; login serves the new passkey script; root and login use current CSS; email-change routes return 404 while default-off | [Smoke](post-cloud-run-smoke.json) |
| Git | Clean main parity, 52 qualified file hashes match, no migrations, prior bookkeeping included | [Git](git-state.json) |

Asset bytes were checked after Worker rollout while the prior origin still
served. The separate final smoke follows Cloud Run readiness. The board index
intentionally omits the passkey enhancement; login verifies its new script URL.

Local qualification in the published source is
`docs/evidence/email-change-2026-11-01/README.md`: 3,564 PHP tests / 33,898
assertions, zero failures and one intentional skip; 29 unique passing browser
configurations; 35 asset/Worker tests. No production member contact, feature
override or content was changed. Actual provider exchange and new-inbox flow
are qualified using synthetic identities/mail locally, not real mailbox receipt.

`email_change` remains default-off, with its guarded **Verified email changes**
control in `/admin/features`. The runbook records activation/rollback; queued
security notices preserve address suppression and mail-readiness rules.

GitHub still reports alerts 60 and 63 open with their old October 23 records,
while published lock/graph versions are patched. [Final alert state](dependabot.json).
No alert was dismissed. GitHub's documented **Refresh Dependabot alerts** menu
action remains UI-only; no authenticated GitHub browser session was available.
See the committed dependency diagnostic for official references.

[Publication](publication.json), [summary](summary.json), and [receipt hashes](sha256.json)
are release artifacts under ADR 0051. Recording this proof does not require
another publication. The verified asset release is recorded locally on
`feature/verified-email-change` for inclusion with the next application change.
