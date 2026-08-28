# Member Surfaces Production Transfer Design

**Date:** 2026-08-27

**Status:** Approved in conversation after review of `CommunityForumDesignSystem.zip`

**Source archive SHA-256:** `8683122937E85111F76E8A29579D284A314845DD4E33956DE0E9BA10054090EC`

**Scope:** Transfer the approved Board Index, Forum Inbox, Search, Compose, and shared member-shell design into the production PHP application, then deploy and verify it.

## Purpose

This specification records the production interpretation of the approved handoff in `CommunityForumDesignSystem.zip`. The archive is a high-fidelity design reference, not executable application code. Its `.dc.html` files, fixture data, `support.js`, `ds-base.js`, and token snapshots do not ship to the runtime.

The governing product distinction is:

- `/` is the place: the stable, non-personalized directory of boards.
- `/inbox` is your attention: the signed-in member's personal cross-board queue.
- `/search` finds a thing whose location is unknown.
- `/compose` is a focused, top-level writing task.

The production application keeps dynamic operator branding, categories, boards, users, feature gates, capabilities, routes, and data. The handoff's eight named boards and counts are fixtures only.

## Sources and precedence

Conflicts resolve through the repository's normal chain:

1. `DECISIONS.md`
2. `PRODUCT_DESIGN.md`
3. `SCHEMA.md` and applied migrations
4. `USER.md`, `ADMIN.md`, `COMMUNITY.md`, and `COMPOSER.md`
5. accepted ADRs and existing authorization/write contracts
6. the approved member-surface handoff and Imladris presentation references

The design system owns presentation. RetroBoards owns behavior. In particular, topic creation remains a full server navigation after a successful write, as required by `COMPOSER.md` and ADR 0020; the prototype-only in-place toast does not replace that contract.

## Approved reconciliation decisions

- Preserve `/feed` as a separate personalized Following surface. It must not return to `/` and it is reached through the identity/secondary menu rather than the board rail.
- Keep `/c/{slug}` and `/t/{id}-{slug}` as the canonical board and topic owners. The Inbox reading pane is a bounded preview with a canonical-topic link, never a second full topic page.
- Keep every production capability, privacy gate, account-state gate, feature flag, CSRF form, idempotency rule, and anti-draft-loss path.
- Preserve muted-board behavior as an attention preference: muted boards remain visible in the place-oriented rail but contribute no unread pill or Inbox unread count.
- Do not add a database migration. Member-surface choices live in the existing versioned user preference JSON.

## Shared member shell

All app routes use one server-rendered shell and one board-rail partial.

### Topbar

The topbar renders:

1. operator-configured brand and real house-mark asset;
2. primary links for Boards, Inbox, and Messages with `aria-current`;
3. the Search entry except on `/search`;
4. persisted rail toggle and, on Inbox, persisted reading-pane toggle;
5. New topic linking to `/compose`, except on `/compose` itself; and
6. identity disclosure containing profile, notifications, drafts, Following, top contributors, settings, moderation/admin destinations when authorized, and logout.

The Inbox pill is the same value as the sum of visible rail unread pills. Guest navigation keeps only destinations that are actually available.

### Board rail

The rail is board-only chrome:

- the same policy-listed categories and boards, in operator order, on every app route;
- unread pills from one bulk, read-gated query for signed-in members;
- muted boards still present, with no attention count;
- a server-rendered, privacy-respecting public presence roster and `/users-online` link when Presence is available; and
- on `/compose`, the same rows rendered as destination controls. Boards that are readable but not postable remain visible and disabled.

The global-shell query remains safe before setup and against missing tables. Every new global lookup has a defensive fallback.

### Persisted pane state

`rail_open` and `inbox_reading_open` are managed reading preferences. The server stamps their resolved values on first paint. Ordinary CSRF-protected POST forms provide a no-JavaScript toggle path; JavaScript may intercept the same controls for an immediate enhancement. `Ctrl/Cmd+B` toggles the rail and `Ctrl/Cmd+J` toggles the Inbox reading pane when focus is not in an editor or form control.

## Board Index `/`

The Index is a rail plus one directory column, with no third pane and no personal signals on directory rows.

It renders:

- pane tabs Boards, Tags, Notices, and Connections;
- hero `Every board in the valley`, the approved lede, and policy-filtered board/topic/post totals;
- order choices `category`, `active`, `newest`, `unanswered`, `top`, and `solved`;
- peek choices `0`, `3`, and `5`;
- the inherited density statement linking to `/settings/appearance`;
- category sections only for category order; all ranked orders dissolve categories into one list;
- dynamic board rows with the relevant order signal and absolute topic/post counts; and
- up to the selected number of read-gated topic peeks per board.

`sort` and `peek` are represented in the query string. For authenticated members, submitting the controls also persists `directory_sort` and `directory_peek`; guests retain the choices in the URL. The server uses stored values only when the corresponding query parameter is absent.

The Tags pane uses the existing read-gated tag catalog and canonical tag links. Notices uses existing notification data/actions. Connections uses existing follower/following data and canonical profile links. Their data remains server-owned and feature-gated.

## Forum Inbox `/inbox`

The Inbox uses independent axes:

- `scope`: `for_you`, `unread`, `mentions`, `replies`, `watching`, `assigned`, `starred`, `mine`, `snoozed`, `needs_answer`, `decisions`, or `solved`;
- `order`: `activity`, `newest`, or `commended`;
- `page`: ordinary pagination; and
- `topic`: the selected read-gated preview target when the enhanced reading pane is open.

