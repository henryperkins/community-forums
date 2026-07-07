# Runbook — Capabilities (resolver enforcement, P5-08/09, Inc 6)

## What the flag/mode control

`capabilities` (default **OFF**) gates **availability**: the `/admin/roles`,
`/admin/roles/{id}`, `/admin/roles/simulator`, `/admin/role-assignments/*`
routes exist and everything they touch (role editor, permission simulator,
scoped assignment grant/revoke/renew) stays 404 while dark. `changeRole`
(`POST /admin/users/{id}/role`) is the one exception — it is **flag-independent**
because it manages `users.role`, which exists regardless of Phase 5.

`CAPABILITIES_MODE` (env, config path `capabilities.mode`, default `shadow`)
controls **posture**, independent of the flag — this mirrors the
`antiabuse.mode` precedent (posture lives in config, not in a flag):

| `capabilities` flag | `CAPABILITIES_MODE` | `AuthorityGate::mode()` | Behavior |
|---|---|---|---|
| off | (irrelevant) | `legacy` | Resolver is never constructed. Every one of the ~30 cutover sites plus the 4 board-roster commands returns exactly the legacy answer. Byte-identical to pre-Inc-6. |
| on | `shadow` (default) | `shadow` | Legacy still decides every live answer. `ResolverShadow` compares and emits `resolver.shadow_mismatch` telemetry on disagreement — Inc 1 behavior, relocated behind the `AuthorityGate` seam. |
| on | `enforce` | `enforce` | The resolver decides. **Fails closed** (`return false`) on any resolver `Throwable` — a DB blip must deny, never allow — and emits `authority.enforce_error`. The legacy closure is still computed, but only for `resolver.enforce_mismatch` telemetry; denials also emit `authority.enforce_denied {capability, source, reason, actor_id, board_id}`. |

`AuthorityGate::legacy()` (an unconditional passthrough) is what hand-constructed
service instances get in unit tests and what the container binds when the flag
is off — the resolver is structurally unreachable in that mode, not merely
unconsulted.

Mode values are normalized (trimmed, case-folded), so ` ENFORCE ` works. An
**unknown** value (typo, or `legacy` — which is the flag-off state, not a
configurable posture) runs the fail-safe `shadow` posture and emits
`capabilities.mode_invalid {raw, effective}` telemetry instead of failing
silently. The **effective** posture is displayed on `/admin/roles`
("Resolver posture: …") — check it after any `CAPABILITIES_MODE` change
rather than trusting the env file.

## Staged rollout (§13.1)

1. **Enable the flag in shadow** (`features.capabilities=true`,
   `CAPABILITIES_MODE` unset/`shadow`). No live behavior changes. Role
   editor/simulator/assignment UI become reachable; grants can be created but
   do not yet apply to anything.
2. **Soak and watch `resolver.shadow_mismatch`.** The *only* expected mismatch
   during the soak window is the suspended-staff pending-view divergence
   (ADR 0016 decision 1: production keeps the legacy "view survives
   suspension" quirk in shadow; the resolver already answers the tightened
   way it will enforce). Any *other* mismatch is a real parity bug — stop and
   investigate before proceeding; it means the resolver disagrees with legacy
   on something this ADR did not intend to change.
3. **Flip `CAPABILITIES_MODE=enforce`.** Live authorization now comes from the
   resolver across the cutover surface (§ below). Suspended staff immediately
   lose pending-content views (the one accepted behavior delta) — expect a
   handful of support questions from staff who don't know they're suspended;
   this is intended, not a bug.
4. **Pilot one narrow custom role on a test board.** Create a role with
   exactly one capability (e.g. `core.thread.lock`), grant it board-scoped to
   one trusted member, and confirm both the route (`POST /mod/t/{id}/lock`
   succeeds, everything else still 403s) and the UI (only the Lock control
   renders — `docs/evidence/browser/{desktop,mobile}/64-deputy-sees-lock-control.png`
   is the reference screenshot for exactly this scenario).
