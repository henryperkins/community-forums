# Assessment A — independent design review

Ceiling: proxy — Windows desktop Chromium 155.0.0.0 at explicit CSS viewport sizes, plus source/DOM mechanism review. This was viewport emulation with the host pointer, not a physical phone, mobile Safari, virtual keyboard, reach test, or assistive-technology journey.

Target: `templates/thread.php`; local fixture route `http://127.0.0.1:18026/t/30-ratified-decisions`. Build: HEAD `468dd5b44a37bfcafacf2649223ef2c548ed9bd5` plus the pre-existing dirty tree identified by the parent. Mode: audit_only. Audience: guest reader and signed-in member. Principal journey: open topic, understand the discussion, find the answer/actions, prepare a reply, return to the list.

Method: Assessment A ran independently of Assessment B. No detector output, prior critique snapshot, or prototype was read. The parent supplied the already-completed context invocation and installed versions (skill 4.5.0, binary 0.1.11). I read the installed critique procedure, Mobile Web UX Runner 2.0.2 and modules 1–7, repository guidance, product/design/spec context and current production source. No application source/configuration was edited, and no reply, poll vote, reaction, report or setting change was submitted. The ordinary local fixture login and thread-read tracking did run. No new server was started by A.

Standing substitution: the parent's isolated local PHP/MariaDB fixture and seeded thread/account data, replacing the deployed installation and real community content. Link previews/Living Brief content were seeded. Provider fetching/generation was not exercised. No browser interception was added by A. Safe-area insets, browser toolbar movement, mobile keyboard and actual touch behavior were not supplied by this viewport override.

## Design specificity

The surface is recognizably authored for RetroBoards. The literary serif hierarchy, restrained twilight palette, gold esteem marks, participants, worded topic states and quiet reply dock reinforce a durable conversation rather than a generic card dashboard. This fits the binding Councillor's Hall identity and the Community Inbox model. The opportunity is task-specific: the page announces that the question is solved but offers no dependable route from that promise to its answer. On a phone, the retained desktop author rail also spends scarce width on identity after the byline has done that job.

The screenshot register was `data-theme="system"` rendered dark and `data-density="comfortable"`. No theme preference was changed. The desktop comparison confirmed the same visual language at 1280×900; it was not a separate complete desktop audit.

## Nielsen scores

All ten heuristics apply to this Read/Operate surface. These are qualitative design scores, not an accessibility conformance score or proof of unexercised success/error states.

| # | Heuristic | Score / 4 | Basis |
|---|---|---:|---|
| 1 | Visibility of system status | 3 | Solved, Marked as the answer, pressed Starred and Catch me up communicate state clearly. Empty Reply is disabled. Final send/pending/error states were not exercised. |
| 2 | Match between system and real world | 3 | An intelligible chronological conversation, bylines, dates, Reply and Copy link. The council vocabulary fits the brief; the nine unlabelled visual reaction symbols still require interpretation. |
| 3 | User control and freedom | 3 | Reply expands and minimizes; native disclosures open/close; board and forum links are visible. Returning to an exact prior filtered Inbox relies on browser history rather than the page's fixed breadcrumb. |
| 4 | Consistency and standards | 3 | A cohesive visual register, semantic headings and familiar forms/disclosures. A small 320px header overflow and abbreviated desktop byline are local inconsistencies. |
| 5 | Error prevention | 3 | Empty Reply is disabled and the 17px textarea preserves normal input/zoom sizing. Controller source preserves invalid reply input. Destructive/duplicate/concurrent failure paths were outside A's interaction scope. |
| 6 | Recognition rather than recall | 2 | The solved result lacks an always-available answer link. After reading, the catch-up shortcut disappears. Reaction choice depends on recognizing nine symbols. |
| 7 | Flexibility and efficiency | 3 | Post permalinks, a useful return-context disclosure and persistent reply entry support several paths. The missing answer shortcut costs unnecessary scrolling. Full keyboard shortcuts were not tested. |
| 8 | Aesthetic and minimalist design | 3 | Calm, coherent hierarchy and restrained controls. The fixed author gutter consumes a disproportionate share of phone prose width. |
| 9 | Error recognition and recovery | 3 | Source at PostController.php:80–89 re-renders a failed reply with errors and original text. This is mechanism evidence only; no A submission/failure/recovery test was run. |
| 10 | Help and documentation | 2 | Formatting names, Preview, image-size hint and AI provenance give some contextual support. Touch users cannot rely on desktop hover hints to learn unusual reaction meanings. |
| **Total** | | **28/40** | **Good; focused reading/navigation improvements are warranted.** |

## Cognitive load

Two failures out of eight: moderate by the critique checklist. Both describe the same reaction-choice design, rather than two independent defects.

