# Assessment B — thread technical evidence

Ceiling: mechanism and desktop Chromium proxy evidence only. No mobile Safari, Android hardware, OS simulator, real software keyboard, screen-reader journey, or field performance was available. Three open findings: one P1 recovery failure and two P2 responsive failures. No application source was edited.

Target: `templates/thread.php`, served at `http://127.0.0.1:18026/t/30-ratified-decisions`, HEAD `468dd5b44a37bfcafacf2649223ef2c548ed9bd5` plus the preserved working bytes. Principal task: open/read the topic, find the accepted decision, reply, recover an interrupted reply, and return to the board.

## Conditions and provenance

- Impeccable skill 4.5.0; binary VERSION 0.1.11. Context was run once by the parent, not repeated here. Mobile Web UX Runner 2.0.2, contract 2026-10-20-r2; audit only, device-dependent changes propose only.
- Assessment B did not read A or prior critique snapshots. Detector findings were withheld until the parent confirmed A's final report was received. Each assessor used its own fresh tab.
- Pre-interruption environment: fresh hidden IAB tab 1, desktop Windows; request UA `Chrome/155.0.0.0`, Chromium brand 155. Browser viewport overrides and tab-scoped touch emulation. `Emulation.setTouchEmulationEnabled({enabled:true,maxTouchPoints:5})` succeeded; this is no physical touch or gesture proof.
- Post-resume environment: fresh Edge tab 631628254 after an unavailable IAB and one connection-refused launch. Edge executable ProductVersion/FileVersion `155.0.4283.62`, obtained from the sole running `msedge.exe` path. It had fresh browser storage/session and required a new fixture sign-in. Pre-resume focus/draft state was not claimed to survive the interruption.
- Account Elladan, isolated MariaDB fixture, thread 30, initially five replies and accepted post p126. Parent supplied/verified test setup uses array mail and dummy AI. The visible living brief and external-looking charter card are fixtures; no external charter link was followed.
- Actual DOM flags: drafts=1, server-drafts=1; no `data-wysiwyg-composer`. All composer observations are the Markdown textarea path. Production's default-on rich editor was not tested. Preview preference was on.
- Normal timing: each geometry read followed the requested viewport transition; screenshots/AX calls used the tool's own settling. No CPU/network throttling for layout checks. Root-font enlargement was a direct CDP CSS proxy, not OS font enlargement or browser zoom. The offline sentinel was filled and offline enabled immediately; its latest save acknowledgement was not separately confirmed before offline.
- Root's asset check failed on the retained digest for `/assets/dist/app-c89b55420ad2231f.js`; no rebuild was performed. Rendered claims concern the served bytes listed below, not a qualified release package. Root later confirmed all 22 pre-existing modified tracked fingerprints and all 15 scoped source hashes remained unchanged.

Loaded assets in both sessions:

```text
/assets/dist/imladris-style-bT2GVmLw.css
/assets/dist/app-style-Cccoax6v.css
/assets/dist/app-c89b55420ad2231f.js
/assets/dist/composer-5aeaedfd8f662ec5.js
/assets/dist/passkeys-16e7134417f0d6af.js
/assets/dist/tour-1801eafe66889a28.js
```

## Detector

One `impeccable.cmd detect --json` invocation scanned ten named markup files: thread.php, post.php, composer.php, composer_shell.php, thread_tools.php, thread_memory_slot.php, living_brief.php, living_brief_empty.php, thread_restructure.php, pagination.php. Exit 0; raw JSON `[]`; stderr empty. Primary findings 0, advisories 0, rule names/locations none, false positives none to classify. PHP static scanning does not establish rendered accessibility or responsive correctness.

The browser API permits read-only evaluate, not the mutable JavaScript injection required by the live detector overlay workflow. No overlay/live server was started or claimed. Fallback: actual route screenshots, DOM/computed geometry, native accessibility tree and CDP observations.

## Findings

### F-03 [P1] Offline reply recovery loses the unsent text after re-entry

Evidence: observed, desktop Edge proxy, open; determinism unknown (one full sequence, no repeated reproduction). Scope is this Markdown fixture sequence, not all phones or all server errors.

1. In fresh Edge at 390×844, enter `Mobile UX audit fixture reply — offline recovery sentinel 1059.`. Enable CDP offline immediately and click Reply.
2. Browser reaches `/t/30/reply` with `ERR_INTERNET_DISCONNECTED`. Earlier landscape text had shown Draft saved; the latest sentinel had no separately checked pre-offline acknowledgement. After the failure the application snapshot said `Saved locally; server draft sync is unavailable` and `Sending…`.
3. Restore connectivity and use browser Back. The exact sentinel appears in the textarea; Reply is disabled. Clicking the textarea expands the dock but leaves Reply disabled. `aria-busy` is absent in this returned document.
4. Follow the breadcrumb `# The Archive`, then the existing topic link. The textarea is now empty and the thread still says five replies. No discard/beforeunload warning was observed; no dialog was accepted.

