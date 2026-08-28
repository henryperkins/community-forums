# ADR 0030: Imladris thread view — fidelity remediation and deferrals

**Date:** 2026-08-28
**Status:** Accepted and implemented on `main` (baseline `12d9d10b`).
**Relates to:** `templates/thread-view/ThreadView.dc.html` in the Claude Design
project `c3e02753-607c-40b6-994c-9ba1a65bb367` — mirrored at
`docs/design-system/imladris/templates/thread-view/`. The design's standalone
export was read alongside it: its `__bundler/template` block unpacks to the same
document, and its `threadData` asset is the `thread-data.js` this work's fixture
reproduces. The export itself stays out of the repository, as the board-index and
inbox exports did; ADR 0028 (board index)
and ADR 0029 (forum inbox), whose method this follows; ADR 0026 (the Living
Brief redesign, whose content decisions this leaves standing); ADR 0025 (link
previews, whose copy this leaves standing); CLAUDE.md's rule that deferrals and
reversals are recorded in an ADR and never silently dropped.

## Context

`/t/{id}` is the surface the product exists for: the durable topic. It had been
built out over many slices — the Study head, the post-bit, the poll, the Living
Brief redesign, link previews, the topic-tools drawer — each faithful to the
handoff in front of it at the time, and never once rendered beside the design as
one page.

Rendering the design's own compiled runtime beside production at 1440×1200,
against **the design's own dataset**, and measuring the same elements in both is
what produced the list below. `thread-data.js` — the design's topic — is
reproduced row for row by `tests/browser/thread-view-fixture.php`: workflow
status and its two history entries, an assignment, two tags, a three-option
poll, a living brief with three versions and two sources, an accepted answer, a
grouped reply, an anonymous post, reactions, a signature, a referenced topic and
a link preview. A topic with three plain replies and nothing attached cannot
disagree with its design, and most of what follows was hiding in exactly the
states such a topic never reaches.

Both design and production resolve **identical token values** — every
`--text-*`, `--surface-*`, `--gold-*` and `--border-*` measured the same in both
runtimes. Every divergence below is in this surface's own rules.

Three findings are the same class as ADR 0028's first P0 — **a superseded rule
left upstream of its own replacement** — and one is the same class as ADR 0029's
first — **the design system ships a component and the surface hand-rolled a
different one.**

## Decisions

### Fixed