| Checklist | Result | Evidence |
|---|---|---|
| Single focus | Pass | The thread is a single reading path; the reply dock rests as one line until focused. |
| Chunking ≤4 items/group | Fail | One post-menu reaction group presents all nine gestures. |
| Related items grouped | Pass | Byline, body, reactions, topic tools, poll and brief have distinct grouping. |
| Visual hierarchy | Pass | Status then title, byline and chronological posts read in that order. |
| One decision at a time | Pass | Topic tools, catch-up, post actions and formatting complexity are disclosed on request. |
| Minimal choices ≤4 | Fail | The nine-icon reaction decision exceeds the guideline; the compact toolbar also exposes four formats plus More. |
| Working memory | Pass | The sticky reply entry repeats the topic title; quoting/contextual links are available. The answer-finding issue is navigation cost, not forced memorization. |
| Progressive disclosure | Pass | A quiet resting dock and native details avoid opening all controls by default. |

## Emotional journey

Arrival feels calm and credible: the title and Solved word promise a useful conclusion. Reading the OP establishes context naturally, but at 320px the prose becomes a narrow ribbon and the accepted result begins roughly 3,966px down the document. A returning member initially gets a satisfying peak when Catch me up links directly to Arwen's answer. A guest, or the same member after reading/reloading, loses that shortcut and must search the stream. The answer's framed plate restores confidence when reached. Opening the action menu is predictable, but nine symbols create a small interpretation valley before the plainly worded Quote, Copy link and Report rows. Focusing Reply gives clear gold focus, identity and Preview; its keyboard behavior and final success/end state are untested. I did not submit a reply.

## Strengths

1. Status is a word as well as a color. Solved and Marked as the answer remain intelligible without relying on hue, and the answer is visually distinguished without making the whole thread noisy (`templates/thread.php:68–75`, `templates/partials/post.php:53–54`).
2. The resting dock and native disclosures preserve attention. The reader can continue reading while contribution, catch-up and administration stay available without an always-expanded toolbar (`public/assets/app.css:1932–1990`, `templates/thread.php:231–235`, `345–357`).
3. The AI-written brief has a distinct title, disclosure and provenance link. It appears after the opening post, so a member-authored problem is established first (`templates/thread.php:196–221`, `313`; visible Living brief and Where this came from controls).

## Priority design issues

### A-01 — P2 — Solved does not get the reader to the answer

Class: Observed rendered navigation friction, open. Strong mechanism support; impact is a design judgment, not a timed usability study.

Reproduction: open the fixture as a guest at 390×844, or reload it as the member after reading. The header says Solved, but that chip is static and there is no View answer action. The member's initial Catch me up disclosure contained an Arwen link to `#p126`, which worked; after following it and reloading the thread, Catch me up was absent. At 320px the accepted post menu/heading began around document y=3,979px, despite only five replies.

Expected: the persistent solved state gives a direct, correctly paginated route to the accepted post. Actual: readers traverse the OP, poll, brief and other replies to locate the promised resolution, unless an incidental unread-context link exists.

Source: `templates/thread.php:35–38`, `68–75` (static state), `231–235` (conditional catch-up), `293` (accepted-post state); `templates/partials/post.php:53–54` (answer label only at the post).

Smallest proposal: add a plainly labelled View answer link beside the Solved state when an accepted post exists, using a server-resolved permalink/page so it also works when the accepted post is not on the current pagination page. Keep chronology and the original post visible. Suggested workflow: `$impeccable clarify` / `$impeccable adapt`.

Checks needed: guest/member; read/unread; accepted post on page 1 and later page; keyboard focus/scroll clears the sticky header; supported phones if changing scroll behavior. No viewport/history workaround is justified merely to add a normal link.

### A-02 — P2 design recommendation — Give phone prose the width currently spent on the author rail

Class: Recommendation based on observed geometry; keep distinct from a demonstrated functional/accessibility defect.

Reproduction: read the fixture at 320×844 and 390×844 with avatars enabled. The post retains a permanent 48px author rail, 10px gap, padding and content margin. The 320px capture shows prose beginning around x=81 and ending around x=285, about 204px wide. The member header occupies about 108px before reading begins, and the resting dock another ~68px. The six-post thread extends to almost 5,000px at 320px. This is a large reading/scroll cost for a short conversation, although all text remains readable and normal vertical scrolling works.

Source: `templates/partials/post.php:28–52`, `75–77`; `public/assets/app.css:10753–10758`, `10785–10788`, `11524–11531`.

Proposal: at an existing narrow breakpoint, make avatar/name/time a compact header row and allow the post body to span the reading column underneath. Preserve esteem, author identity and accepted-answer distinction. Compare total line count and readability using the same content before adopting it. Suggested workflow: `$impeccable layout` / `$impeccable adapt`. This is a small responsive composition option, not a request to replace Imladris or hide identity globally.

### A-03 — P3 — Header actions create a small page-level overflow at 320px

Class: Observed geometry, open. This is polish, not a blocked task and not an established WCAG failure.

Reproduction: member Elladan, Starred state, `Topic tools · every reply`, 320×844. The document client width was 310px (classic scrollbar within the requested 320px viewport), scroll width 311px, and the Topic tools control's right edge was 311.42px. The screenshot shows the row pressed against the right boundary and a page-level horizontal scrollbar. At 390px, client width and scroll width were both 380px.

Source: `public/assets/app.css:10694–10696` (nonwrapping action group), `10715–10725`; `templates/thread.php:120–130` (watch label).

