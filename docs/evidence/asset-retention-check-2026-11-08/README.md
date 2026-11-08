# Asset-retention check for the live release `0ab71f5ca65e7fa0`

**Date:** 2026-11-08 (UTC) · **Revision:** `eeceed780aa9ec92e2a85e9554020a6c6d85d503` (clean tree)
**Status:** sections 1–3 are the review, made without writing anything. Section 4
records the release on branch `assets/record-release-0ab71f5ca65e7fa0`. Merging
that branch to `main` deploys both the Worker and Cloud Run; nothing here was merged
or deployed.

## Finding

The asset release that `forum.candidary.online` serves (`0ab71f5ca65e7fa0`) is the
committed build candidate but is not pinned in `config/assets.json`
`deployedReleases` (`5dc1b40f3b23d46d`, `c66e3b55dba6da68`, `3630440af0d45598`).
The current builder drops an unpinned candidate as soon as a build produces a
different version, so the next asset-changing build would remove whichever of this
release's four exclusive files it changes.

## 1. What the public responses establish (`results/live-check.json`, 04:27:51Z)

- `/` returned 200; its HTML and its `Link: rel=preload` header reference only
  `app-9b906acd1f6f64c8.js`, `app-style-diYLPve0.css`, `forum-base-style-DsiQn3Ye.css`
  and `forum-theme-style-CNIV-nj8.css`, all four in release `0ab71f5ca65e7fa0`.
- All 11 files of that release returned 200 with the manifest SHA-256 and
  `X-RetroBoards-Cache: STATIC` plus `immutable` caching, the headers
  `worker/assets.mjs` sets, so the Worker serving the site allowlists them.
- All 26 other immutable allowlisted files (the three pinned releases) also matched.
- An unlisted path returned 404 `no-store` (allowlist enforcement, `worker/assets.mjs:13-14`).
- `/healthz/` returned `{"status":"ok","database":"ok"}`.

Not established: which Cloud Run revision or commit rendered the HTML, the Worker
version id, cron-job image parity, or migration state. None of these is needed to
show which asset release is live.

## 2. What the current build would remove (`repro.sh`, `results/`)

Mechanism: `bin/build-assets.mjs:173-191` keeps `deployedReleases` plus the new
candidate only; `publishAssets` replaces `public/assets/dist` and `.build/static`
wholesale, and `files` (the Worker allowlist) is recomputed from that set (`:209`).

Of the release's 11 files, 7 are shared with pinned releases and survive any
build. Four are exclusive: the three page stylesheets and the main `app` script.
Scratch-copy runs at this revision (Node 22.22.1, Vite 8.3.5; step 0 `--check`
passed, so the committed outputs reproduce here):

| Scenario | New version | Release `0ab71f5ca65e7fa0` files removed |
|---|---|---|
| 1a: one CSS source edited, nothing recorded | `a11b19271dee84b4` | `app-style-diYLPve0.css` |
| 1b: all four entry sources edited, nothing recorded | `d3ee56066cd40e83` | all four exclusive files |
| 2: `--record-release 0ab71f5ca65e7fa0`, then the 1b edit | `d3ee56066cd40e83` | none (all 11 retained) |

Recording also retires the oldest pin, `3630440af0d45598`, whose four exclusive
files leave the allowlist (`results/3-retired-by-record.json`). This is ADR 0041's
designed three-release window.

A build with no change to the asset sources reproduces `0ab71f5ca65e7fa0` and
changes nothing. Any new version replaces the unrecorded candidate, after which
`--record-release 0ab71f5ca65e7fa0` is impossible: it requires the current
sources and the committed manifest to both be that version (`bin/build-assets.mjs:183-185`).

## 3. What deployment would make unavailable, and which requests fail

A Worker deployed from such a build answers 404 `no-store` for the removed files.
Workers Builds and Cloud Build deploy independently
(`docs/runbooks/deployment-cloud-run.md:12-17`). Failing requests:

- **The real exposure:** if the new Worker is live while the previous Cloud Run
  revision still renders pages, every such page references the removed files.
  Visitors without cached copies get pages missing those stylesheets or the main
  script until the new revision serves.
- **Lower risk:** pages already open loaded the four exclusive files at page load.
  The lazily loaded editor files of this release are all shared with pins.
- **Not affected:** browsers holding cached copies (one-year `immutable`) do not
  re-request them.
- **Not a retention problem:** the reverse skew (new HTML before the new Worker).

## 4. Record (`record/`)

On `main` at `eeceed78` (equal to `origin/main` at 06:20Z):