The route contract is `/inbox?scope=<scope>&order=<order>&page=<page>&topic=<id>`. Invalid or feature-disabled values fall back safely. Existing legacy `filter` links redirect or normalize to the equivalent scope/order without collapsing the two axes.

Pinned topics lead within every order. Scope controls inclusion; order only sequences that result set. The repository returns enough row data for unread, reason, status, assignment, snooze, star, commend, author, board, and activity presentation without per-row queries.

The queue includes:

- the approved personal-view header and shared viewing grammar;
- one semantic row per topic with a real canonical link;
- checkboxes, shift-range selection, a view-scoped sweep bar, and capability-backed bulk actions;
- existing per-topic read, star, snooze, and assignment actions through CSRF-protected routes; and
- keyboard movement/action shortcuts suppressed in inputs, textareas, selects, and editable content.

Without JavaScript, topic links open canonical topic pages and all writes remain ordinary forms. With JavaScript, selecting a row requests a dedicated read-gated preview fragment. The preview contains the opening post, a bounded reply sample, status/author facts, canonical link, and the existing shared composer only when the server says replying is allowed. It does not mount the full topic toolchain.

At widths below 1280px the desktop reading pane is absent from the split layout; on narrow screens an enhanced selection replaces the queue and exposes Back to topics. The canonical-link fallback is always retained.

## Search `/search`

Search uses `/search?q=<query>&scope=<scope>&order=<order>` where:

- scope is `everything`, `topics`, `replies`, or `mine`; and
- order is `relevance` or `newest`.

The replaceable `SearchService` remains the owner of read gating. Its interface accepts an immutable query/options value and returns a stable result shape containing type, URL, title, board, safe snippet, score, and creation time. The MySQL implementation applies scope and order before the result limit; `mine` is empty for guests.

The page uses the approved engraved search well, accessible validation treatment, scope/order controls, result count copy, result anatomy, and empty state. It stays a rail plus one column, never a browsing directory or reading pane.

## Compose `/compose`

`GET /compose` is a signed-in top-level surface. It shows all policy-listed boards in the shared rail and form select, annotating each with server-computed `can_post`. Readable but unauthorized boards are disabled rather than hidden. A suspended, banned, deactivated, or deletion-pending account cannot use the write surface.

The selected board comes from `board=<slug-or-id>` when it is valid, otherwise the first postable board. The server remains authoritative. JavaScript synchronizes the rail buttons and `<select>` in both directions but never changes authorization.

The page reuses the one production `composer_shell` for new topics, including Markdown/WYSIWYG, uploads, drafts, anonymity, counter, CSRF, idempotency, and error recovery. Title validation remains at least three characters and validation re-renders the same surface with typed content, board selection, and errors preserved. Cancel returns to `/`; success follows the canonical topic-navigation contract.

## Progressive enhancement and accessibility

- No inline script, inline style, inline event handler, prototype runtime, client router, or new CDN dependency.
- Every control has a real link or form fallback and every write has CSRF protection.
- State is carried by query strings and managed preferences; client state is limited to selection, cursor, open menu, transient draft UI, and temporary animation state.
- Focus is visible. Menus and sheets implement Escape, focus placement, focus return, and closed-state focus exclusion.
- Keyboard shortcuts never fire while typing.
- Status and unread state use words as well as color; icon-only controls have accessible names.
- Motion uses existing duration/easing tokens and respects reduced motion.
- New CSS is route-scoped and uses semantic tokens only. No raw color values are introduced.
- The production shell remains horizontally stable at desktop and mobile widths.

## Design-system transfer

The reviewed handoff source is mirrored into the owning `docs/design-system/imladris/templates/` directories for provenance. Existing newer Imladris sources win over stale token/component snapshots; token files are compared and reconciled, never blindly overwritten. Real `elven-star.svg`, `commend-star.svg`, bundled fonts, and existing Lucide icon partials are reused.

Generated `public/assets/imladris.css` and resource mirrors are changed only by `composer build:imladris`. The application presentation baseline is refreshed only after same-viewport reference comparisons pass. The clean starting branch's pre-existing digest mismatch from commit `d2517fec` is reported separately.

## Product documentation changes

`PRODUCT_DESIGN.md` and `COMMUNITY.md` are updated so the authoritative product contract says:

- cross-surface routes live in the topbar;
- the rail is boards plus public presence;
- `/feed` remains a separate personalized Following destination in secondary navigation;
- `/compose` is the top-level new-topic route; and
- Index directory controls are non-personal facts, while all member-relative reading signals remain in Inbox.

## Verification and production completion

Completion requires evidence for each layer:

- focused TDD for repository, controller, template, preference, authorization, and no-JavaScript contracts;
- full PHPUnit;
- Imladris source/build/runtime verification and WYSIWYG asset verification;
- Playwright coverage at the exact reference viewport and mobile viewport for light/twilight, JavaScript/no-JavaScript, keyboard, focus, accessibility, console, and overflow;
- side-by-side images placing each source screenshot and production capture in one comparison artifact;
- a project-root `design-qa.md` with a final `passed` result;
- migration/deployment-scope checks proving no schema or infrastructure change;
- a clean, immutable commit pushed and merged to `main`;
- Cloudflare Workers deployment evidence for the exact merged SHA; and
- live verification of `/`, `/inbox`, `/search`, `/compose`, relevant assets, and `/healthz`.

## Non-goals

This transfer does not hard-code handoff fixtures, alter board/thread authorization, remove `/feed`, change canonical topic semantics, introduce a framework, add a migration, enable a dark feature flag, deploy prototype code, or modify production database content.