| # | Change | Why |
|---|---|---|
| 1 | The poll and the living brief render **after the opening post**, not above the stream | `ThreadView.dc.html:459-465` puts both on `raw.op`: the opening post asked the question the poll puts to a vote, and the brief summarises that same question. Production rendered them, plus the since-you-last-read panel, as three blocks *above* `.post-stream`, so every reader met a ballot and an AI-written summary before a single word of the topic. On the fixture that is 620px of apparatus before the first sentence. |
| 2 | The reading column is the design's measure, declared once | `ThreadView.dc.html:56` declares `--measure: 646px` as a custom property precisely "so the top bar and the reading column cannot drift apart". Production was `width: min(100%, 860px)` with the prose capped separately at 70ch, so a post byline ran 743px while the sentence under it ran 539px, and the poll, the brief, the catch-up strip and the composer all sat ~200px past the measure they exist to stand beside. |
| 3 | The scroll gutter is given back outside the measure | `.thread-scroll` reserves a stable scrollbar track plus this surface's 8px inset — 23px in Chromium — once app.js makes it the column's own scroll container. That came straight out of the 646px, which is exactly the width the header byline was short of stating its own reply count. |
| 4 | The facts row carries the byline and the roster and **nothing else** | `ThreadView.dc.html:164-181`. The identity group also carried the tag chips, a visible `IN COUNCIL` eyebrow and a Tended-by/Quiet-until group. The row is `flex-wrap: nowrap` on purpose — so the byline elides rather than shoving the controls onto a second line — and with five items competing the one shrinkable item gave up **all** of its width: the topic's own byline rendered as `Opened by Erestor · 5 repl`, and the eyebrow beside it wrapped to `IN`/`COUNCIL`. |
| 5 | The byline states the opened date, and folds the snooze into its tail | `bylineTail` (`ThreadView.dc.html:1568`) is `· opened · N replies · Quiet until X`. Production omitted the date and hoisted the snooze into a separate group. The snooze is the reader's own, like the reply count beside it. |
| 6 | The assignment is stated where it is changed | The design keeps it out of the header entirely and reads it as `wardenSummary` on the drawer's Topic management summary, which production already renders. |
| 7 | The roster's label is its accessible name | `aria-label="In council"` on the stack (`ThreadView.dc.html:174`), not a visible eyebrow. This reverses one assertion of the high-impact fidelity pass; the eyebrow was measurably breaking the row it sat on, and the finding's intent — *the stack is labelled* — is preserved and still pinned. |
| 8 | Tags move to their own row under the facts line | A **deliberate deviation**: the design has no tags in the header at all and reads them in the drawer, but the drawer is signed-in only, so a guest would lose every route from a topic into `/tags/{slug}` — and those links are how a forum's cross-board taxonomy is crawled. They keep the head, one line lower, where nothing has to give up width for them. |
| 9 | The post stamp returns to the byline it dates | `ThreadView.dc.html:281` sets the time inline after the badges. `.post-head .post-time { margin-left: auto }` — inherited from the pre-Imladris post-bit, whose own comment still called it "the kit's right-aligned time" — put it at the trailing edge of the head, **456px** from the name it qualifies. Superseded-rule class. |
| 10 | The regard plinth drops its printed `COMMENDS` caption | `ThreadView.dc.html:246` renders the mark and the number, with "N commends" as the accessible name. The caption was the same word repeated once per post down the whole stream, inside a 48px rail that cannot hold it — which is why an earlier slice had already shrunk it to `.5rem`. |
| 11 | The title takes `--text-display-lg` and 36ch | 2.15rem was 1.6px shy of the token for no reason anything records, and `max-width: 28ch` broke the title eight characters before the design's `36ch`. |
| 12 | The standing chips take `--text-chip` | `.6rem` rendered them at 9.6px against the design's 11.2px (`ThreadView.dc.html:148`). |
| 14 | The Topic tools pill states the reader's watch | `ThreadView.dc.html:187` appends `watchLabel` — the control that opens the drawer says what the drawer would tell you about the one setting a reader changes most. |
| 15 | The poll is a sunken panel with raised options, under one eyebrow line | `ThreadView.dc.html:453`. Production had it inverted — a raised card with a gold left rule holding *sunken* options, so it sat at the post's own elevation and three ballot rows read as one field of tan — and its head was three objects: a gold ✦ tile, a two-line Poll/Choose-one label, and an `OPEN` status pill on the far right, all announcing a control the question below them names. The design's eyebrow is `Poll · choose one`, and `· closed` when it is. |
| 16 | Poll results are a line and a bar, not three bordered cards | Three bordered boxes inside a bordered panel drew four nested edges for one question. The bar fills `--rule-gold` on a raised track, and the reader's own vote is marked with the commend star rather than a `YOUR VOTE` pill. |
| 17 | The living brief is a gold-edged panel at the design's radius and padding | `ThreadView.dc.html:509`: `1px solid var(--gold-200)`, `--radius-md`, `15px 17px`. `--border-hair` at `--radius-lg` made it a second post. Its label takes `--text-chip` and the commend star the design marks it with. |
| 18 | The brief's provenance moves behind *Where this came from* | `ThreadView.dc.html:520-543`. Version, publication stamp and the source posts printed unconditionally — a metadata line above the summary and an `<h3>Sources</h3>` list below it — so the three-sentence artifact a reader came for arrived wrapped in six lines of bookkeeping. It is a `<details>`: one click, no JavaScript, nothing lost. |
| 19 | The brief's stamp reads in the surface's own register | `2026-08-27 23:00:57 UTC` was the only machine time on a reading page; every post byline beside it reads `Aug 27 at 23:00`. `published_at_raw` is added to the view model so the render can stamp it with `post_datetime()`; the absolute value stays on the `<time>` element. |
| 20 | Brief sources take `--artifact-link` | The token this system spends on a pointer *into* the record — the same choice `Search.dc.html` and the inbox's board reference make (ADR 0029 #2). Contrast is asserted in both registers. |
| 21 | Related topics are **one row, after the stream** | `ThreadView.dc.html:659`. The same idea arrived in two shapes at two ends of the page depending on a state the reader cannot see: a three-card grid inside the brief when one existed, a headed `Related topics` section *above* the stream when one did not. The overlay's `reason` keeps its place as the chip's title. |
| 22 | *Since you last read* becomes the *Catch me up* strip | `ThreadView.dc.html:196-215`: read once, then never again, so it costs **one line** until it is asked to open. It was a full panel — a heading, a count paragraph and a bulleted excerpt list — printed above the topic on every visit with anything unread. A `<details>`, so it opens with JavaScript off. |
| 23 | The unread boundary states how much is past it | `unreadLabel` (`ThreadView.dc.html:231`) is `New since you last read · N replies`. `First unread` named the rule without answering the question a reader crossing it is holding. The rule is asymmetric, as the design draws it, with the gold dot. |
| 24 | An unfurl uses the design system's own `.link-preview` | `components.css:1293` ships the component and this surface had **no consumer for it**: a preview rendered as a `.reference-card` with the host inside `.badge.badge-muted` — the product's uppercase *status* pill, the same object that shouts `SOLVED` — drawing a full-width shouting bar over every card. ADR 0029's first class. Copy and controls are unchanged (see *Deliberate keeps*). |
| 25 | A reference card is drawn as the quotation it is | `ThreadView.dc.html:316-327`: a gold rule, the quote mark, and the board it came from as a gold-ink eyebrow. It wore the same `.badge.badge-muted` for the word "Thread". Scoped to `.post-stream`, so the DM reading room keeps the compact shared card. |
| 26 | The author signature rules off with a solid hairline | The design uses `1px solid var(--border-hair)`; production drew it dashed, a treatment this system otherwise reserves for absence (the removed-preview stub). |
| 27 | The drawer commits a watch and a snooze in one press | `ThreadView.dc.html:769-786`: a segmented control and three pills. Production had a `<select>` and a **Save watch** button, then a second `<select>` and a **Save snooze** button — two interactions and a page load each, for the two settings whose whole value is that they are quick to change. Three one-value forms per axis, so it works with JavaScript off exactly as it does with it. |
| 28 | Pin and lock are switches that state the state | `ThreadView.dc.html:906-909`: *Pinned above the board*, *Locked to replies*. `Pin`/`Unpin` names the act and leaves the reader to infer the state from the verb's tense. `role="switch"` with `aria-checked`; the write is still one POST. |
| 29 | The drawer's action rows are rows | `.topic-tools-section-body` is a grid, so a `<form>` holding one link-button filled its 364px track and centred its label: five management actions rendered as five centred lines of body prose. |
| 30 | The drawer says how to leave it | `drawerFootNote` (`ThreadView.dc.html:920`), staff and member variants. A modal with a scrim and no stated exit is one a keyboard reader has to guess at. |
| 31 | Superseded rules deleted | `.thread-operational-facts` (markup gone), `.related-topic-fallback` (markup gone), the pre-Imladris `.link-preview-item` block that dressed a `.reference-card`, and the `.poll-card { padding: 15px 18px 13px }` override that outranked its own replacement. |

