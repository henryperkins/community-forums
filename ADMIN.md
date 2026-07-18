# RetroBoards — Admin & Moderation Design

**Status:** v0.14 · **Owner:** Henry (lakefrontdigital.io) · **Last updated:** 2026-07-12
**Companion to [DESIGN.md](DESIGN.md).** That document is the source of truth for the whole product; this one owns the **admin and moderation surface** in depth. Where they overlap, DESIGN.md wins for member-facing behaviour and this doc wins for admin/mod behaviour. Same conventions (P0/P1/P2/P3 priorities; `Done (mockup)` / `Planned` / `Live` status; InnoDB / `utf8mb4`).

## Scope

This document breaks down the operator side of RetroBoards: **roles & permissions, moderation workflows, board management, user management, theming, notifications, the integrations/plugin system, and the admin Settings UX/UI**. It does not re-spec member-facing reading/posting (see DESIGN.md §6).

## Contents

1. Roles, States & Scopes
2. Permissions System (who can do what, and where)
3. Moderation Workflows & Functions
4. Board & Category Management
5. User Management
6. Theming
7. Notifications (admin & mod)
8. Integrations & Plugin System
9. Admin Settings UX / UI
10. Data-Model Additions (delta to DESIGN.md §8)
11. Roadmap Delta (admin/mod phasing)
12. Open Questions
13. Changelog

---

## 1. Roles, States & Scopes

RetroBoards uses a **fixed role set** (no admin-defined custom roles in v1), deliberately small and legible. Authority is the product of three independent dimensions: **role** (what you are), **state** (whether your account is in good standing), and **scope** (where a power applies).

> **Naming note.** **Resolved (v0.4): the role is "User"** across all docs (DESIGN.md updated to match); "member" appears only casually in prose.

### 1.1 Roles

| Role | Authenticated? | What it is | How assigned |
|---|---|---|---|
| **Guest** | No | An unauthenticated visitor. Read-only on public content. | Default for anyone not logged in. |
| **User** | Yes | A registered, verified member — the default contributor. | On registration (+ email verification). |
| **Moderator** | Yes | A User entrusted with moderation powers, **scoped to one or more boards** (or site-wide). | Assigned by an Admin. |
| **Admin** | Yes | The operator. Full control over content, users, configuration, and the install. | Seeded at install; granted by another Admin. |

Roles are **cumulative**: Admin ⊇ Moderator ⊇ User ⊇ Guest in baseline capability.

### 1.2 Account states (modifiers, not roles)

A state overrides the role's normal capabilities. State lives on the account (`users.status`); see §10.

| State | Effect | Notes |
|---|---|---|
| **Active** | Normal — role capabilities apply. | Default. |
| **Suspended** | Temporary read-only. Can log in and read, **cannot post/react/DM** until expiry. | Has an `expires_at`; auto-restores. A "timeout". |
| **Banned** | Access revoked. Configurable: *post-ban* (read-only, sees a banner) or *full ban* (login blocked entirely). | Permanent unless lifted. Reason required; logged. Scope can be site-wide or per-board (§1.4). |

Banned/Suspended **always lose to** role: a banned Admin (e.g. compromised account an owner locked) cannot act. Effective authority is computed state-first (§2.4).

### 1.3 Posting modes (identity, not authority)

A **posting mode** affects how a post is *attributed*, never what the author is allowed to do.

| Mode | Public sees | Mods/Admins see | Config |
|---|---|---|---|
| **Normal** | The author's username & avatar. | Same. | Default. |
| **Anonymous** | Author shown as "Anonymous" (no username/avatar). | **Real author** (accountability preserved). | Off by default; enable per-board. |

Design intent: Anonymous is a privacy affordance that **does not** create an unaccountable actor. The post still belongs to a real account in the database; only the public rendering is masked, and moderation tools always reveal the true author. Whether **Guests** may post anonymously at all (i.e. posting without an account) is a separate, higher-risk decision tracked in §12 — default is **no** (must be a logged-in User to post, even anonymously).

### 1.4 Scopes

Powers apply within a **scope**. This is how "where" is expressed.

- **Site-wide** — applies everywhere. Admins are always site-wide. A Moderator may be granted site-wide (rare).
- **Board-scoped** — the common case. A Moderator is assigned to specific boards via `board_moderators`; their powers apply only there.
- **Category-scoped** — convenience: assign a Moderator to a whole category (expands to its boards). Optional, P1.
- **Self** — every User can act on their **own** content (edit/delete own posts) regardless of mod status.

Bans and suspensions also carry scope: a **board ban** blocks one board; a **site ban** blocks everything.

## 2. Permissions System (who can do what, and where)

### 2.1 Capabilities catalogue

Rather than scatter checks through the code, define a flat list of named **capabilities**. Roles are bundles of capabilities; the code checks a capability + scope, never a role string directly. This keeps v1 simple **and** leaves a clean path to granular/custom roles later (§2.6) without rewrites.

**Content**

- `content.read` · `thread.create` · `post.create` · `post.edit_own` · `post.delete_own` · `reaction.toggle` · `thread.star` · `thread.subscribe` · `dm.send` · `post.report`

**Moderation** (scoped)

- `mod.post.edit_any` · `mod.post.delete_any` · `mod.post.restore` · `mod.thread.pin` · `mod.thread.lock` · `mod.thread.move` · `mod.thread.merge` · `mod.reports.handle` · `mod.user.warn` · `mod.user.mute` · `mod.user.suspend` · `mod.user.ban_board` · `mod.anon.reveal` · `mod.log.view`

**Board admin**

- `board.create` · `board.edit` · `board.delete` · `board.reorder` · `board.assign_mods` · `category.manage`

**User admin**

- `user.list` · `user.view_pii` · `user.assign_roles` · `user.ban_site` · `user.delete` · `user.export`

**Site admin**

- `site.settings` · `site.theming` · `site.integrations` · `site.plugins` · `site.webhooks` · `site.audit.view`

### 2.2 Role → capability matrix

Scope column: **Self** = own content; **Board** = only assigned boards; **Site** = everywhere.

| Capability group | Guest | User | Moderator | Admin |
|---|:--:|:--:|:--:|:--:|
| `content.read` | ✓ (public) | ✓ | ✓ | ✓ |
| Create thread / post, react, star, subscribe, DM | — | ✓ | ✓ | ✓ |
| `post.edit_own` / `post.delete_own` | — | ✓ (Self) | ✓ (Self) | ✓ |
| `post.report` | — | ✓ | ✓ | ✓ |
| `mod.post.edit_any` / `delete_any` / `restore` | — | — | ✓ (Board) | ✓ (Site) |
| `mod.thread.pin` / `lock` / `move` / `merge` | — | — | ✓ (Board) | ✓ (Site) |
| `mod.reports.handle` | — | — | ✓ (Board) | ✓ (Site) |
| `mod.user.warn` / `mute` / `suspend` / `ban_board` | — | — | ✓ (Board) | ✓ (Site) |
| `mod.anon.reveal` / `mod.log.view` | — | — | ✓ (Board) | ✓ (Site) |
| `board.*` (create/edit/delete/reorder/assign_mods) | — | — | — | ✓ |
| `category.manage` | — | — | — | ✓ |
| `user.list` / `view_pii` / `assign_roles` | — | — | — | ✓ |
| `user.ban_site` / `user.delete` / `user.export` | — | — | — | ✓ |
| `site.settings` / `theming` / `integrations` / `plugins` / `webhooks` / `audit.view` | — | — | — | ✓ |

