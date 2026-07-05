# ADR 0016: Increment 6 — resolver enforcement cutover + assignment lifecycle

**Date:** 2026-07-04
**Status:** **Accepted.** Decisions 1–6 accepted 2026-07-04 via the interactive design
review (this session's AskUserQuestion decisions). Decision 7 (the per-button
display-flag scope addition) was accepted 2026-07-05, during execution — see
*Owner sign-off* below.
**Supersedes:** none directly. Resolves the two legacy quirks that ADR 0012's A1
sign-off deliberately left open ("keep the documented quirks for Gate A... the
vestigial global-moderator behavior is preserved (not 'fixed' mid-parity)") —
that stance was correct *pre-parity*; this ADR is the post-parity owner decision
`docs/phase5/capability-taxonomy.md` §7 always said would be needed.

## Context

Increment 1 (2026-07-02) landed the capability resolver **in shadow**:
`CapabilityResolver`/`CapabilityRules` answer all 54 catalogued `core.*` keys,
`LegacyAuthorityProjection` maps accepted `users.role`/`board_moderators`/
`protected_owners` authority into resolver grants, and the parity corpus is
clean — **1551 tuples, 1551 agreed, 0 mismatches**
(`docs/evidence/phase5/resolver-parity.md`, fixture v2). The Phase 5 review
hardening pass (2026-07-04, commit `49db9a8`) closed the recorded **E1**
prerequisite — `CapabilityRules::scopeSatisfies` no longer fails open for a
board/category-scoped grant with no target in context — clearing the last
blocker to enforcement.

Before this increment, none of the following existed: any live enforcement
path (every call site's *answer* still came from the legacy expression, not
the resolver); the assignment write lifecycle (`role_assignments` had a
test-only `create()` — no revoke, no renew, no route, no UI); cache
invalidation; the role-demote owner-loss guard (there was no in-app
`users.role` mutation path at all); a capabilities runbook; or the
staged-enablement mode lever. This increment (P5-08 enforcement cutover +
P5-09 scoped assignments + the Inc-6 slice of P5-10 owner-loss wiring) builds
and evidences all of it, deploy-dark, satisfying **GA-DOD-11** and advancing
**GA-DOD-10**/**GA-DOD-12** (`docs/phase5/requirement-ledger.json`).

Two owner decisions were required to resolve documented legacy quirks
(`docs/phase5/capability-taxonomy.md` §7 #5/#6, recorded 2026-07-02 at the Inc
1 review); four more decisions were required to bound this increment's
breadth, authority source, owner-loss wiring, and rollback story
(`docs/superpowers/specs/2026-07-04-inc6-resolver-enforcement-cutover-design.md`
§0). A seventh decision — an in-scope discovery, not a deferral — was made
during execution once per-action route enforcement was found to leave the
moderation toolbar's *display* still gated on one coarse flag.

## Decision

1. **Suspended-staff pending-view tightening (taxonomy §7 quirk 5) —
   ACCEPTED.** Under `CAPABILITIES_MODE=enforce`, suspended staff lose
   pending-content **views** (the approval queue, pending media) exactly as
   they already lose pending **actions** — "state beats role" applied
   uniformly, instead of the two authorities diverging as they do in
   production today. Pinned by
   `AppEnforcementCutoverTest::test_suspended_global_moderator_loses_pending_views_under_enforce_adr_0016`
   (with
   `test_suspended_global_moderator_keeps_pending_view_in_shadow_mode` proving
   the companion half: shadow mode, the default, keeps the legacy quirk
   unchanged). The resulting `resolver.shadow_mismatch` telemetry during the
   shadow soak is **expected**, not a regression signal — documented in the
   runbook so an operator watching the soak doesn't chase it.
2. **Service-owned content-state boundary (taxonomy §7 quirk 6) —
   RATIFIED.** Archived-board freezes, locked-thread rules, and deleted-target
   rules stay service-owned sub-rules, not capabilities. The resolver narrows
   by content state only where the legacy route gate itself was
   `BoardPolicy::canPost` (`core.thread.create`/`core.post.create`/
   `core.thread.tag`). `PollService`'s missing archived-close stays a
   documented pre-existing quirk, unchanged and out of this increment's scope.
3. **Cutover breadth: the board/content surface plus the four board-roster
   POST command endpoints.** ~30 call sites are cut over: `ModerationService`
   (delete/restore/lock/pin/move/reveal-author), the posting floor
   (`core.thread.create`/`core.post.create`/`core.thread.tag`), the three
   dual-path services (solved-answer, polls, thread workflow), the two
   quirk-key sites (`core.user.warn` staff-any, `core.content.view_pending`
   as a site probe), the authority-granting sites
   (`core.appeal.resolve_content`, `core.report.handle`,
   `core.memory.curate`, `core.content.approve`, `core.thread.split_merge`),
   and `AdminService::assignModerator`/`unassignModerator`/`addMember`/
   `removeMember`. The `/admin` console (~19 controllers on bare
   `requireAdmin()`) **stays legacy this increment** — a recorded follow-up,
   not a silent drop — bridged by the `EnforcedCapabilities` honesty clamp
   (Decision 6) so the role editor can never grant a key with no live route to
   back it.
4. **Legacy authority source: the projection stays authoritative ("virtual
   import"); no migration ships.** `users.role`/`board_moderators` remain the
   source of built-in authority, read through `LegacyAuthorityProjection`;
   `role_assignments` holds only new custom/temporary grants. `verify:upgrade`
   stays at **17/17** (no new migration this increment). TM-PE-06
   (non-broadening) is proven by the parity corpus running against the exact
   same resolver that now enforces — not a separate, weaker check.
5. **Build the missing change-role action, with the owner-loss demote path
   real and guarded.** `UserModerationService::changeRole()`
   (route `POST /admin/users/{id}/role`, **flag-independent** — it manages
   `users.role`, which exists regardless of Phase 5) is new. Demoting an admin
   runs `LastOwnerGuard::assertNotLastOwnerForUpdate` inside the same
   transaction as the role write, the mirrored `ProtectedOwnerRepository`
   deactivation, and full session revocation for the target — the **TM-PE-07**
   case. Promote-to-admin and user↔moderator changes are symmetric (role
   write + session revocation + audit).
6. **Mode lever: `CAPABILITIES_MODE` env config, `shadow` (default) |
   `enforce`.** Posture lives in config, not the flag (DECISIONS precedent:
   `antiabuse.mode`) — `capabilities` continues to gate **availability** only
   (routes exist/404); this config value decides whether `AuthorityGate`
   enforces or only shadow-compares. `AuthorityGate` is the single seam:
   **legacy** (flag off — resolver is never constructed, so dark behavior is
   byte-identical to today), **shadow** (Inc 1 behavior, relocated behind one
   seam), **enforce** (the resolver decides and **fails closed** on any
   resolver error; the legacy closure is recomputed only for
   reverse-mismatch telemetry, never for the returned answer).
7. **Per-button moderation display flags ("Option B") — ACCEPTED DURING
   EXECUTION, 2026-07-05.** Once Task 4's per-action *route* enforcement
   landed, the moderation toolbar in `templates/thread.php`/
   `templates/partials/post.php` still gated its **entire** visibility on one
   coarse `can_moderate_board` flag (default key `core.post.delete_any`)
   computed once in `ThreadController`. A custom role holding only
   `core.thread.lock` was authorized to `POST /mod/t/{id}/lock` but the Lock
   button never rendered — unusable, in a server-rendered app with no API
   client, for anyone who isn't a full board moderator or admin. The owner
   chose to make per-action grants genuinely UI-usable **this increment**
   rather than defer it: `ThreadController` now computes one display flag per
   action (`can_pin`, `can_lock`, `can_move` — computed and exposed but not
   yet wired to any control, `can_split_merge`, `can_delete_posts`, plus
   re-keying two pre-existing but wrongly-gated flags, `can_curate_memory`/
   `can_edit_tags`, onto their actual capability keys instead of the
   coarse default). Landed in Task 4b (commit `fba2196`); browser-evidenced
   end-to-end by
   `docs/evidence/browser/{desktop,mobile}/64-deputy-sees-lock-control.png`
   — a deputy holding only `core.thread.lock` sees exactly the Lock control,
   and zero Pin control, in the topic-actions overflow. **This is DONE, not
   deferred.** The one open item is `core.thread.move` (computed, unwired —
   no move-thread control exists anywhere in the app yet); `core.post.restore`
   was deliberately **not** introduced as a display flag, because no UI path
   can ever reach a soft-deleted post today (`PostRepository::listByThread()`
   filters `is_deleted = 0` unconditionally).

## Consequences

**Enforcement behavior deltas (shadow vs. enforce).** Outside Decision 1's
tightening, `enforce` mode is behavior-identical to legacy for every
pre-existing (non-custom-role) actor — the parity corpus proves the
underlying predicates agree, and `AppEnforcementCutoverTest` proves
route-level behavior for admins/board-moderators/plain members is unchanged
end-to-end under `enforce`. The **only** live behavior delta at cutover is
Decision 1 (suspended staff losing pending-content views); everything else is
additive — a new capability becomes newly delegable via custom roles, and no
existing grant is narrowed.

**The honesty clamp (`src/Security/EnforcedCapabilities.php`).** 21 of the 54
catalogued keys have a live route consulting the resolver after this
increment. `RoleService::validateDefinition` refuses to let any custom role
hold a key outside this set ("…is not yet enforceable; it can be granted once
its routes cut over to the resolver") — a grant that would be authorization
theater is refused at definition time, never silently accepted and then
ignored at decision time. The clamp lifts per-key as later increments cut
more surface over; the admin-console cutover (Decision 3) is the largest
remaining source of clamped keys.

**Known characteristic — route-enforcement and custom-delegability are
separate axes (recorded finding).** Of the 21 `EnforcedCapabilities` keys,
~15 moderation/board keys are genuinely custom-delegable end-to-end — proven
by `tests/browser/role-assignments.spec.ts`: a lock-only custom role can lock
and nothing else. The remaining 6 are route-enforced (the clamp's bar) yet
effectively inert for a **custom** role to hold meaningfully:
   - The **3 posting keys** (`core.thread.create`, `core.post.create`,
     `core.thread.tag`) are baseline `system.user` capabilities every member
     already holds, and cannot be narrowed below `post_min_role` (which
     itself narrows on global site-rank, not on custom-role membership) — a
     custom grant of these keys adds nothing a member doesn't already have.
   - The **3 dual-path keys** (`core.thread.mark_solved`, `core.poll.manage`,
     `core.thread.manage_workflow`) give a custom role only the **owner**
     path (resolver-narrowed to the actor's own thread); the
     moderator/staff path is restricted to `system.moderator`/`system.admin`
     role-**kind** by `CapabilityRules::DUAL_PATH_BOARD_AUTHORITY` — an Inc-1
     decision, unchanged here. A custom role cannot become a board-wide
     dual-path authority.
   Route-enforcement (what the clamp gates) and meaningful custom-role
   delegability (a separate, narrower guarantee) are not the same claim. This
   is a known characteristic of the current clamp, not a defect, and this ADR
   is the place that says so plainly rather than leaving it implicit.

**Recorded future requirement — revoke/renew have no per-assignment authority
ceiling.** `RoleAssignmentService::grant()` enforces a grantor ceiling
(**TM-PE-02**: every capability the role carries must resolve `allowed` for
the grantor at the exact target scope) — a board-scoped grantor mathematically
cannot mint site scope. `revoke()` and `renew()` have **no equivalent
per-assignment ceiling check**; both gate on `WriteGate::assertCanWrite` only.
This is **safe this increment** because all three assignment routes
(`POST /admin/roles/{id}/assignments`, `POST /admin/role-assignments/{id}/revoke`,
`POST /admin/role-assignments/{id}/renew`) sit behind `requireAdmin()` — every
caller is already a full admin, so there is no narrower grantor whose ceiling
could be bypassed. **A considered symmetric revoke/renew authority model is a
required prerequisite before any future increment delegates role-management
itself to a non-admin** (the roster commands Task 8 delegated to board
moderators were narrower — assign/unassign a *moderator*/*member* row, not an
arbitrary custom-role assignment). Recorded here as a scope boundary, not a
silent gap.

