# A1 — Core capability taxonomy (Phase 5 entry-gate artifact)

**Date:** 2026-06-30
**Status:** Recorded as the A1 entry-gate artifact required by `PHASE_5_PLAN.md` §2
and §7; satisfies ADR 0004 **D4** ("permission taxonomy, high-risk data classes,
non-delegable list, consent vocabulary"). **Pending product-owner review** (see
`docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md`).
**Precedence:** subordinate to `DECISIONS.md` → `DESIGN.md` → ADR 0004; this
document *instantiates* D4, it does not change it.

> **Data classes are a separate artifact.** D4 also calls for high-risk *data
> classes* + consent vocabulary; those land as `src/Security/DataClasses.php`
> (Foundation **F4**), mirroring `ApiScopes`/`WebhookEvents`. This file is the
> *capability* half only. API Bearer scopes (`src/Security/ApiScopes.php`) are
> also a distinct catalogue and are **not** core capabilities (see §6).

---

## 1. Purpose and how this is consumed

This is the authored `core.*` capability catalogue. It is the single source of
truth that the Foundation increment turns into code:

- **F3** generates `src/Security/CapabilityCatalog.php` (the code-owned `core.*`
  enumeration + the non-delegable protected set) **from this file**, and the
  `0066` seed migration populates the empty `capabilities` + `role_capabilities`
  tables (`0050`) to match.
- **F3 coverage test** (the route/permission "golden matrix" in
  `src/Service/CapabilityInventoryService.php`) is the *enforcement*: every
  authorization call site must map to **exactly one** catalogued key, and every
  key must carry `scope_type` + `risk_class` + a consent string. Where a citation
  below drifts from the working tree, the coverage test fails and is the
  authority — fix the code mapping or this file, never paper over it.
- The resolver (P5-08, Increment 1) reads `capabilities`/`role_capabilities`;
  the **legacy projection** it must reproduce is documented in §7.