Note: Moderators get **`user.list` (read-only, limited)** scoped to participants in their boards so they can act on offenders, but never `user.assign_roles`, `view_pii`, or site bans.

### 2.3 "Where" — scoping rules

- A capability check is always `can(user, capability, target)` where `target` carries the board/category/thread/post. The resolver derives the scope from the target.
- Board-scoped mod powers require the target's `board_id` to be in the user's `board_moderators` set (or category-scoped, expanded).
- Private/hidden boards (§4.3) add a **read** gate: `content.read` itself becomes conditional on membership/role for those boards.

### 2.4 Permission-resolution algorithm

```
function can(user, capability, target):
    # 1. State gate (state beats role)
    if user is null:                      role = GUEST          # not logged in
    else if user.status == BANNED:        return capability in GUEST_READONLY
                                          and not board_banned(user, target)
    else if user.status == SUSPENDED:     allowed = GUEST_READONLY
    else:                                 allowed = capabilities_for(user.role)

    # 2. Capability present at all?
    if capability not in allowed: return false

    # 3. Scope check
    scope = required_scope(capability)          # Self | Board | Site
    if scope == SELF:    return owns(user, target)
    if scope == SITE:    return user.role == ADMIN or has_site_grant(user, capability)
    if scope == BOARD:   return user.role == ADMIN
                              or board_in_scope(user, target.board_id)

    # 4. Read gate for private boards
    if capability == 'content.read' and target.board.is_private:
         if board_members is not enabled yet: return user.role == ADMIN
         return board_member(user, target.board)

    return true
```

Key properties: **fail-closed** (default deny), **state-first** (a ban can't be out-ranked by role), **scope-explicit** (a board mod can't reach another board), and **capability-based** (swap-in custom roles later by changing only `capabilities_for()`).

### 2.5 Worked examples

- *A `#xbox` moderator deletes a post in `#playstation-2`* → `mod.post.delete_any` is Board-scoped; `#playstation-2` not in their set → **denied**.
- *A suspended Admin tries to pin a thread* → state gate downgrades to read-only → **denied** until suspension expires.
- *A User edits their own week-old post* → `post.edit_own` is Self-scoped, they own it → **allowed** (subject to any board "edit window" setting, §4.2).
- *A Moderator clicks "reveal author" on an anonymous post in their board* → `mod.anon.reveal` Board-scoped, in scope → **allowed**, and the reveal is itself written to the audit log.

### 2.6 Extensibility path (not built in v1)

Because checks are capability-based, moving to **granular custom roles** later means: add `roles` and `role_capabilities` tables, let Admins compose bundles, and point `capabilities_for()` at the DB instead of a hard-coded map. No call sites change. We will **not** build this in v1 (§12), but the capability catalogue above is the forward-compatible seam.

## 3. Moderation Workflows & Functions

### 3.1 Flagging / reporting a post

Any User can flag a post. Reporting is the funnel that feeds the queue.

- **Reasons** (fixed list, configurable): Spam, Harassment/abuse, Off-topic, NSFW/explicit, Illegal/dangerous, Other (free-text required).
- **One open report per user per post** (dedup). A second report by the same user updates, not duplicates.
- **Rate-limited** to deter report-bombing; abusive reporting is itself a mod-able behaviour.
- The reporter sees a simple confirmation ("Thanks, a moderator will review"), **not** the outcome by default — avoids retaliation dynamics. (Optional "notify me of the outcome" P2.)
- Multiple reports on the same post **collapse into one queue item** with a report count and the set of reasons.

### 3.2 The reports queue (the moderator's home base)

A single triage surface, scoped to the mod's boards (Admins see all).

- **Item states:** `open` → `triaged` (claimed by a mod) → `resolved` (action taken) or `dismissed` (no action). A claimed item shows who's on it to prevent double-work.
- **Each item shows:** the reported post in context, author (real author even if posted Anonymous), report count + reasons + reporters, the author's recent history and prior actions, and quick-action buttons.
- **Filters:** board, reason, status, age; **sort** by age or report count. Aging items are visually escalated (SLA cue).
- **Bulk actions:** select many → dismiss / delete / lock in one step (each still audited individually).
- **Quick actions** resolve the report and apply a content/user action in one click (delete + dismiss, warn author, lock thread, etc.).

### 3.3 Content moderation actions

| Action | Capability | Reason? | Notifies author? | Reversible? |
|---|---|:--:|:--:|:--:|
| Edit any post | `mod.post.edit_any` | optional | optional | edit history kept |
| Delete post (soft) | `mod.post.delete_any` | required | yes (configurable) | **Restore** |
| Restore post | `mod.post.restore` | — | — | n/a |
| Pin / unpin thread | `mod.thread.pin` | — | — | yes |
| Lock / unlock thread | `mod.thread.lock` | optional | yes | yes |
| Move thread | `mod.thread.move` | optional | yes | yes |
| Merge / split thread | `mod.thread.merge` | optional | — | hard — P2 |
| Mark as spoiler / NSFW | `mod.post.edit_any` | — | — | yes |
| Hide pending review | `mod.post.delete_any` | — | — | yes |

All content actions are **soft** wherever possible (nothing is hard-deleted from the queue path), every action writes an audit entry (§3.6), and "delete" hides content but preserves it for restore and accountability.

### 3.4 User moderation actions

Escalating ladder, each recorded and visible on the user's admin record:

| Action | Capability | Scope | Effect |
|---|---|---|---|
| **Warn** | `mod.user.warn` | Board/Site | Formal notice; user sees it; counts toward history. No functional restriction. |
| **Mute / timeout** | `mod.user.mute` | Board/Site | Read-only for a short duration (hours). Lightweight `Suspended`. |
| **Suspend** | `mod.user.suspend` | Board/Site | Read-only for a set duration (days). **Admin:** site-wide (global `users.status`/`suspended_until` fast-path). **Moderator:** scoped to their assigned board(s) — a time-limited board read-only state recorded in `bans` (`scope='board'`, `type='post'`, `expires_at`), enforced via the board-level gate, not the global account flag. |
| **Ban — board** | `mod.user.ban_board` | Board | Blocks posting (or reading) in one board. |
| **Ban — site** | `user.ban_site` (Admin) | Site | Full or post-only site ban. |
| **Reveal anon author** | `mod.anon.reveal` | Board/Site | Unmasks an Anonymous post's author; the reveal is itself audited. |
| **Add mod note** | `mod.user.warn` | — | Private staff note on the account (not user-visible). *2026-07-18: notes are **admin-only** in the shipped implementation — `user_notes` is globally scoped, so the any-board-moderator mapping over-disclosed; see ADR 0021 (post-review decisions).* |

**Ban evasion** is flagged (not auto-enforced) using soft signals — matching email/IP/device on a new account linked to a banned one — surfaced to Admins with privacy caveats (§5.5). No automated collateral bans.

