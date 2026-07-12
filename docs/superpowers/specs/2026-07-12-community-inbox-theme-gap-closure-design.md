# Community Inbox Theme Gap Closure

**Date:** 2026-07-12
**Status:** Approved scope; implementation design pending written-spec review
**Owner:** RetroBoards core theme

## Context

The Engineering Handoff and `DESIGN.md` define RetroBoards as a Community
Inbox: the inbox is personal, the topic is durable, and the composer is
immediate. The current Imladris theme already supplies the right visual tokens,
desktop rail, split-pane Inbox, topic cards, and progressive-enhancement base.
The live audit found four gaps in the core member journey:

1. password login and registration default to the board index instead of the
   authenticated Inbox;
2. a mobile Inbox topic selection updates `?t=` while the reading pane remains
   hidden;
3. the mobile top bar wraps into a tall, persistent block that crowds the
   conversation; and
4. the reply composer appears only after the entire topic, rather than serving
   as an immediate conversation control.

This change closes those gaps using the existing Imladris responsive patterns.
It does not introduce another theme or visual language.

## Scope

### In scope

- default authenticated landing behavior across password, MFA, passkey, OAuth,
  and registration flows;
- desktop and mobile Community Inbox state transitions;
- compact mobile top-bar and off-canvas rail behavior;
- an immediately reachable, docked thread reply surface;
- responsive cleanup for the reply toolbar and anonymous-posting control;
- focused integration and browser regression coverage in both Imladris themes;
- no-JavaScript preservation for topic navigation and posting.

### Out of scope

- administrator, moderator, settings, package, or identity-console redesigns;
- new colors, typefaces, radii, icon families, or decorative assets;
- changes to Thread Intelligence generation, publication, or feature defaults;
- database migrations or persistent UI-state tables;
- replacing server-rendered routes with a client-side router.

## Approaches considered

### 1. Existing Imladris responsive state pattern — selected

Keep the desktop split pane. On narrow screens, use the UI kit's existing
`is-hidden` list and `is-open` reading-pane states, with a visible Back to topics
control. Compact the same top bar and reorganize the existing thread form into
a dock. This preserves product identity, shareable URLs, and no-JS routes while
closing the actual interaction gaps.

### 2. CSS-only patch — rejected

CSS can reduce header height and improve form wrapping, but it cannot correctly
manage the mobile list-to-topic transition, focus restoration, URL history, or
fetch failures. It would leave the core mobile failure intact.

### 3. Always navigate mobile topics to the canonical thread route — rejected

This is a safe fallback and remains the no-JS behavior, but making it the only
mobile behavior discards the responsive single-pane pattern already defined by
the Imladris UI kit. The enhanced path should feel continuous without making the
canonical route optional.

## Design

### 1. Authenticated entry

`/` remains the public board index and an explicit destination. When no safe
`next` destination was supplied, successful authentication lands at `/inbox`.
This applies consistently to password, MFA, passkey, OAuth, and newly registered
accounts. A supplied same-origin `next` path continues to win, including an
explicit `/`. Logout continues to return to `/`.

Already-authenticated users who visit `/login` or `/register` are sent to
`/inbox`. The redirect helper continues to reject protocol-relative and external
destinations; this work changes only its empty/default result.

### 2. Responsive Inbox state

The desktop behavior remains a rail, topic list, and reading pane. The reading
pane gets a stable content wrapper so topic HTML can be replaced without
destroying its navigation controls.

At `860px` and below:

- the topic list is the initial single pane;
- selecting a topic fetches the canonical server-rendered thread, places it in
  the reading wrapper, marks the list `is-hidden`, and marks the reading pane
  `is-open`;
- a Back to topics button clears the reading state and restores focus to the
  selected topic row;
- `?t=<id>` remains shareable and browser Back/Forward restore the corresponding
  list or reading state;
- an initial mobile `/inbox?t=<id>` opens the reading pane after the same
  validation and fetch path; and
- a failed fetch, unexpected response, or authentication redirect navigates to
  the canonical topic URL instead of leaving a blank pane.

Without JavaScript, topic anchors remain ordinary links to `/t/{id}-{slug}` and
all filters remain server-rendered links.

### 3. Compact mobile chrome