**Admin-roster deputy UX (recorded follow-up).** The four board-roster POST
command endpoints are capability-enforced (Task 8:
`core.board.assign_moderators`/`core.board.manage_members`), so a deputy
holding those keys can already *act* on them directly. The admin-console
**GET** surface those commands live on is still inside Decision 3's
legacy-console boundary — a non-admin deputy is flash-and-redirected off
`/admin/boards` with no `board_edit` disclosure (verified: no information
leak). A full deputy-facing roster UI is part of the recorded admin-console
cutover follow-up, not shipped this increment.

**Rollback levers (both preserved by Decision 4 — nothing was demoted).**
1. `CAPABILITIES_MODE=shadow` (or unset) — decisions revert to legacy
   instantly; the role editor/simulator/assignment UI stay reachable; custom
   grants stop applying (they only ever applied through the resolver).
2. `features.capabilities=false` — everything dark: routes 404, the gate
   returns the legacy answer verbatim, grants are inert. Nothing to
   un-migrate — no DDL shipped this increment.

Both levers are rehearsed with a real transcript in
`docs/evidence/phase5/capabilities-fallback-rehearsal.md` and documented
step-by-step in `docs/runbooks/capabilities.md`.

## Owner sign-off

- **Decisions 1–6** — accepted 2026-07-04 via the interactive design review
  (this session's AskUserQuestion decisions); recorded in
  `docs/superpowers/specs/2026-07-04-inc6-resolver-enforcement-cutover-design.md`
  §0.
- **Decision 7 (Option B per-button display flags)** — accepted 2026-07-05
  during execution, once Task 4's route-only cutover was found to leave
  per-action grants unusable in the UI. Landed and evidenced in Task 4b; not
  deferred.
- **Parity-first stance preserved** for every quirk not explicitly revisited
  here (ADR 0012 precedent stands): §7 #1 (no `edit_any`), #2 (`core.user.warn`
  is staff-any), #3 (vestigial global-moderator exemptions), and #4 (dual
  pending-view authority for threads vs. media) are unchanged by this
  increment.
