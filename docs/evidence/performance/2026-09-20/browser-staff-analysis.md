# Authenticated staff browser evidence — 2026-09-20

> Historical evidence from 2026-09-20, organized on 2026-09-23. Production state, timings, build identifiers and source line references describe that snapshot; they were not remeasured during cleanup. See the [provenance and archive inventory](../../workspace-cleanup/2026-09-23/README.md).

The user supplied login credentials after the initial diagnosis. The main agent authenticated and provided an already established session to this browser track. The browser never submitted a login form, clicked a control, changed a draft, reset read state, or made a production configuration change. This is **staff-account evidence**, with an Administration navigation link rendered on every captured page; it must not be relabeled as an ordinary member sample.

The [initial-state check](production-staff-initial-state.json) used board listings before these browser captures and found every existing thread already read. Both measured image-thread visits had zero `[data-first-unread-boundary]` and zero `[data-first-unread="1"]` markers. The first browser visit is therefore labeled “first observed, already read.” A genuine first-unread browser journey remains unmeasured in both production and the original local browser run. The original local fixture had no saved cursor; its raw `cold-browser-first-unread` label was inaccurate and should be read as first render/no saved cursor. The old raw files are preserved. `src/Repository/PostRepository.php:237-241` explicitly states that a missing cursor yields no unread target. The original browser harness did not record boundary flags. The separate server-side older-cursor follow-up supplies kernel evidence, not browser evidence, for that path. `src/Controller/ThreadController.php:112-152` computes the boundary before the normal read-state update, and `templates/partials/post.php:24-28` emits the measured markers. No thread read state was reset to manufacture a sample.

The new [browser-member-measure.cjs](browser-member-measure.cjs) preserves the earlier harness and adds privacy filtering, authentication/role checks, unread-boundary geometry, and guards against non-GET/HEAD fetch, XHR, beacon, and form submissions. [Offline guard validation](browser-staff-guard-validation.json) exercised seven write attempts, observed no HTTP requests, and retained GET support. The guard does not use Playwright routing or disable cache. No DOM text, page titles, account names, response bodies, cookie/header credentials, or screenshots are saved; topic slugs and account paths are redacted. `CF-Ray` is retained for Worker-log correlation. Session-file lifecycle remains owned by the main agent.

The profile matches the earlier mobile capture: Chromium, 390 × 844 CSS pixels, DPR 2, mobile touch/UA, fourfold CPU throttling, CDP network settings of 150 ms latency, 1.6 Mbps down, and 750 Kbps up. Home and image thread each start in a separate fresh browser context, then repeat within that context. Page starts are separated by at least 30 seconds. The usual observation window extends 15 seconds after `load`; the final natural-poll window extends 65 seconds. “Cold” denotes browser cache only, not a sleeping container. Two route samples cannot establish a real-user p75.

## Page measurements

| Staff page and browser-cache state | Final HTML headers | First 103 | FCP | LCP | CLS |
| --- | ---: | ---: | ---: | ---: | ---: |
| Home, cold | 1,361.6 ms | 34.0 ms | 1,704 ms | 1,736 ms | 0.000235 |
| Home, warm | 1,214.7 ms | 8.2 ms | 1,300 ms | 1,300 ms | 0 |
| Image thread, cold, first observed already read | 2,934.9 ms | 37.6 ms | 3,304 ms | **15,744 ms** | 0.132213 |
| Image thread, warm, already read | 2,362.7 ms | 6.3 ms | 2,468 ms | **2,524 ms** | **1.036996** |

