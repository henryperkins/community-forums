# ADR 0025: Link previews completed and enabled (default-on)

**Date:** 2026-08-09
**Status:** Accepted and implemented. `link_previews` graduated to default-on on
2026-08-09 with the missing build work and the acceptance-evidence package
landed in the same change; it remains operator-reversible through an explicit
`false` override.
**Relates to:** DECISIONS §6 #5 (embeds/unfurl — "P2; opt-in per board;
server-side fetch with SSRF allowlist"), ADR 0003 (Phase 4 closeout deferrals —
carried `link_previews` forward dark), ADR 0022 (the `group_dms` graduation this
one follows), and the 2026-07-13 dark-flag readiness live drive
(`docs/evidence/deploy-dark-features.md`).

## Context

The link-preview *pipeline* has existed since migration `0058`: URL extraction
from post bodies, an SSRF-guarded fetch that pins the connection to the IP the
`EgressGuard` resolved, OpenGraph/Twitter-card metadata extraction, and a card
rendered under the post. What did not exist was everything an operator or a
member needs to live with it. The 2026-07-13 readiness live drive named the gap
precisely and ranked `link_previews` second among the remaining dark carryovers
— behind `group_dms`, ahead of `expanded_files` — with the note that it needed
work **built**, not merely evidenced:

> Inert until `link_preview_allowed_hosts` is populated; `GET /admin/link-previews`
> does not exist (the POST refresh/purge routes are unlinked), and the per-board
> opt-in and author removal are absent.

Two of those three were also *correctness* gaps, not polish. DECISIONS §6 #5 is
a locked decision that unfurling is **opt-in per board**, and nothing enforced
it: with the flag on, every public board would have started fetching. And a
member had no way to take a card off their own post — the only removal was an
operator purge, which the queue upsert deliberately revives on the next edit.

## Decision

1. **`FeatureFlags::DEFAULTS['link_previews']` is `true`.** Any install without
   an explicit `features.link_previews=false` override has the subsystem
   available. The flag remains the incident kill switch: rolling it back returns
   the console, the per-board control and the member remove/restore routes to
   404 and stops the render, while every stored row survives — rollback is
   data-preserving.

2. **"Default-on" means available, not fetching.** Three independent gates must
   all be open before a single outbound request is made, and a fresh install
   opens exactly one of them:

   | Gate | Default | Who opens it |
   |---|---|---|
   | `link_previews` feature flag | **on** | code default (this ADR) |
   | `boards.link_previews_enabled` | **off** | an operator, per board |
   | `link_preview_allowed_hosts` | **empty** | an operator, per host |

   This is the same "default-on but operationally dormant" posture
   `slash_giphy` carries without a `giphy_public_key`, and it is what makes the
   flag flip safe: no install starts making requests to third-party hosts on
   the strength of an upgrade. `/admin/features` reports the dormancy live and
   the badge clears once both steps are done.

3. **The per-board opt-in is honoured at queue time *and* at fetch time.**
   Re-checking in the worker is not redundant: rows queued while a board was on
   must not still reach the network after an operator switched it off (or after
   the post was deleted, held, or moved to a non-public board). A row that fails
   the re-check is marked `blocked` with a reason rather than left to retry.

4. **The author has the last word on their own post.** `link_previews.status`
   gains a `removed` member (migration `0081`) plus `removed_by`/`removed_at`.
   Removal is *sticky*: the queue upsert revives `purged` rows on the next edit
   by design, and a member's take-down must outlive an edit, so `removed` is
   excluded from that revival. Authorised for the post's author and for anyone
   with board-scoped `core.post.delete_any`; account state still beats role, so
   a suspended author is refused. Removed rows are returned only to viewers who
   could act on them, so nobody else can tell a card was ever there.

5. **Operator refresh will not override an author removal.** `refresh()` throws
   on a `removed` row and the console renders no refresh button for it. An
   operator who needs the URL gone still has purge; an operator who disagrees
   with a removal has the moderation surfaces, which are audited as moderation.
   The console must not be a quiet way around a member's decision about their
   own post.

