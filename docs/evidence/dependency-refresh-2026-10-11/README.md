# Dependency refresh — 2026-10-11

PR [#81](https://github.com/henryperkins/community-forums/pull/81) merged as
`18db7c2ea74ac082064607ff5de54b3f9789c5a5`. Its immediately following `main`
commit, `8fb9db9ead2ffa786473e20dae05b262620b6dcf`, reconciles the reviewed runtime
digest under ADR 0024. The locally validated candidate is
`bd44e848420472d54c215910f929ff349e97ec18`.

Vite 8.3.1 regenerated six JavaScript delivery files. The Miniflare test fixture
now uses the upgraded native workers/config API while retaining its real Assets
GET, HEAD, conditional 304, missing-path and cache-policy assertions. The
archived prototype's dependency updates were built and checked separately.

## Completed validation

| Check | Result |
| --- | --- |
| `npm run build`, `npm run check:assets` | Passed |
| `npm run test:assets` | 35 passed |
| Archived prototype build and site tests | Build passed; 4 tests passed |
| Focused Chromium composer, toolbar and retained-release layout | 75 passed; 17 declared viewport exclusions |
| WebKit retained-release layout, desktop/mobile, JavaScript on/off | 12 passed |
| `composer build:imladris`, `composer check:imladris` | Passed on reconciled `main` |
| Full `composer test` | 3,231 tests; 25,593 assertions; two existing skips; zero failures |

The merge's application sources, templates, delivery files, dependency locks,
tests, Worker and deployment sources match the locally tested candidate. The
remaining differences are the merger-only reconciliation metadata and historical
image-upload evidence from PR #89. The eight synthetic local browser captures
are retained in the private review archive; their hashes are recorded in the
deployment receipt. Validation details are also preserved in
[the PR comment](https://github.com/henryperkins/community-forums/pull/81#issuecomment-6112274280).

## Hosted coverage boundary

The full hosted fallback was an additional agent-added hold and was deliberately
canceled after scope reassessment. Parent
`fe705cfa-0728-465b-b8f4-012515fe43a5` and child
`ff95d95e-d787-4748-a6cf-90d632cff32e` are canceled; partial passing groups do
not establish a full hosted pass. Main's retriggered parent
`02381bee-baac-4bfe-8e4f-71fe144582ad` was also canceled before child creation.
GitHub Actions could not start due the existing account billing lock, and
CircleCI has no configuration. The completed local checks above are the merge's
validation evidence.

The baseline and updated dependency trees each reported six high-severity npm
audit findings. This update is not a clean-audit or comprehensive vulnerability
remediation claim.

## Production verification

The exact automatic Cloud Build deployment is
`63052800-76b8-409b-8cc3-162eeb1c5261`, for `8fb9db9e`; the exact-source
Workers Builds check succeeded. The service takes 100% of traffic on
`retroboards-00014-ktf`.

[`merge-deployment.json`](merge-deployment.json) records read-only live asset
hashes, GET/HEAD/304 cache behavior, guest pages, public database health, alias
routing, direct-origin denial, the active Worker version, and the service plus
all cron/monitor images. Its coverage is HTTP/byte verification, with synthetic
local browser evidence; production member writes and physical-device uploads
were not rehearsed by this dependency refresh.

After this verification, `npm run assets:record-release -- 3642fd850eefb9c0`
records the successful deployment, retaining that version and predecessors
`d8529d5d0f5e0219` and `85b4bc099e7bdac1`. It retires eight exclusive files from
oldest release `ffc423564f256d2f`; the delivery set becomes 59 assets. The
post-deployment runtime digest is
`2e4f0664ba64dee5dce3f614bdacb53283272f0214f32c14a06296534c5acb4c`.
The resulting release-record tree also passed the generated asset and Imladris
checks, all 35 Worker/asset tests, and the full 3,231-test PHP suite with 25,593
assertions and the same two existing skips.