### 3.5 Moderation lifecycle (state machine)

```
                 report                 claim                action
  [post] ───────────────▶ (open) ───────────────▶ (triaged) ─────────────▶ (resolved)
     │                      │                          │                        │
     │  auto-flag           │  dismiss                 │  dismiss               │ audit + notify
     │  (filters/spam)      ▼                          ▼                        ▼
     └────────────────▶ (open) ─────────────────────────────────────────▶ (dismissed)
                                                                                │
                                                       appeal (P3) ◀────────────┘
```

Auto-flags (spam filters, word lists, throttles — §3.8) enter the **same queue** as human reports rather than acting silently, unless an Admin explicitly configures hard-block rules. This keeps automated moderation reviewable.

### 3.6 Audit log

Every moderation and admin action appends an immutable record (`moderation_log`, DESIGN.md §8 + extensions §10): **who, what action, on what target, in what scope, why (reason), when**, plus a before/after snapshot for edits. Append-only (no edit/delete). Visible to Admins site-wide and to Moderators for their boards (`mod.log.view`). The log is the backbone of accountability and appeals.

### 3.7 Appeals (Phase 3)

A user who is actioned (post removed, suspended, banned) can submit **one** appeal with a message. Appeals form their own lightweight queue; resolution (uphold / overturn) is recorded and linked to the original action. Kept deliberately minimal for v1+1.

### 3.8 Automation & anti-abuse

Configurable, layered, and **reviewable by default**:

- **New-user throttle:** rate-limit posts / links / DMs for an account's first N hours or posts.
- **First-post approval (optional):** a new user's first post(s) enter a hold queue.
- **Word & link filters:** Admin-managed lists → block, or auto-flag into the queue, or auto-hide-pending-review.
- **Flood / duplicate detection:** repeated identical posts or rapid-fire posting auto-flags.
- **Spam scoring:** delegated to an integration (Akismet-style) via the plugin/hook system (§8); high score → queue or hold.
- **Necro-lock (optional):** auto-lock threads inactive for N months.

Each automated decision writes an audit entry tagged `actor = system` so automated moderation is as accountable as human moderation.

### 3.9 Moderating the v0.2 surfaces

- **Direct messages:** DMs are private but not unmoderatable — a participant can **report** a DM, which surfaces only the reported messages to an Admin (never the whole conversation); handled like any report (§3.2). Mods do not browse DMs.
- **Reputation:** reputation is **derived** from reactions, so it self-corrects when a post or reaction is removed — there is no manual "set reputation". Coordinated reaction abuse (vote-rings) is an anti-abuse signal (§3.8).
- **Presence / who's-online:** staff see real presence, but a user hidden via their privacy setting never appears online to anyone — including the who's-online list and moderators.
- **Notifications:** the suppression list + per-recipient idempotency (§7.5) are the abuse controls; system-sent notifications are audited like any action.

### 3.10 Thread Intelligence operations

Thread Intelligence is default-on as of 2026-07-12: `community_memory` and
`automated_context` both default `true` and remain independently reversible.
The admin console at
`/admin/thread-intelligence` is the credential-free control plane:

- **Observe:** effective flags, credential readiness, validated model/effort,
  global pause and provider-latch health, minutely-worker heartbeat, queue-state
  counts, UTC daily call/input-token budget and next reset, configuration
  warnings, and recent redacted generation evidence.
- **Recover:** audited POST + CSRF actions globally pause/resume generation, clear
  a repaired provider latch, and retry/reconcile/pause/resume an individual
  thread. Retry preserves all shared gates; reconcile forces a current public
  evidence rebuild. Authentication/model failures latch provider claims,
  transient exhaustion eventually becomes `dead`, and repeated truncation,
  invalid output, moderation flags, or excess reconciliation windows become
  `review_required`.
- **Curate:** admins and in-scope board moderators may publish/edit a sourced
  manual brief, refresh, retire, restore, and explicitly resume thread
  automation. Retirement pauses automation; restore alone does not resume. A
  human edit becomes the next model baseline and a curated related row cannot be
  overwritten by an AI overlay.
- **Retain:** the evidence ledger contains source/candidate IDs, model/effort,
  prompt version, bounded failure codes, usage, response ID, and lineage, but no
  raw prompt/response, duplicate post body, credential, or unvalidated generated
  text. Published provenance follows the thread; unpublished terminal evidence
  prunes after 90 days, with unresolved `dead`/`review_required` evidence retained
  through resolution and for 90 quiet days afterward.
- **Roll back:** pause globally, independently pin
  `features.automated_context=false`, then
  `features.community_memory=false`, then remove the environment credential.
  This order preserves jobs, attempts, summaries, citations, relationships, and
  the last safe version. Do not run migration `0077` down on production data.

The canonical settings writes, merge-preserving feature commands, at-least-
minutely worker schedule, heartbeat meanings, budget recovery, evidence pruning,
board-sweep resumption, live-eval syntax, and full rollback/restore procedure are
owned by `docs/runbooks/thread_intelligence.md`. The selected live contract is
`low` / `16000`: 46/46 runs, 149/149 supported claims, and zero incomplete
responses, private-sentinel transmissions, or fabricated decisions.

## 4. Board & Category Management

### 4.1 Categories

Admin CRUD: name, position (drag-to-reorder), default-collapsed flag. Categories are presentation/ordering only — deleting a category requires its boards be moved or deleted first (no orphan boards).

### 4.2 Boards

Admin CRUD with per-board settings:

| Setting | Purpose |
|---|---|
| Name, **slug**, description, icon/emoji | Identity. Slug change issues a 301 redirect (SEO, DESIGN.md §11). |
| Category, position | Placement & ordering. |
| **Visibility:** public / hidden / private | Who can see it (§4.3). |
| **`post_min_role`** | Minimum role to post (e.g. Admin-only announcements). |
| **Allow anonymous posting** | Per-board toggle for the Anonymous mode (§1.3). |
| **Require approval** | New threads/posts here enter a hold queue. |
| **Edit window** | How long Users may edit their own posts (e.g. 0 = unlimited, or 15 min). |
| Allowed tags / prefixes | Optional thread tags (e.g. "Hot", "Solved"). |
| **Moderators** | Users assigned to moderate this board. |
| State: locked / archived | Locked = read-only; archived = retired but preserved. |

### 4.3 Visibility & access

- **Public** — listed and readable by everyone (Guests included).
- **Hidden** — not shown in the sidebar/index, but readable by direct link (useful for staff or low-key boards).
- **Private** — read-gated. In the Phase 1 console this is an active-admin-only hold state because `board_members` is still Phase 2. Once `board_members` ships (§10), private boards become member-scoped: only members of the board (by role or explicit membership) can see them.

### 4.4 Lifecycle

- **Archive** — board becomes read-only; content preserved and still searchable. Reversible.
- **Delete** — soft-delete; requires choosing what happens to its threads (move to another board, or soft-delete with them). Slugs of deleted boards are reserved to avoid collisions and broken links.
- All structural changes are audited.

### 4.5 Management UX

Drag-to-reorder categories and boards; inline edit of board settings; a moderators picker (search Users, assign/revoke); bulk archive. Destructive actions (delete board, delete category) require typed confirmation and show impact ("This board has 38,402 threads").

