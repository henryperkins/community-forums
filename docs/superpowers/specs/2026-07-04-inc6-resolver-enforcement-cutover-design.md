# Design: Increment 6 — Resolver Enforcement Cutover + Scoped Assignment Lifecycle

**Date:** 2026-07-04
**Status:** Approved design (brainstorming output) — pending implementation plan.
**Branch:** to be created (`phase5-inc6-enforcement`), from `main`.
**Phase / gate:** Phase 5, Gate A. Workstreams **P5-08** (enforcement cutover) +
**P5-09** (scoped assignments / temporary grants) + the Inc-6 slice of **P5-10**
(owner-loss safeguard wiring). Satisfies **GA-DOD-11**, advances **GA-DOD-10** and
**GA-DOD-12** (`docs/phase5/requirement-ledger.json`).

> Precedence reminder (CLAUDE.md): `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` >
> surface specs. The Phase 5 `decision #NN` register lives in **`PHASE_5_PLAN.md` §5**
> (not DECISIONS.md). Where this doc and an authoritative doc disagree, the
> authoritative doc wins and this design must be corrected.

---

## 0. Context — what exists, what this increment changes

Increment 1 (2026-07-02) landed the capability resolver **in shadow**: the
DB-backed `CapabilityResolver` → pure `CapabilityRules` core answers all 54
catalogued `core.*` keys, `LegacyAuthorityProjection` maps accepted authority
(`users.role`, `board_moderators`, `protected_owners`) into resolver grants, and
`ResolverShadow` compares three live decisions (`core.thread.create`,
`core.post.create`, `core.post.delete_any`) emitting `resolver.shadow_mismatch`
telemetry without changing any live answer. The parity corpus is clean: **1551
tuples, 0 mismatches** on fixture v2 (`docs/evidence/phase5/resolver-parity.md`).
The E1 fail-open (`CapabilityRules::scopeSatisfies` returning true for a
board/category-scoped grant with no target context) was closed fail-closed on
2026-07-04 (`49db9a8`) — the recorded prerequisite for this increment.

What does **not** exist: any enforcement path (no live decision consults the
resolver), the entire assignment write lifecycle (`role_assignments` has a
test-only `create()`; **no revoke/renew writer, no route, no UI**), cache
invalidation, the role-demote owner-loss guard (there is **no in-app
`users.role` mutation path at all**), a capabilities runbook, and the
staged-enablement mode lever.

The "shadow soak" language in `PHASE_5_STATUS.md` gates the **live switch**
(§13.1 steps 2 and 7 run on a real deployment), not the building of the
enforcement code. `capabilities` is dark everywhere, so no deployment can soak
today; this increment builds and evidences the cutover deploy-dark, and the
runbook stages the soak at enablement.

### Authoritative requirements this increment satisfies

- **P5-09 acceptance (PHASE_5_PLAN §6, verbatim):** "Scope matrix; concurrent
  assign/revoke; expiry tolerance; stale-cache tests; rollback to compatibility
  resolver."
- **P5-09 deliverables:** user assignments; site/category/board scope; effective
  dates/expiry; renewal/revoke; impact preview; cache/version invalidation;
  legacy moderator and board post-gate migration.
- **PHASE_5_PLAN §5 decisions:** #18 (parity before switch, legacy preserved as
  rollback source), #19 (additive bundles, union-then-narrow, no deny), #20
  (state-first invariant), #21 (explicit bounded scopes), #22 (protected keys
  non-delegable), #24 (version-keyed caches; expiry enforced at decision time),
  #25 (simulator = real resolver), #26 (recent reauth for high-impact ops), #27
  (≥1 active recoverable owner), #40 (independent disable path), #41
  (`post_min_role` → capability path only after parity, enum preserved).
- **Grantor authority (§8.5/§9):** a scoped assignment must be authorized by a
  grantor at the same-or-broader scope; the grantor cannot grant a capability
  they do not hold or one marked non-delegable; violating direct requests fail
  and are audited.