6. **DMs are never unfurled.** `queueFromBody` refuses `dm_message` outright.
   A server-side fetch would disclose to the URL's operator that a private
   message contains that URL, with timing that correlates to when it was sent.
   The `link_previews.source_type` enum keeps the member for schema stability;
   no code path reaches it.

7. **Every operator write is audited.** Allowlist and kill-switch saves log
   against `setting`; board opt-in changes log against `board` with before/after;
   per-row refresh/purge log against the **post** the row belongs to, so
   `idx_modlog_target` surfaces them beside every other action on that post.

## Consequences

- Operators upgrading get a new console and a new per-board control, and their
  forums keep behaving exactly as before until they opt a board in. That is the
  intended trade: the flag flip is a no-op for anyone who does not act.
- The `link_previews` row leaves `/admin/features`' "Missing admin operations"
  category, which becomes empty and is retired from the readiness legend — the
  same thing that happened to "Ready for acceptance" when `group_dms`
  graduated. Four readiness categories remain.
- Default-dark flags drop from seven to six: `custom_css`, `expanded_files`,
  and the four ADR 0018 Gate B reservations.
- `expanded_files` is now the only remaining default-dark carryover with build
  work outstanding (member upload/download/quarantine UI plus the scanner
  operations surface); `custom_css` stays **safety-blocked** on the theme
  safe-mode defect. Neither is touched by this decision.

## Evidence

- **PHPUnit** — `AppLinkPreviewTest` (16 tests): board opt-in required before
  anything queues; a queued row blocked when its board opts out before the
  worker runs; kill switch skips without consuming; author remove survives an
  edit and restores; a non-author can neither see nor remove someone else's
  card; operator refresh refuses an author removal; the console's gate report,
  allowlist parsing (422 keeps the typed value), board toggle and audit rows;
  rollback returns every route to 404; a suspended author is refused; and the
  pre-existing SSRF/allowlist and pinned-IP fetch regressions.
  `AppFeatureFlagTest::test_link_previews_defaults_on_and_is_operator_reversible`
  (zero-override liveness, inert-by-default, rollback re-gating, and that a
  rollback-era board edit does not silently revoke a stored opt-in).
  `AppPhase4CarryoverFoundationTest` (graduated posture + independent rollback
  pin). `AppAdminFeaturesTest` (51/6 defaults canary, readiness
  declassification, live dormancy badge clearing).
- **Browser** — `tests/browser/link-previews.spec.ts`, desktop + mobile, in the
  standard `npm run evidence` chain → **8 passed**. Captures:
  `link-previews-console`, `link-previews-console-opted-out`,
  `link-preview-card`, `link-preview-removed-by-author`,
  `link-preview-other-member` under `docs/evidence/browser/{desktop,mobile}/`.
  The author remove/restore journey runs in a `javaScriptEnabled: false`
  context; `.admin-pane` and `.link-preview-cards` axe passes are clean of
  serious/critical violations.
- **Runbook** — `docs/runbooks/link_previews.md` (enable, allowlist, kill
  switch, incident response, rollback).
- **Schema** — migration `0081_link_preview_enablement`, SCHEMA.md v1.42.

## What this decision does not cover

- **Image rendering.** `link_previews.image_url` is captured and stored but
  never rendered. Displaying a remote image would make every reader's browser
  fetch a third-party asset on page load — a privacy leak the server-side fetch
  exists specifically to avoid — and proxying it is a separate storage and
  cache decision. Rendering it needs its own decision record.
- **Summaries.** `source_type = 'summary'` is gated identically (public board +
  board opt-in) but nothing queues summary bodies yet; wiring Thread
  Intelligence output into the queue is deliberately out of scope here.
- **A default allowlist.** No hosts ship allowlisted. Shipping a curated list
  would be us deciding which third parties every operator's server talks to.
