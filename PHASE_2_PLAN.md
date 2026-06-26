# RetroBoards Phase 2 Plan — Community Essentials

**Owner:** Henry  
**Plan type:** Delivery baseline, release train, and formal phase closeout  
**Source hierarchy:** `DECISIONS.md` is authoritative where documents conflict; `DESIGN.md` is the product source of truth; `SCHEMA.md` owns the final database shape; `ADMIN.md`, `USER.md`, `COMPOSER.md`, and `COMMUNITY.md` own their respective surfaces. P0/P1/P2 in the source documents are priority tiers, not delivery-phase numbers.  
**Status context:** Phase 1 is **not yet built** — it is the prerequisite for Phase 2. Phase 2 is the next planned product slice and starts only after Phase 1 is implemented and accepted.

**Entry gate — Phase 1 must be built and closed first.** Phase 2 may begin only once Phase 1's definition of done is green with evidence (auth/session/CSRF; posting create/read/edit/delete; first-run admin + inline moderation; suspended/banned write-gates) and the Phase 1 schema is migrated and seeded per SCHEMA §6 (`users`, `sessions`, `verifications`, `categories`, `boards`, `board_slug_history`, `threads`, `posts`, `settings`, `moderation_log`). Phase 2 begins only on that green baseline. _(Phases 3–7 carry this as their own "## 2. Entry gate"; Phase 2 keeps it inline here to avoid renumbering its sections.)_

## 1. Phase objective

Turn the live core forum into a personal, searchable **Community Inbox** in which a member can:

1. See what is unread and resume a thread from their last-read position.
2. Star threads, react to posts, and subscribe to threads or boards.
3. Receive in-app and email notifications, including `@mentions`, without duplicate or unauthorized alerts.
4. Search public content and any private content they are allowed to read.
5. Hold persistent one-to-one direct-message conversations.
6. Report harmful content and rely on board-scoped moderators to triage it.
7. Build a richer public identity through activity, reputation, follows, badges, solved answers, and cosmetic titles.
8. Control notification, messaging, privacy, block-list, and account-connection settings.

An operator must be able to assign board moderators, manage private-board membership, govern users and reports, inspect an immutable audit trail, and run the notification/email system safely on the existing VPS stack.

Phase 2 is a **release train**, not a big-bang deployment. The core community release may ship before the extended social/account slice, but Phase 2 is not formally closed until both gates below are accepted or a written scope change moves an item to a later phase.

## 2. Definition of done

Phase 2 is accepted only when all of the following are true:

- Every Phase 1 journey remains functional, permission-safe, server-rendered, and usable without JavaScript.
- All Phase 2 schema changes are additive, migratable on a populated Phase 1 database, and verified on both clean and upgraded installations.
- Per-user unread state and stars persist across sessions; unread counts and filters never expose inaccessible boards or threads.
- Reactions are idempotent, enforce one reaction per `(user, post, emoji)`, and update reputation transactionally without counting self-reactions.
- Thread and board subscriptions support In-app/Email and Instant/Daily/Off; a thread subscription overrides its board subscription.
- The notification bell shows an accurate unread count and recent items, supports read/mark-all-read/clear behavior, and deep-links only to content the recipient can currently access.
- Notification creation, email queuing, and retries are idempotent. Authors do not receive their own subscription notifications, and suppressed addresses are never sent mail.
- Daily notification digests are timezone-aware, watermarked, and never duplicated or sent empty.
- `@mentions` are parsed from canonical Markdown, capped at 10 recipients per post, deduplicated, block-aware, and routed through the same notification controls.
- MySQL FULLTEXT search returns thread-title and post-body results with safe snippets and applies the normal read gate before returning or linking to a result.
- Persistent one-to-one DMs support unread state and notifications, enforce each recipient's DM/privacy/block settings, and cannot be used by suspended or banned accounts to bypass write restrictions.
- A participant can report a specific DM; staff see only the reported message/context required for review, not an unrestricted private-conversation browser.
- Users can report posts; duplicate open reports are prevented; reports can be claimed, resolved, or dismissed; board moderators see only their scope.
- Moderators can pin, lock, move, restore, and soft-delete content within scope; user warnings, suspensions, bans, notes, and private-board membership are permission-scoped and audited.
- Moving a thread updates both source and destination counters atomically and does not leak the thread through stale links, search results, notifications, or unread counts.
- Public profiles show correct post/reputation/activity data and enforce profile visibility, blocks, and leaderboard opt-out settings.
- Reputation, titles, badges, follows, the Following feed, solved answers, and the all-time leaderboard remain social/cosmetic and never grant permissions.
- OAuth account linking for Google, Apple, and GitHub prevents silent email-based account takeover and never leaves an account without a usable login method.
- Active-session/device controls revoke the selected session reliably; existing banned-user stale-session protections continue to work.
- Presence, when enabled, uses short-polling/last-seen data, respects the user's visibility setting, and never exposes a hidden user as online.
- All new state-changing routes use CSRF protection, centralized authorization, prepared statements, validation, rate limits where abuse is plausible, and the Phase 1 account-state write gate.
- All denormalized counters have reconciliation tests or a repair command.
- The complete automated suite, HTTP smoke matrix, worker/cron checks, and browser evidence pass on desktop and mobile widths.
- No unresolved critical or high-severity security, privacy, delivery, or data-integrity defect remains.

