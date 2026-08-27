# Imladris Forum Surfaces Production Design

**Date:** 2026-08-02

**Status:** Approved in conversation after visual-prototype review

**Scope:** Production carryover for the Forum Index, individual board, canonical thread, and their shared navigation labels

## Purpose

This companion specification records the approved production translation of the verified Imladris visual prototype at `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/`.

It extends the previously approved Inbox and Board Identity design to the public Forum Index and the canonical thread's presentation. It does not reopen the settled route model:

- `/` is a calm directory of listed boards.
- `/inbox` is a signed-in member's personalized, cross-board forum queue.
- `/messages` is private conversation.
- `/c/{slug}` is one board's topic list.
- `/t/{id}-{slug}` is the focused reading and reply surface.

The production application remains the behavioral authority. The prototype supplies visual hierarchy, density, spacing, type, and surface treatment; its fixture data and client state do not ship.

## Sources and precedence

The production sources retain repository precedence:

1. `DECISIONS.md`
2. `PRODUCT_DESIGN.md`
3. `SCHEMA.md` and applied migrations
4. `USER.md`, `ADMIN.md`, `COMMUNITY.md`, and `COMPOSER.md`
5. existing server-side routes, capability checks, forms, and progressive-enhancement contracts

The approved visual sources are:

- `ImladrisDesignSystem.zip` and the imported design-system templates
- `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/`
- `docs/superpowers/specs/2026-08-02-imladris-forum-inbox-board-identity-design.md`
- `docs/superpowers/specs/2026-07-12-thread-view-study-design.md`

This specification intentionally reconciles older product statements with the approved route model. `PRODUCT_DESIGN.md` must be amended where its shell and URL sections describe `/` or `/c/{slug}` as an open-conversation inbox, and where it specifies board sort tabs. `USER.md` must be amended where it specifies the persisted Default thread sort preference. Those documentation changes ship with the implementation so the higher-precedence product contract and production behavior agree.

## Shared route identity

The application navigation must explain the three commonly confused destinations in plain language:

- **Forum index** — browse the site's listed boards.
- **Forum inbox** — the signed-in member's personal queue across readable boards.
- **Messages** — private conversations.

These remain ordinary server-rendered links. The active destination uses `aria-current="page"`. Operator-provided site name, logo, favicon, brand colors, and board taxonomy remain authoritative; fixture names and counts never replace real data.

No route is added, removed, or redirected by this work. Authentication defaults and explicit safe return destinations remain unchanged.

## Forum Index `/`

### Role

The Forum Index is a quiet directory, not a feed and not a second Inbox. It helps a visitor understand the site's board structure and choose a board.

It must not contain:

- recent-topic or selected-topic previews;
- personalized inclusion reasons or Inbox filters;
- a cross-board composer or board picker; or
- private-message concepts.

### Anatomy

The page contains:

1. a small **Forum index** eyebrow;
2. the operator-configured site name as the page heading;
3. a short explanation that this page lists the site's visible boards and that **Forum inbox** is the signed-in member's personal cross-board queue;
4. board, topic, and post totals calculated only from the already policy-filtered sections rendered on the page; and
5. categories followed by compact, ruled board rows.

Each board row is one clear canonical link and retains its real name, description, visibility label when appropriate, topic count, and post count. Category and list semantics remain intact. Hidden and private-board listing rules remain controlled by `BoardPolicy::isListed()`; the new totals must not disclose excluded boards.

The existing admin-aware and guest-safe empty state remains functional.

## Individual board `/c/{slug}`

### Role

An individual board establishes a single location and then lists its topics. It never becomes a two-pane Inbox or an in-place reader.

### Board identity band

Below the Forum Index breadcrumb, render the approved board-only identity treatment:

- background: evergreen `#2E4A3A`;
- primary text: parchment `#FAF6EC`;
- bottom rule: `3px solid #C29A44`;
- content: real board name, description, topic/post facts, relevant visibility or archive state, and real board-scoped actions.

The band is scoped to `/c/{slug}`. It must not appear on `/`, `/inbox`, `/messages`, or a canonical thread.

### Actions and progressive enhancement

The action order is Follow state first when available, then **New topic** when the current member may post.

- Follow renders only when both the existing `community` and `expanded_feeds` feature gates make its POST route available.
- New topic renders only when the server-provided posting capability permits it; guest, account-state, archive, membership, and board-policy restrictions remain authoritative.
- There is one real topic composer. JavaScript may promote its trigger into the identity band and enhance the existing native `<details>` form as a modal, but must not duplicate composer state or decide authorization.
- Without JavaScript, the same CSRF-protected, idempotent, server-rendered form remains discoverable and usable in normal document flow.
- Validation, old input, anonymous-author controls, and the existing dedicated compose fallback remain unchanged.

### Topic ordering and rows

Board order is fixed and has no toolbar:

1. pinned topics first;
2. most recent `last_post_at` first;
3. topic id as the deterministic descending tie-breaker.

There are no Active, Newest, Unanswered, or Most replies board controls. A reply bumps an unpinned topic; creation time alone does not.

Board rows use an explicit board presentation mode because the row partial is also used by Inbox and tag surfaces. Board rows omit cross-board identity and personal inclusion reasons while retaining the canonical topic link, title, author, status, replies, unread state when available, and latest activity. Inbox selectors and row semantics must remain compatible.

Above the rows, render the visible list heading **Topics**, the activity label **Latest activity**, and the explanatory cue **Pinned first, then last post**. The cue explains the fixed order without presenting it as an interactive sort control.

