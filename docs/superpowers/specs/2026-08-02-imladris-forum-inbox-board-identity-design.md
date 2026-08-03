# Imladris Forum Inbox and Board Identity Design

**Date:** 2026-08-02

**Status:** Approved in conversation

**Scope:** First production slice from `ImladrisDesignSystem.zip` `/templates`

## Purpose

Apply the Imladris `board-index` presentation to the production application without making the personalized forum inbox, an individual board, and private messages appear to be the same product surface.

This is a focused first slice. It governs the shared chrome, topic-row presentation, `/inbox`, and `/c/{slug}`. It does not approve feature additions or removals found in later design templates.

## Sources and precedence

The visual source is:

- `ImladrisDesignSystem.zip`
- `_design_handoff_imladris/templates/board-index/BoardIndex.dc.html`
- the handoff README's shared anatomy and Board Index sections

The production behavior sources remain, in order:

1. `DECISIONS.md`
2. `DESIGN.md`
3. `SCHEMA.md` and applied migrations
4. `USER.md`, `ADMIN.md`, `COMMUNITY.md`, and `COMPOSER.md`
5. existing server-side routes, authorization gates, and progressive-enhancement contracts

The `.dc.html` file is a visual and interaction reference. Its `sc-if`, `sc-for`, local state, fixture data, and client-only callbacks are not production architecture.

## Problem

Production has three distinct concepts:

- `/inbox` is a signed-in member's personalized, cross-board view of forum topics.
- `/messages` is private direct and group conversation.
- `/c/{slug}` is one board's topic list, governed by that board's read and post policy.

The handoff represents the council inbox and a selected board through one flexible mock screen. A literal translation would make `/inbox` and `/c/{slug}` look and behave alike, add a cross-board topic composer to `/inbox`, and add board sorting controls that production does not currently expose.

The approved direction is **role-coded surfaces**: preserve one Imladris visual family, but make the personal queue explain why a topic matters to this member and make the board page establish where the member is.

## Product decisions

### Route identity

- Keep `/inbox`, `/messages`, `/c/{slug}`, and canonical topic URLs distinct.
- Label `/inbox` **Forum inbox** in navigation and page chrome.
- Describe `/inbox` as the member's personal view across every board they can read.
- Keep **Messages** visibly separate and use private-conversation language only there.
- Do not add a new route or redirect existing routes.

### Topic creation

- `/inbox` has no new-topic composer and no board picker.
- `/c/{slug}` owns **New topic**, with the board fixed by the route.
- Existing guest, account-state, capability, archive, membership, and board-policy gates remain authoritative.

### Board ordering

- A board is ordered by **Last post**: pinned topics first, then most recent topic activity, then id as the deterministic tie-breaker.
- A reply therefore bumps an unpinned topic upward. Topic creation date alone does not control board order.
- `/c/{slug}` has no Active, Newest, Unanswered, or Most replies toolbar.
- Retire the global **Default thread sort** preference. Existing persisted `thread_sort` values become inert; no destructive data migration is required.
- Remove **Most replies** from member preferences and production behavior.
- Keep **Newest** and **Unanswered** as `/inbox` filters, where cross-board triage belongs.

### Inbox filtering

Preserve every existing server-backed inbox filter:

- For You
- Unread
- Mentions
- Replies to You
- Watching
- Needs Answer
- Assigned
- Decisions
- Solved
- Snoozed
- Starred
- Mine
- Active
- Newest
- Unanswered

The most common filters may remain directly visible while the rest use an accessible overflow treatment. No filter may be dropped, converted to client-only filtering, or made unavailable without JavaScript.

## Visual design

### Shared Imladris family

Both surfaces use the supplied Imladris tokens and anatomy:

- parchment and raised surfaces
- Cormorant Garamond display type, EB Garamond body type, Marcellus labels, and JetBrains Mono metadata
- evergreen brand treatment and restrained mallorn-gold indicators
- Imladris radii, hairlines, warm shadows, focus rings, status chips, monograms, presence marks, and commend stars
- the production topbar and board rail, rendered from shared PHP partials

Operator-configured site name, logo, favicon, and colors remain authoritative. Do not hardcode the fixture wordmark "Imladris" or fixture community vocabulary where doing so would remove white-label branding.

### Forum inbox

Desktop uses three panes:

1. shared board rail
2. personalized topic queue
3. selected-topic reading pane

The queue header contains:

- eyebrow: **Your personal forum view**
- title: **Forum inbox**
- explanatory lede stating that topics come from across boards the member can read and are organized by attention signals
- unread count when non-zero
- the complete filter set

Every inbox topic row carries:

- board identity, even when the selected filter is not board-specific
- title, snippet, author, replies, recency, status, unread state, and star state when available
- a plain-language inclusion cue where the data has a personal cause

For the **For You** filter, use the existing server-provided `for_you_reason`, including Assigned to you, Mentioned you, Replies to your topic, Watched topic, Watched board, Starred by you, Followed board, or Followed tag. For status filters, the status chip is the inclusion cue. For general ordering filters such as Active and Newest, the selected filter and board label provide the context without a redundant per-row chip.

The reading pane repeats the topic's board breadcrumb so the member never loses location while reading a cross-board queue.

### Board page

Desktop uses the shared rail plus one primary board column. It does not reproduce the inbox reading pane.

