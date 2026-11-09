# Recorded live release `23ca787930a7a0a7`

**Date:** 2026-11-09 (UTC) · **Base:** `11b07629` (`main`, clean worktree) ·
**Node:** 22.22.1

`forum.candidary.online` serves asset release `23ca787930a7a0a7`, built and
published by `11b07629` (Fluent System Icons, ADR 0060). It was the committed
build candidate but was not pinned in `config/assets.json` `deployedReleases`
(`11b63fb564c36ce9`, `0ab71f5ca65e7fa0`, `5dc1b40f3b23d46d`). The builder drops
an unpinned candidate as soon as a build produces a different version, so the
next asset-changing release would have removed `forum-theme-style-DDFaGbhp.css`
and `forum-base-style-CwCGWLhh.css`, which every live page references.

## Live check (`live-check.json`, 02:33:42Z)

GET-only, with
[`asset-retention-check-2026-11-08/live-check.py`](../asset-retention-check-2026-11-08/live-check.py)
against the manifest at `11b07629`:

- `/` returned 200. Its HTML and its `Link: rel=preload` header reference only
  `app-9b906acd1f6f64c8.js`, `app-style-jg6AulwD.css`,
  `forum-base-style-CwCGWLhh.css` and `forum-theme-style-DDFaGbhp.css`, all in
  the release.
- All 11 release files and all 8 other retained immutable files returned 200
  with their manifest SHA-256.
- An unlisted path returned 404 `no-store`, and `/healthz/` returned
  `{"status":"ok","database":"ok"}`.

Not established: which Cloud Run revision rendered the HTML.

## Record

1. `npm run check:assets` reported the committed outputs current
   (`asset-check-before-record.log`).
2. `npm run assets:record-release -- 23ca787930a7a0a7` recorded the release
   (`asset-record-release-23ca787930a7a0a7.log`).
3. `npm run check:assets` passed again (`asset-check-after-record.log`).

`deployedReleases` is now `23ca787930a7a0a7`, `11b63fb564c36ce9` and
`0ab71f5ca65e7fa0`. Recording retires `5dc1b40f3b23d46d` and its 4 exclusive
files: `app-107ef98522c229d0.js`, `app-style-QOPBXP9w.css`,
`forum-base-style-CKKxU1F6.css` and `forum-theme-style-C35sAGgP.css`. They
leave the Worker allowlist and `public/assets/dist`. No allowlist entry is
added, and the current version and URLs are unchanged.