The board keeps its ordinary empty, archived, guest, pagination, and mobile states.

## Canonical thread `/t/{id}-{slug}`

The approved target is the existing Imladris **Thread View — The Study** contract. Production already implements that contract closely, so this slice is an alignment and regression pass, not a structural rewrite.

The canonical thread remains a parchment reading surface with no evergreen board band. Its hierarchy is:

1. Forum Index and board breadcrumb;
2. quiet topic title and real status/byline facts;
3. participants, Star, and capability-driven Topic tools;
4. shipped poll and Living Brief surfaces when enabled and available;
5. chronological posts and their real actions;
6. pagination; and
7. the real reply composer or existing guest/locked/permission notice.

All currently shipped capability- and feature-gated controls remain. This includes subscriptions, snooze, workflow state/history, tags, assignment, polls, Living Brief controls and provenance, accepted answers, reactions, wiki/edit/delete/report/restore actions, anonymous-author reveal, pin/lock/move, and split/merge. Their existing POST routes, CSRF protection, service authorization, audit behavior, and no-JavaScript access are unchanged.

The same canonical thread markup must continue to work when fetched into the Forum Inbox. Reading the canonical route must retain current canonicalization, read marking, privacy, anonymity, and anti-draft-loss behavior.

## Preference and repository contract

The member preference **Default thread sort** and its **Most replies** value are removed from the UI and managed preference schema. The schema version increments so the new contract is explicit. Existing persisted `thread_sort` JSON is preserved as unknown legacy data but ignored by board rendering; no destructive migration is introduced.

The HTML board controller no longer chooses a sort from member preferences. Its repository path uses the fixed board order above.

Any documented API endpoint whose existing `newest` order means topic creation time keeps that distinct contract through an explicitly named repository method and regression coverage. The board UI must not retain a generic sort switch merely to serve that API behavior.

Preference export and documentation reflect only managed, active preferences.

## Implementation boundaries

- Use production PHP templates and scoped rules in `public/assets/app.css`.
- Reuse the generated Imladris token, font, component, icon, and focus foundations.
- Do not hand-edit generated `public/assets/imladris.css`.
- Do not ship `.dc.html`, React, prototype JavaScript, fixture state, inline script, inline style, or a client router.
- Do not globally redefine shared classes such as `.board-header`, `.thread-row`, `.post`, or `.is-*`; scope new presentation to its route surface or pass an explicit partial variant.
- Preserve the existing 860px production shell breakpoint. New page-internal layouts may compact at the approved content breakpoints without changing drawer ownership.
- Keep global shell lookups safe when the database or a table is unavailable.

## Accessibility and failure behavior

- Preserve semantic landmarks, breadcrumbs, headings, category lists, topic lists, forms, and pagination.
- Route, status, visibility, archive, unread, and inclusion states use words in addition to color.
- Every icon-only action retains an accessible name.
- Keyboard focus remains visible. Enhanced composer and Topic tools interactions retain Escape, focus placement, focus return, and closed-state focus exclusion.
- Reduced motion and the strict Content Security Policy remain honored.
- Every navigation and write flow works without JavaScript; enhancement failures fall back to canonical links or native forms.
- Mobile layouts introduce no horizontal overflow and maintain usable target sizes.

## Verification and completion evidence

### PHPUnit

Add or update coverage proving:

- navigation distinguishes Forum index, Forum inbox, and Messages without changing their routes;
- the Forum Index uses only policy-filtered boards for its rows and totals;
- the Forum Index explicitly distinguishes the listed-board directory from the personal cross-board Forum inbox;
- the Forum Index contains no topic preview, Inbox filters, or composer;
- board ordering is pinned-first, then `last_post_at`, then id regardless of legacy `thread_sort` values;
- the public API retains its documented creation-time newest behavior where applicable;
- the Default thread sort preference is absent from UI, schema defaults, and managed export;
- the board identity band, facts, action ordering, action gates, visible fixed-order cue, empty/archive states, and explicit row presentation render correctly;
- the canonical thread stays parchment and retains the complete Study capability/form contract; and
- private, hidden, anonymous, guest, suspended, archived, and feature-disabled paths do not regress.

### Browser evidence

Capture and inspect `/`, `/c/{slug}`, and `/t/{id}-{slug}` at the approved 1266×854 desktop and 390×844 mobile viewports, including:

- parchment and Twilight themes;
- JavaScript-enabled and JavaScript-disabled navigation and composer access;
- keyboard order, focus visibility, focus return, and closed-control focus exclusion;
- exact computed board-band colors and 3px rule;
- no board band on the Forum Index or canonical thread;
- no horizontal overflow;
- no unexpected console errors or warnings; and
- side-by-side comparison with the approved prototype evidence at matching states and viewports.

### Final gates

- focused PHPUnit tests during each change;
- full `php vendor/bin/phpunit`;
- `composer verify:imladris` after a deliberate reviewed application-digest refresh;
- WYSIWYG asset verification;
- focused browser and accessibility evidence;
- `git diff --check` on the final tree.

## Non-goals

This production slice does not:

- implement or redesign the `/inbox` body beyond the already approved separate Inbox specification;
- add a Forum Index preview, recent-topic feed, cross-board composer, or board sort toolbar;
- remove or invent thread capabilities;
- change board authorization, private-message behavior, notification delivery, moderation semantics, or data retention;
- change the global shell breakpoint or replace production navigation behavior;
- add routes, tables, migrations, client-side state stores, or client-rendered application architecture; or
- publish, deploy, merge, or alter production data.
