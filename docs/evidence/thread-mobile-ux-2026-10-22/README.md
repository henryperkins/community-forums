# Thread-view fixes â€” 2026-10-22

Verification ceiling: real local PHP/MariaDB behavior, automated browser mechanisms and desktop viewport proxies. Physical-device checks are **NOT READY**; no phone or OS-simulator result is claimed.

All six approved changes are implemented (ADR 0050):

- F-01: server-confirmed, version-matched draft cleanup; recoverable failed sends; safe history/idempotency handling in Markdown and rich modes; preserve newer local/remote drafts.
- F-02: persistent View answer with guest/member/staff pagination and visibility checks.
- F-03: enlarged topic actions wrap within the available width.
- F-04: the complete expanded dock fits below the header; input/preview scroll internally while Minimize/Reply remain reachable. Account menus remain above the dock.
- F-05: compact mobile byline with full-width prose, references, signatures and reactions; grouped/anonymous identity remains intact.
- F-06: reaction choices show their existing names before activation.

## Local verification

| Check | Result |
| --- | --- |
| Focused kernel/presentation/reaction tests | 56 passed, 474 assertions |
| New desktop + mobile recovery/layout suite, pinned toolchain | 24 passed |
| Additional landscape/account-menu/reaction checks | 4 passed |
| Existing server-draft suite, explicit Markdown fixture | 10 passed |
| Other selected composer/menu regressions | 7 passed, 1 project-specific skip |
| Generated asset build and integrity | Passed |
| Asset runtime/retention suite | 35 passed |
| Imladris runtime generation/check | Passed after the application baseline refresh on main |
| Independent source review | All reported P1/P2 issues resolved |
| Full native Windows suite | 3,337 tests, 29,473 assertions; 12 failures, 2 warnings, 1 skip. All12 failure names and both warnings reproduce on the untouched baseline; no new failure names |
| Mobile runner ledger | Valid, incomplete; exit 3 for outstanding target checks |

The first broad browser run inherited a rich-editor fixture from another suite; the server-draft suite passed when rerun with its required Markdown setup. A combined focused run also exhausted the shared fixture posting bucket; per-test isolation resets only that disposable account's bucket. Failed evidence remains in local output; later results do not erase it.

The shared installed Node tools were older than the repository lockfile. Qualification used an isolated `npm ci` of the unchanged HEAD lockfile: Vite 8.3.1, esbuild 0.28.2, Wrangler 4.149.0 and Miniflare 5.20261006.1-alpha. The primary checkout's package edits and installed dependencies were preserved. Five retained immutable files had CRLF conversion; their original LF bytes exactly matched the manifest. Git now preserves generated assets as bytes and pins the application CSS/compatibility source to LF.

## Evidence and scope

`build-identity.json` records exact source hashes and asset version `035b6defb6d21565`. `verification.json` separates completed, historical and pending evidence. Browser captures are in `browser/`; `independent-review.md` retains review findings and their resolutions. `run.json` retains stable F-IDs and original scopes; the original audit is retained separately. Its 24/40 synthesis score is historical and was not rescored.

The reply recovery inventory has one enabled saved-content field, body Markdown. The fixture board disables anonymous replies, and that field was not activated for coverage. Uploaded images become canonical body Markdown; transport fields do not become a claim about optional saved content. Shared new-topic/DM regressions are representative, not a complete optional-field matrix for those other journeys.

Four physical-device entries jointly cover the applied recovery and dock changes within the five-entry admission cap. Their required isolated reachable build is not provisioned. `device-script.md` gives prerequisites, stop conditions and separate blank outcomes/build/setup confirmation fields. Desktop emulation, the scripted delayed-autosave response and a structurally valid ledger do not certify phones, real radio loss, browser bars, software keyboards or field performance.

## Preservation and release

Guarded integration hash-checked 72 unrelated original paths. The commit uses an explicit source/generated-asset/evidence allowlist; package edits, prompt deletions, Impeccable settings and unrelated scratch files are excluded. The application reconciliation baseline is refreshed on main under ADR 0024; no database migration is involved. Exact commit, remote synchronization and automatic production deployment receipts are recorded after publication.

The source fixes were published as `e5dd37c52bd211c0ac6e7c2650bee43d950e672d`. Both automatic production builds succeeded for that exact commit. Cloud Run revision `retroboards-00043-j9f` serves 100% of traffic; all four cron jobs use its commit-tagged image. The Worker serves all 25 current release assets with matching SHA-256 and immutable cache headers. Public health, homepage and thread reads passed with the expected asset URLs; these are read-only checks, not production submission or phone results. `production/source-deployment.json` and `production/live-assets-source.json` preserve the receipts.

After those checks, the release record pins `035b6defb6d21565` and its two predecessors, retiring two files exclusive to the oldest release. Current asset URLs and bytes do not change. Asset reproducibility, all 35 asset tests, and the refreshed main-branch Imladris application digest pass. The independent full hosted browser lane was still running when this receipt was recorded; its parent build status does not establish a matrix pass. Physical-device provisioning and checks remain pending.
