# Thread View P0/P1 Remediation Design

**Date:** 2026-08-27

**Status:** Approved by the user's instruction to address every verified P0 and P1 finding

**Scope:** Correct the Thread View permalink, unread-cursor, failed-write, presentation, design-system, and deterministic-evidence defects verified in the 2026-08-27 parity review.

## Outcome

Thread View keeps stable page-one permalinks, offers an explicit unread jump, and computes unread state in the same `(created_at, id)` order used to render posts. Failed replies and moderation forms preserve the reader's page and input without advancing read state. The visible topic facts, validation states, poll results, and first-unread boundary match the Imladris handoff without weakening CSP or progressive enhancement. Browser evidence becomes repeatable across projects and repeated runs.

This is remediation, not a new product subsystem. It preserves every existing route, permission rule, feature flag, `thread_user.last_read_post_id` decision, and no-JavaScript write path.

## Authority and hard boundaries

Repository precedence remains `DECISIONS.md` → `PRODUCT_DESIGN.md` → `SCHEMA.md` → the surface specifications. `DECISIONS.md` decision 3 locks unread state to per-thread `last_read_post_id`; this work therefore does not introduce a timestamp cursor or per-post receipts. The cursor identifies a post, while all comparisons resolve that post's `(created_at, id)` tuple.

The following boundaries are fixed:

- A page-less canonical topic URL always renders page 1. URL fragments are client-only and never influence server pagination.
- Explicit unread intent is represented by `?unread=1`; after resolving the first unread live post the server redirects to its explicit `?page=N#pID` location.
- Explicit `?page=N` and validation re-render page context take precedence over unread inference.
- Read state advances only after successful topic GETs, never during a POST re-render with status 422.
- `config/imladris-runtime-baseline.json` remains unchanged on this feature branch.
- Generated `resources/imladris/` and `public/assets/imladris.css` are rebuilt from `docs/design-system/imladris/`; they are never hand-edited.
- No commit, push, merge, deployment, or production-data mutation is part of this implementation.

## Permalinks and explicit unread navigation

`/t/{id}-{slug}` and `/t/{id}` retain page-one semantics. Page-one post permalinks may omit `?page=1`, because they now deterministically resolve to the first page. Pagination and action-return URLs may retain an explicit `?page=1` where preserving the exact current view is useful.

Inbox and other reader-oriented "resume" links use `?unread=1`. For an authenticated reader while either engagement or automated-context behavior is available, the controller asks one dedicated repository query for the first unread live post and its page. If found, it redirects to the canonical slug route with explicit page and fragment. If the reader is caught up, the cursor is absent/invalid, or both relevant flags are dark, the request resolves to page 1 without a hidden jump.

The first unread post receives a server-rendered textual boundary even when `automated_context` is dark. The richer since-last-read context remains independently feature-gated.

## Chronological read cursor

Posts are ordered by `(created_at ASC, id ASC)`. Every unread predicate, cursor advance, last-post repair, and since-last-read range must use that same total order.

`last_read_post_id` remains the stored cursor. Its referenced post is valid only when it belongs to the same thread and is a visible, approved post. A missing or cross-thread cursor is treated as no valid cursor. A candidate cursor advances an existing cursor only when its `(created_at, id)` tuple is later; a numerically larger but chronologically earlier imported or merged post must not move the reader backwards.

Thread listing unread flags and counts join the cursor post and compare the thread's canonical `last_post_at`/`last_post_id` tuple against it. `SinceLastReadContextService` uses tuple predicates and selects its range endpoint chronologically. `RepairService` recomputes `last_post_*` from the chronologically last approved, non-deleted post rather than `MAX(id)`.

A new additive migration adds the composite post-order index `(thread_id, is_deleted, is_pending, created_at, id)`. `SCHEMA.md` records the index and the corrected semantic description, without adding a column.

Split and merge operations repair cursors made invalid by moving posts. A split rebases affected source-thread cursors to the latest remaining post at or before the old cursor tuple, or clears them when none remains. Source-thread rows are cleared after a full merge because the source is redirected and its posts now belong to the target. Valid target-thread cursors remain intact; tuple comparisons make them robust to imported ID/time skew.

## Failed-write preservation

Reply validation continues to render the originating topic with the typed body, but it now carries the requested page explicitly and does not call `markRead()`. Split/merge validation carries both the page and selected post IDs. The restructure partial can therefore re-render the selected rows on the page where the moderator made the request, with the existing error and old input.

Star, workflow, and moderation action returns include the explicit current page so the browser does not silently jump after a write.

## Presentation corrections

The Thread View source and app-owned overrides receive the smallest scoped corrections:

- invalid engraved inputs receive a danger border/frame at the effective unlayered cascade level;
- the desktop fact row and its identity group stay on one line, while mobile explicitly permits wrapping;
- opener/reply prose may ellipsize, but assignment and exact snooze state move into non-clipping operational metadata;
- field spacing is owned once (`gap: 4px`, zero extra error margin), and the generic design-system hint/link-preview selectors are scoped to their actual component structures;
- poll results use an accessible, externally styled SVG bar with textual count/percentage, not the native platform-dependent `<meter>` widget;
- no inline style/script or CSP exception is introduced.

The source design-system bundle `_ds_bundle.js` is stale and is not a production dependency. Because the repository has no reproducible compiler for it, the stale generated file is retired rather than presented as current. Documentation and runtime-asset validation are updated to describe source HTML/CSS/JS previews accurately. This removal is recoverable from Git history.

## Thread Intelligence history

The existing Living Brief amendment history implementation remains unchanged. A focused regression proves an initial publication plus curator amendment renders descending history and a restore action for every eligible version. This closes the verified evidence gap without inventing a service fix for behavior that is already present.

## Deterministic browser evidence

The browser harness owns its mutations:

- status, star, reaction, and no-JavaScript reply cases restore their exact initial state in `finally` cleanup;
- the desktop and mobile evidence projects receive independent database preparation;
- running the focused spec twice without a reset remains green and leaves no additional persistent posts, reactions, stars, or workflow changes;
- keyboard coverage reaches Topic tools by real Tab traversal and proves layered Escape behavior (restructure closes first, Topic tools second, with focus restoration);
- fragment assertions verify that the final URL target exists in the rendered page;
- the stale desktop Thread Tools capture and its mobile counterpart are regenerated from the corrected implementation.

Tracked screenshots are completion evidence, not fixtures used to make assertions pass.

## Acceptance matrix

Completion requires observable tests for:

- page-one canonical and legacy fragment links, explicit unread redirect, returning-reader behavior, and all four engagement/automated-context flag combinations;
- timestamp/ID skew across normal reads and merge, tuple-correct list/count/context behavior, cursor repair, and indexed first-unread lookup;
- reply and split/merge 422 re-renders preserving page/input/selection without read advancement;
- real Living Brief history and restore controls;
- invalid state, fact-row/operational metadata, scoped design-system selectors, and SVG poll bars under strict CSP;
- absent stale bundle, generated-asset reconciliation, unchanged runtime baseline, and asset verification;
- repeatable desktop/mobile browser tests, real keyboard ordering, layered Escape, no-JavaScript behavior, and fresh visual evidence.

Final verification includes focused and full PHPUnit, migration upgrade rehearsal, Imladris build/check/verification with the feature baseline left untouched, JavaScript/PHP syntax, browser/a11y runs on private throwaway databases, visual inspection in the connected browser, and `git diff --check`.
