Ceiling: proxy — Windows Chromium viewport/interaction evidence plus source mechanisms. Phone keyboards, browser chrome, screen readers and device performance remain unverified.

Method: dual-agent (A: `/root/thread_design_review`; B: `/root/thread_technical_review`). Audit only, Mobile Web UX Runner 2.0.2 / October 20 r2 contract; installed Impeccable skill 4.5.0, binary VERSION 0.1.11. A finished before detector findings entered synthesis. This report concerns the current working bytes, not a qualified release or deployed installation.

Target: `templates/thread.php`, fixture `/t/30-ratified-decisions`, revision `468dd5b44a37bfcafacf2649223ef2c548ed9bd5` plus preserved dirty files. Principal task: open/read the topic, find its accepted answer, contribute a reply, recover an interruption, and return to the board.

The visual identity is cohesive and specific to RetroBoards. The highest risk is failed-send recovery: an offline reply sequence restored text with a disabled Reply button, then lost the text after ordinary board navigation and re-entry. Four observed issues remain open, with two separate design recommendations. No fixes were applied.

## Design judgment

Literary typography, restrained twilight color, esteem marks, worded states and a quiet reply dock express the Councillor's Hall identity. Preserve that language. The reading path is calm; its weak points are reaching the resolution, fitting controls when space shrinks, and preserving trust after a failed send.

The synthesized Nielsen judgment is **24/40 (Acceptable; significant improvements needed)**. Assessment A's independent score was28/40 and remains preserved in its original report. B's evidence lowers Control from3 to2, Error prevention from3 to2, and Error recovery from3 to1. These are qualitative design judgments, not a conformance or test score.

| Heuristic | /4 | Assessment basis |
|---|---:|---|
| System status | 3 | Solved, answer caption, Starred and catch-up communicate state. |
| Real-world match | 3 | Familiar chronological conversation; unusual reactions need learning. |
| Control and freedom | 2 | Short-landscape Minimize is obscured; failed-send navigation can lose input. |
| Consistency | 3 | Coherent system; narrow header geometry needs work. |
| Error prevention | 2 | Empty Reply and validation safeguards help; unconfirmed submission state can clear drafts. |
| Recognition | 2 | No dependable accepted-answer jump; nine reaction symbols. |
| Efficiency | 3 | Permalinks/catch-up help; accepted-answer navigation costs scrolling. |
| Minimalism | 3 | Quiet hierarchy; permanent author rail narrows phone prose. |
| Error recovery | 1 | Offline Back restores text with stale send state; re-entry loses that text. |
| Help | 2 | Formatting/Preview/provenance help; reaction meanings depend on names hidden visually. |
| **Total** | **24/40** | All ten apply. Original A28/40 preserved; no detector score added. |

The cognitive checklist had **2/8 failures**, both about the same nine-choice reaction picker: chunking and number of visible choices. Single focus, grouping, hierarchy, one decision at a time, memory support and progressive disclosure passed that design checklist. This is a heuristic, not a measured working-memory limit.

Three strengths are worth retaining: word-and-color status with a distinct answer plate; a resting reply dock and disclosures that keep attention on reading; and a Living Brief clearly identified with provenance after the opening post.

Persona risks: a distracted reader must hunt for the promised answer; a first-time participant must learn unfamiliar reaction symbols; an accessibility-dependent reader still needs a real VoiceOver/TalkBack journey. The exposed reply name and overlay focus paths are useful evidence, but do not establish assistive-technology completion. The perceived journey is calm arrival, a navigation valley while finding the answer, and a serious trust failure if an interrupted reply disappears.

## 1. Verdict and observed issues

**F-01 · P1 · Observed proxy · Open — Failed-send recovery can lose reply text.** In fresh Edge 155.0.4283.62 at 390×844, fill a unique Markdown reply, immediately enable CDP offline and submit. The browser reaches `/t/30/reply` with `ERR_INTERNET_DISCONNECTED`. Restore network and Back: the exact text is present, but Reply remains disabled after focus. Visit The Archive and reopen the topic: the textarea is empty, reply count remains five, and no discard warning was observed or accepted. The failure snapshot also reported “Saved locally; server draft sync is unavailable.” The latest pre-offline save acknowledgement was not separately confirmed, and storage values/remote deletion were not inspected.

Source support: `public/assets/composer.js:912-955` removes pending local drafts and attempts remote discard on a later loaded page without a confirmed success receipt; pending registration is at 1019-1024. The precise disabled-state cause remains a hypothesis. Minimal repair specification: retain unconfirmed drafts until server-confirmed success or explicit discard; reconcile restored text/send state; preserve idempotency and avoid blind retries. Device-dependent proposal only. Checks: C-OFFLINE; B C-19/C-20. This is one recorded sequence, determinism unknown, with no all-phone claim.

**F-02 · P2 · Observed proxy · Open — Solved has no persistent route to its answer.** Guests and already-read members see a static Solved chip without View answer. The unread-member catch-up link worked but disappeared after reading/reloading. In the five-reply fixture, the answer begins about 3,966px down at 320px. Source: `templates/thread.php:72`, conditional catch-up at 233-235; answer caption at `templates/partials/post.php:53-54`. Add an always-available, correctly paginated answer permalink beside the state. Keep chronology. Check C-ANSWER; include accepted answers on later pages in follow-up validation.

