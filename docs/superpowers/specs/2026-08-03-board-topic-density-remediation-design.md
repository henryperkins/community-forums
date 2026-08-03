# Board Topic Density Remediation Design

**Date:** 2026-08-03

**Status:** Approved in conversation after visual comparison

**Scope:** Restore compact scanning density on `/c/{slug}` while preserving the approved Board identity and the distinct `/inbox` information architecture

## Context

The Imladris forum-surface rollout correctly separated the route roles:

- `/` is the Forum Index;
- `/inbox` is a signed-in member's personalized cross-board queue;
- `/c/{slug}` is one board's fixed-order topic list; and
- `/t/{id}-{slug}` is the canonical reading and reply surface.

The Board treatment shipped with the approved evergreen identity band and ruled topic rows. However, its production spacing made the topic list substantially taller and slower to scan than the rest of the forum. The current Board rules use a `91px` minimum row height, `14px` vertical padding, and `32px` above the topic section. That hierarchy over-corrected the distinction from `/inbox`: the routes became visually separate at the cost of ordinary forum density.

The production evidence suite compounded the problem by capturing Forum Index, Board, and Thread in isolation. It does not capture `/inbox` alongside the Board, so it cannot expose cross-surface density drift.

This design is a focused correction to the approved production specification. It does not reopen the route model, Board ordering, or Board identity.

## Approved direction

Keep the current Board identity and ruled-row language, but restore a compact, scannable topic list. The approved visual direction is the browser comparison labeled **B: Keep the new identity, restore scan density**.

The fix is intentionally narrow. It changes Board-specific spacing and adds evidence that compares the two forum-list surfaces. It does not create a shared Inbox/Board component or roll the Board back to its pre-Imladris presentation.

## Visible behavior

### Board identity

The following `/c/{slug}` elements remain unchanged:

- Forum Index breadcrumb;
- evergreen `#2E4A3A` identity band;
- parchment text and `3px` gold bottom rule;
- Board eyebrow, name, description, and facts;
- Follow state and New topic actions;
- the explanatory follow note; and
- the **Latest activity**, **Topics**, and **Pinned first, then last post** labels.

No identity band is added to `/`, `/inbox`, `/messages`, or a canonical thread.

### Desktop topic rows

For a seeded one-line topic at the approved `1266×854` desktop viewport:

- the rendered Board row must be between `60px` and `72px` high;
- the CSS target is a `64px` minimum row height with `8px` vertical padding;
- the monogram target is `32px` square;
- the space above the topic section is reduced from `32px` to `22px`; and
- title, author metadata, reply count, and latest activity remain visible without overlap.

Rows retain their ruled separators, square edges, transparent background, hover/focus treatment, unread and status indicators, and right-aligned activity rail. Typography is not reduced merely to satisfy the height target.

The height range is an acceptance rule for the seeded one-line fixture, not a fixed-height clipping rule. A long title, status chips, translated copy, or enlarged user text may grow the row naturally.

### Responsive topic rows

At the existing mobile content breakpoint, Board rows remain conventional list rows rather than Inbox previews. Their minimum height and padding are reduced from the current `84px`/`13px` treatment to a `72px` minimum height and `9px 4px` padding.

Titles and metadata may wrap. The activity facts may use the existing mobile stacking behavior. No supported viewport may introduce horizontal scrolling, clipped text, overlapping content, or a hidden canonical topic link. Browser checks sample both sides of the existing `680px` Board breakpoint in addition to the approved desktop, shell-transition, and mobile widths.

### Inbox relationship

`/inbox` is the comparison surface, not an implementation target. Its three-pane behavior, personalized inclusion reasons, filters, selection state, density, and canonical navigation remain unchanged. Board rows continue to omit cross-board identity and personal inclusion reasons.

## Implementation boundaries

Production changes are expected only in Board-scoped rules in `public/assets/app.css`, including the existing responsive rules for `.board-view .thread-row-board` and its children.

