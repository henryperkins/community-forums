# Hosted reaction-test follow-up for b42710f7

The final hosted run for `b42710f72b318611d99c11d23256a2a7715cb07e`
finished with **three failing reaction tests**. Its dedicated thread suites passed
34 cases with 14 expected skips. The three production-image upload projects each
passed all 48 cases, including the held-pointer DM image test: **144 passed, no
upload failures or skips**. No further DM change is needed in this follow-up.

The [parent receipt](original-parent-verification.json) remains a failure. Parent
`d8ceee7e-80bf-4f32-89b8-6ad5858c5ada` finished at 20:03:49 UTC; child
`4d8e31e8-7571-473a-91bb-557b7d5c3360` finished at 20:03:36 UTC on October 22.
Both pin control `03690ff92e26b5c3413c2d4d799b402599179676`. The parent verified
metadata, rejected the failed provider steps, and did not accept or hash-validate
the child archive. The [child manifest](original-child-result.json) is retained as
branch-reported data; no archive was downloaded or executed for this follow-up.

## Cause and correction

The two desktop reaction tests try to click the hidden toolbar at 769px. The
approved compact shell uses **More through 860px**, including mouse input. Above
860px, the fine-pointer toolbar owns the picker. The mobile test expects hidden
reaction names and at most three icon rows; the accepted design uses nine named
44px rows. These stale assumptions conflict with [DESIGN.md](../../../../DESIGN.md)
and [ADR 0050](../../../adr/0050-thread-mobile-recovery-and-reading.md).
The [original failures](original-capture-failures.txt) reproduced locally before
any edit; see [baseline](reaction-before.txt).

Only [mobile-controls.spec.ts](../../../../tests/browser/mobile-controls.spec.ts)
changes. It selects the visible responsive trigger, adds the 860/861 boundary,
and requires visible names, nine distinct rows/icons, 44px targets and horizontal
containment. Both themes, JavaScript modes, the real native POST 303, pressed
state, accessible names and fixture restoration remain covered. A route-only
[label-clipping diagnostic](labels-negative.txt) fails the new width assertion
as intended; it is not an application edit or a passing product result.

An extra WebKit run exposed a screenshot-helper hang. The retained
[60-second diagnostic](webkit-helper-diagnostic.txt) shows 17 CSS transitions
already finished and non-pending beyond their 140ms duration while the old helper
awaited their unsettled promises. The helper now skips finished effects and still
waits for unfinished finite effects. No timeout increase, animation cancellation,
or diagnostic sleep is part of the source change.

Application PHP, JavaScript, CSS, templates, dependencies and asset URLs are
unchanged. The compact byline and wider mobile prose remain intact.

## Completed checks

| Check | Result | Evidence |
| --- | --- | --- |
| Original hosted dedicated thread suites | 34 passed / 14 expected skips | [Provider summaries](original-test-summaries.json) |
| Original hosted main capture group | 207 passed / 42 skipped / 3 failed | [Failures](original-capture-failures.txt) |
| Original hosted notifications/settings groups | 248 passed / 18 skipped / no failures | [Provider summaries](original-test-summaries.json) |
| Original hosted production-image uploads | 48 desktop + 48 mobile + 48 WebKit passed | [DM cases and footers](original-upload-checks.json) |
| Final local Chromium controls + conversation reactions | 53 passed / 5 expected skips | [Log](chromium-final.txt) |
| Final local WebKit controls | 20 passed / 4 expected skips | [Log](webkit-final.txt) |
| Generated assets | Current | [Build check](assets-check.txt) |
| Asset/Worker tests | 35 passed | [Log](assets-tests.txt) |
| Fresh independent review, including helper follow-up | No findings | [Review](review.md) |
| Primary checkout preservation | 76 protected paths unchanged | [Receipt](preservation.json) |

The two final local runs use fresh task-only databases, forward migrations and
seed data, with separately supervised PHP servers. Direct mouse/touchscreen
activation checks at 820px, native 861px and touch 390px confirm trigger hit tests
and nine labelled rows; [geometry](direct-pointer-geometry.json) preserves the
setup and measurements. The native 861px probe explicitly scrolls its initially
offscreen post into view before activation. Initial unsuccessful diagnostic/setup
attempts remain in the isolated worktree output and are not product failures or
passing evidence.

Selected final captures: [Chromium 860px](chromium-compact-860.png),
[861px](chromium-toolbar-861.png), [390px](chromium-mobile-390.png),
[WebKit 860px](webkit-compact-860.png), [861px](webkit-toolbar-861.png),
[390px](webkit-mobile-390.png). The [toolchain](browser-toolchain.json) and
[source identity](runtime-identity.json) record the reused worktree's actual
state. Two earlier local presentation-builder checksum metadata files differ
from b427 and are unused by the HTTP renderer; they were preserved and were not
integrated. The other 786 inspected source, asset, migration, dependency and
harness paths match b427 after checkout line-ending normalization.

These checks use Windows browser proxies, synthetic users, disposable MariaDB,
and array mail. They do not establish physical touch, real software keyboards,
production storage/provider behavior, a fresh PHPUnit result, or a full hosted
pass for the corrected tests. The original capture command stopped its remaining
chained groups after its three failures; the independent settings and uploads
still completed. The existing full-PHP-suite limitations remain in the original
thread audit evidence. No original finding or device result is reclassified.
Owned local PHP servers, the labelled database container and its anonymous volume
were removed after verification. Publication and its exact automatic deployments
are verified separately after the scoped commit; no production data is written.

SHA256SUMS records the canonical Git blob bytes of these evidence files.
