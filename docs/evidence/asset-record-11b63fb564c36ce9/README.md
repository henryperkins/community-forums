# Recorded live release `11b63fb564c36ce9`

**Date:** 2026-11-08 (UTC) · **Base:** `3a0f2c28` (`main`, clean worktree) ·
**Node:** 22.22.1

`forum.candidary.online` serves asset release `11b63fb564c36ce9`, built by
`c90c9299` and published with the merge of PR #95. It was the committed build
candidate but was not pinned in `config/assets.json` `deployedReleases`
(`0ab71f5ca65e7fa0`, `5dc1b40f3b23d46d`, `c66e3b55dba6da68`). The builder drops
an unpinned candidate as soon as a build produces a different version, so the
next asset-changing release (Fluent System Icons, ADR 0060) would have removed
`forum-theme-style-Ba2Ycb6L.css`, which every live page references.

## Live check (`live-check.json`, 23:21:47Z)

GET-only, with
[`asset-retention-check-2026-11-08/live-check.py`](../asset-retention-check-2026-11-08/live-check.py)
against the manifest at `3a0f2c28`:

- `/` returned 200. Its HTML and its `Link: rel=preload` header reference only
  `app-9b906acd1f6f64c8.js`, `app-style-diYLPve0.css`,
  `forum-base-style-DsiQn3Ye.css` and `forum-theme-style-Ba2Ycb6L.css`, all in
  the release.
- All 11 release files and all 23 other retained immutable files returned 200
  with their manifest SHA-256.
- An unlisted path returned 404 `no-store`, and `/healthz/` returned
  `{"status":"ok","database":"ok"}`.

Not established: which Cloud Run revision or commit rendered the HTML.

## Record

1. `npm run check:assets` reported the committed outputs current
   (`asset-check-before-record.log`).
2. `npm run assets:record-release -- 11b63fb564c36ce9` recorded the release
   (`asset-record-release-11b63fb564c36ce9.log`).
3. `npm run check:assets` passed again (`asset-check-after-record.log`).

`deployedReleases` is now `11b63fb564c36ce9`, `0ab71f5ca65e7fa0` and
`5dc1b40f3b23d46d`. Recording retires `c66e3b55dba6da68` and its 18 exclusive
files: `app-style-CFVEFxJL.css`, `composer-6daea7e3dfcc4d41.js`,
`imladris-style-DmYWEV0N.css` and 15 retired Imladris web fonts. They leave the
Worker allowlist and `public/assets/dist`. No allowlist entry is added, and the
current version and URLs are unchanged.