## 5. User Management

### 5.1 User directory

Admin-only list with search and filters: by username/email, **role**, **state** (active/suspended/banned), join date, last-seen, post count, and (Admin-only, audited) IP. Sortable, paginated, bulk-selectable. Moderators get a **reduced, read-mostly** directory scoped to participants in their boards — enough to act on offenders, never PII or role controls.

### 5.2 User admin record

A single screen per user:

- **Identity & profile:** username, display name, email (PII-gated), avatar, title/rank, join date, last seen, reputation/post count, verification status.
- **Role & scope:** assign role (User/Moderator/Admin) and, for Moderators, which boards/categories they cover.
- **State controls:** suspend / ban with **reason + duration + scope** (board or site) + ban type (post-only vs full). Lift/restore.
- **History:** posts, reports filed and received, prior mod actions, warnings — a complete accountability trail.
- **Mod notes:** private staff-only notes on the account.
- **Signals:** known IPs / devices and possible alt accounts (Admin-only, audited, privacy-caveated — §5.5).

> **No impersonation/login-as in v1.** "View as" (read-only preview of what a role/user can see) is acceptable later; logging in *as* a user is not — too easy to abuse, hard to audit.

### 5.3 Assignment workflows

Granting Moderator opens a board/category picker. Granting Admin requires a confirmation step and is itself audited and (P1) notified to other Admins. Revocation is immediate and audited.

### 5.4 Bans & evasion

Bans carry scope, duration, reason, and type (§3.4). The system **surfaces** likely evasion (new account sharing email/IP/device fingerprints with a banned one) to Admins as a hint — it never auto-bans collaterally. Banned users hitting the site see a clear, non-leaky "you are banned" state (post-ban) or a generic block (full ban).

### 5.5 Privacy & compliance

- **PII access is gated and logged.** `user.view_pii` (email, IPs) is Admin-only; each access writes an audit entry.
- **Data export** (`user.export`): admin-triggered, and a self-serve request flow (P2), producing the user's data.
- **Deletion / right-to-be-forgotten:** account deletion soft-deletes immediately, then purges on a delay. Choose per policy whether to **anonymise** the user's posts (reassign to a "Deleted user" tombstone, preserving thread integrity) or remove them. Default: anonymise content, purge PII.
- **Retention settings:** configurable retention for IP logs and deleted content.

### 5.6 New-user management

Email verification status and resend; an **approval queue** when registration-approval or first-post-approval is enabled; welcome automation (a pinned-intro nudge or welcome DM). Invitations and invite-only registration are P2.

## 6. Theming

### 6.1 Token-driven by design

The front-end is already fully **CSS-variable driven** — the entire look derives from `:root` tokens in `app.css`. Admin theming is therefore mostly a UI over those tokens, not a rebuild. Branding settings live in the DB (§10) and are injected as a `:root` override block (or compiled to a small CSS file and cached).

### 6.2 What an Admin can change

| Level | Controls | Priority |
|---|---|---|
| **Brand** | Community name, logo (light/dark), favicon, primary + accent color, sidebar style | P1 |
| **Theme / skin** | Pick a preset: **Default (Hybrid)**, **Retro 2002** (the preserved `styles.css` look as a skin), and **Light / Dark** | P1 (default+dark), P2 (retro skin) |
| **Tokens** | Fine-tune individual variables (radius, density, fonts) with a live preview | P2 |
| **Custom CSS** | Raw CSS for advanced admins, scoped & with a clear "you can break the layout" warning | P2 |

### 6.3 Application & safety

- Brand/token changes apply site-wide via the injected `:root` override; **per-user display preference** (e.g. dark mode, retro skin) overrides the site default for that user.
- The token editor **warns on accessibility regressions** (e.g. a chosen text/background pair failing WCAG AA contrast — a stated NFR).
- Custom CSS is gated behind an "advanced" toggle and is the operator's responsibility; it never executes JS.
- Theming never affects the responsive breakpoints or semantic structure.

### 6.4 Roadmap

v1: brand (name/logo/colors) + light/dark. Later: retro skin toggle, full token editor, custom CSS, and **theme packages distributed via the plugin system** (§8) — P2.

## 7. Notifications (admin & mod)

This section covers **staff-facing** alerts and the Admin's control over the whole notification system. Member-facing notifications are specified in DESIGN.md §6.10; the admin angle (templates, what triggers email, broadcasts) lives here.

### 7.1 Staff event types

| Event | Who should hear it | Default channel |
|---|---|---|
| New report / report threshold reached | Board mods (their boards), Admins | In-app + digest |
| Auto-flag / hold-queue item created | Board mods, Admins | In-app |
| Appeal submitted | Admins (+ acting mod) | In-app + email |
| Approval-queue item (new user / first post) | Board mods, Admins | In-app |
| New-user spike / suspected spam wave | Admins | In-app + email |
| Admin role granted / security event | All Admins | Email (immediate) |
| System/health alert (errors, disk, mail failure) | Admins | Email (immediate) |

### 7.2 Routing, scope & noise control

- **Scope-aware routing:** a board moderator is notified only about their boards; Admins get site-wide + system. Reuses the permission scope (§2.3).
- **Per-staff preferences:** each mod/admin can mute or change the channel per event type, with **quiet hours**.
- **Thresholds & digests:** instead of one ping per report, configure "notify when the queue exceeds N" or "when an item ages past T", and batch the rest into a periodic **digest** to prevent alert fatigue.

### 7.3 Channels

- **In-app:** the bell + a dedicated **staff inbox** in the Console.
- **Email:** immediate or digested.
- **Outbound webhook:** push to Slack / Discord / a custom endpoint via Integrations (§8.6) — the common "mod alerts in our team chat" pattern.

### 7.4 Admin control over the notification system

- A **matrix UI**: event type × channel × audience, with thresholds and quiet hours.
- **Email templates:** editable templates for all system emails (verification, password reset, mention, reply, ban notice, warning) with safe variables, **preview, and test-send**.
- **Announcements / broadcast:** an Admin can publish a site-wide banner or a pinned announcement, and send a broadcast notification/email to all members — rate-limited and audited. _(Priority P2; delivered Phase 2 — see §11.)_

### 7.5 Notification email infrastructure (doc v0.2; delivered Phase 2)

The subscription/notification system (DESIGN.md §6.10, §8.3) emails subscribers on new posts/threads. The Admin owns the infrastructure:

- **Domain setup first.** Before any notification email can send, the operator configures a sending domain (SPF/DKIM). The Console surfaces domain status and a setup dialog if it isn't ready — **sending is blocked until then.** (Adapts the adjacent project's "check domain status → set up infra" flow to our email integration, §8.7.)
- **Transactional templates:** `new-post-in-thread` and `new-thread-in-board`, each rendered with thread title, board name, a snippet, and a deep link; subject lines kept short and specific. Editable with preview + test-send (as §7.4).
- **Per-recipient, idempotent send:** the post-insert fan-out enqueues one email per subscriber with `idempotency_key = post_id + ':' + user_id`, so retries never double-send.
- **Suppression list:** bounces, complaints, and unsubscribes add the address to `email_suppressions` (§10); the fan-out skips suppressed recipients. One-click unsubscribe in every notification email.
- **Transactional only:** notification + system emails. **Marketing/digest blasts are out of scope** (unsupported by the transactional path); a member's "email digest" preference (USER.md §4.6) batches *notification* emails, not marketing.

