# ADR 0023 — Admin console audit round 2: closeout and deferrals

- **Status:** accepted (2026-07-18)
- **Relates to:** ADR 0021 (first remediation round), `docs/history/admin-ux-audit-round2-2026-07-18.md` (finding → disposition), `docs/superpowers/plans/2026-07-18-admin-audit-round2-remediation.md` (implementation plan)

## Context

The 2026-07-18 round-2 audit adversarially re-verified the PR #44 remediation (44 of 48 prior findings held) and surfaced 13 new findings plus one wrong prior disposition (F24, email status copy). This ADR records what shipped, two decisions the specs did not pin, and the deferrals that stay owned rather than silently dropped.

## Shipped (see the history doc for per-finding evidence)

1. Over-length moderation input 500s closed: `UserModerationService` caps reason at 255 chars (every sink is `VARCHAR(255)`, including `moderation_log.reason`) and note bodies at 64 KB (`user_notes.body` is `TEXT`) — 422 re-renders with input preserved.
2. Deleted-reply stubs: staff who hold `core.post.delete_any` or `core.post.restore` on the board see soft-deleted replies in place (masked byline, preserved content behind a disclosure, Restore button on `core.post.restore`) — ADMIN §3.3's "preserved for restore and accountability" now has a surface. Member/guest rendering is unchanged.
3. F24 fixed for real: `/admin/email` states one fact per line (transport / From / sending domain); "Sending is configured" can no longer render beside "Set a From address…".
4. Anti-draft-loss closures (custom emoji, email suppress/unsuppress), honest emoji replace copy, admin-warn idempotency seam, bulk-selection preservation, move-direction whitelist, explicit-slug conflict/overflow 422s (boards + tags), humanized rate-limit waits (`human_duration`), dashboard grammar + "New users today" label.
5. Accessible field errors: shared `field_error()`/`field_attrs()` helpers (error id + `aria-describedby` + `aria-invalid` + autofocus-on-first-error) wired across the operator-frequent admin forms; enumerated pockets fixed (unlabeled password inputs, empty `<th>`s, table scopes/regions, `role="alert"` on inline error flashes, pager labels, differentiated mod-queue row buttons).
6. Console IA per ADMIN §9.2: grouped admin nav (Dashboard · Moderation · Content · People · Appearance · Notifications · Integrations · Settings) with real Moderation entries, an Appeals dashboard card + attention line, and inbound links for the two orphaned consoles (`/admin/roles/simulator`, `/admin/packages/security`).

## Decisions (specs were silent)

**D1 — /mod posture rule.** Browsing a staff surface with zero moderation authority returns **404** (existence-hiding — extends `/mod/reports`' behavior and PR #44's "404 byte-identical to missing" precedent, per ADMIN §9.4 "hide what a role can't do"); attempting a staff **action** without authority stays **403**. Applied to `/mod/approvals`, `/mod/appeals`, and `/mod/u/{id}`; `/mod/approvals` additionally gains the `moderation_queue` flag gate it was missing (its dashboard pointers now follow the same flag — pre-fix, the un-gated Approval-hold card kept linking to the un-gated route with the flag off).

**D2 — §9.2 "Approval queue" placement.** The People section's "Approval queue" is read as the (still-deferred, ADR 0021 #3) *registration* approval mode. The content approval-hold queue lives under Moderation, whose §9.2 row already includes "approvals".

## Deferrals (owned, not dropped)

1. **Reports-queue bulk actions** (ADMIN §3.2, fourth bullet: "select many → dismiss / delete / lock in one step (each still audited individually)"). Needs row selection UI, a per-item-audited bulk transaction, and partial-failure semantics. The round-2 audit correctly flagged this as the one spec promise owned by no ADR — it is owned here now.
2. **Thread-level (OP) restore surface.** Deleting an OP runs `purgeThread` (whole-thread soft delete); no route or UI reverses it. The new per-reply stub work intentionally excludes it — a thread-level restore needs its own counters/redirect design.
3. **Deputy-facing roster surface.** The non-admin deputy branch of the roster commands has no UI form anywhere (roster forms render only on the admin-only board-edit console; the deliberate isolation comment in `AdminController::rosterDeputyExit` stands). The audit's "deputy draft-loss redirect" is reclassified accordingly — there is no draft to lose until a deputy surface exists, and building one is this deferral.
4. **Deep-admin field-error wiring residue.** `registries.php` and `role_edit.php` render field errors legibly but are not yet wired to their inputs via the new helpers (their duplicated error keys need per-form scoping first to avoid duplicate element ids). Mechanical follow-up; the helpers and the pattern exist.

## Reclassified

- `data-sole-count` (providers table) is **not** a dead PE hook: no JS reads it, but it is the integration-test anchor for the sole-method lockout count (`AppAdminProvidersTest`). Retained with an explanatory comment.