5. **Broaden.** Add more custom roles/assignments once the pilot is clean.
   The admin console itself (~19 controllers on bare `requireAdmin()`) is
   *not* part of this rollout — it stays on legacy admin-role authorization
   until its own cutover lands (see *The clamp*, below).

## Cutover surface (what actually enforces under `enforce`)

- **Moderation:** delete/restore any post, lock/pin/move/split-merge a thread,
  reveal an anonymous author, approve/reject pending content, handle reports,
  resolve content appeals, curate community memory (`ModerationService`,
  `ApprovalController`, `ReportService`, `AppealService`,
  `CommunityMemoryService`, `ThreadSplitMergeService`).
- **Posting floor:** start a thread, post a reply, tag a thread
  (`PostingService`, `TagController` via `BoardPolicy::canPost`).
- **Dual-path (author-or-staff):** accept/clear a solved answer, manage a
  poll, manage thread workflow status/assignment (`SolvedAnswerService`,
  `PollService`, `ThreadWorkflowService`) — the resolver authorizes if the
  actor owns the target thread **or** holds the board-scoped grant.
- **Quirk keys, preserved as documented:** `core.user.warn` (staff-any, site
  probe, no board target); `core.content.view_pending` (site probe — a
  board-scoped grant correctly does **not** qualify, matching legacy
  `isModerator()` for everyone except suspended staff — see Decision 1).
- **Authority-granting:** assign/unassign a board moderator, add/remove a
  private-board member (`AdminService`) — the 4 board-roster POST commands.
- **Queue discovery (2026-07-07 follow-up):** `/mod/approvals` row scope and
  the `/mod/reports` door+rows derive from
  `ModerationService::moderableBoardIds()` — a per-board gate check on the
  queue's *action* key (`core.content.approve` / `core.report.handle`) — so
  a custom deputy's board- or site-scoped grant surfaces its rows under
  `enforce`, and the approvals door additionally admits (enforce-only) a
  deputy with ≥1 discovered board. Legacy/shadow doors and rows are
  byte-identical to pre-cutover: global moderators still see an empty
  approvals page and 404 at `/mod/reports`, and assigned board moderators
  still cannot open `/mod/approvals` outside enforce.
- **NOT cut over (recorded, not silent):** the board read gate
  (`BoardPolicy::canRead`/`isListed` — union-then-narrow means a grant can
  never broaden a read, so cutover there is a no-op by construction);
  baseline member keys at read-gated sites (every member holds them via
  `system.user`); the vestigial-global-moderator *exemptions* (anti-abuse,
  upload floor, DM throttle — taxonomy §8 non-capabilities, not authority);
  the `DirectMessageService` admin DM-off override (§8, uncatalogued bypass);
  and the entire `/admin` console.

## Emergency fallback

**Capture state FIRST, before touching either lever** — you cannot recover
denial telemetry retroactively:

1. Snapshot recent `authority.enforce_denied`, `authority.enforce_error`, and
   `resolver.enforce_mismatch` telemetry (whatever your telemetry sink is
   configured to retain).
2. Snapshot the relevant `moderation_log` window (`assign_role`,
   `revoke_role`, `renew_role`, `role_assignment_denied`, `change_role`
   actions), since a same-window revoke/grant may explain an unexpected
   denial.

**Then** pick a lever:

- **Lever 1 — `CAPABILITIES_MODE=shadow` (prefer this first).** Every live
  decision reverts to legacy instantly. The role editor, simulator, and
  assignment UI **stay reachable** — you can still inspect/fix a role
  definition or a bad grant while decisions are on legacy. Nothing is
  deleted; re-flipping to `enforce` picks up wherever grants currently stand.