Smallest proposal: allow the action group/watch label to adapt to remaining inline space at the existing narrow breakpoint; preserve full labels and focus rings. Verify Star/Starred and all watch labels at 320px and enlarged text. Suggested workflow: `$impeccable adapt`.

### A-04 — P3 design recommendation — Make unusual reaction meanings learnable by touch

Class: Recommendation, not an observed accessibility failure. The current source intentionally uses distinct symbols and screen-reader/hover names, per DESIGN.md:482.

Reproduction: open More post actions on Arwen's post at 390×844. It reveals nine icon choices in a 3×3 grid before Quote, Copy link and Report. Heart/Laugh may be familiar; Kindled/Seconded/Illuminating require interpretation. Accessible/sr-only names and `title` attributes exist. A phone hover/long-press tooltip outcome was not tested and should not be promised.

Source: `templates/partials/post.php` renders the shared post-actions partial; live `.post-menu-reactions` buttons have `data-label`, `title`, `.reaction-name.sr-only`; `public/assets/app.css:11428–11429`; binding current component description `DESIGN.md:480–482`.

Proposal: retain the brand symbols and gestures, but add a visible name in the picker or an explicit tap/focus explanation that does not submit the reaction merely to discover its meaning. This is an improvement option requiring a design decision, not an instruction to replace the documented picker. Suggested workflow: `$impeccable clarify`.

## Persona red flags

- Casey, distracted mobile reader: the resolved result takes several viewport lengths to find once the unread shortcut is gone; the permanent identity gutter makes a short thread long. Actual thumb reach, missed taps, browser bars and interruption recovery are untested.
- Jordan, first-time participant: Solved looks like a conclusion but has no attached next step; nine symbols ask for learned meanings before the plain-language post actions appear. The overall heading/Reply path is otherwise clear.
- Sam, accessibility-dependent reader: there is a skip link, useful heading hierarchy and a labelled reply textarea. No essential interaction failure was established in A. The regard markup repeats a visible number plus the number-and-unit in an sr-only span (`post.php:43–46`), a possible verbosity issue to check in the real screen-reader journey; do not call the IAB AX rendering VoiceOver/TalkBack proof.

## Minor observations and exclusions

- The 1280px desktop capture truncates the byline to “Opened by Erestor · Oct 20 · 5 repl…” while Starred/Topic tools remain full. `app.css:10689–10700` explicitly makes identity yield before controls. This is a useful content-priority review, not a separate mobile defect.
- Narrow desktop-pointer composer toolbar buttons measured 28×28px, minimize 26×26px, and send 36×44px. Do not generalize these to phones: `app.css:1499–1509` applies a 44px minimum to the toolbar family when hover is none or the pointer is coarse. A used viewport override alone did not activate those media features. Post menu triggers themselves measured 44×44px at narrow width.
- The reaction menu's live source includes sr-only names and title attributes. IAB's native AX diff briefly displayed blank names for open-menu buttons, so A does not assert an inaccessible-name defect from that tool output.
- The reply textarea was 17px; viewport meta was `width=device-width, initial-scale=1` with no zoom restriction. This is mechanism evidence, not observed iOS focus behavior.
- No real keyboard, browser chrome/safe areas, landscape, 200% text, network/performance, offline/timeout, tab discard, screen reader or send-recovery journey was completed by A. No field metrics were collected. These remain for B or the device script, not inferred passes.

## Evidence captures

The documented CUA screenshot API returned images into the tool transcript; it exposed no supported filesystem-save method. No raster screenshot files are claimed here. Precise captures are available in A's tool transcript:

1. `Inspect thread at 390px phone viewport` — guest, 390×844, thread start and sticky login bar.
2. `Inspect authenticated phone thread and developer capability` — member, 390×844, thread start and resting reply dock.
3. `Read browser identity and catch-up path` — member, 390×844, expanded Catch me up disclosure.
4. `Follow catch-up link to accepted answer` — member, 390×844, accepted answer and successful `#p126` navigation.
5. `Open mobile post actions from its disclosure` — member, 390×844, 3×3 reaction picker plus named post actions.
6. `Inspect expanded reply composer at phone width` — member, 390×844, focused composer and geometry.
7. `Capture 320px thread reading start` — member, 320×844, title/controls/prose and horizontal scrollbar; DOM measurements recorded above.
8. `Compare desktop reading hierarchy and release viewport` — member, 1280×900; viewport then reset.

Browser identity was read with the supported tab CDP Runtime.evaluate command: `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36`. Browser.getVersion was unavailable through the scoped capability; no fallback version was guessed.

IAB tab id 1 was created specifically for A, hidden. Its viewport override was reset before handing ownership back. No other agent tab was used or resized by A after that handoff.

## Questions for synthesis

These are recommendation choices for the parent to adapt into the required final close, not questions sent separately to the user:

1. Which reading improvement matters first: **direct View answer navigation**, **wider phone prose**, or **both**?
2. For reaction learnability, keep **the current compact icon picker** or add **visible gesture names inside the picker**?