1. The live check was repeated at 06:20:44Z (`record/live-verify-0ab71f5ca65e7fa0.json`)
   with the same result as section 1: 11/11 release files and 26/26 retained files
   byte-identical, the home page and its preload header referencing only this
   release's entry files, an unlisted path answering 404, and health ok.
2. `npm run check:assets` reported the committed outputs current
   (`record/asset-record-check-0ab71f5ca65e7fa0.log`).
3. `npm run assets:record-release -- 0ab71f5ca65e7fa0` recorded the release
   (`record/asset-record-release-0ab71f5ca65e7fa0.log`). `check:assets` passed
   again afterwards (`record/asset-check-after-record.log`), and `npm run
   test:assets` passed (35/35).

The complete generated diff is `config/assets.json` plus four deletions under
`public/assets/dist/`:

| Manifest field | Before | After |
|---|---|---|
| `version`, `urls` | `0ab71f5ca65e7fa0` | unchanged |
| `deployedReleases` | `5dc1b40f…`, `c66e3b55…`, `3630440a…` | `0ab71f5c…`, `5dc1b40f…`, `c66e3b55…` |
| `releases` | those four | those three |
| `files` (the Worker allowlist) | 45 entries | 41: the four below removed, nothing added or changed |

**Eviction:** the four files exclusive to `3630440af0d45598` leave the allowlist
and `public/assets/dist`. They are `app-e5669eff1f842115.js`,
`app-style-D7aglA01.css`, `composer-c1ff478070e3a1d8.js` and
`tour-f068bdb2d74e6d21.js`. After this merges, the Worker answers 404 for them.
Three releases have gone live since `3630440a`: `c66e3b55` and `5dc1b40f`, each
recorded as deployed, and now `0ab71f5c`. Only a page left open since
`3630440a` was live, or a stale cached copy of its HTML, still references them. All 11 files of each retained release are allowlisted and present.

## 5. What the gate covers

The release version is derived from build output, not from a list of source paths.
It is the first 16 hex digits of the SHA-256 over the published file descriptions
(`bin/build-assets.mjs:166-173`): the eight raw sources under `public/assets/`,
and every Vite and minifier output byte under `dist/`. Any merge whose build
produces a version other than the committed one replaces the unrecorded candidate.
That includes edits to asset sources, and also changes to `src/client/`, fonts,
`vite.config.mjs`, `bin/build-assets.mjs`, the bundler or minifier versions in
`package-lock.json`, and any toolchain difference between Workers Builds and the
Docker asset stage (`node:24-alpine`) that changes output bytes. A merge that
leaves the output unchanged rebuilds the same version and removes nothing.

## 6. Build parity (`build-parity/`)

Production builds the assets twice: the Docker asset stage (`node:24-alpine`)
feeds the Cloud Run image's HTML, and Workers Builds feeds the Worker's allowlist.

- **Docker:** a tree with this commit's asset build inputs (same input digest,
  `build-parity/docker-asset-stage.txt`), exported with `git archive`, was built with
  `docker build --target assets` (Node v24.22.2, Vite 8.3.5). The resulting
  `config/assets.json` is byte-identical to the committed manifest (version
  `0ab71f5ca65e7fa0`, pins `0ab71f5c`, `5dc1b40f`, `c66e3b55`), and all 33 files
  in `public/assets/dist` match (`build-parity/docker-asset-stage.json`,
  `build-parity/docker-build.log`). Locally (Node 22.22.1) `check:assets`
  matches as well.
- **Workers Builds:** a branch push uploads a preview version. The check run
  reports only the build and version IDs, and the Worker redirects every
  non-canonical hostname, so a preview's asset bytes can't be fetched from here.
  The evidence that Workers Builds agrees is the live check: the production
  Worker built from `eeceed78` serves all 37 immutable files of that commit's
  manifest byte for byte. This commit changes no build input other than the
  manifest's retention fields and the retired files themselves. Verify the
  branch's `Workers Builds: retroboards` check succeeded before merging.

## Reproduce

```sh
docs/evidence/asset-retention-check-2026-11-08/repro.sh . <out-dir>     # scratch copies only
python3 -I docs/evidence/asset-retention-check-2026-11-08/live-check.py \
  config/assets.json https://forum.candidary.online 0ab71f5ca65e7fa0     # read-only GETs
```

The first `repro.sh` attempt used `/tmp`, a different filesystem, so the
node_modules hard links failed and the builds did not run. Its output was discarded
and the script now stages beside the checkout. Workers Builds and the Docker
asset stage (Node 24) were not run here.