- **Lever 2 — `features.capabilities=false` (broader; use if lever 1 isn't
  enough, e.g. the resolver itself is misbehaving, not just one decision).**
  Every capabilities route 404s, `AuthorityGate` returns the legacy answer
  verbatim everywhere (mode becomes structurally `legacy` — the resolver
  isn't merely bypassed, it's never constructed), and every grant becomes
  inert. There is nothing to "un-migrate": no schema shipped with this
  increment, so turning the flag back on later resumes from the same
  `role_assignments` rows.

Both levers were rehearsed on the local dev instance with a real
command/output transcript —
`docs/evidence/phase5/capabilities-fallback-rehearsal.md`.

## Immediate revoke procedure

`/admin/roles/{id}` → the assignment's **Revoke** button → confirm.
**Deliberately no password/reauth required** — `RoleAssignmentService::revoke()`
is designed to be fast because it is purely narrowing (you can only ever
revoke an *existing* grant, never widen one), and an excessive grant needs to
stop immediately, not after a reauth round-trip. Session + CSRF +
`WriteGate::assertCanWrite` are enough; the action is fully audited
(`moderation_log` action `revoke_role`, telemetry `role_assignment.revoked`)
and takes effect on the assignee's very next authorization decision — there is
no cache to wait out (see *Caching*, below). Contrast: **grant** and **renew**
both require the admin's current password (`ReauthGate::requirePassword`) —
they are high-impact/re-broadening actions; revoke alone is fast-path by
design.

## Caching — why there is nothing to wait out

There is no cross-request cache. `CapabilityResolver` only memoizes *within*
one request (grants bundle per actor, board-context per board, decision memo
keyed `capability|actor|board|owner|user|category|at-bucket`); the next
request always reads live tables. Every mutating path
(`RoleService` create/update/clone, `RoleAssignmentService`
grant/revoke/renew, `changeRole`, `AdminService`
assign/unassign-moderator and add/remove-member) calls
`CapabilityResolver::invalidate()` inside its own transaction. If a revoked
assignee still appears authorized, that is not a caching problem — check
whether a *second*, still-active grant supplies the same capability (multiple
active grants are allowed; the resolver is an allow-if-any-qualifying-grant
union) before assuming a bug.

## Parity reconcile

```bash
php bin/console verify:resolver-parity   # re-run the F9-fixture corpus; expect 0 mismatches
php bin/console repair                   # recompute every denormalized counter/reputation AND
                                          # reconcile protected_owners from users.status
```

`verify:resolver-parity` is the TM-PE-06 proof: it runs the exact same
resolver that enforces, against the same fixture, and writes
`docs/evidence/phase5/resolver-parity.md`. A non-zero mismatch count after any
future change to `CapabilityRules`/`LegacyAuthorityProjection` means the
resolver and legacy authority have diverged somewhere the taxonomy didn't
intend — treat it as a release blocker, not a warning. `repair` does not touch
capabilities directly, but its `protected_owners` reconciliation is the
backstop for the owner-loss guard this increment wires into `changeRole` (see
below).

## Owner recovery pointer