Expected: keep the unconfirmed reply recoverable, with a usable send/correction path. Actual: native form restoration briefly exposes text with stale send state, then ordinary re-entry loses it.

Likely source cause: `public/assets/composer.js:912`–955 clears a pending submission's local draft on any later page except the POST-action URL with an existing body; it also issues a server-draft discard request. `composer.js:1019`–1024 registers pending state on submit. No localStorage/pending values were inspected, and successful server-draft deletion was not proved. The disabled send state is consistent with input restored after initialization without a new input event; that causal detail remains a hypothesis.

Minimal repair specification: clear drafts only on an explicit successful submission receipt for that context/idempotency key; retain unconfirmed text across errors and navigation. Reconcile restored textarea, expansion, and send state on history/form restoration. Do not add blind retries. Suggested command: `$impeccable harden`.

### F-01 [P2] Header action row does not reflow with enlarged text

Evidence: observed desktop IAB proxy plus source support, open. At 320×720 normal text, client width was 310 and scroll width 311, exposing a horizontal scrollbar. At 360/390/430 and normal text, scroll width matched client width.

After CDP `CSS.setEffectivePropertyValueForNode` set the root font to 32px (baseline 16px), computed body/reply font became 34px and title 56px. At both requested 320×720 and 390×844, document scroll width became 504px (client widths 310/380). `.thread-facts-actions` was 489.796875px wide, x=14, right=503.796875; Topic tools alone was 345.53125px wide. At 844×390 the document did not overflow, but the byline was compressed to 29.203125px and ellipsised.

Source: `public/assets/app.css:10696` fixes the action group at `flex:0 0 auto` with no wrapping. The phone override at 11463–11467 wraps the identity group, not the action pair. `templates/thread.php:120`–130 renders the Starred and Topic tools/watch labels.

Expected: keep both named actions within the available reading column when text grows. Proposed repair: allow the action group and/or watch descriptor to wrap within its column, preserving the action labels. This is resize/reflow risk evidence, not a completed WCAG conformance finding or an actual OS larger-text result. Suggested command: `$impeccable adapt`.

### F-02 [P2] An expanded reply dock covers its own minimize control in short landscape

Evidence: observed in IAB and an additional Edge hit test, open; source support. The actual software keyboard was absent.

Pre-interruption IAB, coarse-touch proxy, 844×390, Markdown draft and preview on: sticky member bar covered y=0–108. Dock y=24.109375, height=365.890625. Minimize x=687, y=47.109375, width=44, height=44: wholly behind that bar. The textarea started at y=91.109375, partly behind it. At 390×844 the expanded dock was y=536, height=308 and remained visible.

Fresh Edge, fine pointer, same 844×390 scenario with a loaded server-draft status row: dock y=-8.9375, height=398.9375; Minimize x=705, y=15.0625, width=26, height=26. `document.elementFromPoint` at its center returned the `forum-bar-search` anchor (`Search the council`), proving pointer interception by chrome. The Minimize control was not separately keyboard-focused in landscape; keyboard focus obscuration is therefore not claimed as an executed check.

Source: `public/assets/app.css:1906`–1909 makes the whole dock sticky at bottom 0; 993–1000 caps only the textarea, while 1328–1337 adds the preview below it. The measured whole dock can exceed the height remaining below chrome. `app.js:1054`–1061 adjusts scroll padding, which does not reduce this layout height.

Proposed device-dependent repair: budget the whole expanded dock, including preview/status/meta rows, against the visible height below chrome; provide an internal scroll region and keep Minimize/Send reachable. Preserve document reading scroll and validate with real keyboards. Suggested command: `$impeccable adapt`, followed by `$impeccable polish` after substantive fixes.

## Positive evidence and check counts

22 executed UI/scenario checks: 15 scoped passes and 7 failures across the three findings. The zero detector hits are not counted as UI passes. Counts describe atomic check conditions, not 22 independent features or a conformance score.

