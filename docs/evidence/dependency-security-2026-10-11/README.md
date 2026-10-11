# Security dependency update — 2026-10-11

PR [#90](https://github.com/henryperkins/community-forums/pull/90) merged as
`3c48c6d98560fa36c9f4744f92dbbafc8cce64a9`, with the immediately following
`main` reconciliation `f5870e9a0fd338040d47d86e972dafa3bdf01da2` under ADR 0024.
The browser-validated candidate is `605afac10807ac46603342a47004b16fa1146fd9`.

The dependency tree updates ProseMirror View to 1.42.6, ProseMirror Model to
1.25.12, Sharp to 0.35.5, Source Map JS to 1.2.2 in both lockfiles, and Undici
to 7.29.1 through Wrangler 4.149.0. Root and archived-prototype npm audits both
report zero vulnerabilities. GitHub's Dependabot API reports no open alerts
after the merge; ten alerts were open before it.

## Compatibility repair and validation

The original updated composer failed eleven relevant browser cases. The new
ProseMirror model validates attributes at node creation, exposing Milkdown
7.21's string-valued list spread flags and null image text/title values.
Local schema extensions normalize those parser values to their declared
boolean/string types, including task/ordered-list serialization and image-title
clearing. The upstream validators remain enabled. A new HTML clipboard-slice
regression rejects invalid list/image attributes; existing tests prove supported
paste, source-mode tasks, formatting and image insertion still work.

| Check | Completed result |
| --- | --- |
| Generated delivery build/check | Passed; lazy editor entry and adapter rebuilt |
| Worker/asset tests | 35 passed |
| Archived prototype build/site tests | Build passed; 4 passed |
| Root and archived-prototype npm audit | Zero vulnerabilities in both |
| Chromium focused composer/retained-release checks | 62 passed; 16 existing viewport exclusions |
| WebKit desktop/mobile retained layout and rich composer/image checks | 18 passed |
| Imladris build/check | Passed on reconciled `main` |
| Full PHP suite | 3,231 tests; 25,593 assertions; two existing skips; zero failures |

The runtime/test/dependency/asset/Worker/deploy sources match the validated
candidate. Main-only changes are reconciliation metadata/documentation and the
owner-directed permanent GitHub Actions constraint. Eight synthetic local
desktop/mobile, JavaScript on/off captures are privately retained and hash-bound
by the release receipt. The PR also records
[the completed merge validation](https://github.com/henryperkins/community-forums/pull/90#issuecomment-6112714754).

## Release checks and GitHub Actions

The owner confirmed GitHub Actions is permanently unavailable and billing will
not be repaired. `AGENTS.md` and the Cloud Run runbook record the local
PHP/asset/focused-browser release path, and the reference workflow is manual-only.
The optional broad hosted parent `a6cd9ed2-58cb-436b-b64e-17c0ba6eacf9` and
child `407c2970-56bc-4d25-b060-5eb9fd859950` were deliberately canceled after
completed local checks. Main's broad retrigger
`bfb83249-f541-4e9a-bfdb-dcdc6a62bec8` was also canceled. None establishes a
full hosted acceptance pass.

The automatic production build for reconciled main is
`a8855e30-ecfe-4d75-b089-8051f81d4b36`. The read-only release verification
receipt is [`merge-deployment.json`](merge-deployment.json); it binds live asset
hashes/cache behavior and exact service/job images to the release source.
Coverage is synthetic local browser verification and read-only production HTTP
checks; physical-device uploads and production member writes are outside it.

The initial deployment passed all 61 allowlisted live asset hashes,
GET/HEAD/304 cache checks, guest page/CSP checks, public database health,
canonical alias routing and unauthenticated direct-origin denial. The service
and all cron/monitor jobs match the exact release image.

`npm run assets:record-release -- aa3e487a59d3d205` then records the verified
deployment with predecessors `3642fd850eefb9c0` and `d8529d5d0f5e0219`. The only
expired exclusive file is `app-ead709125428cd4d.js`, from oldest release
`85b4bc099e7bdac1`; final delivery contains 60 assets. Its reviewed runtime digest
is `3c9562305a2842e139efb2d8a68c7105308efff05674c1e1f8c735764aa773bd`.
The release-record tree also passes all 35 asset tests, generated/Imladris checks,
the clean npm audit, and the full 3,231-test PHP suite with 25,593 assertions and
the same two existing skips.
