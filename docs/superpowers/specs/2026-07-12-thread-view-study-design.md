# Thread View — The Study

**Date:** 2026-07-12
**Status:** Approved with review amendments; awaiting amended-spec confirmation
**Owner:** RetroBoards core theme

## Context

The attached Imladris design-system handoff selects [ThreadView.dc.html](../../design-system/imladris/templates/thread-view/ThreadView.dc.html), “The Study,” as the production direction for the RetroBoards topic page. Its central change is structural: a topic should read as a quiet, durable record, while personal controls and moderation tools remain close at hand without occupying the space above the first post. The complete handoff notes are committed at [design_handoff_thread_view/README.md](../../design-system/imladris/design_handoff_thread_view/README.md); the template’s `support.js`, `ds-base.js`, and `thread-data.js` dependencies sit beside it so the normative prototype remains reviewable and renderable.

The current application already implements the underlying behavior through server-rendered forms and capability-gated services: starring, subscriptions, snooze, assignment, workflow status and history, tags, polls, Living Brief curation, accepted answers, reactions, anonymous-author reveal, wiki posts, pin/lock, and split/merge. The work therefore adapts the selected visual design to the existing PHP and progressive-enhancement architecture. It does not copy the prototype runtime or replace authoritative server behavior with client-side state.

`DECISIONS.md`, `DESIGN.md`, `USER.md`, `COMMUNITY.md`, `COMPOSER.md`, and the repository security invariants continue to outrank prototype behavior. In particular:

- authorization remains capability- and board-scope-based rather than inferred from a generic “staff” role;
- anonymous authors remain masked in every public render; the existing reveal action stays a separate audited disclosure and does not mutate the public byline;
- all writes continue through the existing POST endpoints, CSRF checks, services, transactions, and redirects;
- the canonical Markdown composer and its no-JavaScript textarea remain the only reply path; and
- feature flags continue to determine which subsystems and controls exist.

## Scope

### In scope

- the canonical `/t/{id}-{slug}` topic page and the same topic markup when fetched into the Community Inbox;
- a high-fidelity 860px Imladris reading surface with a deliberately quiet topic head;
- capability-driven Topic tools presented as a desktop drawer and mobile bottom sheet;
- an in-flow, native-details no-JavaScript fallback for the same tool forms;
- status, byline facts, tags, participant stack, Star, and Topic tools placement;
- Living Brief and poll card fidelity while preserving their existing data and disclosure contracts;
- accepted-answer, identity, title, reputation, grouping, reaction, and per-post action treatments;
- a hover/focus toolbar on pointer devices and an always-reachable action row on touch devices;
- the existing reply composer restyled as the selected sticky dock without changing its submission contract;
- strict-CSP JavaScript for drawer, modal, menu, focus, copy-link, quote, and accordion enhancement;
- light/dark/system theme behavior (the parchment and twilight display registers), responsive layout, reduced motion, keyboard, no-JavaScript, and accessibility behavior;
- focused PHPUnit and Playwright regression coverage plus desktop/mobile evidence captures.

### Out of scope

- copying the documentation-only `support.js`, `ds-base.js`, Design Component runtime, or prototype state into production templates or `public/assets/`;
- new database columns, migrations, routes, permissions, or business services;
- AJAX workflow/status/tag/poll/moderation mutations;
- simulated Undo actions for destructive requests;
- poll reopening/removal, public anonymous-author unmasking, or other prototype-only state with no current backend contract;
- replacing the application top bar, global search, navigation rail, theme tokens, icon partial, or shared composer engine;
- changing Thread Intelligence generation, eligibility, publication, provenance, or retention;
- changing the real reaction set merely to match the prototype’s fictional Commend/Seconded/Illuminating set.

## Approaches considered

### 1. Server-first structural redesign — selected

Recompose the existing PHP templates around the selected visual hierarchy, extract focused partials for Topic tools and split/merge, retain each real form and capability gate, and use small delegated JavaScript only to lift the same markup into drawers, sheets, menus, and a modal. This reaches the target faithfully while keeping the canonical routes and no-JavaScript journey complete.

### 2. CSS-only rearrangement — rejected

CSS can quiet the current stacked panels, but it cannot provide correct drawer semantics, focus restoration, Escape/scrim behavior, exclusive accordion state, or a reliable mobile sheet. It would also leave the current forms in a DOM order that does not match the selected information architecture.

### 3. Client-rendered prototype replica — rejected

Porting the Design Component state machine would duplicate server state, permissions, validation, and write behavior. It would conflict with the vanilla PHP architecture, strict CSP, canonical POST forms, dynamic Inbox loading, and the requirement that reading and posting work without JavaScript.

