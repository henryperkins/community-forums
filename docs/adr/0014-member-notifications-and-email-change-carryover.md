# ADR 0014 - Member notification controls and email-change carryover

**Date:** 2026-07-02
**Status:** Accepted as a deferral and carryover record.

## Context

`USER.md` section 4.6 specifies a member notification matrix across event type
and delivery channel, plus quiet hours, per-thread mute, pause-all-email, digest
preview, test send, and bounce recovery. The shipped `/settings/notifications`
surface now provides daily digest timezone/hour controls, a global
pause-all-email switch, and a list of thread/board subscriptions.

`USER.md` section 3.2 specifies a verified email-change flow: the new address
must be verified before it becomes active, and the old address must be notified.
The shipped `/settings/account` surface intentionally leaves the email field
disabled and labels it as not editable in this version.

The 2026-07-02 settings UX audit found these as untracked gaps. This ADR records
them so later release status cannot silently claim the full USER sections are
complete.

## Decision

The current release continues to ship the implemented subset:

- Daily digest timezone/hour controls, pause-all-email, and subscription listing
  under `/settings/notifications`.
- Disabled account email display under `/settings/account`.

The remaining USER section 4.6 member notification controls are explicit
carryover work. Completion requires the per-type x per-channel preference matrix
to be stored in `user_preferences.prefs` or a documented successor, and those
preferences must gate in-app fan-out, email queueing, and digest inclusion. Quiet
hours, per-thread mute, digest preview, test send, and bounced address re-enable
must either ship in the same slice or receive a narrower accepted ADR before
USER section 4.6 is marked complete.

The USER section 3.2 email-change flow is explicit carryover work. Completion
requires a route and service flow that keeps the old email active until the new
address is verified, notifies the old address, rate-limits abuse, records an
audit/security event, and preserves account recovery guarantees for password and
OAuth-linked accounts.

## Consequences

- The existing notification page must be described as a partial implementation,
  not as the full USER section 4.6 matrix.
- The pause-all-email switch is member-owned preference state; it must not clear
  or replace bounce, complaint, unsubscribe, or admin-manual suppression rows.
- Queued notification/system email rows for a paused member are dequeued as
  suppressed rather than held for later catch-up.
- The existing disabled email field is acceptable only as a labeled deferral; no
  inert or unverifiable email-change route should be added.
- Future phase status and evidence must cite this ADR until each carryover item
  has implementation and tests.