The board identity header restores the approved Direction A treatment from the visual companion: an evergreen `#2E4A3A` field with parchment `#FAF6EC` text and a `3px` mallorn-gold `#C29A44` bottom rule. It sits below the breadcrumb and contains the board name, description, and board-scoped actions. This treatment belongs only to `/c/{slug}`; it does not extend to `/`, `/inbox`, `/messages`, or the canonical topic view. The canonical topic header remains parchment and follows the supplied Thread View source so reading stays visually focused.

The board header emphasizes:

- `#board-name`
- board description
- topic and post counts when available
- visibility or archive state when relevant
- follow state when the existing feature is enabled
- **New topic** when the current member may post

Board topic rows omit the inbox's board label and personalized inclusion reason because the board itself already establishes location. They emphasize topic title, author, status, replies, and latest activity.

Selecting a board topic opens its canonical topic route.

### Private messages

Messages keep their existing private-conversation information architecture. They do not reuse forum-inbox phrases such as topic queue, board, For You, or selected-topic preview.

## Responsive behavior

### Forum inbox

- Wide desktop: rail, queue, and reading pane are visible.
- Narrow desktop/tablet: the reading pane may collapse according to the existing inbox breakpoint while the queue remains primary.
- Mobile: selecting a topic transitions from queue to reading view with an explicit **Back to topics** action.
- Without JavaScript: topic links open their canonical pages normally.

### Board page

- The board remains a conventional board list at every width.
- Mobile stacks the header, actions, topic rows, and pagination without introducing an inbox-style preview state.
- Topic creation remains reachable as a normal server-rendered form.

## Architecture and data flow

No new route, table, migration, client router, or cross-board composer is introduced.

Expected ownership:

- `src/Controller/InboxController.php` continues to own the personalized queue request.
- `src/Repository/ThreadUserRepository.php` remains the source of inbox rows and `for_you_reason`.
- `src/Controller/BoardController.php` continues to enforce the board read gate and passes the fixed `last_post` order.
- `src/Repository/ThreadRepository.php` retains a fixed, whitelisted Last post query for board listing.
- `templates/inbox.php` owns the three-pane personalized presentation.
- `templates/board.php` owns the board-specific presentation and topic composer mount.
- `templates/partials/thread_row.php` provides shared row anatomy through explicit presentation inputs rather than route inference.
- `templates/partials/sidebar.php` and `templates/partials/topbar.php` expose the clarified navigation labels while preserving branding.
- `templates/account/preferences.php` no longer exposes Default thread sort.
- `public/assets/app.css` supplies application-specific layout rules over the generated Imladris foundation.
- `public/assets/app.js` retains progressive enhancement for the inbox reading pane only.

Removing the board-sort preference requires corresponding updates to its validation, documentation, and tests. Legacy JSON preference values may remain stored but are ignored.

## Progressive enhancement and failures

- Filters and pagination are ordinary links and work without JavaScript.
- The inbox reading pane may fetch a canonical topic page, but redirects, non-OK responses, parse failures, or missing required markup fall back to normal navigation.
- Fetching state uses `aria-busy`; focus moves to the loaded topic heading only after a successful enhancement.
- Browser history remains shareable through the existing inbox topic query state.
- Board writes remain ordinary CSRF-protected POST forms.
- No client state decides authorization, visibility, account standing, or feature availability.

Empty states are surface-specific:

- For You: **Nothing needs your attention right now.**
- Unread: **You're all caught up — nothing unread.**
- Other inbox filters: name the selected personal scope.
- Empty board: **No topics here yet.**
- Filtered inbox results must offer a clear route back to an unfiltered or broader inbox view.

## Accessibility

- Preserve semantic navigation, headings, lists, forms, and pagination.
- Use `aria-current="page"` for the active route or filter.
- Status and inclusion cues use words as well as color.
- All icon-only actions have accessible names.
- Keyboard and screen-reader users receive the same route distinction and filter access.
- Mobile queue-to-reading transitions restore focus predictably.
- Respect reduced motion and the existing strict CSP; add no inline production script or style.

## Verification and completion evidence

### PHPUnit

Add or update integration coverage proving:

- `/inbox` requires a member and remains a cross-board read-gated queue.
- `/inbox` renders Forum inbox identity, board labels, all 15 filters, and For You reasons.
- `/inbox` does not render a new-topic composer.
- `/c/{slug}` preserves guest/private/hidden-board behavior.
- `/c/{slug}` renders topic creation only when the current member may post.
- board ordering is pinned-first then Last post and ignores legacy `thread_sort` values.
- Default thread sort and Most replies are absent from account preferences.
- empty states retain their distinct meanings.

### Browser evidence

Capture and inspect:

- `/inbox` and `/c/{slug}` at desktop and mobile viewports
- parchment and Twilight themes
- selected and empty inbox reading-pane states
- keyboard navigation through rail, filters, topic rows, Back to topics, and board composer
- JavaScript-disabled canonical navigation
- simulated inbox fetch failure falling back to the canonical topic page
- browser console output with no unexpected errors or warnings

### Final gates

- focused PHPUnit tests
- full `php vendor/bin/phpunit`
- `composer verify:imladris`
- browser evidence suite
- accessibility suite
- `git diff --check`

## Non-goals

This slice does not:

- redesign the canonical thread view
- apply any subsequent `/templates` screens
- add a users-online directory, account Regard ledger, profile report target, or any other design-ahead capability
- add a board filter toolbar or cross-board topic composer
- remove or graduate feature flags
- change board authorization, thread status semantics, private messaging, notification logic, or data retention
- copy `.dc.html`, React, or design-preview JavaScript into production

Later template slices require their own feature-delta review and approval before implementation.
