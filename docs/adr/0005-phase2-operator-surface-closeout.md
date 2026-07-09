# ADR 0005: Phase 2 operator-surface closeout — email-ops dashboard pull-forward

**Date:** 2026-06-29
**Status:** **Accepted as the Phase 2 operator-surface closeout decision record.**
Recorded under the locked product decisions in
`docs/superpowers/plans/2026-06-29-phase2-operator-surfaces-contract.md` (decision #1).
This ADR reverses a recorded Gate B deferral **deliberately rather than silently**
(DESIGN §13 / CLAUDE.md "deferrals are recorded in `docs/adr/000N-*.md` — never
silently dropped"). It does **not** accept any item it lists as still deferred.

## Context

`docs/history/PHASE_1-4_HISTORY.md#phase-2-status` (Gate B acceptance checklist) re-scoped the dedicated
admin email **delivery dashboard** to Phase 3, while the underlying primitives —
`statusCounts`, worker stats, and one-click-unsubscribe suppression recovery —
already shipped in Phase 2. Without the dashboard, operators could observe and
recover the email queue only via the console or direct DB access.

The Phase 2 operator-surface closeout (2026-06-29) pulls the dashboard back into
Phase 2 so operators get queue visibility and recovery from the admin console.
Because a recorded deferral must never be silently reversed, this ADR documents
**both** what is now shipped and what remains deferred, so the carryover ledger
stays legible.

This closeout is the umbrella for four merged operator-surface groups:

- **Group A** — per-user admin record (member detail / account state surface).
- **Group B** — board reorder + archive ("close everything") tooling.
- **Group C** — site announcements: dismissible banner + **in-app** broadcast.
- **Group D** — the admin email-ops dashboard that this ADR records (Tasks 1–4).

Groups A, B, and C are merged; this ADR's decision focuses on Group D and on the
explicit deferrals and one side-effect that cross those groups.

---

## Decision

### 1. Pulled forward into the Phase 2 closeout (built now)

The admin `/admin/email` delivery dashboard, gated behind the **existing `email`
flag** (no new flag introduced):

- a **filterable delivery log** over `email_deliveries` (filter by status, kind,
  and recipient email);
- **queue status cards** driven by `statusCounts` plus worker stats;
- **test-send**, rate-limited under the `email_test` policy and **fail-closed**:
  it refuses to send when the transport is unconfigured
  (`Mailer::isConfigured()` is false);
- manual **failed-delivery requeue**, using the existing audited
  `EmailOpsService::requeueFailed()` path;
- manual **suppression list add/remove**, applying the **ADMIN §7.6 per-user
  subscription `email_enabled` cascade** (removing/adding a suppression keeps the
  subscription channel state consistent);
- a **From/config status banner** that blocks-until-ready when the transport has
  no configured From address;
- a read-only **CSV export** of the delivery log.

Every mutation is `requireAdmin` + `WriteGate` gated and writes one
`moderation_log` audit row.

**No schema change:** the dashboard reads/writes only existing tables
(`email_deliveries`, `email_suppressions`, `subscriptions`, `moderation_log`).

### 2. Deferred from this closeout, later resolved

These were recorded as owned, explicit carryovers for the 2026-06-29 closeout —
**not** claimed as done in this ADR and **not** silently reclassified. They were
implemented later in the 2026-06-30 carryover slice under ADR 0008:

- **(a) Email-broadcast announcement channel.** Group C shipped the dismissible
  announcement **banner** and **in-app** broadcast **only**. The later slice adds
  email broadcast fan-out through `email_deliveries.kind='system'`.
- **(b) `NotificationEmailWorker` `kind='system'` render path.** The worker
  was intentionally **NOT** modified in this closeout. The later slice renders
  announcement payloads and applies suppression/worker policy.
- **(c) ADMIN §7.5 SPF/DKIM domain-status / sending-blocked gate.** Only
  `Mailer::isConfigured()` (From-address presence) was enforced in this closeout.
  The later slice adds cached SPF/DKIM status, manual refresh, and opt-in verified
  domain send blocking.

### 3. Product-owner sign-off item — Group B side-effect

The Group B archive "close-everything" tightening **removed the tag-edit
carve-out**. Tagging now follows `canPost`. As a result, a **board moderator who
is NOT a member of a *private* board can no longer tag there**. This is a
deliberate behavior change flagged for product-owner sign-off (it narrows who can
tag on private boards), not a bug.

---

## Consequences

- The Phase 2 email surface is **observable and recoverable from the admin
  console** without a console/DB detour, behind the existing `email` flag.
- The three deferred items in Decision #2 carry forward as **explicit, owned
  deferrals** to be picked up in a later phase with their own scope record; none
  is implied to be shipped by this closeout.
- The Group B tag-edit tightening (Decision #3) awaits product-owner sign-off; if
  rejected it must be reverted/scoped, not left implicit.
- No migration lands with this closeout; the dashboard is purely additive UI over
  existing tables.