This catalogue was derived from a full read-only inventory of the authorization
surface (`Controller::requireUser/requireAdmin`, `ModerationService::canModerate`,
`WriteGate`, `BoardPolicy`, every `Admin*Controller`, and the service layer) at
the working tree of commit `5fee422`; re-anchored 2026-07-05 (Increment 6,
commit `628ef4d`) for every site the enforcement cutover touched (§4.2/§4.3
dual-path and moderation citations, §7 #3). Citations are `file:line` anchors,
not a frozen snapshot.

> **Known citation drift (recorded, not fixed here):** the code-owned golden
> matrix this file feeds, `src/Service/CapabilityInventoryService::MATRIX`, was
> transcribed from this taxonomy at A1-authoring time and has **not** been
> re-anchored to match — most of its `file:line` values (e.g. `core.user.warn`
> still cites `UserModerationService.php:179`; the real `assertStaff()` guard is
> now at line 358) are stale by the same drift documented below. Fixing that
> array is source-code maintenance outside this docs-closure task's scope
> (`CapabilityInventoryCoverageTest` validates key coverage, not line-number
> accuracy, so nothing is silently broken by the drift); recorded here as a
> follow-up rather than left implicit.

## 2. Schema contract (`0050`) — these are the taxonomy fields

The `capabilities` table fixes the vocabulary; this file may not invent values
outside the ENUMs:

| Column | Values | Meaning here |
|---|---|---|
| `capability_key` | `core.<area>.<action>` | globally unique; `namespace=core`, `source=core` |
| `scope_type` | `site` \| `category` \| `board` \| `self` | broadest scope the capability applies at |
| `risk_class` | `low` \| `medium` \| `high` \| `protected` | consent prominence + delegability ceiling |
| `is_delegable` | 0/1 | may a custom role / extension / grant include it |
| `is_protected` | 0/1 | non-delegable protected capability (decision #22) |
| `description` | ≤255 chars | short human label (the consent string lives in code, §5) |

**Invariant:** `risk_class='protected'` ⟺ `is_protected=1` ⟺ `is_delegable=0`.
Extensions can never request a `protected` capability — the
`installed_package_permissions.risk_class` ENUM (`0049`) is only `low|medium|high`.

## 3. Risk classes & consent vocabulary

| `risk_class` | Delegable? | Consent treatment |
|---|---|---|
| `low` | yes | implicit with the role/baseline; no separate consent prompt |
| `medium` | yes | itemized in the human-readable permission/consent summary on grant + on any increase (`PHASE_5_PLAN` §65) |
| `high` | yes | itemized **and prominent**; recent reauthentication required where policy demands it (ReauthGate, F7) |
| `protected` | **no** | never offered for delegation; held only by protected system authority (`protected_owners`), exercised through dedicated owner/recovery flows |

`scope_type` semantics: `site` = whole instance; `category`/`board` = narrowed to
a specific container via a `role_assignments.scope_id`; `self` = the actor's own
account/content only (these are baseline `system.user` capabilities, never granted
piecemeal).

---

## 4. The catalogue (54 keys)

`D`=`is_delegable`, `P`=`is_protected`. "Authority today" is the *current* gate
the resolver's legacy projection must reproduce (§7), with a representative call
site; the F3 coverage test enumerates the complete set.

### 4.1 Read / visibility — anchor role `system.guest`

| Key | scope | risk | D | P | Description | Authority today (primary site) |
|---|---|---|---|---|---|---|
| `core.board.read` | board | low | 1 | 0 | Read boards, threads, posts, and public site surfaces, subject to the board read gate. | guest; `BoardPolicy::canRead/isListed` (`src/Security/BoardPolicy.php:27,37`); private boards need `board_members` (scope narrowing, not a separate key) |

### 4.2 User baseline — anchor role `system.user` (coarse; never granted à la carte)

| Key | scope | risk | D | P | Description | Authority today (primary site) |
|---|---|---|---|---|---|---|
| `core.thread.create` | board | low | 1 | 0 | Start a topic in a board you can post to. | user + `BoardPolicy::canPost` (`PostingService.php:80`; `BoardPolicy.php:66`) |
| `core.post.create` | board | low | 1 | 0 | Post a reply (thread not locked). | user + canPost (`PostingService.php:197`) |
| `core.post.edit_own` | self | low | 1 | 0 | Edit your own post. | owner (`PostingService::editOwnPost:300`) |
| `core.post.delete_own` | self | low | 1 | 0 | Delete your own post. | owner (`PostingService::deleteOwnPost:364`) |
| `core.content.react` | board | low | 1 | 0 | React to posts, star threads, vote in polls. | user, can-read (`EngagementController.php:32,71`; `PollService::vote:80`) |
| `core.content.report` | board | low | 1 | 0 | Report a post or message to moderators. | user (`ReportController.php:29`; `DirectMessageService::reportMessage:207`) |
| `core.thread.tag` | board | low | 1 | 0 | Add or change tags on a thread you can post in. | canPost (`TagController::updateThread:149`; flag `tags`) |
| `core.thread.mark_solved` | board | low | 1 | 0 | Accept/clear the answer on a thread — your own thread (any member) or any thread in a board you moderate. | author OR board-mod (`SolvedAnswerService::authorize:202`; flag `community`) — **dual-path** |
| `core.poll.manage` | board | low | 1 | 0 | Create or close polls on a thread — your own thread (any member) or any thread in a board you moderate. | author OR board-mod (`PollService::canManageThread:172`; flag `polls`) — **dual-path** |
| `core.thread.manage_workflow` | board | low | 1 | 0 | Manage a thread's workflow (status + assignment): authors change non-final statuses and self-(un)assign in self-assignment boards; staff set staff-only statuses (`decision_made`/`archived`) and assign other members. | author/self OR staff (`ThreadWorkflowService::authorizeStatus:244` dual-path, `::canStaffAssign:284` staff-only; flag `topic_workflow`) — **dual-path** |
| `core.message.participate` | self | low | 1 | 0 | Send and manage your own DMs and group conversations (incl. group-owner actions). | user + eligibility (`DirectMessageService.php:57,80,336`; flags `dms`,`group_dms`) |
| `core.upload.create` | self | low | 1 | 0 | Upload images and files in the composer. | user (`MediaController.php:31,70`; flags `uploads`,`expanded_files`) |
| `core.draft.manage_own` | self | low | 1 | 0 | Save and restore your own composer drafts. | owner (`DraftController.php:23`; flags `drafts`,`server_drafts`) |
| `core.account.manage_self` | self | low | 1 | 0 | View and manage your own member surfaces and account: feed, inbox, presence, notifications, composer aids, profile, security/MFA, preferences, sessions, blocks, follows, subscriptions, personal organization, and data export/deactivate/delete-request. | owner (`AccountController`/`SettingsController`/`ProfileMediaService`/`FollowController`/`SubscriptionController`/`OnboardingController`/`BlockController`/`PersonalOrganizationController`/`FeedController`/`InboxController`/`PresenceController`/`NotificationController`) |

> **Dual-path capabilities.** `core.thread.mark_solved`, `core.poll.manage`, and
> `core.thread.manage_workflow` are held by the thread author (the resolver narrows
> them to the actor's own thread) **and** by board-moderators (board-wide); the
> resolver authorizes if the actor owns the target **or** holds the board-scoped
> grant, and the service applies any finer sub-rules (e.g. `manage_workflow`'s
> staff-only `decision_made`/`archived` statuses and staff-assignment of *other*
> members). All *other* owner actions instead use distinct `_own` self keys
> (e.g. `core.post.edit_own` / `core.post.delete_own`).

### 4.3 Moderation (board-scoped via `canModerate`) — anchor role `system.moderator`

`canModerate(user, boardId)` = `WriteGate.canWrite(user) && (isAdmin || board_moderators(boardId))` (`src/Service/ModerationService.php:54`).

| Key | scope | risk | D | P | Description | Authority today (primary site) |
|---|---|---|---|---|---|---|
| `core.post.delete_any` | board | medium | 1 | 0 | Delete any member's post in a board you moderate. | canModerate (`ModerationService::deletePost:109`) |
| `core.post.restore` | board | medium | 1 | 0 | Restore a soft-deleted post. | canModerate (`ModerationService::restorePost:173`) |
| `core.thread.lock` | board | medium | 1 | 0 | Lock or unlock a thread. | canModerate (`ModerationService::toggleLock:88`) |
| `core.thread.pin` | board | medium | 1 | 0 | Pin or unpin a thread. | canModerate (`ModerationService::togglePin:67`) |
| `core.thread.move` | board | medium | 1 | 0 | Move a thread (moderator on both boards). | canModerate ×2 (`ModerationService::moveThread:211`) |
| `core.thread.split_merge` | board | medium | 1 | 0 | Split or merge threads (moderator on both). | canModerate ×2 (`ThreadSplitMergeService.php:35,114,117`; flag `split_merge`) |
| `core.post.reveal_author` | board | **high** | 1 | 0 | Reveal the author of an anonymous post. | canModerate (`ModerationService::revealAuthor:283`) — deanonymization |
| `core.content.approve` | board | medium | 1 | 0 | Approve or reject held/pending content. | canModerate (`ApprovalController::assertCanModerate:114`; flag `anti_abuse`) |
| `core.content.view_pending` | board | low | 1 | 0 | View held/pending content awaiting moderation. | canModerate / global-mod (`ApprovalController::index:33`; `ThreadController.php:417`; `MediaController::authorizePendingMedia:211`) — **dual legacy authority, §7** |
| `core.report.handle` | board | medium | 1 | 0 | Triage reports: view queue, claim, resolve, dismiss. | board-mod of report's board (`ReportService::canHandle:81`; flag `moderation_queue`) |
| `core.appeal.resolve_content` | board | medium | 1 | 0 | Resolve appeals against post/content actions. | board-mod of target's board (`AppealService::assertCanResolve:247`; flag `appeals`) |
| `core.memory.curate` | board | medium | 1 | 0 | Curate community memory: summaries, related topics, wiki posts. | board-mod curator (`CommunityMemoryService::assertCurator:289`; flag `community_memory`) |
| `core.user.warn` | **site** | medium | 1 | 0 | Issue a formal warning and add staff notes to a member. | **staff-any** (admin OR mod of *any* board), targets site-wide (`UserModerationService::assertStaff:358`; flag `moderation_queue`) — **legacy quirk, §7** |

### 4.4 Administration — anchor role `system.admin`

| Key | scope | risk | D | P | Description | Authority today (primary site) |
|---|---|---|---|---|---|---|
| `core.user.suspend` | site | high | 1 | 0 | Suspend a member and lift suspensions (not self/another admin). | admin (`UserModerationService::suspend/assertAdmin:69,187`) |
| `core.user.ban` | site | high | 1 | 0 | Ban a member and lift bans. | admin (`UserModerationService::ban:86`) |
| `core.user.manage` | site | medium | 1 | 0 | Administer member records: directory/record view, set cosmetic title, clear signature, manual badge grant/revoke. | admin (`AdminUserController.php:32,56,70,86,102`) |
| `core.appeal.resolve_user` | site | high | 1 | 0 | Resolve appeals against account actions (warn/suspend/ban). | admin (`AppealService::assertCanResolve:247`) |
| `core.category.manage` | site | medium | 1 | 0 | Create, edit, delete, reorder categories. | admin (`AdminController.php:76`; `AdminService`) |
| `core.board.manage` | category | medium | 1 | 0 | Create, edit, delete, archive boards, move them between categories, and reorder boards within a category; set a board's posting floor. | admin (`AdminService::createBoard:198`,`updateBoard:229`,`reorderBoards:334`,`moveBoard:369`) |
| `core.board.assign_moderators` | board | high | 1 | 0 | Assign or remove board moderators. | admin (`AdminService::*Moderator:466,496`) — grants authority |
| `core.board.manage_members` | board | medium | 1 | 0 | Add or remove members of a private board. | admin (`AdminService:523,547`) |
| `core.site.configure` | site | medium | 1 | 0 | Configure site name, structure, moderation settings. | admin (`AdminController.php:54,65`) |
| `core.site.branding` | site | medium | 1 | 0 | Manage branding, theme, custom CSS. | admin (`BrandingController.php:73`; flags `branding`,`custom_css`) |
| `core.site.tags` | site | low | 1 | 0 | Administer the tag catalogue. | admin (`TagController.php:84`; flag `tags`) |
| `core.site.badges` | site | low | 1 | 0 | Administer badge rules. | admin (`AdminBadgeRuleController.php:21`; flag `badge_rules`) |
| `core.site.emoji` | site | low | 1 | 0 | Administer custom emoji. | admin (`AdminCustomEmojiController.php:20`; flag `custom_emoji`) |
| `core.site.announcements` | site | low | 1 | 0 | Set or clear the announcement banner. | admin (`AdminAnnouncementController.php:38`; flag `announcements`) |
| `core.site.link_previews` | site | low | 1 | 0 | Refresh or purge link previews. | admin (`AdminLinkPreviewController.php:16`; flag `link_previews`) |
| `core.site.email` | site | medium | 1 | 0 | Operate email: dashboard, test, domain verify, requeue, suppressions, export. | admin (`AdminEmailController.php:35`; flag `email`) |
| `core.site.api_tokens` | site | high | 1 | 0 | Mint and revoke read-only API tokens. | admin + reauth (`AdminApiTokenController.php:26`; flag `api_tokens`) |
| `core.site.webhooks` | site | high | 1 | 0 | Manage outbound webhooks (create, rotate secret, test, replay, delete). | admin + reauth (`AdminWebhookController.php:33`; flag `webhooks`) |
| `core.site.secrets` | site | high | 1 | 0 | Manage service secrets in the vault. | admin (`SecretVault`; flag `service_secrets`; no route yet) |
| `core.package.manage` | site | high | 1 | 0 | Install, update, pin, roll back, enable, disable, uninstall packages and themes; manage registries. | admin (`AdminExtensionController.php:19` + Gate A package routes; flags `package_registry`,`package_themes`) |
| `core.package.review` | site | high | 1 | 0 | Operate the publisher/review/advisory console. | admin (P5-07-A; flag `package_registry`) |

### 4.5 Protected — non-delegable (decision #22 / D4)

Held by **protected system authority (`protected_owners`), NOT by the
`system.admin` role-capability set**; exercised through dedicated owner/recovery
flows and guarded by `LastOwnerGuard` (F5). Never appear in any role's
`role_capabilities`, never offered to custom roles or extensions.

| Key | scope | risk | D | P | Description | Backing |
|---|---|---|---|---|---|---|
| `core.owner.transfer` | site | protected | 0 | 1 | Designate or transfer site ownership. | `protected_owners`, `owner_transfer_history` (`0050`) |
| `core.owner.recovery` | site | protected | 0 | 1 | Perform break-glass account/owner recovery. | `protected_owners.recovery_status` (`0050`) |
| `core.trust.manage_keys` | site | protected | 0 | 1 | Manage registry trust roots and signing keys (rotation/revocation). | `registry_trust_keys` (`0049`); see A4 |
| `core.signature.override` | site | protected | 0 | 1 | Override or bypass package signature verification. | supply-chain integrity (P5-01) |
| `core.audit.integrity` | site | protected | 0 | 1 | Authority over audit-log integrity. | `moderation_log` / immutable audit rows |

---

## 5. Consent strings

The DB `description` is a short label; the **human consent string** (shown on
grant/increase) is code-owned in `CapabilityCatalog.php` (F3), keyed by
capability. Style: second person, action-first, scope-explicit, no jargon —
e.g. `core.post.delete_any` → *"Delete other members' posts in boards this role
moderates."*; `core.site.webhooks` → *"Create and manage outbound webhooks,
including their signing secrets, for the whole site."* `protected` capabilities
have **no** consent string (never delegated). F3 asserts every non-protected key
has a non-empty consent string.

## 6. Role → capability seed (F3 parity map)

Cumulative (guest ⊂ user ⊂ moderator ⊂ admin), reproducing today's authority.
`0050` already seeds the four `roles` rows; `0066` seeds `role_capabilities`:

| Role (`role_key`) | Holds |
|---|---|
| `system.guest` | `core.board.read` |
| `system.user` | guest + all of §4.2 (14 baseline keys, incl. the 3 dual-path keys, resolver-narrowed to owned content) |
| `system.moderator` | user + all of §4.3 (12 board-mod keys + `core.user.warn`); also exercises the §4.2 dual-path keys board-wide |
| `system.admin` | moderator + all of §4.4 (21 admin keys) |
| *(protected)* | §4.5 keys are **not** role-mapped; resolved via `protected_owners` |

The *assignment scope* (site/board) is set by `role_assignments`, not by this
map. The legacy projection that creates those assignments — and the exact
handling of board-scoped `system.moderator` vs the vestigial global
`users.role='moderator'` — is **Increment 6 (P5-09)**, constrained to be
non-broadening (§7). This file only fixes *which capabilities each role holds*. The two dual-path keys
(§4.2 note) illustrate the split: `system.user` holds them resolver-narrowed to
owned content, while board-wide use comes only through a board-scoped moderator
assignment.

## 7. Documented legacy quirks (parity-first — the resolver reproduces these)

The catalogue reflects authority that **actually exists**; the resolver shadow
(Inc 1) and enforcement cutover (Inc 6) must reproduce current behavior, not
silently "fix" it. Each quirk below is preserved and flagged; any deliberate
change is a separate, owner-approved decision after parity is clean.

1. **No `edit_any`.** No moderator/admin path edits another member's post —
   `POST /posts/{id}/edit` only ever calls `editOwnPost` (`PostingService.php:308`).
   There is intentionally no `core.post.edit_any` key.
2. **`core.user.warn` is "staff-any".** Warn/staff-notes/queue-views gate on
   admin-OR-moderates-*any*-board (`UserModerationService::assertStaff:179`) and
   act site-wide, while pin/lock/delete are board-scoped. Modeled as `site`
   scope; the legacy projection grants it to anyone with ≥1 board-mod assignment.
3. **Vestigial global `users.role='moderator'`.** A global moderator with no
   `board_moderators` row passes `isModerator()` exemptions (anti-abuse bypass
   `AntiAbuseService.php:62`, approval-queue *view* `ApprovalController::index:31`
   [the `core.content.view_pending` site-probe's *legacy* argument, since Inc 6 —
   see quirk 5 below], upload-floor bypass `MediaController.php:40` + pending-media
   view `MediaController::authorizePendingMedia:209` [same treatment as the
   approval queue: the legacy closure passed into the capability probe],
   DM-throttle bypass `DirectMessageService.php:287`) yet `canModerate` is false
   everywhere. The projection must **not** broaden this into board powers.
4. **Dual pending-view authority.** `core.content.view_pending` is gated by
   `canModerate` for threads but the global mod role for media — same conceptual
   decision, two authorities today; reproduced as-is.
5. **Pending-content *views* skip the state gate.** *(Recorded 2026-07-02, Inc 1
   review.)* The approval-queue view (`ApprovalController.php:29`) and
   pending-media view (`MediaController.php:204`) gate on bare `isModerator()`
   with no `WriteGate`, so a suspended global moderator (or suspended admin) can
   still *view* pending content in production, while every pending *action*
   runs through `canModerate` and is state-blocked. The capability model
   applies "state beats role" uniformly, so the resolver — and the Inc-1 parity
   oracle, deliberately — denies `core.content.view_pending` for suspended
   staff. This is a **known divergence from production**, invisible to the
   parity corpus because oracle and resolver agree with each other. Owner
   decision at the Increment 6 route cutover: state-exempt the view paths to
   preserve the quirk, or accept the (safer) tightening as an approved change.
   Until then the routes keep their legacy gates and nothing changes.

   **Resolved 2026-07-04 (ADR 0016):** the tightening is accepted; under
   `CAPABILITIES_MODE=enforce` suspended staff are denied
   `core.content.view_pending`; pinned by
   `AppEnforcementCutoverTest::test_suspended_global_moderator_loses_pending_views_under_enforce_adr_0016`
   (with `test_suspended_global_moderator_keeps_pending_view_in_shadow_mode`
   pinning the shadow-mode companion: the legacy quirk stays intact while
   `CAPABILITIES_MODE=shadow`, the default). Live-rehearsed on the local dev
   instance: `docs/evidence/phase5/capabilities-fallback-rehearsal.md`.
6. **Content-state closes are service-owned sub-rules, not capabilities.**
   *(Recorded 2026-07-02, Inc 1 review.)* Archived-board freezes
   (`ModerationService::assertNotArchived`, `ReactionService.php:78`,
   `SolvedAnswerService.php:54`, `ThreadWorkflowService.php:52` — note
   `PollService` has no archived close), locked-thread and deleted-target rules
   live in the services and keep applying unchanged at and after the resolver
   cutover. The resolver answers "is the capability held at this scope"; it
   applies content-state narrowing only where the legacy *route gate* itself
   was `BoardPolicy::canPost` (`core.thread.create`, `core.post.create`,
   `core.thread.tag`). The Inc-1 parity oracle mirrors the same boundary, so
   the corpus proves parity of capability-holding predicates; per-action
   content-state behavior remains covered by the services' own tests.

   **Ratified 2026-07-04 (ADR 0016).** No behavior or modeling change; the
   boundary above is confirmed as the intended, permanent shape, not a
   placeholder pending a future decision.

## 8. Explicitly NOT capabilities (out of catalogue)

The F3 coverage test must classify these call sites as *non-capability* with the
listed rationale, so "every call site maps to exactly one key OR a recorded
exclusion":

- **Account state** (`WriteGate::assertCanWrite` `src/Security/WriteGate.php:22`) —
  orthogonal state axis ("state beats role"); the resolver *narrows by* state.
- **Board read gate** internals (`BoardPolicy` visibility/membership) — enforced
  as the read gate the resolver consults, not as per-action keys.
- **Feature flags** (`FeatureFlags::enabled`) — availability gate layered before
  authorization, not authority.
- **API Bearer scopes** (`ApiScopes`: `read:boards`,`read:threads`) — the API
  principal carries no role; manifest "API scope" requests map to `ApiScopes`,
  not capabilities.
- **Reputation & auto-badges** (`ReputationLedgerService`, `BadgeService::evaluateForUser`) —
  system-internal, no role gate; reputation grants no power (CLAUDE.md). *Admin
  moderation of them* is `core.user.manage`.
- **Profile fields** — owner-only self-edit folded into `core.account.manage_self`;
  admin override is `core.user.manage`.
- **DM allow-none admin override** (`DirectMessageService.php:276`,
  `assertCanContact`) — a recipient's `allow_dms='none'` preference blocks a new
  conversation from anyone except an admin (`!$sender->isAdmin()`). This is a
  bare role check, not routed through any capability key, and is **not** cut
  over by Increment 6 (recorded as a deliberate non-goal,
  `docs/superpowers/specs/2026-07-04-inc6-resolver-enforcement-cutover-design.md`
  §2/§10) — an uncatalogued bypass, unchanged.
- **Bootstrap/auth/unsubscribe** (setup, login/register/reset/verify, signed
  unsubscribe/OAuth-state tokens) — guest- or token-authenticated, not role
  capabilities.
- **Structural invariants** (last-admin guard `AccountLifecycleService.php:230`,
  the protected-owner "≥1 active recoverable owner" rule) — enforced
  transactionally by `LastOwnerGuard` (F5), not modeled as capabilities.

## 9. Verification (F3 owns enforcement)

1. **Coverage:** golden matrix asserts every authorization call site → exactly
   one catalogued key or a §8 recorded exclusion.
2. **Schema conformance:** every key has a valid `scope_type`/`risk_class`; the
   `protected ⟺ is_protected ⟺ ¬is_delegable` invariant holds; no `protected`
   key is reachable through any delegation/grant path.
3. **Parity:** the §6 seed reproduces guest/user/mod/admin authority on the
   Phase-5 fixture (F9) with zero mismatch (consumed by Inc 1's parity corpus).
4. **Consent:** every non-protected key has a non-empty consent string.

## 10. Open follow-ups (deferred, not blocking)

- Whether to *correct* any §7 quirk (e.g. give global `moderator` real powers, or
  add `edit_any`) is a **post-parity** owner decision, not part of Gate A.
- `core.board.manage` is `category`-scoped (the broadest unit board administration
  delegates at); cross-category board moves, like `core.thread.move`, require
  authority on both source and destination, enforced in-service.
- Extension-namespaced capabilities (`<vendor>.<pkg>.*`) are subordinate to core
  and validated against this catalogue at install (P5-02); their shape is defined
  with manifest v2, not here.