## Design

### 1. Page structure

`templates/thread.php` remains the canonical leaf template. It is reorganized into four regions inside `.thread-conversation`:

1. `.thread-scroll`, containing the topic head, reading aids, post stream, and pagination;
2. `.thread-dock`, containing the reply composer or the existing guest/locked/permission notice;
3. one server-rendered Topic tools aside for signed-in viewers when at least one tool section is available; and
4. the split/merge disclosure for viewers with that capability.

On the standalone route, the conversation column is centered and capped at 860px. Inside the Inbox reading pane, the same template fills the available reading region but keeps the same readable maximum. The global top bar is unchanged.

The prototype archive is reference input only. Production uses the tokens already defined in `public/assets/app.css`, the existing monogram and icon partials, and the application’s system-serif fallback stacks.

The conversation/scroll/dock regions, participant stack, guest join bar, and ten-minute grouping rule are already shipped. The implementation plan must treat those as retained baselines to restyle or reposition, not as new subsystems to build.

### 2. Quiet topic head

The header contains only information and two immediate actions:

- the existing Home/board breadcrumb;
- a balanced display-serif H1 with inline Pinned, Locked, and canonical workflow-status chips;
- a single Solved chip when an accepted answer supplies the solved state and workflow is unavailable, avoiding duplicate “Solved” markers;
- a byline with the masked opener, reply count, current assignment, and the signed-in viewer’s snooze fact when present;
- read-only linked tag chips;
- an “In council” participant stack using the repository’s non-anonymous participant data;
- the existing Star form; and
- a Topic tools trigger for signed-in viewers with available sections.

When `topic_workflow` is enabled, exactly one workflow-status chip is always shown, including **Open** in the resting state. It is word-plus-color and maps the real values `open`, `needs_answer`, `solved`, `decision_made`, and `archived` to the selected Imladris status tokens. When workflow is disabled but an accepted answer exists, one fallback Solved chip is shown. Pinned and Locked remain separate neutral/gold facts.

Assignment and snooze are rendered as byline facts, never as header controls. Tag editing, subscription frequency, status changes, assignment, pin/lock, memory curation, poll creation/closure, and split/merge leave the header entirely.

This intentionally places subscription frequency in **Your watch** rather than restoring the older Bell/Bell-off header treatment described in `DESIGN.md` §6.10 and `USER.md` §4.6. It follows the selected Study handoff and the current consolidated thread-action direction: Star remains the one-tap header action, while notification configuration stays on the thread page behind Topic tools. Subscription persistence, channels, frequency, and precedence do not change.

The participant stack remains privacy-safe: anonymous contributors are excluded by the repository and never inferred in the template. On narrow screens it may collapse to a count label rather than overflow horizontally.

### 3. Reading aids

The existing Living Brief remains above the post stream and retains all authoritative disclosure content: the label’s lineage link to `/privacy#thread-intelligence` (the required member path to the processor disclosure), the meta row’s curator/automation attribution, version, publication time, sanitized body, readable sources, reference cards, and related topics. Its visual treatment becomes the handoff’s raised parchment card with a gold left rule, compact label/meta row, and readable 72ch body. Curators receive a “Curate in Topic tools” button that opens the drawer directly to the memory section; without JavaScript, the same curation section remains reachable in flow.

The poll remains content rather than a tool panel. Its current voting and result-visibility rules are preserved. The card adopts the selected compact header, question, option rows, result rows, `<meter>` bars, and footer. Poll creation and the existing one-way Close action move into Topic tools. The UI does not offer reopen/remove controls because no such production contract exists.

The deterministic “Since you last read” context remains readable content below the memory slot. The related-topic fallback remains the existing `elseif` inside that slot when no Living Brief is available. Both share the reading treatment and stay outside moderation controls.

### 4. Topic tools

The tools markup is rendered once. Without JavaScript it is an in-flow `<aside>` beneath the topic head whose sections are native `<details>` disclosures. A small initializer stamps that specific thread instance as enhanced only after its trigger and delegated handlers are available; the trigger then becomes visible and the same aside becomes:

- a fixed right drawer at desktop widths, no wider than 392px;
- a full-width bottom sheet capped at 86dvh at 768px and below; and
- a layered surface above a real scrim.

The enhanced drawer has an explicit heading, close button, `aria-labelledby`, `aria-modal="true"`, initial focus, Tab containment, Escape dismissal, scrim dismissal, and trigger-focus restoration. JavaScript adds dialog semantics only in the enhanced presentation; the no-JavaScript aside keeps ordinary document semantics. Opening the drawer locks background scrolling without changing server state.