### Deliberate keeps — where production does **not** follow the design

| # | Kept | Why |
|---|---|---|
| A | The three-pane app shell | The design's thread view is a standalone page with its own minimal top bar and no rail. Production keeps the member topbar and the board rail, as `/` and `/inbox` do — the design's own retired rail was a *thread-specific* right-hand reference rail (roster, tags, ledger), not the product's navigation, and its comment says so. |
| B | The reading column scrolls internally | `.thread-scroll` is the fixed-height column of the Community Inbox shell, not the design's whole-page scroll with a sticky dock. Changing it would change the shell, not this surface. |
| C | The breadcrumb's first hop still reads **Forum index** | `ThreadView.dc.html:141` says *Home*. The 2026-08-02 forum-surfaces spec names three destinations in plain language precisely because they are confused — *Forum index* (`/`), *Forum inbox* (`/inbox`), *Messages* — and `/` carries a `Forum index` eyebrow of its own. The prototype has no such neighbours to disambiguate from. A product naming decision outranks a prototype's word for the same link. |
| C2 | The brief's pause sentence is unchanged | `ThreadView.dc.html:517` says *"Automatic refresh is paused, so this brief may be behind the replies below."* The 2026-08-26 living-brief redesign spec pins the production sentence **verbatim** (*"Copy, exactly: Automatic refresh is paused for this topic. The brief stands as published."*), and ADR 0026 §2a shipped it two days ago. A dated exact-copy instruction outranks a prototype's phrasing. |
| C3 | Link-preview copy and controls | The design says *Remove this preview* / *Restore* with an ownership-specific hint sentence; production says *Remove preview* / *Restore preview* / *Link preview removed from this post.* Those strings are ADR 0025's, pinned by four browser and two PHPUnit assertions, and this is a visual remediation. The ownership hint the design adds **is** adopted, since production knows the owner. |
| D | The Living Brief's content model | ADR 0026 settled what the brief says and which curator controls it carries, six weeks after the design was drawn. Only its frame, its label, its stamp's register and the placement of its provenance change here. |
| E | The composer's *Reference a post* / *Paste a link* pickers | The design attaches a reference through a search palette and resolves a pasted link in the composer. Production creates both by typing a URL into the body, server-side, which is the shipped `ContentReferenceService` / `LinkPreviewService` contract. A picker is a feature, not a fidelity fix. |
| F | The post action toolbar's own set | The design's `···` menu carries *Make wiki*, *Reveal author — logged* and a warden *Remove* with a required reason; production already carries all three, plus a wiki-revision revert the design has no equivalent for. |