There is no separate "capabilities" owner-recovery flow — the protected-owner
invariant (`≥1 active recoverable owner`, decision #27) is a Foundation F5
concern, and this increment's only owner-loss addition is wiring `changeRole`'s
admin-demote path into the same `LastOwnerGuard` every other owner-loss path
already uses. If the owner set ever looks wrong (e.g. after a bulk role
change), run `php bin/console repair`, which idempotently reconciles
`protected_owners` from `users.status='active'` and reports how many rows it
touched (see `docs/evidence/phase5/foundation-f3-f5.md` for the full spine
and its own worked recovery example). There is no owner "password reset" —
the guard structurally refuses to let the last recoverable owner be demoted
or deactivated in the first place, so recovery is about reconciling the
`protected_owners` projection, not restoring access.

## The clamp — what "not yet enforceable" means

`src/Security/EnforcedCapabilities.php` lists the 21 of 54 catalogued keys
with a **live** call site after this increment. `RoleService::validateDefinition`
refuses to save a custom role that holds any key outside this set: *"…is not
yet enforceable; it can be granted once its routes cut over to the
resolver."* This is an honesty guarantee, not a bug — a role that could hold
`core.site.branding` (an `/admin` console key, not yet cut over) would look
authorized in the editor while every actual branding route still checks
`requireAdmin()` and ignores the grant entirely. The clamp stops that gap
from being silently offered. The simulator still *resolves* all 54 keys
(useful for previewing what a future cutover would mean); only role
**definition** is clamped.

**A key passing the clamp is not automatically meaningfully delegable to a
custom role** (ADR 0016, Consequences). Of the 21 enforced keys:

- ~15 moderation/board keys are genuinely custom-delegable end-to-end — a
  role holding only `core.thread.lock` really can lock and nothing else
  (`tests/browser/role-assignments.spec.ts`).
- The 3 posting keys (`core.thread.create`/`core.post.create`/
  `core.thread.tag`) are baseline `system.user` capabilities every member
  already holds and cannot be narrowed below `post_min_role` — granting them
  via a custom role adds nothing.
- The 3 dual-path keys (`core.thread.mark_solved`/`core.poll.manage`/
  `core.thread.manage_workflow`) give a custom role only the **owner** path;
  the board-wide moderator/staff path stays restricted to
  `system.moderator`/`system.admin` role-kind
  (`CapabilityRules::DUAL_PATH_BOARD_AUTHORITY`, an Inc-1 decision).

The admin-console cutover (recorded follow-up) is expected to be the largest
source of newly-clamped keys lifting; it does not change this dual-path
restriction.

## Browser-evidence reproduction

`role-assignments.spec.ts` needs **both** the dark-surface fixture flag and
`enforce` mode — the deputy half of the journey (the per-button display-flag
proof) exercises a seeded user (`bob`) with no legacy moderator row at all, so
under the default `shadow` posture legacy authority alone decides and the Lock
control would never render regardless of the grant:

```bash
cd tests/browser
CAPABILITIES_MODE=enforce RB_BROWSER_DARK_SURFACES=1 npx playwright test role-assignments.spec.ts
```

The admin-side grant/revoke half of the same journey is mode-independent
(`RoleAssignmentService::assertGrantorCeiling` consults `CapabilityResolver`
directly, not through `AuthorityGate`) and would pass under either posture —
it is `enforce` specifically that the deputy-visibility assertion requires.
Running the spec without `CAPABILITIES_MODE=enforce` is a reproducibility
trap, not a flake — if it ever fails locally, check the env var first.

## Acceptance evidence

- `tests/Integration/Security/AuthorityGateTest.php` — the three-mode matrix,
  enforce-mode fail-closed on resolver error, legacy mode never touching the
  resolver.
- `tests/Integration/Core/AppEnforcementCutoverTest.php` — end-to-end route
  behavior under `enforce`/`shadow` across the full cutover surface, the
  per-key granularity proof, and the ADR 0016 suspended-staff pending-view
  pin.
- `tests/Integration/Service/RoleAssignmentServiceTest.php`,
  `tests/Integration/Core/AppRoleAssignmentTest.php` — grant/revoke/renew
  lifecycle, the grantor ceiling (TM-PE-02), reauth/CSRF/404-dark/422
  draft-loss behavior.
- `tests/Integration/Core/AppChangeRoleTest.php` — promote/demote matrix, the
  last-owner demote block (TM-PE-07), session revocation, audit.
- `tests/browser/role-assignments.spec.ts` — no-JS grant → deputy sees the
  Lock control only → revoke journey, axe-clean
  (`docs/evidence/browser/{desktop,mobile}/62-64-*.png`).
- `docs/evidence/phase5/resolver-parity.md` — 1551 tuples, 0 mismatches,
  re-run on this increment's resolver.
- `docs/evidence/phase5/performance-budgets.md` — `resolver.p95` MEASURED
  (PASS) against the 5 ms D11 target.
- `docs/evidence/phase5/capabilities-fallback-rehearsal.md` — the real
  fallback-lever rehearsal transcript.