Controls use data hooks such as `data-topic-tools-open`, `data-topic-tools`, `data-topic-tools-close`, and `data-topic-tools-scrim`. Event handling is delegated from `document` so tools also work when a topic is fetched into the Inbox after `app.js` has initialized. The Inbox insertion path runs the same idempotent initializer on the newly inserted `.thread-conversation`; `.has-js` alone never hides the only copy of the forms.

Only one accordion section stays open in the enhanced drawer. Native `<details>` behavior remains authoritative without JavaScript. The available sections are derived from real feature flags and capability booleans, not role names:

1. **Your watch** — existing thread subscription frequency and personal snooze forms when their features are enabled.
2. **Standing** — current status and status ledger for workflow-enabled topics; authorized viewers also receive the existing status-change form and reason field.
3. **Tags** — linked current tags for readers and the existing checkbox editor for viewers with `can_edit_tags`.
4. **Living Brief** — the exact existing curator forms for `can_curate_memory`: resume automation when paused; otherwise refresh; publish a summary with `body` plus `source_post_ids`; retire the current summary; restore one item from history; and add a related topic.
5. **Topic management** — assignment controls, clear accepted answer, poll creation/closure, pin, lock, and split/merge, each included only when its existing capability permits it. The production label is capability-neutral because some topic owners can manage a poll or assignment without being staff.

All forms retain their original action, method, field names, CSRF field, validation, and permission gate. A successful action reloads through the existing redirect and flash. No drawer action pretends to succeed locally.

Guests keep today’s read-only status ledger. Because they do not receive Topic tools, a closed, in-flow Status history disclosure remains available near the quiet header when history exists. Signed-in viewers receive the same ledger in Standing; no viewer gets two rendered copies.

### 5. Split and merge

The existing split and merge forms move into one focused partial. Without JavaScript they remain a native disclosure within Topic management. With JavaScript, opening that disclosure closes the drawer and lifts its contents into the selected 600px modal (full-screen sheet on mobile) with a scrim, close button, Escape behavior, focus containment, and focus restoration.

The real backend contract remains visible:

- split selects non-OP posts and requires a new topic title;
- merge requires the numeric target topic ID;
- the server remains responsible for validation, authorization, logging, redirects, and repair-safe mutations; and
- no client-side Undo control is shown.

### 6. Post stream

Posts become quiet reading rows rather than a stack of raised cards. Each row has a 48px identity column and a flexible prose column. The accepted answer receives a green underlay plate, border, “Marked as the answer” flag, and gilt monogram. OP and accepted authors retain the gilt treatment.

The post query includes the author’s cosmetic title override, and `ThreadController` resolves the displayed title through the existing `TitleService`. The resulting real labels (`New`, `Member`, `Regular`, `Veteran`, `Legend`, or an operator override) render as flavor-only chips and grant no authority. Anonymous posts suppress title and reputation. Staff, OP, Wiki, edited, and anonymous states continue to use their existing data; no public “revealed” state is invented.

Consecutive grouping keeps the existing ten-minute, same-author, non-anonymous rule and continues to exclude OP, accepted, staff, and wiki posts. When the rendered calendar day changes, the stream inserts a quiet day divider before the first post of the new day. UTC remains the server time basis.

Post bodies keep the existing pre-sanitized HTML, reference cards, link previews, signatures, and Markdown treatments. The selected typography and gold blockquote rule are applied without raw output changes.

### 7. Reactions and post toolbar

Existing reaction chips are restyled as compact Imladris pills. The application’s real emoji and custom-shortcode set remains intact. The toolbar does not display a fictional gold Commend control that would submit a different reaction.

For signed-in viewers, the post row groups its current actions into a toolbar:

- the existing reaction picker;
- a progressive-enhancement Quote control that appends a bounded Markdown quote to the real reply textarea and dispatches the normal input event;
- the accepted-answer form when `can_mark_solved` permits it;
- a permalink/copy-link affordance with a normal anchor fallback; and
- a native-details overflow containing only the existing owner, moderator, wiki, reveal, delete, and report forms available to that viewer.

On hover-capable pointers, `.post:hover`, `.post:focus-within`, and an open menu reveal the floating toolbar. On coarse pointers and at mobile widths, the actions are always visible with at least 44px targets. No action is reachable only by hover.

