# Independent Assessment A — Inbox controls and menus

Target: `templates/inbox.php` and the Inbox presentation in `templates/partials/thread_row.php`, HEAD `573f905d`. Mode: Operate. No detector output or previous critique was consulted. Read-only source and private fixture inspection; no action POSTs were submitted. Browser: fresh independent Playwright Chromium session `critique_design8031`, closed after one batched pass at 1440×1000 and 390×844, explicit light/dark set only in that document. Captures are the 24 PNGs in this directory. Supplied three iPhone photos were also inspected. Native iPhone Safari, failure flows, action writes and assistive technology were not exercised.

## Design specificity verdict

Authored and recognisably Imladris. Parchment/twilight registers, literary serif titles, restrained evergreen/gold cues and the durable-topic list have a clear product identity. This is not a category-interchangeable dashboard. The weak point is the everyday control family: toolbar disclosures, bulk buttons, row actions and help use different dimensions, type roles and panel geometry. The hall is coherent; the tools on its table feel assembled from separate iterations.

The supplied images are accurately pointing to an optical and interaction problem, even where text is technically contained. At 390px, Show receives a 147px-wide two-line control while Sort is a 48px-wide, centred two-line control. Both are 44px high, but their distinct alignment, type hierarchy and hidden chevrons make the bar look squeezed and uneven. Fixing only overflow would leave the complaint intact.

## Nielsen scores, 0–4

| # | Heuristic | Score | Key evidence |
|---|---|---:|---|
| 1 | Visibility of system status | 3 | Scope/order/count/unread/selection are visible. Snooze success copy is generic rather than naming its return condition, source-only. |
| 2 | Match with the real world | 3 | Topic, read, star and snooze are familiar. Four near-overlapping dates and the fixed Monday shortcut require translating intent into a schedule. |
| 3 | User control and freedom | 2 | Menus dismiss with Escape; clearing a snooze exists for snoozed rows. There is no stay-off-until-I-return choice or nearby undo; current scope can remove the affected topic. |
| 4 | Consistency and standards | 2 | Toolbar targets are 44px, row trigger 34px on mobile, row menu items 32px and bulk buttons 30px. Panel padding/radii/type differ. |
| 5 | Error prevention | 2 | Page-limited bulk language and server selection validation help. Small, adjacent snooze choices and the Monday-only bulk/keyboard defaults invite unintended durations. |
| 6 | Recognition rather than recall | 3 | Scope, order and current counts are shown, and menu text is explicit. Removing both disclosure chevrons makes the mobile scope/order look like static fields. |
| 7 | Flexibility and efficiency | 3 | Bulk selection, keyboard j/k/Enter/e/s/# and independent URL-backed choices support triage. The snooze shortcut silently chooses Monday and offers no alternate intent. |
| 8 | Aesthetic and minimalist design | 2 | Strong palette and calm content, weakened by disproportionate control widths, mixed panel treatments, tiny action labels and two-line bulk wrapping. |
| 9 | Error recovery | 2 | Bulk validation returns to the view with a plain selection message, but does not explain a next step or preserve selection. Source-only; failure flow not exercised. |
| 10 | Help and documentation | 3 | Help explains Show versus Sort and exposes task-oriented shortcuts. It occupies much more visual space than the actions and repeats the obsolete Monday commitment. |
| | Total | 25/40 | Acceptable: significant targeted improvements needed. |

These are surface-level design scores, not a functional certification. H1/H3/H5/H7/H9 include bounded source review where writes were intentionally not exercised.

## Cognitive load

Intrinsic load is low: choose what to read or postpone. Extraneous load is moderate because the same postponement has four row dates, one different fixed bulk date and one fixed keyboard date. Germane load is otherwise reasonable: the queue metaphor is familiar and Help explains the two independent choices.

Checklist failures: **visual hierarchy**, **one thing at a time**, **minimal choices**. Grouping, working memory and progressive disclosure pass. Three failures = moderate cognitive load.

Specific overloaded decision points: row menu has five visible actions (read state plus four snooze variants); scope menu has twelve choices across three legitimate groups, but the 420px scroll window hides lower options without a strong continuation cue. The row menu is the avoidable overload: separate the read action from a labelled Snooze group containing exactly two choices.

## Emotional journey

Arrival feels calm and credible. The topic titles and gold attention cues give an immediate sense of what matters. The first emotional valley comes at the mobile toolbar: a broad blank scope field sits beside a cramped Sort control, with no visible chevrons to explain how either opens. Selecting a topic makes the page feel busier as a pale bulk box inserts two rows of undersized buttons. Opening its row menu provides clear words, but the four subtly different dates slow a simple decision. The end of snoozing currently says only “Topic snoozed” or a generic count of updated topics, source-only; naming the return condition and offering a clear path back would finish with reassurance.

## What works

1. The existing Imladris identity supports durable conversation. Serif, parchment/twilight, subtle greens and sparse gold give the inbox warmth without competing with topic titles.
2. Show and Sort remain independent, with current values, topic counts and URL state. That is a sound triage model worth preserving.
3. The underlying menu model is restrained: native disclosures/forms, one open menu, Escape dismissal, contextual read labels and task-focused Help. The polish should build on this structure.

## Priority issues

### P1 — Snooze expresses too many dates and omits the user's actual second intent

**What:** The row menu offers Later today/Tomorrow/Monday/Next week; bulk and # choose Monday only. None can stay off until the member turns it back on.

**Why:** The same task means different things depending on how it is invoked, and four near-neighbour dates force unnecessary comparison. The owner's requested persistent option needs real semantics.