- Normal reflow had no document overflow at requested widths 360, 390, 430 or collapsed short landscape 844×390.
- Topic tools acquired focus on Close, cycled Shift+Tab/Tab, closed with Escape, restored the opener, and toggled expanded state. Body scrolling was locked while open. Native mobile Back and iOS scroll leakage were not tested.
- Mobile post actions opened through their visible 44×44 summary. The simplified CUA tree briefly omitted child names; native CDP accessibility data confirmed Commend/Heart/etc., Quote in your reply, Copy link, and Report names. This was a tool representation artifact, not a reported product defect.
- Reply had an accessible combobox name and computed 17px font; no zoom cap in the viewport meta. The freeform body has no type/inputmode/autocomplete/enterkeyhint/autocapitalize/spellcheck attributes; that is recorded, not automatically a defect. Native keyboard behavior remains untested.
- In pre-resume IAB, a draft survived reload exactly, explicit Minimize preserved it, and reopening retained it. These passes do not cover interrupted submission or tab discard.
- The one later ordinary, tagged local submission succeeded as post p128, visible/nonpending (independently confirmed by parent), increased replies from 5 to 6, and left the reply field empty. Return to `/c/the-archive` passed. An ambiguous text locator wait failed after the successful navigation because the string matched visible post/preview and hidden edit textarea; no submit was repeated.
- Fresh Edge console read returned no warning/error entries. Offline CDP event retrieval was truncated and did not retain a loadingFailed event; browser error UI is the failure evidence. No LCP, INP, CLS, TBT, field or device speed numbers were measured.

## Ten master areas

| Area | Disposition | Highest evidence and remaining work |
|---|---|---|
| Reach and placement | Defects found | Proxy F-02. Actual grip/reach and toolbar collisions require phones. |
| Viewport and safe areas | Defects found | Proxy F-01/F-02. No real inset, dynamic toolbar, installed-mode or pinch test. |
| Touch targets | No defect found in sampled controls | DOM 32px Star/Topic tools; 44px post summary/reactions/send height. Tags 21.92px high need spacing-exception assessment; no AA failure inferred. Primary 44–48px sizing remains ergonomic advice. |
| Forms and keyboard | Defects found | Proxy F-02/F-03; 17px named body. Actual keyboard/focus zoom/IME/autofill not tested. |
| Gestures and scrolling | No defect found in inspected mechanism | Source shows document-owned thread scroll; no custom drag in this journey. Synthetic drags, native edge navigation, pinch and pull-to-refresh not tested. |
| Overlays, navigation, dense data | No defect found in checked overlay paths | Proxy Topic tools/post-menu entry, focus cycle, Escape and return. No native Android Back/Safari scroll-lock proof. No dense table in the fixture journey. |
| Content priority | No technical loss found in sampled route | Proxy title/status/OP/brief/accepted-post/related/reply order. Design hierarchy belongs to independent A. |
| Performance | Not tested | Instrumentation enablement is not a metric. No lab/field/device performance claim. |
| Errors and recovery | Defects found | Proxy F-03 plus one normal successful reply. 422 server validation, lost applied response, deduplication and real tab discard not tested. |
| Accessibility on mobile | Defects found at proxy scope | F-01/F-02 plus checked labels/focus. VoiceOver/TalkBack, actual text zoom and conformance not established. |

All seven modules were reviewed: (1) principal journey exercised; (2) layout/root-font proxies exercised, chrome/insets untested; (3) Markdown form/draft/recovery exercised, software keyboard untested; (4) geometry and coarse-emulation capability checked, physical touch/reach untested; (5) focused overlay/navigation paths exercised, native Back untested; (6) scroll/gesture source reviewed, real gestures untested; (7) offline recovery and ordinary completion exercised, metrics/concurrency/real discard untested.

## Remaining device script and questions

No fixes were applied, so no device-regression slots were consumed. Within a default ten-entry budget, prioritize separately reportable iPhone Safari and Android Chrome entries for: (a) offline→Back→board→reopen draft recovery; (b) expanded preview-on dock in short landscape with keyboard open/closed; (c) enlarged text/reflow at the actual supported narrow widths and toolbar states; then VoiceOver and TalkBack journeys. Record exact device, OS/browser, CSS viewport, text-enlargement mechanism, build and setup separately. At 320 CSS px, use an actually supported capable device/simulator; if unavailable retain that portion as not run.

These require a phone-reachable isolated fixture containing the same working bytes; loopback is not a reachable phone deployment. The parent stopped the owned server/DB after checks. Rich/default-on editor, supported mobile-version policy, field data and real-service AI/mail behavior remain open. No additional source edits, deployment, or phone setup is implied.

Cleanup: root font restored to 16px before the IAB session disappeared; OS-text-scale/font-size probes restored; offline restored false; Edge viewport reset and the successful audit tab closed. No detector live server existed. The parent owns and confirmed PHP/DB cleanup. The initial Edge error tab is ephemeral. Screenshots were emitted in the CUA tool transcript; this folder contains the recorded measurements and textual/JSON evidence, not exported screenshot files. The initial helper ledger was not validated as a completed schema-2 run; use `technical-results.json` for this assessment's finished structured record.
