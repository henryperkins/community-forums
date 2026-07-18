# ADR 0021: Admin-console remediation closeout — what shipped, what stays deferred

**Date:** 2026-07-18
**Status:** Accepted as the deferral record for the 2026-07-18 admin-console remediation.
This ADR exists because the repo's rule (DESIGN §13 / CLAUDE.md) is that deferrals are
recorded, never silently dropped — and the 2026-07 admin UX review found four spec
promises tracked nowhere. The remediation ships most of them; this document owns
the remainder so later status cannot silently claim the ADMIN.md sections complete.

## Context

`docs/history/admin-ux-review-2026-07.md` (48 findings) and its 2026-07-18
re-verification identified defects, draft-loss regressions, orphaned UI, and four
untracked deferrals (bulk moderation, the audit-log screen, email-template
editing, the staff alert matrix). The 2026-07-18 remediation fixes the defect and
draft-loss classes wholesale and builds three of the four untracked features.

## Decision — shipped in this remediation (summary, not a deferral)

- `post_min_role` regression fixed + "Who can post" and "Edit window" board
  controls (edit window now enforced in `PostingService::editOwnPost`, staff exempt).
- Bulk user moderation end-to-end (`/admin/users/bulk` → confirm → per-member
  audited apply), replacing the dead phantom UI.
- `/admin/audit`: filterable, paginated audit-log screen (admin, site-wide).
- `/mod/u/{id}` staff panel restoring the moderators' §3.4 warn/note surface; the
  thread **Move** control restored in Topic tools; both were UI-orphaned routes.
- Board delete-with-move (ADMIN §4.4 "choose what happens to its threads").
- Anti-draft-loss re-renders for split/merge/move, role clone, appeal
  resolution, announcements (429), dashboard settings; honest requeue feedback;
  branding upload failures surfaced; reauthed webhook delete; distinct
  pause/resume flashes; package-lifecycle success flashes; typed-confirm ban and
  branding reset; audited PII reveal (email + recent IPs) on the user record;
  suspensions recorded as `bans.type='post'` (read-only), no longer as `full`.
- Reports queue: board/reason/status filters, pagination, >24 h aging cue,
  per-item "Warn author" path.
- Tag merge impact-confirmation page; a11y/label/scroll-region sweep; onboarding
  tour suppressed on `/admin*` and `/mod*`.

## Decision — explicit deferrals (owned, not silent)

1. **Email-template editing** (ADMIN §7.4/§7.5: editable templates, preview,
   test-send). Requires a template storage/versioning model and a render-preview
   seam in the Mailer path. Until then the fixed transactional templates stand.
2. **Staff alert matrix + staff inbox** (ADMIN §7.1–§7.4: event × channel ×
   audience, thresholds, quiet hours, digests). Sibling of the member matrix
   already deferred in ADR 0014; both should land on one preference-matrix
   substrate rather than two divergent ones.
3. **Registration approval mode, verification-requirement toggle, password
   policy, rate-limit editor** (ADMIN §9.3 Settings → Registration/Security;
   §5.6 approval queue). Each needs its enforcement path built first —
   registration-approval queue, policy checks in AuthService, and a bounded,
   validated policy editor over `config` limits. The UI ships only with the
   enforcement (inert settings are not evidence).
4. **Ban types, durations, and board scope** (ADMIN §3.4/§5.4: post-only vs
   full bans, expiring bans, board bans/mutes, moderator board-suspend via
   `bans` `scope='board'`). Enforcement today rides `users.status` only; board
   rows and expiry sweeps for bans do not exist. This is a WriteGate/
   BoardPolicy/worker design, recorded here rather than faked with fields.
5. **Board-scoped moderator audit view** (`mod.log.view`, ADMIN §3.6). The
   shipped `/admin/audit` is admin-only; `moderation_log` rows do not carry a
   board id, so scoping needs per-target board resolution or a logged board
   column before a moderator view can be honest.
6. **Remaining §4.2/§4.1/§4.5 board settings**: icon/emoji, a locked state
   distinct from archived, allowed thread prefixes, category default-collapsed,
   bulk archive. No schema/enforcement exists for these; adding fields now would
   be inert UI.
7. **link_previews operations console** — already tracked as "Missing admin
   operations" on `/admin/features` and in `docs/evidence/deploy-dark-features.md`;
   reaffirmed here. The flag stays default-dark until the ops surface exists.
8. **Drag-and-drop structure reorder.** The ↑/↓ button forms remain the
   mechanism; `POST /admin/structure/reorder` is retained (tested) as the
   enhancement's future target. The dead `data-reorder-*` attributes were
   removed so the DOM stops promising JS that does not exist.
9. **Alt-account / device signals** on the user record (ADMIN §5.5). The
   audited email+IP reveal shipped; matching heuristics across accounts are a
   privacy-sensitive design of their own.
10. **Board soft-delete with reserved slugs** (ADMIN §4.4, including the
    optional soft-delete-threads-with-board path). The PR #44 safety
    remediation (2026-07-18) codifies the shipped behaviour as
    hard-DELETE-with-forced-move: every thread row (hidden, held, and deleted
    included) moves to a destination inside one locking transaction, and the
    board row is removed — which cascades `board_slug_history` away and so
    actively un-reserves its slugs. Soft-delete semantics (retained row,
    reserved slugs, optional thread soft-delete) remain the §4.4 target and
    are deferred, not dropped.

### Post-review decisions (2026-07-18, PR #44 review)

- **Private staff notes are admin-only**, narrowing §3.4's "Add mod note →
  `mod.user.warn`" mapping. The shipped `user_notes` table is globally scoped
  (no board column), so any-board-moderator read/write meant every moderator
  of any single board could read every note about every member — strictly
  worse than the narrowed capability. Board-scoped notes can widen this later;
  the narrowing is annotated at ADMIN §3.4 and recorded in its changelog.
- **Follow-up (pre-existing, out of the PR #44 scope):** the reports queue
  de-anonymizes without an audit trail — `ReportRepository.php` queue/detail
  queries select the raw author of anonymous reported posts (lines 77, 85,
  102, 107) and `templates/mod/reports.php:91-113` renders `@username` plus a
  "Warn author…" link, bypassing the audited `/mod/p/{id}/reveal` flow every
  other surface uses. Logged here for its own fix; deliberately not expanded
  into the PR #44 remediation.

## Consequences

- ADMIN.md §§3.4, 4.1–4.5, 5.5, 7.1–7.5, 9.3 remain **partially** delivered;
  status documents must cite this ADR rather than claiming them complete.
- Each deferral above ships only together with its enforcement path and tests.
- The four formerly-silent deferrals are now either built (bulk moderation,
  audit screen — items shipped above) or owned here (email templates, staff
  matrix), closing the no-silent-deferrals violation the 2026-07 review flagged.