- **Threat-model fixtures to flip `stub` → `implemented`:** TM-PE-02, TM-PE-03,
  TM-PE-06, TM-PE-07, TM-PE-08 (`docs/phase5/threat-models/fixtures.json`).
- **§11.5 runbook obligations:** resolver fallback, parity reconcile/repair,
  immediate revoke, owner recovery — authored and rehearsed.

### Owner decisions recorded with this increment (→ ADR 0016)

1. **Suspended-staff pending-view tightening (taxonomy §7 quirk 5): ACCEPTED
   2026-07-04.** Under enforcement, "state beats role" applies uniformly:
   suspended staff lose pending-content *views* (approval queue, pending media)
   as they already lose pending *actions*. An approved behavior change at
   cutover, pinned by a regression test. Shadow mode keeps legacy behavior; the
   known mismatch is expected telemetry, documented in the runbook.
2. **Service-owned content-state boundary (taxonomy §7 quirk 6): RATIFIED
   2026-07-04.** Archived/locked/deleted closes stay service-owned sub-rules.
   The resolver narrows by content state only where the legacy route gate itself
   was `BoardPolicy::canPost`. (The PollService missing archived-close stays a
   documented quirk; out of scope.)
3. **Cutover breadth: board/content surface** (~30 sites, §2 below). The
   `/admin` console (~19 controllers on `requireAdmin()`) stays legacy this
   increment; the role editor **clamps grantable keys to the enforced set**
   (§3); the console cutover is the recorded immediate follow-up, not a silent
   drop.