Raw timing, geometry, resource records, cache headers, auth flags, and write-guard results are in [browser-production-staff-home.json](../../workspace-cleanup/2026-09-23/README.md#archived-scratch) (`browser-production-staff-home.json`, archived) and [browser-production-staff-thread.json](../../workspace-cleanup/2026-09-23/README.md#archived-scratch) (`browser-production-staff-thread.json`, archived). Every main response is HTTP 200 and verifies the signed-in body marker, logout form, account-settings link, and Administration navigation. Both normal poll widgets are present. No blocked write attempts or observed non-GET/HEAD network requests occur in these four page samples.

Final HTML header time uses `navigation.finalResponseHeadersStart`; using the first 103 would understate HTML latency by more than a second. Warm home spends `1,214.7 / 1,300 = 93.44%` of LCP waiting for final HTML headers. Warm thread spends `2,362.7 / 2,524 = 93.61%` there. Their remaining client intervals are 85.3 and 161.3 ms respectively. Thus the warm thread still narrowly exceeds the 2.5-second LCP target even with the large image already cached.

## Cold staff thread attribution

The LCP element is `/media/1`: the same 2,166,961-byte PNG, 1698 × 926 intrinsically, displayed at 278.97 × 152.14 CSS pixels. The image resource begins at 3,285.2 ms, receives response headers at 4,443.3 ms, and finishes at 15,726.8 ms. The LCP paint follows at 15,744.0 ms.

| Critical-path interval | Arithmetic | Duration | LCP share |
| --- | --- | ---: | ---: |
| Final HTML header wait | 2,934.9 − 0 | 2,934.9 ms | 18.64% |
| Image discovery/scheduling | 3,285.2 − 2,934.9 | 350.3 ms | 2.22% |
| Image response wait | 4,443.3 − 3,285.2 | 1,158.1 ms | 7.36% |
| Image transfer | 15,726.8 − 4,443.3 | **11,283.5 ms** | **71.67%** |
| Image completion to paint | 15,744.0 − 15,726.8 | 17.2 ms | 0.11% |

Image response wait plus transfer account for 79.03% of LCP. Adding final HTML wait brings the measured accounting to 97.67%. The roughly 449 ms difference between image transfer and its isolated byte budget (`11,283.5 − 2,166,961 / 200 = 448.7 ms`) occurs while other resources share the emulated connection; it is not assigned entirely to one competing file.

The rich editor is actually present here: the staff DOM contains three composer inputs/editors and one compact reply dock. Its 817-byte encoded entry is requested at 3,363.2 ms; the 143,620-byte encoded adapter is requested at 3,567.5 ms and finishes at 5,271.9 ms. The page subsequently GETs both summary/reply draft endpoints and Giphy configuration. The Giphy endpoint returns 404; both draft GETs return 200. No response bodies or draft content are saved. These staff controls and three editors differ from the earlier single-composer ordinary-member fixture.

The media, editor entry, adapter, stylesheets, scripts, and fonts report browser cache delivery on the repeat thread visit. The repeat image transfers zero bytes. Dynamic HTML and polls remain network requests. Hashed static responses retain immutable one-year caching and `X-RetroBoards-Cache: STATIC`; compressed HTML/CSS/JavaScript use zstd. The HTML receives Early Hints for the four core preloads. As in the earlier report, resources populated through those hints can subsequently appear as cache reuse in the document timing record, so `resourceTransferBytes` is not a complete count of the initial wire transfer. The encoded resource-body sum is 222,476 bytes for home and 2,580,057 for the staff thread, including cached bodies on repeat visits.

## Layout stability

The warm thread's CLS 1.036996 consists of two closely spaced shifts: 0.949994 when the deferred app script applies enhanced mobile geometry, then 0.087002 when the composer dock collapses after hydration. At 2,497.7 ms MAIN moves from y=481.20 to y=62 and the dock enters its fixed position; at 2,570.4 ms the dock height changes from 207.75 to 67 pixels. The source paths are `public/assets/app.js:8`, `public/assets/app.css:1689-1706`, `public/assets/composer.js:2670`, and `public/assets/app.css:1825-1854`.

The cold thread records the same raw rail/dock-position shift, but Chromium flags it `hadRecentInput: true`, so the standard CLS calculation excludes that entry. No clicks or taps were sent by this harness; the cause of that browser classification remains unverified. Raw entries are retained. The included cold CLS window totals 0.132213: tiny font changes (~0.000582), a **0.044629 image-geometry shift**, and the 0.087002 composer collapse. At 4,471.3 ms, shortly after PNG headers arrive, the thread-memory slot moves downward exactly 152.14 pixels, matching the image's rendered height. This authenticated page therefore provides evidence for reserving intrinsic image dimensions in addition to fixing the separate late enhancement shifts.

## Implications for the recommendations

The [earlier offline WebP benchmark](browser-image-benchmark.json) applies to this URL with matching dimensions and byte count: 640 × 349 pixels at quality 82 produces 37,432 bytes. At 1.6 Mbps = 200 bytes/ms, transfer saving remains `(2,166,961 − 37,432) / 200 = 10,647.645 ms`. Applied as a transport-only estimate to this staff trace, that gives `15,744 − 10,647.645 = 5,096.355 ms`, with HTML/image origin waits and other work unchanged. It is not an end-to-end variant replay. Image discovery has a separate maximum ceiling of `3,285.2 − 2,934.9 = 350.3 ms`, which includes unavoidable HTML transfer/parsing and must not be double-counted with overlapping stylesheet work.

These authenticated measurements strengthen both priorities: responsive image delivery for cold loads and fewer/cheaper server round trips for warm loads. Optimizing only image bytes cannot meet the staff cold LCP target while HTML alone takes 2.9 seconds and the image response adds another 1.16 seconds. Reserving image geometry could address the observed 0.044629 cold shift; the larger warm rail/dock shifts need their own progressive-enhancement fix, with no established LCP saving. The server/controller ownership and implementation risks remain as documented in [browser-analysis.md](browser-analysis.md); this track changed no application behavior.

## Observed authenticated polls

The final [65-second natural poll window](../../workspace-cleanup/2026-09-23/README.md#archived-scratch) (`browser-production-staff-polls.json`, archived) starts a fresh browser context on home at 13:13:23 UTC and leaves the page untouched. Its HTML TTFB is 1,423.3 ms, FCP/LCP 1,804 ms, and CLS 0.000235. Both immediate polls start before first paint. Each repeats naturally without any click, forced fetch, visibility toggle, or timer override.

| Page observation | Bell duration | Presence duration |
| --- | ---: | ---: |
| Home, cold browser | 746.8 ms | 565.6 ms |
| Home, warm browser | 723.5 ms | 467.9 ms |
| Image thread, cold browser | 764.8 ms | 654.0 ms |
| Image thread, warm browser | 846.4 ms | 599.2 ms |
| Natural 65-second window, initial pair | 724.8 ms | 565.3 ms |
| Natural 65-second window, repeated pair | 856.9 ms | 811.0 ms |

There are six browser observations per endpoint. Bell's observed median is 755.8 ms (range 723.5–856.9); presence's is 582.4 ms (range 467.9–811.0). Every observed duration exceeds the 300 ms target under this mobile lab profile. These are naturally overlapping browser requests rather than serialized server-latency measurements; the main agent's subsequent paced HTTP probe is the appropriate companion evidence.

In the 65-second window, bell starts at 1,644.9 ms, ends at 2,369.7 ms, and starts again at 62,375.5 ms: `62,375.5 − 2,369.7 = 60,005.8 ms` after response completion. Presence starts at 1,645.9 ms, ends at 2,211.2 ms, and starts again at 62,218.3 ms: `62,218.3 − 2,211.2 = 60,007.1 ms` later. The scheduling matches `public/assets/app.js:76-110`: immediate startup followed by a 60-second delay after success. The later pair takes longer; heartbeat/session-write effects and backend concurrency cannot be separately inferred from this browser trace alone. No latency gain is assigned to suppressing or delaying these polls without a controlled intervention.

## Verification and limits

All five browser navigations returned HTTP 200 and confirmed signed-in controls and Administration navigation. Every recorded network request used GET; the write guard recorded **zero blocked attempts**, and the browser observed **zero non-GET/HEAD requests**. The final browser process closed before the main agent resumed authenticated serial probes. The session cookie was consumed only from the private file and was neither printed nor saved in evidence. Main-agent cleanup owns that file and the production tail session.

The new guard and measurement script pass `node --check`; offline write prevention passed its seven-attempt check. [browser-staff-validation.json](browser-staff-validation.json) records JSON/auth/request/privacy checks. No implementation files or previous browser evidence files were edited by this follow-up. Only new `browser-member-measure.cjs`, `browser-production-staff-*`, `browser-staff-*` evidence files were written.

This does not establish ordinary-member production latency, a genuine first-unread browser path, stable p75 Core Web Vitals, a sleeping-container cold start, or an end-to-end optimized image result. A role-specific staff view, existing account state, and three hydrated editors materially affect these results. The large initial raw shift's unexpected `hadRecentInput` classification is retained and explicitly unresolved. Concurrent Worker logs require matching both ray and request path; this browser analysis makes no container-duration assignments from ray alone.