**F-03 · P2 · Observed proxy · Open — Header actions fail enlarged-text reflow.** At requested 320×720 and 390×844, setting HTML font size from 16px to 32px through CDP produced body/reply 34px, title 56px and document scroll width **504px**, against client widths 310/380px. The action group was about 490px wide. This is a root-font stress proxy, not OS larger text or browser page zoom. Normal 320px separately exposed only about 1px overflow; normal 360/390/430px did not overflow. Source: `app.css:10696`, 11463-11467; `thread.php:120-130`. Let the action/watch label group wrap or compact within available width while preserving names and focus rings. Check C-HEADER; B C-02/C-07/C-08.

**F-04 · P2 · Observed proxy · Open — Short-landscape composer hides Minimize beneath the header.** At 844×390, preview-on Markdown expansion placed IAB's 44px Minimize at y47-91 behind the y0-108 member bar. Fresh Edge with a loaded server-draft status row put the dock partly above the viewport; a center hit on Minimize reached **Search the council**. No real software keyboard was involved, and separate keyboard focus on Minimize was not tested. Source: `app.css:1906-1909`, 993-1000, 1328-1337; `app.js:1054-1061`. Budget the whole dock, including preview/status rows, against available height; keep Minimize/Send reachable and allow appropriate internal scrolling. Device-dependent proposal only. Check C-LANDSCAPE; B C-17/C-18.

Canonical IDs above preserve the original evidence through `run.json`'s explicit A/B ID map. No finding was closed or verified.

## 2. Coverage and substitutions

Standing local setup substitutions: real PHP 8.3.35 and MariaDB 11.8.9 with seeded test accounts/content, array mail, dummy AI credential, seeded Living Brief/link card, and `wysiwyg_composer=false`. No external charter link was followed. CDP offline was scenario state, not an injected server response. No source code or request-response stub was substituted. Actual served URLs are recorded in B; the retained asset digest check failed and no rebuild occurred.

| Master area | Disposition | Highest evidence | Remaining |
|---|---|---|---|
| Reach/placement | Defects found | Proxy F-02/F-04 | Grip, real toolbar/keyboard reach |
| Viewport/safe areas | Defects found | Proxy F-03/F-04 | Real chrome/insets, actual zoom/text settings |
| Touch targets | No defect in sampled controls | Mechanism | Full spacing assessment and physical touch |
| Forms/keyboard | Defects found | Proxy F-01/F-04 | Rich editor, real keyboard/IME, runtime 422 |
| Gestures/scrolling | No defect in inspected mechanism | Mechanism | Pinch, edge navigation, pull-to-refresh, iOS chaining |
| Overlays/navigation/data | No defect in checked overlay paths | Proxy | Native Back, Safari scroll lock; no dense table in fixture |
| Content priority | Defects found | Proxy F-02 | Reading recommendations below |
| Performance | Not tested | None | Lab, field and hardware metrics |
| Errors/recovery | Defects found | Proxy F-01 | Lost applied response, duplicates, rich-mode recovery, tab discard |
| Accessibility | Defects at proxy scope | Proxy F-03/F-04 | VoiceOver/TalkBack, native zoom and full conformance |

All seven modules were reviewed for applicability. Review of a module does not imply all target states passed. B recorded **22 UI/scenario conditions: 15 scoped passes and 7 failures**, spanning its three technical findings. These counts exclude detector hits and are not added to the grouped ledger checks.

The detector scanned ten named markup files once, exit 0, raw `[]`: zero primary/advisory hits and no false positives to classify. It missed the behavioral/responsive issues above. No browser overlay was injected: the documented read-only browser API could not perform the mutable injection procedure. Manual rendered/DOM/CDP evidence was the documented fallback.

Useful passes: Topic tools focus entry/cycling/Escape/return; post-action entry and native accessible names; ordinary draft reload/minimize preservation; one ordinary local reply p128; return to the board. Root independently read the test DB: p128 was visible/nonpending in thread30 and reply_count became six. Those ordinary passes do not refute the failed-send sequence.