The mobile top bar stays one row at the existing `--topbar-h` of `62px`.
It retains the navigation toggle, house mark, notification bell, identity,
settings, moderation count where applicable, and logout action. At the narrowest
width, the wordmark and nonessential text labels may hide while their accessible
names remain intact.

The full search field leaves the mobile top bar. Search remains reachable via a
mobile-only rail entry using the repository's existing Lucide search icon
partial. Desktop search and the desktop top bar are unchanged. The off-canvas
rail begins below the real top-bar height and closes on link activation, scrim,
or Escape. Motion continues to honor `prefers-reduced-motion`.

### 4. Immediate reply surface

The thread template becomes a conversation viewport with two explicit regions:

1. a scrollable topic region containing the header, workflow, memory, poll,
   posts, and pagination; and
2. a docked member composer or guest join bar.

The same structure is used on the canonical thread route and inside the Inbox
reading pane. Locked, archived, or otherwise non-replyable topics simply let the
scrollable region consume the available space.

On small screens, the composer has a compact resting state so it does not cover
the conversation. Focusing the textarea, restoring rejected input, or rendering
a validation error expands it. The existing formatting toolbar becomes a
horizontally scrollable single row rather than wrapping into several rows. The
anonymous-posting checkbox uses a stable two-column alignment so its explanation
wraps beside the checkbox instead of detaching from it.

The form action, CSRF token, idempotency key, draft synchronization, Markdown
textarea, WYSIWYG enhancement, validation preservation, and submit behavior stay
unchanged. This is a layout and reachability change, not a new posting path.

## State and failure handling

- The canonical topic URL remains the source of truth for fetched content.
- Inbox enhancement state is derived from the URL plus DOM classes; it is not
  persisted to the database or local storage.
- A stale or inaccessible topic falls back to its real navigation response, so
  existing read gates and login redirects still decide access.
- Back/Forward never reuse a hidden stale pane without reloading the selected
  topic through the established fetch path.
- Failed posting retains the typed body and opens the composer, following the
  existing anti-draft-loss contract.
- Desktop behavior does not depend on the mobile state classes.

## Accessibility

- The mobile Back to topics control is a real button with a visible label.
- Opening a topic moves focus to its heading; closing it restores focus to the
  originating topic link.
- Hidden mobile panes are removed from visual and keyboard navigation together.
- Compact header controls retain at least 44 by 44 CSS-pixel targets.
- Hidden visual labels retain explicit accessible names.
- The dock does not obscure focused controls, validation messages, or the final
  post at 200% zoom.
- Status continues to use words plus color, never color alone.

## Testing

Implementation follows test-first development.

### Server-side regressions

- password login and registration default to `/inbox`;
- MFA, passkey, and OAuth default to `/inbox`;
- every flow preserves an explicit safe `next` destination;
- unsafe `next` values still fail closed;
- the Inbox renders the stable reading wrapper and mobile Back control; and
- failed reply validation retains content and marks the dock expanded.

### Browser regressions

Approval of this spec includes use of the repository's existing Playwright CLI
harness for the focused browser checks.

- desktop `1280x800`: the three-pane Inbox still opens a topic in place;
- mobile `390x844`: selecting a topic shows the conversation and Back restores
  the list and focus;
- direct mobile `/inbox?t=<id>` opens the conversation;
- mobile top bar remains a single compact row with no horizontal overflow;
- mobile search remains reachable through the off-canvas rail;
- the reply dock is visible without scrolling through the complete topic and
  expands when focused or when validation state is present;
- the anonymous-posting control and formatting toolbar do not overflow;
- the core journey works with JavaScript disabled through canonical links and
  server-rendered forms;
- parchment and twilight receive matching layout checks; and
- focused axe scans report no serious or critical violations.

### Completion checks

- focused PHPUnit suites pass;
- the complete PHPUnit suite passes;
- focused browser and accessibility checks pass;
- desktop and mobile screenshots are compared against the accepted Imladris
  reference and the pre-change audit captures; and
- `git diff --check` and PHP syntax checks are clean.

## Expected files

- `src/Controller/AuthController.php` and the corresponding OAuth/passkey auth
  boundaries;
- `templates/inbox.php`, `templates/partials/topbar.php`,
  `templates/partials/sidebar.php`, `templates/thread.php`, and
  `templates/partials/composer.php`;
- `public/assets/app.js` and `public/assets/app.css`;
- focused integration, unit/static, and browser tests.

No schema or migration file is expected.
