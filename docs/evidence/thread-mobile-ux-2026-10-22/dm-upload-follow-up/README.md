# DM upload regression follow-up, 2026-10-22

The completed hosted run for `4062e8c1` failed two browser cases: the stale thread
toolbar positioning assertion, corrected in `a72d2331`, and the DM held-pointer
test's setup assertion. The user explicitly included the DM test in this follow-up.
The [original parent receipt](original-hosted-verification.json) remains a failure.

The production-image fixture reproduces the DM failure before its actual pointer
and publication checks: the form bottom and viewport are both 844px. That valid
layout no longer creates the overflow needed to exercise deferred dock scrolling.
The [test](../../../../tests/browser/upload-http.spec.ts) now uses a 390x640 viewport
for all three projects and aligns Send near the viewport bottom, with a 2px inset
for fractional mobile pixels. The form must still extend below the fold. No
application source, styles, templates, asset URLs, or dependencies changed.

| Check | Result | Evidence |
| --- | --- | --- |
| Original DM test against the actual production Dockerfile image | 1 failed at setup, as hosted | [Before](dm-before.txt) |
| Updated held-pointer case, three projects | 3 passed; form bottom 726.7-727.1px at height 640px; held Y movement under 0.41px | [Geometry](dm-geometry-final.txt), [mobile](dm-mobile-before.png), [WebKit](dm-webkit-mobile-before.png) |
| Negative diagnostic, route-only removal of the production DM pointer guard | Expected failure at 86.8px movement | [Mutation](dm-mutation.txt) |
| Desktop Chromium upload suite | 48 passed, no skips or failures | [Desktop](dm-desktop-full.txt) |
| Mobile Chromium upload suite | 48 passed, no skips or failures | [Mobile](dm-mobile-full.txt) |
| WebKit mobile upload suite | 48 passed, no skips or failures | [WebKit](dm-webkit-mobile-full.txt) |
| Hosted thread suites at `a72d2331` | 22 desktop + 12 mobile passed, 14 expected skips | [Provider log excerpt](a72-hosted-thread-checks.json) |
| Independent test review | Ready; no findings | [Review](review.md) |

Each complete upload project starts with new volumes in the explicitly labelled
task-only Compose project, using a clean Git archive of `a72d2331` and the production
Dockerfile. Only the browser test differs. The suites include 39 composer cases
with transport stubs and 9 real Apache/PHP HTTP cases per project. The HTTP cases
verify parser limits, actual uploads/publication and parent binding, container
restart persistence, and current reader authorization. Runtime PHP 8.2.35 limits
are 8 MiB per file / 10 MiB per request / 5 MiB application maximum; see
[limits](runtime-limits.json) and [toolchain](toolchain.json).

The overflow, visible-button, under-1px movement, native POST 303, rendered image,
and finalized attachment/body assertions all remain. The mutation changes only
the response to the local diagnostic browser; it is not an application edit or a
passing production-image result. Intermediate unsuccessful setup experiments
are retained in the isolated worktree's `output/thread-hosted-rerun/` directory.

These are Windows browser proxy checks with synthetic data, a disposable MariaDB,
and Docker-volume media. The held sequence uses the existing mouse down/up API
even in the mobile projects. They do not establish physical touch, real software
keyboards, production R2, a fresh PHPUnit result, or full hosted matrix acceptance.
The `a72d2331` full hosted parent was still active at this snapshot; its passing
thread suites do not upgrade that parent to a pass. Deployment and later hosted
receipts are checked separately after publication. All 76 pre-existing dirty or
untracked paths in the primary checkout remain byte-for-byte or link-for-link
unchanged. No original audit finding or device evidence is reclassified here.