### 7.6 Digest scheduling, deliverability & delivery log (doc v0.4; delivered Phase 2)

Completeness items (audited against an adjacent implementation), translated to our **vanilla-PHP + VPS cron/worker + SMTP** stack:

**Frequency & digests**

- **Per-subscription frequency** — each subscription is **Instant / Daily / Off** (`subscriptions.frequency`, DESIGN.md §8.3); a **thread setting overrides its board**, and "Off" skips all sends for that target.
- **Instant** rides the post-insert fan-out (queue worker); **Daily** activity is collected for the digest.
- **Timezone-aware daily digest** — each user has a **timezone** and a **preferred digest hour** (`users.timezone`, `users.digest_hour`). An **hourly cron** sends a user's digest when their local clock hits that hour and there's new activity.
- **Watermarking** — `users.last_daily_digest_at` ensures a digest contains only activity since the last one and is never sent twice or empty.
- **Digest template** — a dedicated branded, responsive **daily-digest** email (alongside the instant `new-post-in-thread` / `new-thread-in-board` templates) listing boards/threads with new activity and deep links.
- **Cron cadence** — a **minutely** job drains the instant-email queue; an **hourly** job evaluates digest eligibility per timezone. Both run as VPS cron/worker processes (DECISIONS.md §2).

**Deliverability & compliance**

- **Bounce & complaint webhooks** — inbound endpoints receive ESP bounce/complaint events and add the address to `email_suppressions` (§10). *(Requires the chosen provider to emit these — a selection criterion when the SMTP/ESP provider is picked, DECISIONS.md.)*
- **Suppression cascade** — when an address is suppressed, **all of that user's email subscriptions are disabled** (`subscriptions.email_enabled = 0`) to protect sender reputation. Done in **app logic** (we use app-layer, not DB triggers — DECISIONS.md).
- **One-click unsubscribe** — every notification email carries an unsubscribe link to a **`/unsubscribe`** page that validates a signed token (a `verifications`-style token, USER.md §7), applies the change, records suppression, and confirms — **no login required**.
- **Suppression recovery** — a settings action lets a user **re-enable** email for a previously-suppressed address after confirming their inbox works (removes the suppression, re-enables their subscriptions).

**Transparency & troubleshooting**

- **Delivery activity log** — `email_deliveries` (§10) records each send (instant / digest / test / system) with **status** (Sent / Bounced / Complained / Suppressed / Failed) and error detail; surfaced as a log in the Console and in `/settings/notifications`, with a **CSV export**.
- **Test send** — a "send a test notification" action from `/settings/notifications` verifies deliverability end-to-end.
- **Digest preview** — a live preview of which boards/threads the next digest will include, from current unread activity + subscriptions.

## 8. Integrations & Plugin System

### 8.1 Philosophy

Extensibility **without forking core**, safe by construction, and minimal in v1. We build an internal **hook system** and a couple of **first-party integrations** now; we open a third-party plugin ecosystem only once the security model is ready (P2). The hook system is dogfooded by our own integrations, so it's proven before anyone else uses it.

### 8.2 Architecture — events (actions) & filters

Core emits **named events** at lifecycle points; extensions subscribe. Two kinds, a deliberately familiar pattern:

- **Actions** — "do something when X happens" (side effects). e.g. `user.registered`, `user.banned`, `thread.created`, `post.created`, `post.reported`, `report.resolved`, `notification.queued`.
- **Filters** — "transform this value" (pure-ish). e.g. `post.render` (raw → sanitised HTML; a plugin can add a syntax-highlighter or oEmbed), `post.body.presave`, `email.template`, `spam.score`.

```php
// Action: react to an event
hooks.on('post.reported', function (report, post, reporter) {
    // e.g. forward to a Slack webhook, or bump a counter
});

// Filter: transform a value through the chain
hooks.filter('post.render', function (html, post) {
    return addOEmbed(html);   // each plugin gets the previous output
});

// Provide a capability-gated service (e.g. a spam scorer)
hooks.provide('spam.score', akismetScorer);   // moderation automation consumes it
```

### 8.3 Extension points

A plugin may register: event listeners & filters; an **admin settings panel** (its own page in the Console); **admin menu items / dashboard widgets**; **scheduled jobs** (cron); **content-render filters**; **moderation automations** (contribute a spam scorer or auto-rule); **auth providers** (OAuth); and **themes/skins**.

### 8.4 Packaging & lifecycle

- **Manifest:** id, name, version, author, **required capabilities/permissions**, hooks/events used, settings schema, min core version.
- **Lifecycle:** install → enable → configure → disable → uninstall (**with data cleanup**). State tracked in a `plugins` table (§10) + files on disk.
- **Updates:** version-checked; a failed migration disables rather than half-applies.

### 8.5 Security & trust model

Server-side plugins run with app privileges, so v1 is **conservative**:

- **First-party / vetted only** in v1 — no arbitrary marketplace install yet.
- **No `eval`/arbitrary code from the UI**; capability-gated APIs only.
- **Explicit consent:** enabling a plugin shows the permissions it requests (e.g. "read user emails", "send outbound HTTP").
- **Disable-on-error:** a plugin that throws is auto-disabled and logged, never taking the site down.
- A formal **sandbox + review process** is the prerequisite for opening to third parties (P2, §12).

### 8.6 Webhooks & admin API

- **Outbound webhooks:** fire on chosen events to a URL with **HMAC-signed** payloads and retries. This is the low-effort path for Slack/Discord/Zapier/n8n without writing a plugin.
- **Signing secrets:** webhook secrets are stored as SecretVault references (`secret_ref`, `svcsec_*`), shown once at creation/rotation, and never stored in plaintext.
- **Admin/REST API:** a minimal, **token-authenticated** API (scoped tokens, audited) for automation — read stats, manage content/users. Tokens are managed in the Console (§9).

### 8.7 First-party integration targets

Built as modules/plugins on the hook system, both to be useful and to validate it: **Email/SMTP provider**, **Spam** (Akismet-style `spam.score` provider), **Outbound webhooks** (Slack/Discord), **OAuth login**, **Analytics**, **Search backend** (swap MySQL FULLTEXT → Meilisearch/Elastic, DESIGN.md §14), **Media/CDN** storage.

### 8.8 v1 scope