**Fix:** A separated Snooze section containing exactly **“Til tomorrow”** and **“Til I turn it back on”**, shared by row and bulk actions. Make # open that choice or use a clearly documented single default consistent with the agreed behaviour. Retain a clear “Turn back on” action for the snoozed state and explain the condition in feedback. The persistent choice must be truly indefinite and reversible; a label over a long future date does not implement the promise. `parseSnooze()` currently defaults unknown values to null, which clears snooze, so this cannot be a copy-only substitution.

**Suggested command:** `/impeccable clarify`, followed by `/impeccable harden` when behaviour is authorised.

### P1 — Mobile triage actions are too small and disagree with the toolbar

**What:** Mobile row trigger is 34×34px, row-menu items are 32px high, bulk buttons are 30px high with 11.2px labels. Toolbar controls are 44px high.

**Why:** Small adjacent choices are harder to tap and read one-handed; the lower-density menu family looks less deliberate than the bar above it.

**Fix:** Give all action targets a 44px touch floor and one readable action-label role. Use full-width menu rows. Let bulk controls wrap in an intentional grid or concise action disclosure rather than a tiny irregular button cloud. Preserve density in the topic list separately from touch affordances.

**Suggested command:** `/impeccable adapt` and `/impeccable polish`.

### P2 — Scope and Sort have incompatible optical layouts

**What:** At 390px the scope is 147×44px and left-aligned; Sort is 48×44px and centred, with value text reduced. Scope eats the flexible width, Sort looks like a cramped miniature box, and both chevrons disappear.

**Why:** Their shared purpose is obscured, and the bar looks poorly fitted even though none of the text visibly clips in the normal-size pass.

**Fix:** Keep the authorised one physical toolbar row, but design the two disclosures as one control family: matching label/value stack, alignment, inset and vertical rhythm; reserve the 44px icon actions; allocate a deliberate minimum width for Sort before giving remaining space to Scope. Retain a small chevron or equally explicit disclosure cue rather than removing it at the exact width where the label is abbreviated. Use semantic type/space/border tokens.

**Suggested command:** `/impeccable layout`.

### P2 — Menus have competing container and text rhythms

**What:** Scope/Sort/Actions use a 280px, 12px-radius panel with 8px inset; row actions use a 196px, 7px-radius panel with 6px inset, 32px items and body-font labels. Help expands into a large block directly under a page-wide write action. “Snooze ·” is repeated four times.

**Why:** The user must recognise several menu dialects for the same Inbox, and the row menu reads as dense fine print while Help reads like an article. More area does not yield clearer decisions.

**Fix:** One menu primitive with consistent 7–12px system geometry, 8px inset, readable label type, 44px rows and a predictable small anchor gap. Let width follow content with a viewport cap; the long persistent snooze phrase must wrap cleanly or fit without shrinking. Use a subtle divider and small Snooze group label; remove repeated prefixes. Separate concise Help/shortcut material visually from the page action while retaining progressive disclosure.

**Suggested command:** `/impeccable polish` and `/impeccable typeset`.

### P3 — The list separator and bulk border silhouettes need a clean finish

**What:** Each bottom hairline turns upward through the row's rounded lower corners, producing a half-card silhouette. The pale bulk box uses a distinct border treatment that becomes especially bright in twilight.

**Why:** The combination looks like incomplete card edges rather than a confidently ruled queue, matching the user's awkward-border observation.

**Fix:** Make separators straight, inset rules independent of the rounded hover/selection wash. Use the shared semantic boundary token for the bulk surface in both registers and the same restrained corner geometry as adjacent functional controls. Do not introduce chamfers or replace the incumbent identity.

**Suggested command:** `/impeccable polish`.

## Persona red flags

- **Casey, distracted mobile member:** The 34px ellipsis, 32px row actions and 30px bulk buttons are smaller than the comfortably readable 44px toolbar. A two-line bulk cloud and four date variants make a quick postponement slower and more error-prone.
- **Alex, habitual triager:** Bulk and # hard-code Monday while the row offers four durations. Alex cannot express “hide it until I return” and must remember different defaults for equivalent paths.
- **Jordan, new member:** Scope and Sort lose their disclosure arrows on mobile, so the broad For You/count area can read as a static summary. Help explains Show even when the visible Show caption is hidden; the permanent snooze state needs an explicit route and reversal label.

## Minor observations

- “Sort: Activity” is an intelligible abbreviation, but the rendered 11.2px value beside the 13.76px label is too fine; do not solve fitting by making the critical current value the smallest word.
- The scope menu's groups are useful, but the lower Snoozed/Topic state destinations are below its initial scroll window. Add a restrained scroll/continuation cue or reduce only noninteractive group gaps.
- A generic “topic updated” flash does not tell a member whether it was read, starred or snoozed. Action-specific copy is a low-complexity reassurance improvement once changes are authorised.

## Repair direction

A scoped refinement, preserving the all-serif Imladris register and the shared single-row toolbar: one disclosure family, one menu primitive, one touch-target rule, one two-choice snooze contract across row/bulk/keyboard, then straighten the ruled list edges. This is a component-coherence problem with a small behavioural seam, not a need to redesign the Inbox.

## Provocative questions for synthesis

1. Should # open the two-choice snooze menu, or immediately use the agreed default?
2. Should “Til tomorrow” mean the next morning in the member's timezone or exactly 24 hours? Current source uses 24 hours UTC-relative; that distinction must be made explicit before behaviour changes.

## Memory provenance

Memory helped identify the final application cascade and preservation preference; both were verified against current DESIGN.md. Registry used: MEMORY.md:318–323, rollout id 01a11215-ff0e-71c2-abac-ae61cded4359. No past critique was read.
