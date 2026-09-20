# ADR 0035: Member settings completion carryovers

**Date:** 2026-09-20

**Status:** Proposed during planning; not a claim of implementation or accepted feature completion.

## Context

The notification and account-settings audits on `7257ca42` identified defects in implemented flows and separate specification gaps. The approved repair scope covers the existing notification features and all nine account-settings findings. Recording a separate gap must not imply that it shipped or silently expand the repair into a new subsystem.

ADR 0014 already covers the full event/channel matrix, quiet hours, digest preview, member test-send, suppression recovery, and email editing. ADR 0021 covers the separate staff notification/inbox carryover. This proposal gives the newly identified member security-activity and email-discoverability gaps an explicit home.

## Proposed disposition

| Gap | Current evidence and scope | Completion evidence required |
|---|---|---|
| Member security-activity view | USER §3.3 and §2's auth-event references describe a member view; the audited route/template inventory has no such view. Session listing/revocation is implemented and is not equivalent. Defer the new activity surface from this repair. | Define event provenance, retention, ownership and sensitive-field filtering; implement the member route and navigation; prove cross-account isolation, useful empty/error states, pagination and no-JS/mobile behavior. |
| Discoverability by email | The privacy preference is persisted/displayed, but the audit found no discovery consumer. This is an incomplete product contract, not evidence of a present email-disclosure exploit. Do not claim a working email-discovery feature or infer that email addresses should become public. | Specify exactly who can discover whom, consent/default behavior, address verification and abuse limits before adding a consumer; then prove opt-out, blocked/private account behavior and non-disclosure through API/HTML tests and browser evidence. |
| Username editing | USER §3.2 describes it, while the shipped profile form edits other identity fields. Existing phase/username-history prose is not a delivered member workflow. Retain this scope gap explicitly rather than adding rename/history/redirect behavior to the notification repair. | Establish uniqueness, reserved names, rate limits, redirects/history and moderation treatment; implement validation and safe dependent-link behavior; add member/security/browser evidence. |
| Last-20 notification dropdown | PRODUCT_DESIGN §6.10 describes the dropdown. This repair supplies the persistent bell/count and shared full/embedded list, but does not implement a new dropdown. | Reuse the same authorized read model and presenter; implement keyboard/focus, dismissal, mobile, no-JS fallback and current unread state without another eligibility or copy implementation. |

The first two entries are newly identified, previously untracked gaps. The latter two make the boundaries of the current repair explicit; they are not claimed to have been accepted deferrals already. The full notification matrix and staff alert work retain their existing ADR owners instead of moving here.

## Consequences

- The current repair must complete saved-feed open/rail/digest behavior and all audited broken account flows; those are not deferred by this ADR.
- USER/product/status/evidence ledgers must distinguish implemented controls from these unresolved contracts. Storage alone is not completion evidence.
- Before release, record this proposal's disposition and any clarifying UI copy for an inert preference; do not automatically mark it Accepted just because an implementation plan exists.
- No migration or runtime behavior changes are made by this document.

## References

- [Combined implementation plan](../superpowers/plans/2026-09-20-unified-notifications.md)
- [Design and audit coverage](../superpowers/specs/2026-09-20-unified-notifications-and-account-settings-design.md)
- [Existing member notification carryovers](0014-member-notifications-and-email-change-carryover.md)
- [Existing admin console carryovers](0021-admin-console-remediation-and-deferrals.md)