Without JavaScript, action disclosures and forms remain visible in normal flow. Enhanced menus use native `<details>` plus delegated outside-click and Escape dismissal. Rejected inline edits preserve their current text/error contract and reopen the relevant post controls.

### 8. Composer dock and flashes

`templates/partials/composer.php` remains the shared reply form. The thread context adds the selected identity strip and card treatment while retaining:

- CSRF and idempotency fields;
- the canonical Markdown textarea;
- WYSIWYG/source-mode enhancement;
- formatting, references, uploads, preview, draft save/restore, and counter behavior;
- anonymous-posting controls where the board permits them;
- validation error and old-input preservation; and
- the existing submit action and permission gates.

The dock stays below the scrollable topic region on canonical and Inbox renders. Desktop uses the raised card with a soft page fade; mobile keeps the existing compact resting state and expands on focus/input. Dynamic viewport sizing and a safe-area/keyboard-aware bottom inset keep the expanded composer above the software keyboard; the implementation should retain the existing `dvh` behavior and add the smallest `visualViewport`/safe-area enhancement needed where it is insufficient. The guest and locked notices receive the selected quiet-bar treatment.

Successful thread flashes may be styled as bottom-center toasts and dismissed after the existing short display interval. Error flashes remain persistent and in flow. Toasts contain only server-confirmed outcomes and never synthesize Undo.

## Progressive enhancement and state

Server-rendered HTML is complete before JavaScript runs. Enhanced UI state is limited to the open drawer, open accordion, open post menu, open split/merge disclosure, focus origin, and body scroll lock. It is not stored in the database or local storage.

The document-level handlers must work for both the initial canonical page and thread HTML inserted by the Inbox fetch path. They resolve the nearest `.thread-conversation` so multiple stale or hidden fragments cannot control the wrong drawer. References to the Inbox “toolbar” in acceptance checks mean the **post action toolbar**, not the shared composer formatting toolbar.

Closing order is outermost-first: post/menu disclosures close before the split/merge modal, which closes before Topic tools. Escape never triggers a server action. Browser Back remains governed by the Inbox history implementation and is not overloaded for drawer state.

Quote and copy are optional conveniences. If the Clipboard API, composer target, WYSIWYG bridge, or JavaScript is unavailable, the normal permalink and server reply form remain usable. No write depends on either enhancement.

## Failure handling

- Failed or unauthorized writes continue through existing exception mapping and flash behavior.
- Validation failures do not lose reply or edit text; the appropriate composer/control is visibly reopened.
- A failed Inbox fetch still falls back to the canonical topic route.
- Missing feature data omits the relevant card or tools section without leaving empty chrome.
- A topic with no tools does not render an inert Topic tools trigger.
- A missing/unmigrated optional table remains handled in the existing controller/global-shell boundaries; this change adds no global database lookup.
- JavaScript errors leave the in-flow no-JavaScript-compatible forms in the DOM; CSS does not lift or hide the only copy until that thread instance has been explicitly marked enhanced with an operable trigger.

## Accessibility and security

- All writes remain POST forms with CSRF tokens; no GET mutates state.
- Capability gates in the controller/template mirror the authoritative services one-for-one.
- Anonymous masking, private-board read gates, pending-content exclusion, and source-post access checks remain unchanged.
- The drawer and modal receive names, focus containment, Escape/scrim close behavior, and trigger-focus restoration.
- Native summaries, buttons, links, labels, field errors, and forms remain keyboard-operable.
- Hover-revealed actions are also revealed by focus and permanently exposed on touch.
- Visible controls meet 44 by 44 CSS-pixel touch targets on mobile.
- Focus uses the existing high-contrast focus token; status never relies on color alone.
- `prefers-reduced-motion` disables drawer, sheet, menu, modal, and toast animation.
- No inline script or style is introduced, preserving the strict CSP.
- The mobile sheet, composer, post actions, and modal must not create horizontal overflow at 390px or at 200% zoom.

## Testing

Implementation follows test-first development.

### Server-side regressions

- a guest receives the quiet reading head, public status/tags/brief/poll, read-only status-history disclosure, and join bar but no Star, Topic tools trigger, or write forms;
- a member receives Star and only the watch/standing/tag controls permitted by current features and capabilities;
- a topic owner retains accepted-answer and poll-management controls without acquiring moderator controls;
- a board moderator receives only the pin, lock, split/merge, delete, reveal, memory, and other controls their individual capabilities allow;
- under the current default legacy/shadow authority mode, a suspended privileged user does not receive write controls that `WriteGate` forbids; this regression must not be described as proof of future resolver-enforce semantics without a separate enforce-mode check;
- status, assignment, snooze, tags, and participant facts render in the intended header/tool locations and the old stacked workflow panels are absent;
- every moved form retains its action, CSRF field, and field names;
- anonymous authors remain masked and expose neither title nor reputation;
- cosmetic author titles are derived through `TitleService` and do not affect permissions;
- accepted, grouped, wiki, staff, edit-error, poll, Living Brief, and no-feature states retain their current semantics; and
- no-JavaScript markup contains one reachable copy of every authorized form.