### Deferred

| # | Deferred | Evidence a follow-on needs |
|---|---|---|
| D1 | The catch-up strip has no dismiss control | The design's ✕ is client-only state its prototype needs because it has no read tracking. Production advances the read position on GET, so the strip clears itself on the next visit. A dismiss that survives a reload needs a row to write to, and there is no such column; adding one for a control the reader can already satisfy by reading is not worth a migration. Revisit if the strip is ever shown on a topic the reader is not reading. |
| D2 | The design's warden roster in Topic management | `ThreadView.dc.html:869` lists every warden of the board as a monogram row to assign with one press. Production's assign control is a username text field, because `ThreadWorkflowService::canStaffAssignThread` accepts any assignable member, not a fixed roster, and `moveDestinations`-style scoping for assignees does not exist yet. |
| D3 | The split/merge dialog's reply picker | The design lists every reply in the topic with a checkbox and an excerpt; `partials/thread_restructure.php` already does this, and was not re-measured in this pass. |
| D4 | The brief's `Drawn from` line does not mark a source that is gone | `ThreadView.dc.html:527-535` strikes through a source post that has been deleted, split away or merged out, and says so. `ThreadIntelligenceViewService::sources()` **withholds** such rows instead, so the render cannot tell "gone" from "never cited". Making the difference visible is a service change, not a template one. |
| D5 | `thread-view-study.spec.ts`'s *Inbox-inserted topics* test is red, and was before this work | It asserts that the inbox's reading pane injects a `[data-thread-study]` and that app.js enhances it idempotently. Since ADR 0029 (`12d9d10b`) the pane renders `partials/inbox_preview.php` — an `.inbox-preview`, never a thread view — so the mechanism has no consumer left. Its row locator is stale on top of that (`.thread-row`/`.thread-title` → `.inbox-thread-row`/`.inbox-row-title`), and the seed's 26 Thread Intelligence topics push the named topic off the first page of the queue besides. Whether the reading pane should inject the full thread view again is a product question, not a fidelity fix, so the test is annotated and left as found rather than half-repaired. |
| D6 | `community-inbox-theme.spec.ts` is 13 red, and was before this work | Same cause as D5, on the same surface: every failing assertion waits on `[data-inbox-list] .thread-row`, `a.thread-title` or `[data-inbox-reading] .thread`. Rendered against the live e2e inbox those three match **0** elements while `.inbox-thread-row` / `.inbox-row-title` match 20 — the classes ADR 0029 renamed them to. This work touches none of `templates/inbox.php`, `templates/partials/inbox_thread_row.php`, `partials/inbox_preview.php`, the inbox controllers or `app.js`; its only edit to that spec file is one locator (`Topic tools`, below), which loosens a matcher rather than tightening one. Repairing the inbox's own browser evidence belongs to the inbox, not here. |
| D7 | `imladris-forum-surfaces.spec.ts` is 2 red, and was before this work | Both are on other surfaces: `.inbox-tabs a` matches nothing since ADR 0029 renamed the queue's chrome, and the forum index's 800px overflow check reads 785 against 800 after ADR 0028's board-index rewrite. Confirmed pre-existing by running both against `HEAD`'s `public/assets/app.css` with this branch's templates: same two failures, same numbers. |
| D8 | `composer-shell.spec.ts`'s *reduced motion* test is red, and was before this work | It waits on `.composer-send > span` inside the **new-thread** composer on a board page. Nothing here touches the composer shell, the board template or the composer's stylesheet; confirmed pre-existing by running it against `HEAD`'s `public/assets/app.css`. `field-error-a11y.spec.ts`'s `:user-invalid` test and one `a11y.spec.ts` admin/DM pair are red on `main` for the same reason and are likewise untouched. |
| D9 | Guests still see no `Topic tools` | Correct in both, but production also gives a guest no route to the tag list on a topic — see #8, which is why the tags stayed in the head. |