4. **Legacy authority source: projection stays authoritative** ("virtual
   import"). `users.role`/`board_moderators` remain the source of built-in
   authority, read through `LegacyAuthorityProjection` per decision;
   `role_assignments` holds only new custom/temporary grants. **No migration
   ships.** TM-PE-06 (non-broadening) is proven by the parity corpus running
   against the same resolver that enforces.
5. **Build the missing change-role action** (§5) so the role-demote owner-loss
   path is real, guarded, and testable (TM-PE-07).
6. **Mode lever: `CAPABILITIES_MODE` env config**, values `shadow` (default) |
   `enforce`. Flag stays availability-only (DECISIONS: posture lives in config;
   `antiabuse.mode` precedent). §13.1 step 7's staff-first staging is
   operational, per runbook.

---

## 1. The seam: `AuthorityGate`

New `src/Security/AuthorityGate.php` — the single decision choke point.
Composes the existing pieces; does not replace them.

```php
final class AuthorityGate
{
    public const MODE_LEGACY  = 'legacy';   // capabilities flag OFF
    public const MODE_SHADOW  = 'shadow';   // flag ON + CAPABILITIES_MODE=shadow (default)
    public const MODE_ENFORCE = 'enforce';  // flag ON + CAPABILITIES_MODE=enforce

    public function __construct(
        private ?CapabilityResolver $resolver,  // null in MODE_LEGACY
        private ?ResolverShadow $shadow,        // non-null only in MODE_SHADOW
        private Telemetry $telemetry,
        private string $mode,
    ) {}

    public function mode(): string;

    /** @param callable():bool $legacy the current legacy gate expression */
    public function allows(callable $legacy, ?User $actor, string $capability, array $target, string $site): bool;

    /** Throwing form; ForbiddenException with the caller-supplied message. */
    public function assert(callable $legacy, ?User $actor, string $capability, array $target, string $site, string $message): void;
}
```

Per-mode behavior of `allows()`:

- **legacy:** return `$legacy()`. The resolver is never constructed or touched
  (container passes `null`), so dark behavior is byte-identical to today.
- **shadow:** `$l = $legacy()`; `$this->shadow?->compare($l, …)`; return `$l`.
  Exactly today's Inc 1 behavior, relocated behind one seam.
- **enforce:** `$decision = $resolver->can(actor, capability, target)`. On any
  `Throwable`: emit `authority.enforce_error` and **return false — enforcement
  fails closed**; this is authorization, a DB blip must deny, never allow.
  Then compute `$l = $legacy()` (guarded; swallow its errors — it is telemetry
  only here) and emit `resolver.enforce_mismatch` when it disagrees — the
  §13.2 "capture mismatch state" requirement, kept for Gate A observability.
  Denials emit `authority.enforce_denied {capability, source, reason, actor_id,
  board_id}`. Return `$decision->allowed`.

**Wiring (App::buildContainer):** mode = flag off → `legacy`; else
`config('capabilities.mode') === 'enforce'` → `enforce`; anything else →
`shadow` (fail-safe). `PostingService` and `ModerationService` swap their
`?ResolverShadow` constructor param for an `?AuthorityGate $authority = null`
that defaults to `AuthorityGate::legacy()` internally — hand-constructed
service instances in tests (e.g. `TestCase::posting()`) stay valid, and the
container always injects the real gate; every other cutover site receives the
gate the same way.

**One deliberate deviation from the "null collaborator when dark" pattern:**
the gate is always bound (dark = legacy passthrough) so ~30 call sites stay
unconditional one-liners with the legacy closure inline. Dark inertness is
proven by the all-flags-off regression
(`AppFeatureFlagTest::test_core_forum_survives_with_every_feature_flag_disabled`)
plus the `AuthorityGateTest` legacy-mode case — with a `null` resolver the gate
structurally cannot consult it.

`config/config.php` gains `'capabilities' => ['mode' => Env::get('CAPABILITIES_MODE', 'shadow')]`;
`.env.example` documents it.

---

## 2. Cutover sites (~30) and their capability keys

Line numbers are current HEAD (`3ad11c7`); taxonomy §4 anchors have drifted
(+2…+105, one method rename) and get re-anchored where touched.

**`ModerationService` — per-action keys.** `canModerate(User, int $boardId,
string $capability = 'core.post.delete_any')` and
`assertCanModerate(...)`/`requireModeratableThread(...)` gain the key
parameter; internally the whole legacy expression (`writeGate->canWrite &&
(isAdmin || boardMods->isModerator)`) becomes the gate's legacy closure, so
under `enforce` the resolver's state check replaces the bundled WriteGate call:

| Site | Key |
|---|---|
| `deletePost` (:110) | `core.post.delete_any` |
| `restorePost` (:169) | `core.post.restore` |
| `togglePin` (via `requireModeratableThread` :59) | `core.thread.pin` |
| `toggleLock` (:80) | `core.thread.lock` |
| `moveThread` (:215, :216 — both boards) | `core.thread.move` |
| `revealAuthor` (:279) | `core.post.reveal_author` |
| bare probes (`PostController:162` mod-delete branch, display flags) | `core.post.delete_any` |
| `ApprovalController:110` approve/reject actions | `core.content.approve` |
| `ThreadController:369` pending-thread view | `core.content.view_pending` (board target) |
| `AppealService:257` content appeal | `core.appeal.resolve_content` |
| `ThreadSplitMergeService:35,114,117` | `core.thread.split_merge` (merge ×2) |
| `ReportService::canHandle` (:76,80) | `core.report.handle` |
| `CommunityMemoryService::assertCurator` (:289) | `core.memory.curate` |

The inline `canModerate` recompute in `ThreadController:164-168` (and the
per-flag repeats at :227-228, :262-263) switches to the service/gate so display
flags cannot drift from enforcement.

**Posting floor (`BoardPolicy::canPost` callers).** The gate's legacy closure
is the current `canPost(board, user, isMember)` call; the resolver reproduces
archived/read/floor narrowing (corpus-proven):
`PostingService:89` → `core.thread.create`; `PostingService:212` →
`core.post.create`; `TagController:167` → `core.thread.tag`;
display/picker sites (`ThreadController:101,229`, `PostController:197`,
`BoardController:78`) → matching keys.

**Dual-path services.** Pass `owner_id` in the target and let the rules'
dual-path branch decide author-vs-moderator in one call:
`SolvedAnswerService::authorize` (:195-199) → `core.thread.mark_solved`;
`PollService::canManageThread` (:165-169) → `core.poll.manage`;
`ThreadWorkflowService` staff branches (:213-220, :249) →
`core.thread.manage_workflow`.

**Site-scope quirk keys.** `UserModerationService::assertStaff` (:284) →
`core.user.warn` (no target; legacy staff-any closure preserved).
`ApprovalController:29` (queue view) and `MediaController:204` (pending-media
view) → `core.content.view_pending` as a **site probe** (no board target):
site-scoped grants (vestigial global-mod direct grant, `system.admin`) qualify;
board-scoped moderator grants correctly do not (E1 fail-closed) — matching
legacy `isModerator()` for everyone except suspended staff, which is the
approved tightening (ADR 0016).

**Authority-granting sites.** `AdminService::assignModerator`/`unassignModerator`
(:473/:503) → `core.board.assign_moderators` {board_id};
`addMember`/`removeMember` (:530/:554) → `core.board.manage_members` {board_id}.

**Deliberately NOT cut over (documented in the spec + runbook):**
- The read gate: `BoardPolicy::canRead`/`isListed` call sites — the resolver
  consumes `canRead` as its read axis; union-then-narrow means no grant can
  broaden reads, so cutover is a no-op by construction.
- Baseline member keys at read-gated sites (`core.content.react`,
  `core.content.report`, self-scope keys): every member holds them via
  `system.user`; additive roles cannot remove them; nothing to delegate.
- The vestigial-global-mod *exemptions* (anti-abuse `AntiAbuseService:62`,
  upload floor `MediaController:39`, DM throttle `DirectMessageService:287`) —
  taxonomy §8 non-capabilities (abuse-heuristic exemptions, not authority).
- `DirectMessageService:276` (admin override of a member's DM-off preference) —
  uncatalogued bypass; recorded in the taxonomy notes, unchanged.
- The `/admin` console on `requireAdmin()` (breadth decision), including
  `core.appeal.resolve_user` (`AppealService:238-243`, admin-only site key).
- `MysqlSearchService:97` (read-scope filter) and
  `NotificationEmailWorker:191` (worker-side read copy — flagged for a later
  consistency cleanup, out of scope).

---

## 3. The honesty clamp: `EnforcedCapabilities`

New code-owned `src/Security/EnforcedCapabilities.php`: the exact list of keys
with live gate call sites after this increment (21):

```
core.thread.create  core.post.create  core.thread.tag
core.thread.mark_solved  core.poll.manage  core.thread.manage_workflow
core.post.delete_any  core.post.restore  core.thread.lock  core.thread.pin
core.thread.move  core.thread.split_merge  core.post.reveal_author
core.content.approve  core.content.view_pending  core.report.handle
core.appeal.resolve_content  core.memory.curate  core.user.warn
core.board.assign_moderators  core.board.manage_members
```

`RoleService::validateDefinition` rejects any key outside this set with an
honest message ("…is not yet enforceable; it can be granted once its routes cut
over"). The role editor annotates un-enforced keys as disabled; the simulator
still resolves all 54. A pin test asserts the list is a subset of the catalogue,
all delegable, and matches this spec. The clamp lifts per-key as later
increments cut more surface; the admin-console cutover is the recorded
follow-up that removes most of it.

---

## 4. Assignment lifecycle: `RoleAssignmentService`

New `src/Service/RoleAssignmentService.php`. **Custom roles only** in the Gate A
UI (`kind='custom'`; built-in authority stays legacy-managed per the projection
decision — one authority path per source, no dual-management confusion).
`role_assignments`/`role_assignment_history` (migration 0050) already carry
every needed column; **no DDL**.

- **`grant(User $admin, string $currentPassword, int $roleId, string $username,
  string $scopeType, ?int $scopeId, ?string $startsAt, ?string $endsAt,
  ?string $reason): int`**
  Order: `WriteGate::assertCanWrite` → `ReauthGate::requirePassword` (high
  impact, decision #26) → role exists and is custom → subject user resolved by
  username → scope: `site|category|board`; `scope_id` **required** unless site
  (mirrors the E1 fail-closed posture), board/category must exist → windows
  optional, `Y-m-d H:i` UTC, `ends_at > starts_at` and `> now` →
  **grantor ceiling:** for every capability key of the role,
  `resolver->can($admin, key, targetForScope(...))` must allow; a failure
  writes a `moderation_log` `role_assignment_denied` audit row and throws
  `ValidationException` (TM-PE-02: a board-scoped grantor mathematically cannot
  mint site scope — their board-scoped grants fail `scopeSatisfies` for a site
  target). Then, in one transaction: `create()` + history `grant` +
  `moderation_log` `assign_role` + telemetry + `resolver->invalidate()`.
  Multiple active grants are allowed (no unique constraint) — the documented
  "deterministic union" answer to §8.2 #9: allow-if-any-qualifying-grant.
- **`revoke(User $admin, int $assignmentId, ?string $reason): void`**
  **No reauth** — narrowing-only and emergency-fast (runbook: "revoke an
  excessive grant immediately"); session + CSRF + WriteGate + audit suffice.
  Transaction: `SELECT … FOR UPDATE` the un-revoked row (else 422), set
  `revoked_at`/`revoked_by`, bump `assignment_version`, history `revoke`,
  `moderation_log`, telemetry, invalidate.
- **`renew(User $admin, string $currentPassword, int $assignmentId, string
  $endsAt): void`** — reauth (re-broadening). Row-locked; revoked rows refuse
  ("create a new grant"); expired-but-unrevoked rows may be extended; new
  `ends_at > now`; bump version; history `renew`; audit; invalidate.
- **Expiry is decision-time only** (`CapabilityRules::windowValid`; `ends_at`
  strictly greater-than — TM-PE-03). No sweeper worker in Gate A: the plan
  requires the grant to stop "without waiting for cleanup", which decision-time
  enforcement satisfies; assignment lists compute and display expired state.
  Recorded as a non-gap.

**Repository additions** (`RoleAssignmentRepository`): `findForUpdate(int)`,
`revoke(int $id, int $by)`, `updateEndsAt(int $id, string $endsAt)` (both bump
`assignment_version`), `listForRole(int $roleId)` (JOIN users; includes
revoked/expired with computed status for display).

**Routes** (all `requireAdmin()` + `gate()` 404-dark + noindex; POST mutations;
422 re-renders preserve typed input per the anti-draft-loss pattern):
- `POST /admin/roles/{id}/assignments` — grant (form on the role detail page:
  username, scope select + board/category select, optional window, reason,
  current password; shows the role's capability count + current active-assignee
  impact before submit).
- `POST /admin/role-assignments/{id}/revoke`, `POST /admin/role-assignments/{id}/renew`.
- The existing `/admin/roles/{id}` page gains the assignments section
  (`templates/admin/role_edit.php`).

---

## 5. Change-role action + the owner-loss slice

`UserModerationService::changeRole(User $admin, string $currentPassword, int
$userId, string $newRole): void`, route `POST /admin/users/{id}/role`
(`AdminUserController`). **Flag-independent** (it manages `users.role`, which
exists regardless of Phase 5) — a new surface, so no legacy behavior to
preserve; `LastOwnerGuard` is wired unconditionally (its parity-safe fallback
covers unseeded installs).

- Admin-only + `WriteGate` + `ReauthGate::requirePassword`; `newRole ∈
  user|moderator|admin`; same-role is a 422.
- **Demote from admin:** in one transaction —
  `LastOwnerGuard::assertNotLastOwnerForUpdate($target, 'role')` (the TM-PE-07
  demote case), `UserRepository::setRole()` (new method),
  `ProtectedOwnerRepository::deactivate($userId)` (new method — the owner row
  must not survive the authority it mirrors), full session revocation for the
  target, `moderation_log` `change_role` with before/after.
- **Promote to admin:** setRole + `designateOrReactivate($userId, $admin->id())`
  + session revocation + audit, in one transaction.
- **user ↔ moderator:** setRole + session revocation + audit.
- Telemetry + `resolver->invalidate()`. Session revocation uses the existing
  session repository; if no revoke-all-for-user method exists, add one beside
  `revokeOtherSessionsFor`.

---

## 6. Caching and invalidation

Per-request memos inside `CapabilityResolver` (no cross-request cache exists or
is added, so cross-request staleness is structurally impossible — the next
request reads live tables):

- grants bundle per actor (one projection + one assignments query per actor per
  request), board-context per board id, and a decision memo keyed
  `capability|actor|board|owner|user|category|at-bucket`.
- `CapabilityResolver::invalidate(): void` clears all memos. Mutating services
  inject the resolver directly for this (it is always container-bound and lazy;
  the gate does not expose invalidation): `RoleService` create/update/clone,
  `RoleAssignmentService` grant/revoke/renew (which already injects it for the
  grantor ceiling), `changeRole`,
  `AdminService::assignModerator/unassignModerator/addMember/removeMember`.
- `roles.version` and `assignment_version` keep bumping on every edit — the
  audit/optimistic-concurrency spine and the key for any future cross-request
  cache (none in Gate A).
- Acceptance mapping: stale-cache tests = same-request grant/revoke visibility
  through the memo + TM-PE-08 (key removed from role → assignee denied on next
  decision) + TM-PE-03 (explicit `$at` past `ends_at` denies despite a warm
  memo, distinct at-bucket).

Budget note: `resolver.p95` (5 ms D11 target) is re-measured on the F9 fixture;
simulator duration and assignment-change propagation are **measured and
recorded** in `docs/evidence/phase5/performance-budgets.md` without inventing
D11 gates (§11.3 requires measurement; no target exists).

---

## 7. Rollback and staged enablement

Two rehearsed levers, both preserved by the projection decision (legacy tables
were never demoted):

1. `CAPABILITIES_MODE=shadow` (or unset): decisions revert to legacy instantly;
   role editor/simulator/assignment UI stay available; custom grants stop
   applying (they only ever apply through the resolver).
2. `features.capabilities=false` (settings override): everything dark —
   routes 404, gate returns legacy verbatim, grants inert. Never approximated
   as Admin; nothing to un-migrate (no DDL shipped).

Staged enablement (runbook, per §13.1): enable flag in shadow → watch
`resolver.shadow_mismatch` over the soak window (the only expected mismatch is
the documented suspended-staff pending-view tightening) → `CAPABILITIES_MODE=enforce`
→ pilot one narrow custom role on a test board (step 6) → broaden. Emergency
drill: capture mismatch/audit state, then flip lever 1 (or 2); rehearsal
transcript lands in `docs/evidence/phase5/`.

---

## 8. Tests and evidence (produced on the cutover commits)

PHPUnit (fresh + two reused-schema runs green; strict-mode rules apply):

- `tests/Integration/Security/AuthorityGateTest.php` — three-mode matrix,
  enforce-mode fail-closed on resolver error, reverse-mismatch telemetry,
  legacy mode never touches the resolver.
- `tests/Integration/Service/RoleAssignmentServiceTest.php` — grant/revoke/renew
  lifecycle, validation matrix, grantor ceiling (**TM-PE-02** incl. the audited
  denial), deterministic concurrent revoke/renew via row locks, union
  semantics.
- `tests/Integration/Core/AppRoleAssignmentTest.php` — no-JS HTTP journeys,
  reauth, CSRF, 404-dark, 422 draft-loss re-render.
- `tests/Integration/Core/AppChangeRoleTest.php` — promote/demote matrix,
  last-owner demote blocked (**TM-PE-07**), owner-row reconcile, session
  revocation, audit; works with `capabilities` dark.
- `tests/Integration/Core/AppEnforcementCutoverTest.php` — end-to-end under
  flag+enforce override: per-key granularity (a lock-only custom role can lock
  but not delete — the delegation proof), posting floor, dual-path, warn,
  **the approved suspended-staff pending-view tightening pinned with an ADR 0016
  reference**, and the same routes under shadow keep legacy behavior.
- `CapabilityResolverTest` extensions — memo invalidation on grant/revoke/edit
  (**TM-PE-08**), warm-memo expiry via `$at` (**TM-PE-03**).
- `tests/Unit/Security/EnforcedCapabilitiesTest.php` — clamp pin;
  `RoleServiceTest` clamp cases.
- `AppFeatureFlagTest` — new flag-gated routes dark; enforcement inert dark;
  the all-flags-off core-survival regression stays green.
- `verify:resolver-parity` re-run (**TM-PE-06**: same resolver enforces;
  corpus stays 0-mismatch) with the evidence doc refreshed;
  `verify:phase5-budgets` re-run; `verify:upgrade` re-run for the record
  (no new migration expected — 17/17).

Browser/a11y (dark-surface pattern: `RB_BROWSER_DARK_SURFACES=1`, theme
safe-mode neutralization per the api-tokens spec precedent):
`tests/browser/role-assignments.spec.ts` — grant → simulator reflects → revoke
journey, desktop + mobile PNGs (62+ — next free after the Inc 5 security-console
61), axe scan of the role pages with the assignments section.

---

## 9. Docs, ledger, and process deliverables

- **ADR 0016** — the two owner decisions (§0) + the four fork decisions
  (breadth/projection/change-role/mode), following the ADR 0012 sign-off
  pattern. (0015 stays reserved for Gate A acceptance.)
- **`docs/runbooks/capabilities.md`** — staged rollout, both rollback levers,
  emergency fallback drill (capture state first), immediate-revoke procedure,
  parity reconcile + `bin/console repair`, owner-recovery pointer, clamp
  explanation. Fallback rehearsal transcript → `docs/evidence/phase5/`.
- **`docs/phase5/capability-taxonomy.md`** — §7 #5 marked resolved-tightened
  (ADR 0016), #6 ratified; re-anchor the drifted §4 lines at touched sites;
  note the DM allow-none bypass in §8.
- **`docs/phase5/requirement-ledger.json`** — GA-DOD-10 → **R4**, GA-DOD-11 →
  **R4** (browser/no-JS/security/perf/operating evidence all land here),
  GA-DOD-12 → **R3** (deactivate/delete/passkey/demote wired + reauth; provider
  Inc 8, invitations Inc 9 recorded in notes); evidence arrays updated
  (Phase5EvidenceMapTest enforces file existence). R5 for all three waits for
  staged enablement + owner acceptance.
- **`docs/phase5/threat-models/fixtures.json`** — TM-PE-02/03/06/07/08 →
  `implemented` with real `test=` paths.
- **`PHASE_5_STATUS.md`** — Inc 6 section with suite/evidence numbers; rewrite
  the stale "blocked until shadow soak" line to point at §13.1 enablement.
- **`.env.example`** — `CAPABILITIES_MODE` documented.
- **No SCHEMA.md change** (no DDL). `docs/evidence/…/deploy-dark-features.md`
  route counts updated for the three new flag-gated routes.

## 10. Non-goals (recorded, not silent)

Admin-console per-key cutover (immediate follow-up; the clamp is its bridge);
governance groups/approvals/access review, owner-transfer UI, `subject_type='group'`
surfaces (Gate B, P5-10); expiry sweeper/notification worker; materialized
legacy import/dual-write; `enforce_staff` mode; remodeling the anti-abuse/
upload/DM-throttle exemptions or the DM allow-none override; the
`NotificationEmailWorker` read-copy consistency fix; search read-scope changes;
usernameless/passkey items (Inc 7 closed).

## 11. Risks and mitigations

- **A cutover site maps to the wrong key** → per-site tests assert legacy
  parity under shadow AND expected decisions under enforce; the corpus guards
  the primitive predicates; reverse-mismatch telemetry catches drift live.
- **Enforce-mode perf regression on hot paths** (pickers looping boards) →
  per-request memos (§6) + `resolver.p95` re-measured; reverse-shadow legacy
  closures are cheap (indexed single-row reads, mostly already loaded).
- **Fail-closed enforce errors lock operators out during a DB blip** → the
  error also fails the request anyway (PDO exceptions); runbook lever 1
  restores legacy decisions in one env flip; telemetry `authority.enforce_error`
  makes it visible.
- **Clamp surprises operators** → honest validation message + role-editor
  annotation + runbook section.