### Browser regressions and evidence

Approval of this spec includes use of the repository’s Playwright harness and evidence database. Existing tests that drive the relocated controls are part of this change: update `gate-a.spec.ts` (workflow and split/merge journeys plus `29-topic-workflow`), `a11y.spec.ts` (workflow and split/merge scopes), `thread-intelligence.spec.ts` (Living Brief curator controls and `77-living-brief-curator-controls`), and `community-inbox-theme.spec.ts` where its dynamically loaded thread assertions overlap.

- desktop: open/close Topic tools by trigger, close button, scrim, and Escape; verify focus entry/restoration and exclusive accordion behavior;
- desktop: verify the selected quiet header, reading width, Living Brief, poll, accepted answer, post toolbar/menu, and composer dock with operative `data-theme="light"` and `data-theme="dark"` values; retain `system` fallback coverage where applicable, and use `RB_BROWSER_DARK_SURFACES=1` for evidence fixtures/captures that require the harness’s dark surfaces;
- Inbox: load a topic dynamically and repeat drawer, post-toolbar, quote, menu, and modal interactions;
- mobile 390×844: verify bottom-sheet treatment, grab/close affordance, 44px targets, always-visible post actions, compact composer, full-screen split/merge sheet, and no horizontal overflow;
- no JavaScript: navigate to the canonical topic, open native tool/action disclosures, and submit a real server reply;
- keyboard: traverse the post toolbar, drawer, accordion, and modal; verify Escape layering and focus restoration;
- accessibility: focused axe scans on the topic head, Topic tools, post stream, composer, and split/merge modal report no serious or critical violations;
- motion: add the harness’s first explicit `page.emulateMedia({ reducedMotion: 'reduce' })` check and verify nonessential transitions are removed;
- mobile keyboard: exercise the compact-to-expanded composer under a representative shrunken visual viewport/safe-area condition and verify its controls remain above the keyboard boundary; and
- evidence: refresh the canonical desktop/mobile thread captures, `29-topic-workflow`, and `77-living-brief-curator-controls`, add open Topic tools captures under `docs/evidence/browser/`, and update `docs/evidence/browser/README.md`.

If focused coverage is added in a new Playwright spec, add that filename to the `evidence` script in `tests/browser/package.json` (and to `a11y` when applicable), because that script is the CI evidence entry point. There is no machine evidence manifest; `docs/evidence/browser/README.md` is the prose index.

### Completion checks

- focused PHPUnit tests pass after each red/green cycle;
- PHP syntax checks pass for every changed PHP file;
- the full `composer test` suite passes against the test database;
- focused Playwright desktop, mobile, no-JavaScript, and axe tests pass;
- the browser evidence command completes and writes the required captures;
- `git diff --check` is clean; and
- the final diff contains no prototype runtime in production templates/assets, inline CSP violation, accidental inclusion of unrelated user worktree changes, or inert control. Updating existing browser specs and evidence named above is expected and in scope.

## Expected files

- `templates/thread.php`;
- `templates/partials/post.php`;
- `templates/partials/composer.php`;
- new focused partials under `templates/partials/` for Topic tools and split/merge;
- `src/Controller/ThreadController.php` and `src/Repository/PostRepository.php` only for display-model data such as resolved cosmetic titles;
- `public/assets/app.css` and `public/assets/app.js`;
- focused integration tests, likely extending `tests/Integration/Core/AppThreadUxAuditTest.php` or a dedicated thread-view test;
- focused Playwright coverage under `tests/browser/`; and
- updates to the existing `gate-a.spec.ts`, `a11y.spec.ts`, `thread-intelligence.spec.ts`, and `community-inbox-theme.spec.ts` contracts that exercise relocated controls;
- `tests/browser/package.json` if a new focused spec must join the CI evidence/a11y commands;
- refreshed desktop/mobile evidence images and `docs/evidence/browser/README.md`; and
- the documentation-only normative reference under `docs/design-system/imladris/templates/thread-view/` plus its handoff README.

No migration, route, feature-flag default, service mutation contract, or prototype runtime file under production templates/assets is expected.
