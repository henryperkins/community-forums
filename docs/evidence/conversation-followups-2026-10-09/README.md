# Conversation follow-ups — 2026-10-09

Follow-up work on the reviewed conversation-interactions slice
([2026-10-08 record](../conversation-interactions-2026-10-08/README.md)), in three
commits: `dd40ad76` (reactions), `bb831e7f` (mentions) and `15fbcd70`
(performance). This is local implementation and verification; nothing was pushed
or deployed.

## 1. Reaction recovery and accessibility — `dd40ad76`

- **Stalled requests are bounded.** An enhanced toggle aborts after 15 seconds,
  the Inbox preview's bound. Every copy of the chip unlocks, focus stays put and
  the topic reload is offered, because the server may still have committed the
  toggle. The browser regression never answers the POST and uses Playwright's
  clock: the chips are still locked at 14 s and released at 15.5 s, and the next
  real toggle works.
- **Failures are announced.** The thread renders one empty
  `<p class="sr-only" role="status" data-reaction-announcer>` before any failure.
  Refusals and uncertain toggles write into it, clearing it first so an identical
  failure is still a change. The browser regression checks that the region exists
  before the failure, records `[message, '', message]` for two identical failures,
  and confirms the tray holds no late-inserted live region. PHPUnit pins exactly
  one empty region, and none with engagement off. Screen readers were not run;
  this is the structural pattern they rely on.
- **Expired suspensions are named.** Reactor names follow `User::isActive()`: a
  member whose timed suspension has elapsed is named again, while current and
  indefinite suspensions still only count.

## 2. Mention eligibility and visual consistency — `bb831e7f`

- **Bare `@`** omits the viewer and anyone blocked in either direction, since
  mention notifications skip both. Typed queries are unchanged. The existing
  `AppMessagesRefinementTest` contract says only the DM recipient picker filters
  blocks, and filtering typed results would let a member detect who blocked them.
- **Show avatars** now applies to Inbox rows and to the Inbox preview's bylines,
  replies and composer person rows. PHPUnit counts the monograms on both surfaces.
  [`desktop/conversation-inbox-preview-avatarless.png`](desktop/conversation-inbox-preview-avatarless.png)
  shows text-only bylines, and a bare `@` that offers `@alice` and `@carol` but
  not the viewer.
- **Zoom alignment.** The source mirror copied the rounded `clientWidth`. At a
  fractional width it broke lines differently from the textarea:
  [mirror](zoom/before-fix-110pct-555.55px-mirror-glyphs.png) and
  [textarea](zoom/before-fix-110pct-555.55px-textarea-glyphs.png) glyphs at 110%
  and 555.55 px, captured before the fix. The mirror fits one more `i` on the
  first line, and every later highlight shifts. Whole-pixel layouts at 80–125%
  zoom were already aligned. The mirror now takes its size from the textarea's
  observed content box. The regression runs at 90% and 110% across 555.55,
  557.83 and 560.3 px. It requires geometry within 0.02 px and glyph-ink
  agreement above 0.95. Aligned frames score 1.0 (0.981 at worst, from
  textarea/div anti-aliasing at a fractional pixel ratio); the pre-fix drift
  scored 0.79. It passed in Chromium (8 repeats) and WebKit (4 repeats).

## 3. Performance — `15fbcd70`

**Head script.** Chromium cold loads with CDP latency/throughput emulation and
the cache disabled; FCP is the median of 7 samples, in ms. The full output,
including DOMContentLoaded and the instant-script control, is in
[`verification/head-script-fcp.txt`](verification/head-script-fcp.txt).

| Profile | Page | Before | After | Script fetch (before) | CSS done |
|---|---|---|---|---|---|
| 100 ms / 10 Mbps | `/inbox` | 852 | 852 | 124–234 | ~797 |
| 100 ms / 10 Mbps | topic | 868 | 868 | 123–232 | ~796 |
| 150 ms / 100 Mbps | `/inbox` | 440 | 444 | 173–333 | ~385 |
| 150 ms / 100 Mbps | topic | 452 | 456 | 174–330 | ~382 |
| either | `/messages/{id}` | unchanged | unchanged | still loaded | — |

The measured render delay is about zero. The script was parser-blocking on
every page, but the preloaded stylesheets finished later on both profiles, and
an instantly fulfilled script made no difference either. Loading it only at
`/messages/{id}` removes the request elsewhere. That avoids the case where cached
CSS would leave the script on the critical path, without changing first paint.
`app.js` consumes an abandoned signal on other pages.

**Reaction queries.** Probe counts on a fresh test database:

| Path | Before | After |
|---|---|---|
| Native add | 27 | 24 |
| Native remove | 18 | 15 |
| Enhanced add | 22 | 21 |
| Enhanced remove | 16 | 15 |

A native POST no longer reads the JSON summary (counts and names). The
private-board membership re-check reuses the read gate's memoized lookup.
Viewers who cannot write, and so see no names, skip the names read. PHPUnit pins
`native == enhanced` for self toggles, and pins +1 query per reaction for writers
and +0 for suspended viewers.

**Composer work in hidden editors.** Exact call counts (CDP precise coverage)
and style/layout cost (CDP Performance metrics) while typing 76 characters on a
topic with 1 and with 15 hidden edit composers:

| Page | Variant | Style recalc ms | Layouts | Layout ms | Script ms |
|---|---|---|---|---|---|
| 1 hidden | pre-handoff (`8206122d`) | 113–119 | 237 | 21–22 | 16 |
| 1 hidden | handoff (`bb831e7f`) | 156 | 276 | 27–28 | 21 |
| 1 hidden | follow-up | 121 | 238 | 23 | 21–22 |
| 15 hidden | pre-handoff (`8206122d`) | 245–270 | 236 | 45–48 | 19–21 |
| 15 hidden | handoff (`bb831e7f`) | 399–406 | 290 | 69–71 | 28–29 |
| 15 hidden | follow-up | 265–277 | 238 | 48 | 23–25 |

Hidden editors themselves were cheap: about 0.05 ms of reads per editor per
resize, with no extra layouts or style recalcs. The cost came from the visible
composer's mirror copying the draft into itself on every keystroke, which re-ran
style matching across the page. The mirror now stays empty while it has nothing
to highlight and skips unchanged style writes. A `max-width: 100%` guard keeps it
inside its wrap during the frame a resize takes to reach its exact width; the
Messages phone check had caught a 624 px mirror at 390 px.

## Verification on the final tree (`15fbcd70`)

- Full PHPUnit on four private-database shards: **3,200 tests, 25,332
  assertions, one skip** ([`verification/phpunit.txt`](verification/phpunit.txt)).
- Chromium, one fresh `prepare.sh` per group
  ([`verification/chromium.txt`](verification/chromium.txt)):
  - conversation specs: **82 passed**, 4 skips
  - composer shell/expansion/WYSIWYG: **93 passed**, 33 skips
  - Messages polling: **13 passed**, 13 skips
  - Messages refinement: 4 passed, 5 skips, and `:168` failing as before (below)
- WebKit conversation specs: **82 passed**, 4 skips
  ([`verification/webkit.txt`](verification/webkit.txt)).
- `composer verify:imladris` (24 tests, 305 assertions), `composer check:imladris`,
  `npm run check:assets` and the worker/asset tests (24) all pass.
- **Negative controls.** Each new regression failed against the code it guards:
  - announcer and timeout: 4 browser failures before the change
  - suspension names: PHPUnit failure
  - bare `@`: PHPUnit failure
  - Inbox avatars: 2 → 1 monogram count and 5 preview monograms
  - zoom: the geometry gate fails
  - native summary: 14 vs 12 queries
  - names read: 54 vs 53 queries
  - signal consumption: the key survives `/inbox`
  - empty mirror: 27 writes
  - resize overflow: the page overflows

## Failures not caused by this work

- `messages-refinement.spec.ts:168` cannot find "New accounts" in the Messages
  list pane. It fails identically on a detached worktree at the pre-handoff
  commit `8206122d`, with its own database.
- `ThreadIntelligenceConcurrencyTest::test_abandoned_reserved_generation_is_settled_and_completed_after_owning_job_revalidation`
  fails only between 00:00 and 00:11 UTC: its 11-minute backdate crosses the
  daily budget bucket. It passed at 00:11 and on every other run.
- Mobile `composer-expansion` `networkidle` timeouts appear only when several
  specs share one prepared database; alone, the spec passes (20 passed).

## Limits and release preconditions

- Nothing was pushed or deployed. On 2026-10-08 23:52 UTC, `forum.candidary.online`
  answered every container-backed route with HTTP 500 (Cloudflare 1101). Worker
  logs showed `The container is not running` from `startAndWaitForPorts`, with
  such failures logged daily since 2026-10-01. Static assets still served.
- `5de73df06f287ae9` (the `8206122d` build) is live but was never recorded in
  `deployedReleases`. Before deploying, verify its live HTML and asset hashes,
  record it from an export of `8206122d`, and rebuild, as the 2026-10-08 record
  describes.
- Zoom was emulated with device scale factors and forced fractional widths.
  Screen readers and physical devices were not exercised.