Internal event/filter system + the post-render filter pipeline + the email/SMTP first-party integration. **Outbound webhooks, the spam integration, and hook-system GA land in Phase 3** (§11; DECISIONS §4 #10; v1 = Phases 1–2). **Public plugin install / marketplace is P2**, gated on the security model.

## 9. Admin Settings UX / UI

**The Admin Console should feel like a control room / operations inbox** — searchable queues, bulk actions, filters, scoped views, strong auditability, and reversible-by-default actions. It applies Outlook's triage discipline (dense lists, a command bar, keyboard flow) to *running* a community, consistent with the Community-Inbox thesis (DESIGN.md).

### 9.1 Two surfaces

1. **Inline moderation** — lives *in* the forum: hover actions on posts, a mod toolbar on threads, the Report control. Fast and in-context for everyday moderation.
2. **The Admin Console** (`/admin`) — a dedicated space for queues, configuration, bulk work, and anything structural. Visually consistent with the hybrid app (same shell + tokens), but clearly a separate mode.

Moderators see a **reduced Console** scoped to their boards (Reports, Audit, limited People); Admins see everything.

### 9.2 Console information architecture

Left-nav, grouped:

| Section | Contains |
|---|---|
| **Dashboard** | Health at a glance: open reports, approval/hold items, new users today, active users, recent mod actions, system flags. |
| **Moderation** | Reports queue · Audit log · Automation rules (filters, throttles, approvals). |
| **Content** | Boards & Categories · Thread tags/prefixes. |
| **People** | Users directory · Roles & Moderators · Bans · Approval queue. |
| **Appearance** | Branding · Themes/skins · Custom CSS. |
| **Notifications** | Staff alerts matrix · Email templates · Announcements/broadcast. |
| **Integrations** | Plugins · Webhooks · API tokens. |
| **Settings** | General · Registration · Security · Email · Privacy/Legal · Advanced. |

### 9.3 Key screens (wireframe-level)

- **Dashboard** — metric cards + a "needs attention" list (the queues with counts) + recent audit feed. The operator's landing page.
- **Reports queue** — per §3.2: list of grouped items, context preview, quick actions, filters, bulk.
- **Board manager** — categories/boards list with drag-reorder, inline settings, "Add board", moderators picker; destructive actions need typed confirmation with impact stats.
- **User manager** — directory + the per-user admin record (§5.2).
- **Appearance** — live token/brand editor with a real-time preview pane and theme picker.
- **Integrations** — installed plugins (enable/disable/configure with their permission prompts), webhook endpoints, API tokens.
- **Settings → Registration/Security** — registration mode (open / approval / invite), email-verification requirement, password policy, rate limits, anonymous-posting default.

### 9.4 Design principles for the Console

- **Same look, distinct mode** — reuse the app shell and tokens; a persistent "Admin" indicator so no one confuses it with the public site.
- **Safe by default** — confirm destructive actions (typed confirmation for irreversible ones), show impact, prefer reversible/soft operations, dry-run where feasible.
- **Audit everything** — every config and content change writes to the log (§3.6).
- **Search-first lists** with filters and **bulk actions** — admins work at scale.
- **Progressive disclosure** — basic settings up front, advanced behind a toggle.
- **Least privilege in the UI** — hide what a role can't do rather than show-and-deny.
- **Responsive** — urgent actions (handle a report, ban) work on mobile; the console collapses to one column with the section nav in a drawer (mirrors the app's mobile pattern).

### 9.5 First-run setup wizard

**Phase 1 subset (planned):** on a fresh migrated install, normal routes redirect to `/setup` until the operator creates the first **Admin**, sets the community **name/brand**, and picks a **starter set** of categories/boards. The wizard uses the Phase 1 auth/session/CSRF services and writes only to the `users`, `settings`, `categories`, and `boards` tables. Everything it sets is editable later in Settings.

**Deferred:** email/domain configuration and registration mode (open / approval / invite) stay in the later admin/settings slice because those enforcement paths do not exist yet.

## 10. Data-Model Additions (delta to DESIGN.md §8)

These extend the core schema in DESIGN.md §8. Same conventions (InnoDB, `utf8mb4`, `BIGINT UNSIGNED` keys).

### 10.1 New tables

```sql
-- Typed key/value site configuration (theming tokens, registration mode, toggles, ...)
CREATE TABLE settings (
  `key`      VARCHAR(64) NOT NULL,
  `value`    JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bans & board-bans (source of truth + history; users.status is a denormalised cache)
CREATE TABLE bans (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  scope      ENUM('site','board') NOT NULL DEFAULT 'site',
  board_id   BIGINT UNSIGNED NULL,                  -- required when scope='board'
  type       ENUM('post','full') NOT NULL DEFAULT 'post',
  reason     VARCHAR(255)    NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME        NULL,                  -- NULL = permanent
  lifted_at  DATETIME        NULL,
  lifted_by  BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_bans_active (user_id, expires_at, lifted_at),
  CONSTRAINT fk_bans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Formal warnings (user-visible; optional points toward auto-escalation)
CREATE TABLE warnings (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  issued_by  BIGINT UNSIGNED NOT NULL,
  board_id   BIGINT UNSIGNED NULL,
  reason     VARCHAR(255)    NOT NULL,
  points     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_warn_user (user_id, created_at),
  CONSTRAINT fk_warn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Private staff notes on an account (never user-visible)
CREATE TABLE user_notes (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_user_id BIGINT UNSIGNED NOT NULL,
  author_id       BIGINT UNSIGNED NOT NULL,         -- staff member
  body            TEXT            NOT NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notes_subject (subject_user_id, created_at),
  CONSTRAINT fk_notes_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Membership for private/hidden boards (read-gate)
CREATE TABLE board_members (
  board_id   BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  added_by   BIGINT UNSIGNED NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (board_id, user_id),
  CONSTRAINT fk_bm_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  CONSTRAINT fk_bm_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Installed plugins / integrations
CREATE TABLE plugins (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug         VARCHAR(64)     NOT NULL,
  name         VARCHAR(120)    NOT NULL,
  version      VARCHAR(20)     NOT NULL,
  is_enabled   TINYINT(1)      NOT NULL DEFAULT 0,
  config       JSON            NULL,
  installed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plugins_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Outbound webhooks
CREATE TABLE webhooks (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                 VARCHAR(80)     NOT NULL,
  url                  VARCHAR(512)    NOT NULL,
  events               JSON            NOT NULL,    -- list of event names to deliver
  secret_ref           VARCHAR(64)     NOT NULL,    -- svcsec_* SecretVault reference, not plaintext
  is_active            TINYINT(1)      NOT NULL DEFAULT 1,
  consecutive_failures INT UNSIGNED    NOT NULL DEFAULT 0,
  disabled_at          DATETIME        NULL,
  disabled_reason      VARCHAR(190)    NULL,
  last_status          INT             NULL,        -- last delivery HTTP status
  last_delivered_at    DATETIME        NULL,
  created_by           BIGINT UNSIGNED NOT NULL,
  created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_webhook_active (is_active),
  CONSTRAINT fk_webhook_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE webhook_deliveries (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  webhook_id      BIGINT UNSIGNED NOT NULL,
  event_type      VARCHAR(80)     NOT NULL,
  event_id        VARCHAR(64)     NOT NULL,
  payload         MEDIUMTEXT      NOT NULL,
  status          ENUM('queued','delivered','dead') NOT NULL DEFAULT 'queued',
  attempt_count   INT UNSIGNED    NOT NULL DEFAULT 0,
  max_attempts    INT UNSIGNED    NOT NULL,
  next_attempt_at DATETIME        NULL,
  last_attempt_at DATETIME        NULL,
  response_status INT             NULL,
  error           VARCHAR(255)    NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_idem (webhook_id, event_type, event_id),
  KEY idx_delivery_claim (status, next_attempt_at),
  CONSTRAINT fk_delivery_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scoped admin API tokens
CREATE TABLE api_tokens (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80)     NOT NULL,
  token_hash  CHAR(64)        NOT NULL,             -- store only the hash
  scopes      JSON            NOT NULL,
  created_by  BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME       NULL,
  expires_at  DATETIME        NULL,
  revoked_at  DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_api_token_hash (token_hash),
  KEY idx_api_token_created_by (created_by),
  KEY idx_api_token_active (revoked_at, expires_at),
  CONSTRAINT fk_api_token_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email suppression list (bounces, complaints, unsubscribes) — notification fan-out skips these
CREATE TABLE email_suppressions (
  email      VARCHAR(255) NOT NULL,
  reason     ENUM('bounce','complaint','unsubscribe','manual') NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-send delivery log (activity view, statuses, CSV export, troubleshooting)
CREATE TABLE email_deliveries (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NULL,
  email      VARCHAR(255) NOT NULL,
  kind       ENUM('instant','digest','test','system') NOT NULL,
  subject    VARCHAR(255) NULL,
  status     ENUM('queued','sent','bounced','complained','suppressed','failed') NOT NULL DEFAULT 'queued',
  error      VARCHAR(255) NULL,
  message_id VARCHAR(191) NULL,
  idempotency_key VARCHAR(191) NULL,                          -- SCHEMA §7 #9: post_id+':'+user_id for 'instant' fan-out; NULL for digest/test/system
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_deliv_idem (idempotency_key),                 -- dedupes one send per (post,recipient); InnoDB allows multiple NULLs
  KEY idx_deliv_user (user_id, created_at),
  KEY idx_deliv_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 10.2 Additions to existing tables

| Table | Add | Why |
|---|---|---|
| `users` | `suspended_until DATETIME NULL` | Temporary suspension expiry (`status` already exists). |
| `boards` | `visibility ENUM('public','hidden','private') DEFAULT 'public'`, `allow_anonymous TINYINT(1) DEFAULT 0`, `require_approval TINYINT(1) DEFAULT 0`, `edit_window_seconds INT DEFAULT 0` | Per-board access, anon mode, approval hold, edit window. (`post_min_role`, `is_archived` already in DESIGN.md.) |
| `posts` | `is_anonymous TINYINT(1) DEFAULT 0`, `is_pending TINYINT(1) DEFAULT 0`, `ip VARBINARY(16) NULL` | Masked rendering (author still `user_id`); hold/approval queue; post IP (90-day retention, Admin-only audited; built Phase 2). |
| `threads` | `is_pending TINYINT(1) DEFAULT 0` | New-thread approval hold. |
| `reports` | `assigned_to BIGINT UNSIGNED NULL`, `reason_code ENUM(...)` | Queue claim; structured reasons (plus free-text). |
| `moderation_log` | `before_json JSON NULL`, `after_json JSON NULL`; allow `actor_id` to denote **system** for automated actions | Edit snapshots; accountable automation. |

> **Anonymous integrity:** an anonymous post still has a real `posts.user_id`; only the public render is masked. `mod.anon.reveal` exposes the author and writes an audit entry. This is what keeps "Anonymous" accountable rather than a loophole.

## 11. Roadmap Delta (admin/mod phasing)

Mapped onto DESIGN.md §13 phases (whose strategic "Phase 3" and "Later (P2)" buckets subdivide into delivery Phases 3–7 — see SCHEMA §6):

- **Phase 1 (MVP) — planned, not yet built.** Seed roles (Admin / User / Guest); a **first-run setup wizard** for creating the first admin, setting the community name, and creating starter categories/boards on a fresh migrated install. Admin board & category CRUD in a minimal **`/admin` console** (create/edit/hide/admin-only private visibility/delete-empty, with slug-change 301 redirects that respect the same read gate before redirecting) alongside **site naming** and a **baseline audit feed**. **Inline moderation**: admins can pin/unpin and lock/unlock threads and soft-delete any post from the thread view, with every action audited to `moderation_log`. **Suspended/banned user write gating**: suspended users keep login + read access but are blocked (403) from creating threads, replying, editing, or deleting; stale sessions for banned users are likewise blocked at write time. **Target evidence (to be created):** `tests/Integration/Core/AppSetupTest.php`, `tests/Integration/Service/SetupServiceTest.php`, `tests/Integration/Core/AppAdminTest.php`, `tests/Integration/Core/AppModerationTest.php`, `tests/Integration/Core/AppWriteGateTest.php`, `tests/Integration/Core/AppPrivateBoardAccessTest.php`, plus HTTP/browser smoke on `/setup`, `/admin`, and authenticated admin flows.
- **Phase 2 (community essentials).** Moderator role + per-board assignment; **flagging + reports queue**; full content & user moderation actions; audit-log UI; **user management** screen; board visibility (hidden/private) + `board_members`; **in-app notifications + the full notification-preference matrix and timezone-aware daily digests** (the email worker comes online here — see USER §4.6); **password-reset and registration email-verification flows**; email templates; **admin announcements/broadcast** (site banner + opt-in broadcast notification/email, §7.4); **reporter outcome-notifications** ("notify me of the outcome", §3.1); **board/category drag-reorder and board archive** (§4.4–§4.5 — deferred from the Phase 1 minimal console). _(Theming/branding, outbound webhooks, and spam integration moved to Phase 3 — see §11 Phase 3.)_
- **Phase 3 (polish & scale).** **Branding/theming (brand + dark mode) and retro skin / custom CSS**; automation rules (filters, throttles, approval queues); **spam-scoring integration**; appeals; **internal plugin/hook system GA** + first-party integrations; **outbound webhooks (durable delivery)** + admin API & tokens; advanced privacy flows (export/delete); category-scoped mods; **IP-capture retention/purge job** (90-day purge + anonymise of login/post IPs, §5.5 — the `sessions.ip`/`posts.ip` seam). _(Notification matrix + digests moved to Phase 2.)_
- **Later — delivery Phases 5–7.** Public plugin ecosystem + sandbox/review (**Phase 5**); granular custom roles (**Phase 5**); multi-community administration (**Phase 7**). _(Priority tier P2; see PHASE_5_PLAN / PHASE_7_PLAN and DESIGN §13.)_

## 12. Open Questions

> **Resolved in [DECISIONS.md](DECISIONS.md) §4.** Retained below for context.

| # | Question | Owner | Blocking? |
|---|---|---|---|
| 1 | **Anonymous** = masked identity for logged-in Users (recommended) vs. guests posting without an account? | Product | §1.3, schema — Phase 2 |
| 2 | Standardise role naming: **"User"** (this doc) vs "Member" (DESIGN.md). | Product / docs | Low, do soon |
| 3 | Confirm fixed roles for v1 (no granular custom roles until P2). | Product | Phase 1 |
| 4 | Plugin runtime & **sandbox/review** model before any third-party plugins. | Eng | Blocks public ecosystem (P2) |
| 5 | Do we store IP addresses at all, and for how long? (Ban-evasion vs privacy.) | Henry / legal | Phase 2 privacy |
| 6 | Appeals: in scope for v1+1, and how lightweight? | Product | Phase 3 |
| 7 | Default registration/approval model (open / approval / invite; first-post approval?). | Product | Phase 1/2 |
| 8 | Suspension modelling: `users.status` + `suspended_until` vs. unify everything in `bans`. | Eng | Phase 2 |
| 9 | Moderator scope at launch: board-only, or also category-scoped? | Product / Eng | Decided — board-scoped v1; category-scoped **Phase 3** (DECISIONS §4 #9) |
| 10 | Webhooks + admin API in Phase 3, or pull earlier for "mod alerts in Slack"? | Eng | Decided — **Phase 3** (DECISIONS §4 #10; webhooks P2-priority, admin API P3-priority, both deliver in Phase 3 per §11) |

## 13. Changelog

| Version | Date | Notes |
|---|---|---|
| v0.15 | 2026-07-18 | PR #44 safety remediation deviations recorded (ADR 0021): §3.4 "Add mod note" is admin-only in the shipped implementation (globally-scoped `user_notes` under any-board-mod read over-disclosed); §4.4 board delete ships as hard-DELETE-with-forced-move inside one locking transaction (every thread row moves, slugs un-reserve via cascade) with soft-delete + reserved slugs still deferred as ADR 0021 item 10. |
| v0.14 | 2026-07-12 | Added §3.10 for Thread Intelligence health, recovery, curator, provenance/retention, and data-preserving rollback workflows; linked the canonical runbook, recorded the selected live-eval contract, and reconciled the joint default-on graduation with independent rollback pins. |
| v0.13 | 2026-06-28 | Reconciled outbound webhook storage with the B2 delivery implementation: `webhooks.secret` is replaced by `secret_ref` (`svcsec_*` SecretVault reference), added the durable `webhook_deliveries` ledger, and documented show-once signing secrets. |
| v0.12 | 2026-06-26 | Consistency pass: aligned the §2.4 SUSPENDED resolver branch (`GUEST_READONLY_PLUS_SELF` → **`GUEST_READONLY`**) and the §2.5 worked example ("read-only+self" → "read-only") to the already-decided read-only (no self-write) behaviour in §11 + PHASE_1_PLAN; corrected §8.8 v1 scope to the internal hook system + email only, noting outbound webhooks, spam integration, and hook-system GA land in **Phase 3** (DECISIONS §4 #10; v1 = Phases 1–2); added `posts.ip VARBINARY(16) NULL` to the §10.2 additions (90-day retention, Admin-only audited; built Phase 2 — DECISIONS §4 #5, SCHEMA reconciliation #10). **Resolved (suspend scope) [Henry]:** set §3.4 Suspend scope to **Board/Site** to match §2.2 — Admins suspend site-wide (global `users.status`/`suspended_until`); a moderator suspends within their assigned board(s) as a time-limited board read-only state (`bans` `scope='board'`/`type='post'`/`expires_at`), enforced via the board-level gate, not the global account flag. |
| v0.11 | 2026-06-26 | **Status-truth pass (nothing is built yet):** rewrote the §11 Phase 1 bullet from "is live" to planned and relabeled its test-file list as **target** evidence (none exists); relabeled the §9 "Live Phase 1 subset" as planned; reworded the v0.5/v0.6/v0.7 entries below from "Shipped/now live" to "Specified (design only — not built)". No scope changes. |
| v0.10 | 2026-06-26 | Consistency pass: corrected the §3.5 lifecycle-diagram appeal annotation `(P2)` → `(P3)` (the v0.9 pass relabeled the §3.7 header but missed the diagram; Appeals is P3 priority / Phase 3 delivery — DECISIONS §4 #6); mapped §11's "Later (P2)" items to **delivery Phases 5–7** (public plugin ecosystem & custom roles → Phase 5, multi-community → Phase 7) and noted DESIGN §13 subdivides into Phases 3–7; set §12 row 10 (webhooks/API) to its decided value (Phase 3); added **P3** to the header conventions legend; bumped the stale header (was v0.8, behind its own v0.9 row). |
| v0.9 | 2026-06-26 | Consistency pass: changed the §3.7 Appeals header from "(P2)" to "(Phase 3)" (matches DECISIONS §4 #6 + the Phase 3 plan); gave previously-unscheduled operator features explicit owners in §11 — announcements/broadcast, reporter outcome-notifications, and board/category reorder + archive to **Phase 2**, the IP-retention purge job to **Phase 3**; added `email_deliveries.idempotency_key` + `uq_deliv_idem` to the §10.1 DDL to match SCHEMA §7 #9; set §12 row 9 (mod scope) to its decided value and tagged §7.5/§7.6 with their delivery phase. |
| v0.8 | 2026-06-25 | Consistency pass on §11: moved the full notification matrix + daily digests into **Phase 2** (matching DESIGN/USER and the Phase 2 plan) and added the password-reset/email-verification flows there; moved branding/theming, outbound webhooks, and spam integration to **Phase 3** (matching DESIGN §13 and the Phase 3 plan). |
| v0.7 | 2026-06-21 | **Specified** the Phase 1 first-run setup wizard (design only — not built): a fresh migrated install gates normal routes to `/setup`, creates the first admin, persists `settings.site_name`, creates starter categories/boards, signs the admin in, and redirects to `/admin`. Email/domain setup and registration-mode controls remain deferred. |
| v0.6 | 2026-06-21 | **Specified** the next operator slice (design only — not built): **admin inline moderation** (pin/unpin, lock/unlock threads, soft-delete any post) with every action audited to `moderation_log` (actor, action, target, before/after), plus **suspended/banned user write gating** (403 on every write path; stale sessions for banned users are blocked at write time, not just at login). Updated §11. |
| v0.5 | 2026-06-21 | **Specified** the first admin/operator slice (design only — not built): `/admin` for board/category management, hidden-board toggle, admin-only private visibility, empty-item deletes, site naming, and a lightweight audit feed. The setup wizard, full moderation controls, and the broader Console surface remain follow-up work. |
| v0.1 | 2026-06-19 | Initial admin/mod design. Roles/states/scopes + capability model & resolution algorithm; moderation workflows (flag→queue→action→audit→appeal) and functions; board & category management; user management & privacy; theming; staff notifications; integrations/plugin hook system + webhooks; admin Settings UX/IA; data-model delta; roadmap delta; open questions. |
| v0.2 | 2026-06-19 | Folded-in features: notification **email infrastructure** (§7.5 — domain setup gate, `new-post-in-thread`/`new-thread-in-board` templates, per-recipient idempotency, suppression list); moderation of the new surfaces (DMs/reputation/presence/notifications, §3.9); `email_suppressions` table (§10). |
| v0.3 | 2026-06-19 | Framework integration: Admin Console framed as a **control room / operations inbox** (§9); role naming **standardised on "User"** (§1.1). |
| v0.4 | 2026-06-19 | Notification-completeness pass (§7.6): per-subscription frequency, timezone-aware daily digests + watermarking + cron cadence, bounce/complaint webhooks, suppression cascade + recovery, `/unsubscribe` token page, delivery activity log + CSV + test send + digest preview; new `email_deliveries` table (§10). |
