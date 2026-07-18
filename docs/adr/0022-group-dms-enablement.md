# ADR 0022: Group DMs intentional enablement (default-on)

**Date:** 2026-07-18
**Status:** Accepted and implemented. `group_dms` graduated to default-on on
2026-07-18 with the acceptance-evidence package landed in the same change; it
remains operator-reversible through an explicit `false` override.
**Relates to:** ADR 0003 (Phase 4 closeout deferrals — carried `group_dms`
forward dark), the 2026-07-13 dark-flag readiness live drive
(`docs/evidence/deploy-dark-features.md`), and the Gate A engineering baseline
recorded in `docs/history/PHASE_1-4_HISTORY.md`.

## Context

Group conversations shipped with Phase 4 Gate A as an accepted engineering
baseline — bounded creation over the DM substrate, membership intervals, owner
actions, unread/history boundaries, admin-actionable reports, inactive-account
rejection, and DM-report rate limiting — but stayed deploy-dark pending three
things the 2026-07-13 readiness audit named explicitly: committed
browser/no-JS/a11y evidence, an abuse/moderation runbook, and an intentional
product enablement decision. The audit ranked `group_dms` first among the
remaining dark carryovers ("Ready for acceptance": the member journey already
worked end-to-end on desktop and mobile).

The product owner directed the graduation on 2026-07-18.

## Decision

1. `FeatureFlags::DEFAULTS['group_dms']` is `true`. Any install without an
   explicit `features.group_dms=false` override serves group conversations.
   The flag remains the incident kill switch: rolling it back re-gates
   creation (422, draft preserved) and the four management routes (404) while
   existing group conversations stay readable and replyable — rollback is
   data-preserving and never deletes or reverses anything.
2. **Staff access stays report-only.** The only staff window into a group is a
   member-filed report of a specific message (`reports.dm_message_id`); staff
   who are not participants receive 404 on the conversation itself. No
   private-message browser may be added under this decision — expanding staff
   visibility into DMs of any kind requires a new decision record.
3. The bounded-room posture is confirmed: participant cap via
   `dm.group_participant_cap` (default 12), recipient eligibility identical to
   1:1 rules at creation/add time (blocks, inactive-account rejection,
   `allow_dms`, new-account throttle), and the deliberate group-block
   semantics — later block changes do not rewrite an existing room; the
   affected member's tools are mute, leave, and report.
4. Membership intervals are a hard read boundary on every surface. The
   graduation evidence run surfaced one gap — the conversation-list preview
   (and its search) used the globally newest message regardless of the
   viewer's join boundary — fixed with this change
   (`ConversationRepository::listForUser` now honours
   `joined_after_message_id`; regression pinned in
   `AppDirectMessageTest::test_group_list_preview_respects_the_join_boundary`).

## Evidence

- PHPUnit: `AppFeatureFlagTest::test_group_dms_defaults_on_and_is_operator_reversible`
  (zero-override liveness, rollback re-gating, data preservation, dark-neighbour
  isolation); `AppDirectMessageTest` (DM substrate + the join-boundary preview
  regression); `AppPhase4GateATest` (Gate A group regression coverage);
  `AppAdminFeaturesTest` (50/7 defaults canary + readiness declassification).
- Browser: `tests/browser/group-dms.spec.ts` desktop+mobile captures
  `group-dms-01…06` and the JavaScript-disabled `group-dms-07-no-js`
  (docs/evidence/browser/), in the standard `npm run evidence` set.
- Accessibility: scoped axe scans of `.dm-compose`, `.dm-inforail`, and
  `.dm-threadpane` in `tests/browser/a11y.spec.ts`, desktop + mobile.
- Operations: `docs/runbooks/group_dms.md` (rollback mechanics, abuse-handling
  playbook, escalation ladder).

## Consequences

- The Phase 4 Gate A flag set is now fully default-on; the deploy-dark
  inventory's "Ready for acceptance" readiness category retires with its last
  row, leaving three dark carryovers (`link_previews`, `expanded_files`,
  `custom_css`) and the four ADR 0018 Gate B reservations.
- Operators inherit a live member-messaging surface with group fan-out; the
  runbook's escalation ladder (tighten `rate_limits.dm` → suspend accounts →
  flag off → `dms` off) is the supported incident path.
- Members gain group rooms whose history visibility is interval-bounded in
  both directions; the list-preview hardening keeps that promise on every
  surface that renders message content.
