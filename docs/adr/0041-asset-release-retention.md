# ADR 0041: Retain recent immutable asset releases

Date: 2026-09-27. Status: accepted for implementation; release evidence belongs
in `docs/evidence/asset-release-retention-2026-09-27/`.

## Problem

The asset build deleted every previous fingerprinted file. The Worker allows
only files named by its own `config/assets.json`, while PHP emits HTML from its
container's manifest. A page received before a deployment can therefore request
a stylesheet the current Worker no longer serves. Delayed editor imports have
the same problem for their module and CSS/font dependencies.

The live previous-release `app-style-CF_djGmG.css` returned 404 while the current
`app-style-C45PPeLE.css` and unchanged Imladris stylesheet returned 200. Replacing
the main stylesheet reference with that previous URL in a WebKit page reproduced
the reported iPhone symptoms: an exposed skip link, duplicate navigation
controls, and a static board rail above the content. The actual phone's network
trace was unavailable; this is a verified matching failure mechanism.

## Decision

- Pin the three most recent deployed asset versions in
  `config/assets.json.deployedReleases`. Ordinary builds preserve that baseline
  while replacing the current candidate. The delivery set in `releases`
  includes the candidate and all three pins, sharing identical versions/files.
  Each release records its complete fingerprinted file set, including lazy
  editor chunks and fonts.
- Serve original bytes under their original immutable URLs. Verify every
  retained SHA-256 before writing build outputs; reject missing, changed, or
  non-delivery paths. Unknown/private requests retain the Worker's 404 behavior.
- Stage both complete asset directories and the manifest before replacing any
  published output. Keep directory backups until the manifest is installed
  last; restore both directories on installation failure. If rollback itself
  fails, report and preserve the backup locations for recovery. Retained files
  are build inputs and must survive a failed write.
  Docker overlayfs directory-rename failures use a complete backup copy before
  removing the old directory, preserving the same rollback contract.
- Derive the release version from the current build alone. Local edits,
  previews, source reverts, checks, and failed deployments do not advance the
  deployed baseline. Superseded local candidates are removed.
- After verifying a successful deployment, run
  `npm run assets:record-release -- <deployed-version>` in its source checkout
  and commit the resulting manifest and delivery files before the next release.
  This explicit step advances the baseline, keeping that deployment and its
  two predecessors. The supplied version must match both the current sources
  and the previously built manifest. The command does not contact production;
  the operator supplies the version verified against live asset hashes. Never
  record a preview or failed deployment. Repeating the same record is idempotent.
- Publish the retained closure to both `public/assets/dist` and `.build/static`
  and include it in the shared manifest. Docker copies that manifest into its
  assets stage before rebuilding. Builds require no Git checkout or network
  archive to recover past files.
- Seed the first retained window with exact compiled files from `e4bc0acd`,
  `f2f616de`, and `7fcc4b9b`. Existing CSS, templates, and current entrypoint URLs
  retain their design contract in PRODUCT_DESIGN §5.2 and ADR 0032.
  Before integration, profile follow-up `0f7b7486` deployed successfully and
  all 50 of its live asset hashes were verified. Recording that release advances
  the final baseline to `0f7b7486`, `e4bc0acd`, and `f2f616de`, with 52 files.
- When upgrading an older manifest, conservatively pin its existing retained
  window once (or its single release if it predates retention).

## Bounds and verification

The current candidate plus three deployed versions bound storage to at most
four distinct file sets. Once the candidate's deployment is recorded, the set
returns to three. Keeping all pins during development also permits a source
revert without recovering a discarded older release. Only recording a fourth
distinct deployment retires the oldest pin; its exclusive files leave both
output directories and the allowlist. This is not indefinite support for old
open tabs. Rolling back to a bundle that lacks the running container's newer
assets still requires deployment coordination.

The build lifecycle test runs real successive Vite builds and checks retention,
exact bytes, lazy dependencies, deployment-based expiry, repeated local builds,
source reverts, version-checked recording, legacy-manifest upgrade, corruption
refusal, private-path refusal, and recoverable write/installation failures.
The Workers runtime test
serves retained dependencies through its real asset binding. Browser evidence
loads real signed-in pages with current and previous asset references in both
Chromium and WebKit, on desktop and mobile, with and without JavaScript.