### Superseded by this ADR

`docs/superpowers/specs/2026-07-12-thread-view-study-design.md` was written from
the **previous** Study handoff and describes the implementation this replaces.
Three of its sentences no longer hold, and are superseded here rather than left
to be read as current:

- *"The existing Living Brief remains above the post stream"* → #1.
- *"Its visual treatment becomes the handoff's raised parchment card with a gold
  left rule, compact label/meta row…"* → #17, #18.
- *"The deterministic 'Since you last read' context remains readable content
  below the memory slot"* → #22. The capability keeps its name in `USER.md`; the
  strip that carries it is labelled *Catch me up*, as the design labels it.

Everything else that spec fixed — the participant stack's privacy, the poll's
voting and result-visibility rules, `Your watch` as the home of subscription
frequency, Star as the one header action, the tools markup rendered once and
enhanced per instance — is untouched.

## Consequences

- **`.thread-operational-facts` is retired.** `AppImladrisFidelityTest` pinned it, and its assertion is inverted here with the reason recorded in the test itself. That slice's finding was real — the byline was overflowing — and its fix created a third group on a row that can hold two; the design's answer is to move one of the three facts into the byline and the other out of the header.
- **`thread-participants-label` is retired**, per #7, with the same treatment.
- `ThreadIntelligenceViewService` gains one key, `published_at_raw`. No behaviour changes.
- Three new partials — `partials/poll.php`, `partials/thread_memory_slot.php`, `partials/catch_up.php` — exist so the poll and the brief can be built once and emitted inside the stream.
- A page that does **not** carry the opening post still gets the poll and the brief, at the head of its own stream. The design's stream is four posts long and its brief simply vanishes on page 2; on a topic with a hundred replies that would hide the one artifact written to spare a reader the backlog, from exactly the reader who is deepest in it.

## Evidence

`docs/evidence/imladris-thread-view-remediation/` — twelve frames, each written
by an assertion in `tests/browser/thread-view-remediation.spec.ts` that measures
rather than looks: the shared measure across four regions, the document order of
the five regions, the byline's `scrollWidth` against its `clientWidth`, the gap
between the last byline badge and the stamp, the poll panel's and its options'
computed backgrounds, the brief's border colour and padding, the source link's
computed contrast against its own panel in **both** registers, the strip's
closed height and its no-JavaScript open, and the unread rule's asymmetric
flex bases.

`tests/browser/thread-view-fixture.php` seeds the design's own topic.

The shared `docs/evidence/browser/` set is **not** refreshed here. Tonight's runs
were per-spec and desktop-only, so committing them would have mixed vintages and
split the desktop and mobile halves of the same captures; refreshing that set is
`npm run evidence`, one run, and it belongs to its own commit. The captures under
`docs/evidence/browser/desktop/80-…89-` therefore still show the pre-remediation
thread view until it is made.

Verified: `tests/Integration` OK — 2098 tests, 12783 assertions, 1 skipped.
`tests/Unit` OK — 636 tests, 7368 assertions. Playwright, desktop project:
`thread-view-remediation` 12 passed, `thread-view-study` + `thread-content-presentation`
18 passed / 1 failed (D5, pre-existing), `thread-intelligence` + `link-previews`
13 passed, `role-assignments` (CAPABILITIES_MODE=enforce) 3 passed,
`rich-content` 2 passed. `php bin/build-imladris-assets.php --check` current —
the application-surface digest was re-baselined in this commit because the
digest's roots are `templates/` and `public/assets/`, both of which this work
edits; the member-surfaces bridge inside `app.css` is untouched, so
`components.css` needed no sync.

## Browser-facing test updates

The accessible name of the Topic tools trigger is now `Topic tools · every
reply` (#14), so the six specs that opened the drawer with
`{ name: 'Topic tools', exact: true }` name it `{ name: /^Topic tools/ }`
instead — the button is still the only control whose name starts that way.
`role-assignments.spec.ts` reads the lock control as a `switch` named *Locked to
replies* (#28), and `thread-view-study.spec.ts` parks the pointer before
asserting the post toolbar is at rest, which the test claimed but never did.