## 3. Scope and release gates

### Gate A — Phase 2 core community release

Gate A is the minimum public release that satisfies the top-level Phase 2 roadmap in `DESIGN.md` and `README.md`:

- Persistent reactions, stars, and per-thread unread tracking.
- Inbox filters/sorts backed by server state: Unread, Starred, Mine, Active, Newest, and Unanswered where supported.
- Thread/board subscriptions and the in-app notification bell.
- Instant and daily notification email through the VPS worker/cron path, including suppression and one-click unsubscribe.
- `@mentions` with autocomplete/enhancement plus a no-JavaScript submission path.
- MySQL FULLTEXT search behind a search-service interface.
- Persistent one-to-one DMs, DM unread state, notification integration, and DM reporting.
- Reports queue, board-scoped moderators, thread move/restore, user moderation records, private-board membership, and audit-log UI.
- **Reporter outcome-notifications** ("notify me of the outcome" when a report is resolved/dismissed), routed through the normal notification controls (ADMIN §3.1, §11).
- Accurate post counts and reaction-derived reputation on profiles, with a simple cosmetic title/rank.
- Supporting notification, privacy, block-list, and DM-permission settings required to make the above safe.
- Account recovery: **password-reset and registration email-verification flows** (the email worker enables these; storage already shipped in Phase 1).
- **Per-board `post_min_role` enforcement** and masked-**"Anonymous"** posting where a board enables it, with audited moderator reveal ([Rec] — confirm scheduling; the columns already ship in Phase 1).
- Completion evidence, feature flags, migration/rollback notes, and operational runbooks.

### Gate B — Phase 2 extended closeout

These are committed to the Phase 2 window by the surface-specific roadmaps and complete the broader community/account layer. They may ship after Gate A, but require either acceptance or an explicit re-scope before Phase 2 closes:

