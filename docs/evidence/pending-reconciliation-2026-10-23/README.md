# PR #91 asset reconciliation — 2026-10-23

Local qualification of Dependabot PR #91 (`4115343466c1ec0db259454bafc93cd7c9588517`)
merged with GitHub `main` at `9178f170f65324edd73ce683331db3cc9c500119`.
This record establishes local validation only; it does not record publication or
a production deployment.

## Change and reproduction

The package changes retain current main's Neon dependencies and update Vite from
8.3.1 to 8.3.3. A clean `npm ci --ignore-scripts --no-audit --no-fund` succeeded.
Before regeneration, `npm run check:assets` failed on exactly:

- `public/assets/dist/wysiwyg-composer-C_SnS523.js`
- `config/assets.json`

`npm run build` regenerates Vite's composer loader and changes the manifest from
asset version `d558418fe08d412c` to `432ce95c7cdbe032`. The Milkdown adapter, source
JavaScript, source CSS, and generated CSS remain byte-identical to current main.
The loader change is Vite-generated preload handling; its dynamic import still
loads `milkdown-adapter-zC8kMFUx.js` and exports `loadWysiwygAdapter`.

The generated JavaScript changes the application surface digest. After reviewing
the composer contract and the generated diff, the integration candidate updates
`config/imladris-runtime-baseline.json` and regenerates
`resources/imladris/manifest.json` to digest
`71617540514b16eca5337a9e9205dd61daf99d8abf40ffef2271a2a899c8bc6b`.
The imported design contract, design digest, and generated Imladris CSS are unchanged.

All three `deployedReleases` records are unchanged, and the bytes and manifest
hashes of their 30 distinct assets were verified. No release was newly marked
deployed. See [asset-retention.json](asset-retention.json).

## Validation

Environment: Node 24.21.0, npm 11.19.0, PHP 8.4.27. PHP dependencies and browser
dependencies were copied into this isolated worktree from the existing checkout;
root npm dependencies were installed from the candidate lockfile.

| Check | Result | Evidence |
| --- | --- | --- |
| Clean root dependency install | Pass | [npm-ci.log](npm-ci.log) |
| Asset reproducibility before generation | Expected failure on the two paths above | [assets-before.log](assets-before.log) |
| Asset generation and reproducibility | Pass | [assets-build.log](assets-build.log), [assets-check.log](assets-check.log) |
| Worker and asset tests | 35 passed, zero skips | [assets-tests.log](assets-tests.log) |
| Imladris generation and freshness | Pass | [imladris-build.log](imladris-build.log), [imladris-check.log](imladris-check.log) |
| Full `composer test` | 3,353 tests, 29,677 assertions, one skip; exit 0 | [phpunit.log](phpunit.log) |
| Focused Chromium browser tests | Nine passed; five declared mobile skips; zero retries | [browser.log](browser.log) |

PHPUnit used `retroboards_pr91_unit_20261023`; browser tests used
`retroboards_pr91_browser_20261023`, separate rate-limit/package stores, and port
8093. The initial new database names lacked the development user's permitted
`retroboards%` prefix; the qualifying run used the names above. No existing
application or test database was reset.

The browser selection covers canonical Markdown submission, lazy rich-preview
fetching, switching between rich and source modes, active-format tracking, and
wide/tall/small uploaded-image previews. Submission and caret-format tracking
also ran at the 390px mobile viewport. The five mobile skips are the existing
desktop-only preview, mode-semantics, and three image-sizing cases. These are
browser-emulation results, not physical-device evidence. The full hosted browser
matrix and production deployment checks were not run.

Browser command, from the isolated repository root:

```sh
E2E_PORT=8093 \
DB_DATABASE=retroboards_pr91_browser_20261023 \
RATELIMIT_PATH=storage/ratelimit-e2e-pr91 \
PACKAGES_STORAGE_PATH=storage/packages-e2e-pr91 \
RB_EVIDENCE_DIR=docs/evidence/pending-reconciliation-2026-10-23/browser \
tests/browser/node_modules/.bin/playwright test \
  --config tests/browser/playwright.config.ts \
  composer-shell.spec.ts composer-toolbar-hardening.spec.ts \
  --grep 'toolbar reflects active marks|rich toolbar follows caret|rich preview defaults|inline .* image|submit state keeps' \
  --retries=0
```