Sampled post summary/reaction controls had 44px geometry; Star/Topic tools were 32px high. A 44-48px ergonomic preference is separate from [WCAG 2.5.8's 24px floor and exceptions](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum). Tags about21.92px high need spacing assessment before any AA failure claim. The root-font stress test likewise is not a completed [WCAG reflow evaluation](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).

Body field: freeform Unicode Markdown textarea/combobox with an accessible reply name and computed17px font. `inputmode`, `autocomplete`, `enterkeyhint`, `autocapitalize` and `spellcheck` were absent; absence is recorded, not automatically a defect. All LCP/INP/CLS/TBT values are **not measured**.

## 3. Recommendations, separate from defects

**F-05 · P2 recommendation — Widen phone prose.** At320px the persistent author rail leaves roughly204px of prose width and nearly5,000px of document for six posts. Compare a compact avatar/byline/time header with text spanning beneath it, preserving identity, esteem and answer distinction. Source: `post.php:28-52`; `app.css:10753-10758`, 11524-11531. This is a reading-efficiency design judgment, not an accessibility failure.

**F-06 · P3 recommendation — Give uncommon reactions visible names.** The nine-symbol picker has accessible names/title hints, as the current design specifies. Preserve its symbols; consider visible gesture labels or non-submitting explanation for touch users. This is an optional design choice, not an observed accessible-name failure.

Minor observations: the desktop byline yields to controls and truncates; regard's visible and sr-only counts may be verbose in an actual screen-reader journey. The simplified CUA tree's briefly blank reaction names were a tool representation issue: native accessibility data supplied the names. Neither lead is promoted to a confirmed defect.

## 4. Changes made

No application source, dependency, Git history, deployment or production content changed. Test-only activity included login/read tracking, drafts and one tagged reply. Twenty-two pre-existing modified tracked hashes and fifteen thread-scope source hashes matched their captured values. The owned PHP server and labelled MariaDB container were removed; no mounted volume existed. Browser connectivity/overrides were restored and the final tab inventory was empty.

## 5. Device script

Eight separately reportable entries are planned within budget10; applied-device-fix reservation0/5. **Not ready to execute:** first provision a phone-reachable isolated preview matching the captured source fingerprints. The loopback server is stopped, and no phone setup/build is confirmed. These are prospective phone checks related to the findings, not original Windows-scope coverage or verification of an applied fix.

Every entry in `device-script.md` records prerequisites, exact requested states, build/setup substitutions as unknown, ordered steps, expected behavior, and separate blank Result / Build confirmation / Setup confirmation / Notes / Screenshot fields. Record actual device/OS/browser, CSS viewport, text settings and each state separately; a portrait pass does not cover landscape. Test writes only against an isolated backend.

| ID | One configuration per result | Principal steps and expected outcome |
|---|---|---|
| C-PHONE-SAFARI-BOTTOM | iPhone Safari, Bottom tabs; record versions | Read/find answer; enlarge text; expand/collapse bars; landscape; keyboard; offline-send→Back→board→reopen. Reachable controls and preserved draft. |
| C-PHONE-SAFARI-TOP | iPhone Safari, Top tabs; record versions | Bars, enlarged text, landscape, tools and keyboard; control/focus paths remain reachable. |
| C-PHONE-SAFARI-COMPACT | iPhone Safari, Compact layout if offered | Record actual layout/version; bars, enlarged text, landscape, keyboard. Unsupported portion stays Not run. |
| C-PHONE-ANDROID | Android Chrome and native gesture Back | Reading, enlarged text, landscape, tools/Back, keyboard and failed-send recovery. |
| C-PHONE-VOICEOVER | iPhone Safari with VoiceOver | Headings/answer, authors/esteem, named reactions, tools and reply/recovery; record actual speech/focus. |
| C-PHONE-TALKBACK | Android Chrome with TalkBack | Same principal journey, names and focus return; record speech/focus. |
| C-PHONE-NARROW | An actually supported narrow phone configuration | Record CSS width;320px portion only if available; Star/watch labels, long content, answer navigation and text enlargement. Missing320px coverage stays Not run. |
| C-PHONE-PERF | Mid-range Android Chrome with remote DevTools | Measure navigation/interactions/long tasks/shifts with recorded network/CPU conditions. Report lab values separately from field data. |

No entries are hidden beyond budget. A phone pass cannot prove unknown mail/AI/backend boundaries, and no expected result here is an observed pass.

## 6. Open questions and limits

- Which mobile browser/OS versions and Safari layouts are actually supported?
- A matching phone-reachable test preview and actual tester/device access are unprovisioned.
- The rich editor is default-on in production but untested in this pinned Markdown fixture. Runtime 422 validation, lost successful response/deduplication and real tab discard are untested.
- Field and lab performance data are absent. Real email/AI provider behavior was not exercised.
- The latest pre-offline save acknowledgement and successful server draft deletion are not established. The precise observed offline/Back/re-entry failure remains open.
- `npm run check:assets` failed on retained `/assets/dist/app-c89b55420ad2231f.js`; source-to-release asset qualification is not established. No rebuild was attempted.
- Screenshots were emitted into CUA transcripts and are indexed in A/B reports; no unsupported local PNG export is claimed.

The schema-2 ledger has no structural errors; its result is valid but incomplete (exit3), with failed verification and the open P1 preserved. This is not a green release, complete target verification or accessibility-conformance claim.

## 7. Follow-up choices

1. Scope: **recovery only**, **the four observed issues**, or **observed issues plus the two design recommendations**?
2. Reading composition: keep the **current author rail**, or compare a **compact byline with wider mobile prose**?

Detailed evidence: `assessment-a/assessment-a.md`, `assessment-b/technical-report.md`, `assessment-b/technical-results.json`, `run.json`, generated `report.md` / `summary.json`, source-preservation and resource-cleanup records. No further action is authorized by this critique itself.