- Following/followers, a query-time Following feed, and new-follower notifications.
- Fixed auto/manual badge catalogue, badge awards, badge notifications, and profile badge display.
- Accepted/solved answers with an idempotent reputation bonus and Problem Solver badge.
- Profile activity tabs and an opt-out all-time Top Contributors page.
- OAuth sign-in and account connections for Google, Apple, and GitHub, including **OAuth avatar-import** (sets `users.avatar_source='oauth'` and caches the provider avatar URL on `oauth_identities.avatar_url`; DECISIONS §5 #4, USER §8/§9).
- Saved view plus favorite/mute/reorder board preferences.
- Active sessions/devices, revoke-one, and log-out-everywhere-else controls.
- Username-change history and safe old-profile redirects if username changes ship in this gate.
- Self-service data export/delete only after retention, anonymization, and grace-period policy is approved and tested.
- Minimal privacy-respecting presence and the Phase 2 mobile drawer/FAB/tap-target polish.
- Delivery activity visibility, test send, digest preview, and recovery from email suppression.
- **Admin announcements/broadcast**: a site-wide banner or pinned announcement plus an opt-in broadcast notification/email to members, rate-limited and audited (ADMIN §7.4, §11).
- **Operator board archive and board/category drag-reorder** beyond the Phase 1 minimal console (ADMIN §4.4–§4.5; uses `boards.is_archived`), with the same read-gate-safe slug handling as Phase 1.

_(Reconciled 2026-06-26: announcements/broadcast, reporter outcome-notifications, and admin board archive/reorder were assigned to Phase 2 by ADMIN.md §11 but were missing from this plan; added here so each has a delivery owner. Phase 4 lists them only as defensive "conditional carryovers.")_

### Explicitly deferred

The following must not delay Phase 2 acceptance unless formally re-scoped:

- Group DMs.
- 2FA/TOTP, passkeys/WebAuthn, or additional OAuth providers.
- Image/file attachments, link unfurls, tables, task lists, slash commands, custom emoji, GIFs/polls, and server-synced drafts.
- Meilisearch/Elastic, advanced relevance tuning, or a dedicated search cluster.
- SSE/WebSockets; Phase 2 uses short-polling and normal HTTP.
- Appeals, merge/split, category-scoped moderators, configurable automation-rule builders, and granular custom roles.
- Tag/board follows, remove-a-follower, time-windowed leaderboards, custom badges, fan-out feed storage, and community-memory features.
- Admin branding/theme editor, retro skin, custom CSS, public plugin marketplace, admin REST API, and general outbound webhooks.
- PWA/offline mode, imports from other forums, multi-community/multi-tenant support, and i18n.

## 4. Locked implementation decisions

The plan assumes the following decisions are not reopened during delivery:

- PHP 8.x, vanilla front controller/micro-router, PDO prepared statements, MySQL 8/MariaDB, and server-rendered HTML with progressive enhancement.
- Markdown remains canonical for posts; rendered HTML is cached and allowlist-sanitized.
- Unread state uses `thread_user.last_read_post_id`, not per-post receipts.
- Reactions are the reputation input; each received reaction is +1, self-reactions contribute zero, and reputation grants no authority.
- Search uses MySQL FULLTEXT behind a replaceable search interface.
- Notification fan-out is app-layer, transactionally initiated, and processed by the VPS worker; no database triggers.
- Email is SMTP behind the existing `Mailer` interface; delivery must be idempotent and suppression-aware.
- The bell and presence use short-polling; no WebSocket dependency.
- DMs are one-to-one for this phase even though the participant schema can support more later.
- Moderation is capability-based with board scope; fixed roles remain User, Moderator, and Admin.
- Block and privacy checks are server-side and apply to DMs, mentions, follows, notifications, profiles, feeds, and search-derived links.
- The mention cap is 10 recipients per post.

## 5. Delivery workstreams

| ID | Workstream | Deliverables | Acceptance evidence | Dependency | Release gate |
|---|---|---|---|---|---|
| P2-00 | Baseline, schema, and rollout controls | Phase 1 regression baseline; traceability matrix; additive migrations; feature flags; upgrade/rollback rehearsal; counter-repair commands | Clean-install and Phase-1-upgrade migration tests; schema reconciliation review; full Phase 1 suite green | None | A |
| P2-01 | Personal inbox state | `thread_user` read/star persistence; unread counts; Starred/Unread/Mine filters; read cutover policy; mark-all-read path; sort tabs | Repository/service tests; multi-user isolation tests; no-JS and browser proof; inaccessible-content count tests | P2-00 | A |
| P2-02 | Reactions and reputation foundation | Reaction toggle; grouped counts; author notification; transactional reputation update; deletion/restore reconciliation; cosmetic title thresholds | Idempotency/concurrency tests; self-reaction regression; counter repair; browser and no-JS forms | P2-00 | A |
| P2-03 | Subscriptions and in-app notifications | Thread/board subscribe controls; channel/frequency precedence; notification fan-out; bell/dropdown; read/all-read/clear; settings list | Fan-out and precedence tests; duplicate suppression; permission-change/deep-link tests; short-poll smoke | P2-00, P2-01 | A |
| P2-04 | Email worker and digests | Durable queued sends; SMTP adapter path; idempotent retries; templates; suppression; unsubscribe; instant worker; hourly digest scheduler; watermarks | Fake-mailer integration tests; worker restart/retry tests; timezone/digest fixtures; bounce/suppression tests; operational smoke | P2-03 | A |
| P2-05 | Mentions and composer integration | `@username` recognition/autocomplete; submit-time recipient resolution; cap/dedupe; block-aware notifications; safe rendered links; edit behavior | Parser/service tests; XSS tests; blocked/missing/renamed user cases; browser keyboard and no-JS proof | P2-00, P2-03 | A |
| P2-06 | Search | Search interface; FULLTEXT migrations; thread and post queries; snippets; public/board-scoped mode; results popover/page; mobile affordance | Relevance fixtures; visibility-gate tests; deleted/pending/private-content exclusions; query plan/performance evidence | P2-00 | A |
| P2-07 | Direct messages | One-to-one conversations; send/read/unread; notifications; privacy and block enforcement; throttles; report-message flow | Participant authorization tests; block/allow-DM matrix; suspended/banned tests; reported-context privacy tests; browser/no-JS evidence | P2-00, P2-03 | A |
| P2-08 | Moderation and private boards | Report submission/dedupe; scoped queue; claim/resolve/dismiss; move/restore; warnings/bans/notes (+`posts.ip`/`sessions.ip` ban-evasion signals); board moderators; `board_members`; audit UI | Full role/scope matrix; counter tests for move; immutable audit assertions; private-board direct-request and search tests | P2-00; P2-03 for staff alerts | A |
| P2-09 | Community identity | Profile activity; follows; Following feed; fixed badges; solved answers; all-time leaderboard; title display; humane anti-abuse rules | Feed/privacy/block tests; badge idempotency/backfill; solved-answer authorization; leaderboard opt-out; browser evidence | P2-02, P2-03, P2-10 | B |
| P2-10 | Member controls and account expansion | Notification/privacy/block settings; Saved and board preferences; OAuth connections **and avatar-import (`users.avatar_source` + `oauth_identities.avatar_url`)**; session/device controls; optional export/delete policy flow | Preference default/merge tests; OAuth state/PKCE/collision tests; orphan-login prevention; session revocation; privacy/export fixtures | P2-00 | A for required privacy controls; B for account expansion |
| P2-11 | Presence, responsive, and progressive enhancement | Last-seen heartbeat; privacy-safe roster; short-poll endpoint; mobile drawer/FAB/tap targets; keyboard and focus behavior | Hidden-presence tests; polling authorization/cache tests; mobile/keyboard/accessibility browser evidence | P2-10 and completed UI surfaces | B |
| P2-12 | Hardening and phase close | Security review; load/concurrency checks; observability; runbooks; evidence index; staged rollout; release notes; rollback validation | Full suite; smoke matrix; worker/cron health proof; no critical/high defects; product-owner acceptance | All | A and B |

## 6. Recommended execution sequence

### Milestone 0 — Scope lock, schema reconciliation, and baseline

- Freeze Gate A, Gate B, and the explicit deferral list.
- Map every definition-of-done statement to a test or operational proof.
- Run and archive the full Phase 1 suite before adding migrations.
- Inventory every existing write route and create the Phase 2 route/permission matrix.
- Reconcile the deployed Phase 1 schema against `SCHEMA.md` before writing new migrations.
- Implement the email-outbox idempotency key: `SCHEMA.md` v1.4 (§7 reconciliation #9) now defines `email_deliveries.idempotency_key` (`VARCHAR(191) NULL`, `UNIQUE KEY uq_deliv_idem`) = `post_id + ':' + user_id`. Land this column in the additive migration and enforce dedupe in the fan-out path before email work starts.
- Decide and record the unread cutover rule. Recommended default: store a Phase 2 launch timestamp in `settings`; content before the cutover starts read, and later content uses lazy `thread_user` rows.
- Define feature flags for engagement, notifications, email, search, DMs, moderation queue, and community features.
- Deliver the `blocks` table and a minimal `is_blocked(viewer, target)` predicate as part of the baseline shared services. Both mentions (P2-05, Milestone 2) and DMs (P2-07, Milestone 3) require block-aware filtering, so the predicate must exist before Milestone 2; P2-10 retains only the block-management UI and privacy settings, not the underlying check. _(Decision 2026-06-26: pulled forward to remove the backward dependency where P2-05/P2-07 needed `blocks`, previously owned by the Milestone-5 workstream P2-10.)_

**Exit gate:** The Phase 1 regression baseline is green; every Phase 2 requirement has an owner/evidence target; migrations and unresolved schema decisions are approved.

### Milestone 1 — Personal inbox and engagement foundation

- Ship `thread_user` state and the read/star repositories.
- Implement unread derivation from `last_read_post_id`, thread-opening updates, unread board totals, and Starred/Unread filters.
- Add reaction persistence and grouped reaction rendering.
- Update reputation in the same transaction as reaction add/remove and post delete/restore.
- Add repair/recompute commands for post counts, board/thread counters, and reputation.
- Verify all personal state is user-isolated and cannot reveal private content through counts.

**Exit gate:** Two users can read, star, and react independently; unread and reputation remain correct after retries, deletes, restores, and concurrent requests.

### Milestone 2 — Notification domain, mentions, and email delivery

- Implement subscriptions with thread-over-board precedence and independent in-app/email channels.
- Insert notification rows during the originating write transaction while excluding the actor/author.
- Build the bell, last-20 dropdown, unread count, deep links, and bulk read/clear actions.
- Add `@mention` parsing, resolution, deduplication, block checks, and notification creation.
- Use `email_deliveries` or the approved outbox as the durable queue; run the worker separately from the web request.
- Add instant templates, digest template, timezone scheduling, watermarking, suppression, and signed unsubscribe.
- Fail closed when the sending domain/provider is not configured; in-app delivery must continue.

**Exit gate:** A post can fan out in-app and email notifications exactly once, worker retries do not duplicate mail, and blocked/inaccessible recipients receive nothing.

### Milestone 3 — Search and direct communication

- Add/build FULLTEXT indexes using a deployment-safe migration plan.
- Implement public and board-scoped search with safe snippets and visibility filtering.
- Add persistent one-to-one conversations, messages, participant unread state, and DM notifications.
- Enforce `allow_dms`, blocks, account status, and new-user throttles before creating a conversation or message.
- Add DM reporting that exposes only the reported message and necessary local context to authorized staff.
- Verify search and notifications never provide an indirect path into a private, hidden, pending, or deleted item.

**Exit gate:** Guests can search only public content; authorized members can find permitted private content; two eligible users can exchange DMs; ineligible or blocked pairs cannot.

### Milestone 4 — Scoped moderation and operator controls

- Add Moderator role behavior and per-board assignments.
- Implement post-report reasons, one-open-report dedupe, collapsed queue items, claim/triage/resolution/dismissal, and board scope.
- Add move/restore and full content moderation actions with reasons and before/after audit snapshots.
- Add user directory/record, warnings, suspensions, site/board bans, staff notes, and lift/restore actions.
- Activate `board_members` so private boards move from Phase 1's admin-only hold state to member-scoped reads.
- Add staff in-app notifications for new/aging reports within scope.
- Exercise every action by direct HTTP request as Guest, User, scoped Moderator, out-of-scope Moderator, and Admin.

**Exit gate:** A board moderator can fully handle an in-scope report and cannot observe or mutate another board; an Admin can govern site-wide; all actions are immutable in the audit log.

### Milestone 5 — Community identity and account completion

- Add profile activity tabs and correct counters.
- Add follow/unfollow, follower/following counts, privacy gates, and a paginated query-time Following feed.
- Seed the fixed badge catalogue and add idempotent automatic/manual awards.
- Add accepted-answer selection for the thread author and authorized moderators; award the configured bonus and badge once.
- Add all-time Top Contributors with leaderboard opt-out; keep titles/badges/reputation cosmetic.
- Implement OAuth providers through the shared provider abstraction, including state, nonce, PKCE, verified-email handling, explicit collision linking, and last-login-method protection; import the provider avatar on link/login (set `users.avatar_source='oauth'`, cache the provider URL on `oauth_identities.avatar_url`), keeping monogram fallback when absent.
- Add Saved/board organization, active sessions/devices, and approved self-service account-data flows.
- Add privacy-respecting presence and mobile/keyboard polish after the final DOM and routes stabilize.

**Exit gate:** The complete community/account journeys work without changing the permissions model, leaking blocked/private activity, or creating an account-takeover path.

### Milestone 6 — Release candidate and formal closeout

- Run clean-install and Phase-1-upgrade migrations against production-like data volumes.
- Run the full automated suite, worker/cron test matrix, HTTP smoke matrix, and browser suite.
- Test Gate A with JavaScript disabled; test all enhanced paths at supported desktop/mobile widths.
- Exercise queue backlog, SMTP outage, database restart, worker restart, and feature-flag rollback scenarios.
- Review search, feed, unread, notification, and reports queries for indexes/N+1 behavior.
- Resolve all blockers, document accepted low-risk defects, update roadmap/status/evidence, and rehearse restore from backup.
- Accept Gate A, then Gate B; record any formally re-scoped item before opening Phase 3.

**Exit gate:** Product owner signs off on the phase definition of done and the release evidence index is complete.

## 7. Data and migration plan

### 7.1 Migration groups

Apply additive migrations in dependency order and deploy schema before enabling corresponding application code:

1. **Core state:** `board_moderators`, `thread_user`, `blocks` (needed by groups 2–3 for block-aware filtering), the Phase-2 `users`/`boards`/`threads` column additions (enumerated below), and the FULLTEXT indexes (`ft_threads_title`, `ft_posts_body` — P2-06).
2. **Engagement/notification:** `reactions`, `subscriptions`, `notifications`, `email_suppressions`, `email_deliveries`, and the approved idempotency/outbox constraint. _(The Phase-2 admin **announcements/broadcast** feature (scope §3) reuses these — no `announcements` table: `notifications.type='announcement'` for the in-app broadcast/system notice (SCHEMA §7 #13), a `settings.site_announcement` banner key, a pinned thread for a pinned announcement, and `email_deliveries.kind='system'` for the broadcast email.)_
3. **DMs:** `conversations`, `conversation_participants`, `dm_messages`.
4. **Moderation/access:** `reports`, `bans`, `warnings`, `user_notes`, `board_members`, and **`posts.ip` + `sessions.ip`** (`VARBINARY(16)`, the post-IP/login-IP ban-evasion signals — ADMIN §5.4; Admin-only/audited, with the 90-day-retention purge/anonymise job built in Phase 3 — PHASE_3_PLAN P3-05). _(Added 2026-06-26: SCHEMA §7 #10/#11 move both IP columns' build to Phase 2 — the phase that first uses them (P2-08) — since no Phase 1 item built them.)_ _(Fixed 2026-06-26: dropped the "expanded moderation-log fields" clause — the full `moderation_log` table ships in Phase 1, PHASE_1_MIGRATIONS migration 0010; no moderation-log fields remain for Phase 2 to add.)_
5. **Member controls:** `user_preferences`, `user_board_prefs`, `oauth_identities` (incl. `avatar_url` for the imported provider avatar), the `users.avatar_source` column (first set by OAuth avatar-import), and `username_history` where used. (`blocks` moved to group 1 — see above.) _(Added 2026-06-26: `users.avatar_source` + `oauth_identities.avatar_url` give OAuth avatar-import — DECISIONS §5 #4, PHASE_1_MIGRATIONS §4 — its Phase-2 home; `users.avatar_path` and the upload/Gravatar pipeline remain Phase 3.)_
6. **Community:** `follows`, `badges`, `user_badges`, plus `threads.accepted_answer_post_id`.

**Phase-2 `ALTER … ADD` column additions** (deferred by `PHASE_1_MIGRATIONS` §4 — that list is authoritative). On `users`: `title`, `signature`, `website`, `pronouns`, `profile_visibility`, `allow_dms`, `show_presence`, `timezone`, `digest_hour`, `last_daily_digest_at`, `last_seen_at` (+ `idx_users_last_seen`), `avatar_source`. On `boards`: `edit_window_seconds`, `is_archived`. These power cosmetic titles, signatures, privacy controls, the timezone-aware **daily digest** and **presence** (both in the DoD, §2), and the Gate-B **board archive** (scope §3). _(Per `PHASE_1_MIGRATIONS` §1, `boards.edit_window_seconds`/`is_archived` are cheap flags a team may have pre-shipped in Phase 1 — then those two are a no-op here. `posts.ip`/`sessions.ip` are in group 4; `threads.accepted_answer_post_id` in group 6; the FULLTEXT indexes in group 1.)_

Do not create unused later-phase tables merely because they appear in the full consolidated schema. In particular, `plugins` and `api_tokens` (Phase 3) and `reputation_events` (Phase 4) are not required for Phase 2's all-time leaderboard.

### 7.2 Upgrade and backfill rules

- **Unread:** avoid marking every historical thread unread. Use the approved cutover timestamp and lazily create `thread_user` rows.
- **Stars/subscriptions/notifications:** begin empty unless a real legacy source exists; do not synthesize historical notifications or email.
- **Counters:** recompute and compare user post counts and board/thread counters before enabling reputation/feed/profile views.
- **Reputation:** starts from persisted Phase 2 reactions. If reactions existed in another source, import them first and run one idempotent recompute.
- **Badges:** seed the fixed catalogue idempotently. Backfill deterministic historical badges only after the badge rules are frozen; never double-award.
- **Private boards:** remain inaccessible to non-admins until explicit `board_members` rows exist. The migration must not broaden visibility.
- **Account states:** reconcile existing `users.status`/`suspended_until` with the new ban history without lifting or weakening an active restriction.
- **Search:** build indexes in a maintenance-safe way, verify the query plan, then enable the search flag.
- **Email:** no queued notification is sent until sender-domain configuration, suppression handling, and unsubscribe behavior pass smoke tests.

### 7.3 Transactional invariants

The following changes must commit or roll back together:

- Reaction row, rendered count state, reputation counter, and any reaction notification.
- Thread/post insert, denormalized counters, subscription notification rows, and durable email-outbox rows.
- DM insert, conversation timestamp, participant unread state, and DM notification.
- Thread move, source/destination counters, permissions re-evaluation, and moderation audit row.
- Report resolution, moderation action, user/content state change, and audit entry.
- Solved-answer selection, reputation bonus, badge award, solved notification, and audit event where applicable.

## 8. Critical acceptance scenarios

| Scenario | Expected result |
|---|---|
| Existing Phase 1 upgrade | Populated Phase 1 database migrates without data loss; old routes continue to work before flags are enabled |
| Unread cutover | Historical content follows the approved baseline; newly active threads become unread only for the correct users |
| Read isolation | Opening a thread advances only the current user's read position; another user's state is unchanged |
| Star/filter | Star toggles idempotently and appears in the current user's Saved/Starred view only |
| Reaction retry | Repeated or concurrent toggle requests cannot create duplicate rows or drift reputation |
| Self-reaction | Reaction may render if allowed, but it contributes zero reputation to the author |
| Delete/restore | Removing/restoring a reacted post adjusts visible reactions and reputation consistently |
| Subscription precedence | Thread-level Off overrides board Instant; thread Daily overrides board Instant; one effective notification path is chosen |
| Fan-out exclusion | Post author and blocked/ineligible users receive no subscription notification |
| Bell behavior | Unread count, last-20 list, mark-read, mark-all-read, clear, and deep links remain consistent across refreshes |
| Permission change after notification | A user who loses board access cannot follow an old notification link or infer the protected content from its payload |
| Email retry | Worker crash/restart and SMTP retry produce at most one delivered message per idempotency key |
| Daily digest | Digest respects timezone/hour, includes only new eligible activity, and does not send twice or empty |
| Suppression/unsubscribe | Suppressed address is skipped; unsubscribe works without login using a validated signed token; recovery requires confirmation |
| Mention | Up to 10 unique valid users are notified once; blocked, inaccessible, duplicate, or nonexistent mentions do not notify |
| Mention edit | Adding a new mention through an edit notifies only the newly mentioned eligible user; existing mentions are not resent |
| Public search | Guest sees only public, non-deleted, non-pending content with sanitized snippets |
| Private search | Authorized member can find private-board content; unauthorized user receives neither result nor count leakage |
| DM eligibility | Allowed members can message; block, Allow-DMs=None, suspension, ban, or throttle prevents creation/send |
| DM privacy report | Reporting a DM exposes the reported message and necessary context only to authorized staff |
| Report dedupe | A user's second open report for the same post updates/reuses the existing report rather than creating queue spam |
| Scoped reports queue | Board moderator sees and handles only assigned-board items; Admin sees all |
| Thread move | Move preserves posts, updates counters, recalculates access, and writes one complete audit record |
| Private-board membership | Added member can read; removed member immediately loses direct, search, unread, and notification access |
| User moderation | Warn/suspend/ban/lift actions enforce duration/scope, notify as designed, and are fully audited |
| Follow/block | Blocked user cannot follow, DM, or mention the blocker; feed and profile lists respect privacy |
| Following feed | Feed is paginated, topic-anchored, query-time, and excludes inaccessible/deleted/blocked activity |
| Solved answer | Only the OP or authorized moderator can choose a post in the same thread; bonus/badge/notification apply once |
| Leaderboard | Ranking uses canonical reputation, excludes opted-out users, and grants no permissions |
| OAuth return | State/nonce/PKCE failures reject; verified-email collision requires proof of the existing account; banned account cannot bypass status |
| Unlink login method | Removing a provider is blocked when it would leave no usable login method |
| Session revocation | Revoked device cannot continue authenticated requests; current-session and log-out-everywhere behavior are distinct |
| Presence privacy | Hidden user never appears in roster/count/detail returned to another member |
| Suspended/banned state | Every Phase 2 state-changing endpoint is covered by the central gate or an explicitly documented safe exception |
| No-JS operation | Search results, reactions, stars, subscriptions, notification management, DMs, reports, and moderation retain functional form/redirect paths where applicable |

## 9. Test and evidence policy

Every completed workstream must include all applicable evidence:

1. **Unit tests:** parsers, policy objects, precedence rules, reputation math, title/badge rules, OAuth normalization, and worker scheduling.
2. **Repository/service integration tests:** migrations, transactions, counters, fan-out, unread state, search filtering, DMs, reports, and moderation scope.
3. **Concurrency/idempotency tests:** reaction toggles, report dedupe, notification fan-out, email retries, badge awards, solved answers, and session revocation.
4. **HTTP smoke tests:** status, redirects, CSRF, authorization, feature flags, polling endpoints, worker health, and unsubscribe callbacks.
5. **Browser verification:** every visible route, popover/dropdown, mobile change, keyboard flow, and accessibility-sensitive interaction.
6. **No-JavaScript verification:** all core Gate A reads and writes must retain a server-rendered path.
7. **Security/privacy regressions:** direct-request authorization, private-board/search leakage, blocked-user contact, OAuth takeover, XSS in snippets/mentions/DMs, and PII/audit access.
8. **Operational tests:** worker restart, SMTP failure, suppression, cron/digest time zones, queue backlog, database restart, backup restore, and feature-flag rollback.
9. **Performance evidence:** query plans and bounded response behavior for inbox counts, bell polling, FULLTEXT search, feed reads, reports queue, and high-fan-out posts.

Recommended minimum evidence index:

- `tests/Integration/Core/AppThreadStateTest.php`
- `tests/Integration/Core/AppReactionTest.php`
- `tests/Integration/Core/AppSubscriptionTest.php`
- `tests/Integration/Core/AppNotificationTest.php`
- `tests/Integration/Worker/NotificationEmailWorkerTest.php`
- `tests/Integration/Worker/DailyDigestWorkerTest.php`
- `tests/Integration/Core/AppMentionTest.php`
- `tests/Integration/Core/AppSearchTest.php`
- `tests/Integration/Core/AppDirectMessageTest.php`
- `tests/Integration/Core/AppReportQueueTest.php`
- `tests/Integration/Core/AppModeratorScopeTest.php`
- `tests/Integration/Core/AppPrivateBoardMembershipTest.php`
- `tests/Integration/Core/AppCommunityProfileTest.php`
- `tests/Integration/Core/AppFollowFeedTest.php`
- `tests/Integration/Core/AppBadgeSolvedTest.php`
- `tests/Integration/Core/AppOAuthTest.php`
- `tests/Integration/Core/AppUserPreferencesTest.php`
- `tests/Integration/Core/AppSessionManagementTest.php`
- Migration/backfill tests, route-permission inventory tests, `/healthz` plus worker/cron health smoke, and Playwright/browser evidence for corresponding UI paths

These are target evidence names, not claims that the files already exist.

## 10. Observability and operating requirements

Before enabling Gate A in production, expose or log enough information to operate it safely:

- Structured logs with request/correlation IDs for notification fan-out, worker claims, sends, suppression, search errors, DM/report actions, and OAuth callbacks without logging tokens or private message bodies.
- Health checks for web/database plus a worker heartbeat and last-success timestamps for instant queue draining and digest evaluation.
- Queue metrics: queued, oldest age, sent, failed, suppressed, and retry count.
- Product counters: unread/bell poll errors, search latency/no-result rate, DM send failures, open/aging reports, and fan-out recipient counts.
- Security counters: rejected CSRF, denied private-content reads, rate-limit hits, OAuth callback failures, and block/privacy denials.
- A documented procedure to pause email, disable a feature flag, drain/replay safe queue items, recompute counters, rebuild search indexes, and restore from backup.

Targets should be set after an initial baseline, consistent with the product design. At minimum, track subscription adoption, notification click-through, email failure/suppression, search result clicks/no-result queries, DM reply rate, reports per 1,000 posts, median report-resolution time, reaction participation, follow adoption, and solved-thread rate.

## 11. Risks and controls

| Risk | Control |
|---|---|
| Phase 2 becomes an unshippable bundle | Enforce Gate A/Gate B and require written approval for any scope movement |
| Historical threads create an unread flood | Use a documented cutover timestamp and lazy read-state rows |
| Notification or email duplicates | Durable unique idempotency key, transactional outbox behavior, retry tests, and worker claim locking |
| High-fan-out post slows the web request | Keep fan-out inserts bounded/batched and move SMTP work to the worker; measure transaction duration |
| Email damages sender reputation | Domain readiness gate, suppression cascade, unsubscribe, bounce/complaint handling, and delivery monitoring |
| Search leaks private/deleted content | Apply the canonical read policy before result serialization and again on destination routes |
| Notification payload leaks revoked access | Store minimal identifiers, re-check access on read/click, and render a generic unavailable state |
| DM feature creates harassment channel | Allow-DMs controls, blocks, rate limits, new-user throttle, report-message path, and account-state gates |
| Moderators act outside their boards | Central capability/scope resolver and direct-request tests for every action |
| Report queue reveals private DMs | Show only the reported message and minimal context; no general DM browsing capability |
| Denormalized counters drift | Transactional updates, periodic reconciliation, repair commands, and invariant tests |
| Reputation is gamed or becomes permission-bearing | No self-rep, rate limits/abuse signals, derived counters, cosmetic-only policy, and no capability checks against reputation |
| OAuth links the wrong account | PKCE/state/nonce, verified-email handling, explicit collision proof, stable provider IDs, and no silent auto-merge |
| Block/privacy rules diverge by feature | One shared interaction/privacy policy consumed by DMs, mentions, follows, feed, notifications, and profiles |
| FULLTEXT migration locks a busy database | Test on production-like volume, use a maintenance-safe migration, and keep search feature-flagged until complete |
| Polling overloads a single VPS | Combined/bounded endpoints, sensible intervals, conditional responses, indexes, and rate/latency monitoring |
| JavaScript becomes mandatory | No-JS acceptance tests and PRG forms for every Gate A action |
| Schema docs and deployed migrations drift | Migration-to-`SCHEMA.md` review in P2-00 and schema snapshot checks in CI |

## 12. Staged release and rollback

Recommended enablement order:

1. Deploy additive migrations and dark backend code with all Phase 2 flags off.
2. Enable read/star state for staff accounts, then all members.
3. Enable reactions and validate reputation/counter reconciliation.
4. Enable in-app subscriptions/notifications and short-polling; keep email paused.
5. Start the worker with test recipients, then enable instant email, then daily digests.
6. Enable mentions, search, DMs, and reports/moderation in separate flag changes.
7. Accept Gate A before enabling broader social/account features.
8. Enable follows/feed, badges/solved, OAuth/account controls, and presence incrementally for Gate B.

Rollback rules:

- Disable the affected feature flag before changing data.
- Pause email workers before any notification rollback; preserve queued rows for inspection rather than deleting them.
- Keep migrations additive during the phase; do not drop Phase 1 columns or data in the same release that introduces replacements.
- Roll back application code only to a version that tolerates the new tables/columns.
- Re-run counter repair and permission smoke tests after rollback.
- Restore from backup only for proven corruption; feature disablement is the first response for logic defects.

## 13. Release checklist

### Gate A

- [ ] Scope, deferrals, and evidence map approved.
- [ ] Phase 1 regression baseline remains green.
- [ ] Clean-install and populated-upgrade migrations pass.
- [ ] Email idempotency/outbox schema gap resolved.
- [ ] Unread cutover policy implemented and verified.
- [ ] Reactions, stars, unread, subscriptions, notifications, mentions, search, DMs, reports, scoped moderation, and minimal reputation pass acceptance.
- [ ] Notification/privacy/block/DM settings pass their server-side enforcement matrix.
- [ ] Worker, instant email, digest, suppression, and unsubscribe paths pass operational tests.
- [ ] Search/private-board/notification deep-link leakage tests pass.
- [ ] Guest, User, suspended, banned, scoped Moderator, out-of-scope Moderator, and Admin matrices pass.
- [ ] Gate A paths pass without JavaScript and at desktop/mobile widths.
- [ ] Counter-repair and queue-operating procedures are documented.
- [ ] No critical/high defects remain.
- [ ] Backup, staged rollout, pause-worker, and rollback rehearsals pass.
- [ ] README, changelog, schema, and completion evidence are updated.
- [ ] Gate A product-owner acceptance recorded.

### Gate B and phase close

- [ ] Follows/feed, badges, solved answers, activity profiles, and all-time leaderboard pass privacy and idempotency tests.
- [ ] OAuth provider, collision, linking/unlinking, and banned-account tests pass.
- [ ] Saved/board preferences and session/device controls pass.
- [ ] Approved export/delete behavior passes policy and data-integrity review, or is formally re-scoped.
- [ ] Presence and mobile/keyboard/accessibility browser evidence is complete.
- [ ] Email delivery visibility/test/recovery tools pass, or are formally re-scoped.
- [ ] All Gate B deferrals are recorded in the roadmap rather than silently omitted.
- [ ] Full Phase 2 evidence index and product-owner closeout are recorded.

## 14. Phase handoff

After Phase 2 closes, Phase 3 should concentrate on polish and scale rather than finishing hidden Phase 2 obligations: richer settings/preferences, composer uploads and advanced formatting, anti-spam automation, performance/caching, accessibility and SEO audits, admin branding/theming, product onboarding, and later integration/plugin work.

Before handoff, carry forward measured baselines for notification delivery, search quality/latency, inbox/read adoption, DM usage, report-resolution health, reputation/follow participation, and the highest-cost queries. Those baselines determine which Phase 3 performance and UX work is evidence-led rather than speculative.

## 15. Source references

- `README.md` — current live status, top-level Phase 2 community-essentials scope, stack, and evidence policy.
- `DESIGN.md` §§6.7–6.16, 8–13 — engagement, DMs, search, notifications, moderation, profiles, presence, schema deltas, architecture, permissions, non-functional requirements, metrics, and phasing.
- `DECISIONS.md` — authoritative choices for unread state, polling, search, DMs, notification fan-out, email/worker hosting, roles, OAuth providers, and reputation.
- `SCHEMA.md` §§1–7 — authoritative table shapes, Phase 2 build cut, and reconciliation decisions.
- `ADMIN.md` §§2–5, 7, 10–11 — scoped capabilities, reports queue, content/user moderation, private boards, user management, notification email operations, and admin phasing.
- `USER.md` §§2–8 — OAuth/linking, member settings, notification/privacy controls, sessions, board organization, profiles, data flows, and account phasing.
- `COMPOSER.md` §§6, 16–17 — mention behavior, canonical Markdown integration, composer safety, and rich-composer phase boundary.
- `COMMUNITY.md` §§2–14 — reaction-derived reputation, follows/feed, badges, solved answers, leaderboards, anti-abuse stance, data model, and community phasing.