- Do not change global `.thread-row` behavior.
- Do not hand-edit generated `public/assets/imladris.css`.
- Do not add a shared Inbox/Board density token or presentation component.
- Do not change `templates/board.php` or `templates/partials/thread_row.php` unless CSS alone cannot satisfy an approved acceptance rule. If markup changes become necessary, stop and obtain approval for the expanded scope.
- Do not change PHP controllers, repositories, services, feature flags, routes, ordering, JavaScript, schema, migrations, or persisted data.
- Preserve the existing strict CSP and progressive-enhancement contracts.

The server-rendered data flow is unchanged: `BoardController` supplies fixed-order topics, `templates/board.php` selects the Board presentation, and the thread-row partial renders the existing content. This remediation changes only how that markup occupies space.

## Failure and edge behavior

The CSS must prefer natural growth over truncation. Long titles, multiple status chips, enlarged text, and translated metadata can exceed the normal row target. The activity rail must not cover the title or author line. At narrow widths it continues to stack according to the existing Board-specific media query.

Because no new JavaScript or request path is introduced, there is no new runtime error channel. With JavaScript disabled, the same topic links, composer, forms, and pagination remain available. Empty, archived, guest, private, hidden, suspended, and feature-gated Board states are unaffected.

## Verification and completion evidence

### Focused browser contract

Extend `tests/browser/imladris-forum-surfaces.spec.ts` so the primary forum-surface test covers the authenticated `/inbox` before navigating to `/c/general`.

The evidence must:

- capture `/inbox` and `/c/general` from the same seeded database and signed-in member;
- cover the existing `1266×854` desktop and `390×844` mobile projects;
- capture the existing light and dark theme variants;
- assert that Inbox retains `[data-inbox]`, its filter navigation, personal queue rows, canonical topic links, and the expected desktop/mobile list-to-reading behavior, while rendering no Board identity band;
- retain the current Board identity color, action, accessibility, and overflow assertions;
- locate the seeded **Share your favourite keyboard shortcuts** Board row, confirm its title occupies one rendered line at the desktop viewport, and then assert that this exact `.thread-row-board` is between `60px` and `72px` high;
- assert that the topic title, author metadata, reply count, and latest activity are visible;
- assert no horizontal overflow at `1266px`, the existing `800px` shell-transition regression width, both `681px` and `680px` sides of the Board breakpoint, and `390px`; and
- record no unexpected console warnings, console errors, page errors, or failing HTTP responses.

Store refreshed route evidence under `docs/evidence/imladris-forum-surfaces-production/`. Add Inbox evidence without pretending that the older Board prototype is an approved Inbox reference. The evidence report must state that the density relationship was inspected directly.

### Accessibility and progressive enhancement

Retain the existing serious/critical Axe checks for `.board-view`, visible keyboard focus, native canonical topic links, and the JavaScript-disabled Board composer/thread test. Compact spacing must not hide semantic content or focus indicators.

### Final gates

Run on the final working tree:

- the focused `imladris-forum-surfaces.spec.ts` desktop and mobile projects;
- the existing `community-inbox-theme.spec.ts` desktop and mobile projects;
- the full PHPUnit suite via `composer test`;
- `composer verify:imladris`;
- `git diff --check`; and
- a final visual inspection of paired Inbox and Board evidence at desktop and mobile sizes.

## Acceptance criteria

The remediation is complete when:

1. `/c/{slug}` retains the approved identity band and fixed-order Board semantics.
2. A seeded one-line desktop Board row renders within the `60px` to `72px` range.
3. Mobile rows remain compact but grow naturally for wrapped content.
4. Topic titles, metadata, status, replies, activity, focus, and canonical navigation remain intact.
5. `/inbox` is visually and behaviorally unchanged.
6. Paired Inbox/Board evidence demonstrates that the routes are distinct but still belong to one forum.
7. Focused browser checks, the full PHPUnit suite, asset verification, and whitespace checks pass.

## Non-goals

This work does not:

- redesign the Forum Index, Forum Inbox, canonical thread, global shell, or Board identity band;
- change topic ordering, pagination, creation, moderation, subscriptions, notifications, or permissions;
- restore the old rounded Board cards;
- make Board and Inbox rows structurally identical;
- add a density preference or new design token system;
- add a route, table, migration, feature flag, or client-side state store; or
- deploy, merge, or mutate production data.
