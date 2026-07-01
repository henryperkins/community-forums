# RetroBoards — Consolidated Database Schema

**Status:** v1.26 · **Owner:** Henry (lakefrontdigital.io) · **Last updated:** 2026-07-01
**This file is the single authoritative reference for the full database schema.** It consolidates the DDL that is otherwise scattered across [DESIGN.md](DESIGN.md) §8, [USER.md](USER.md) §7, [ADMIN.md](ADMIN.md) §10, [COMPOSER.md](COMPOSER.md) §16, and [COMMUNITY.md](COMMUNITY.md) §11 into one place, with each doc's *"additions to existing tables"* folded directly into the table definition.

Those source docs remain the narrative source of truth for *why* each field exists; this file is the source of truth for the *final shape* of each table. When the two disagree, the reconciliations in §7 below are authoritative (they were applied to fix genuine drift between the docs).

## Conventions

- **Engine / charset:** InnoDB, `utf8mb4` everywhere.
- **Keys:** `BIGINT UNSIGNED` surrogate PKs; `DATETIME` stored UTC.
- **Soft deletes** where history matters (`is_deleted`, `lifted_at`, etc.).
- **Denormalised counters** (`post_count`, `reply_count`, `thread_count`, `last_post_*`, `reputation`) are maintained transactionally on write (DESIGN.md §8.1).
- Inline `-- comments` mark **provenance** (which doc contributed a column) and **phase**.
- Lengths/types are sensible starting points, not immutable (DESIGN.md §8.1).

## Table index

| # | Table | Domain | Phase | Source doc |
|---|---|---|---|---|
| 1 | `users` | Core | 1 | DESIGN §8.2 (+USER §7.2, ADMIN §10.2, DESIGN §8.3) |
| 2 | `sessions` | Core / auth | 1 | Canonical DDL consolidated in §1 (origin: the auth-design slice `2026-06-20-auth-design.md` §7.1, a planned/historical artifact — the DDL now lives here) |
| 3 | `categories` | Core | 1 | DESIGN §8.2 |
| 4 | `boards` | Core | 1 | DESIGN §8.2 (+ADMIN §10.2) |
| 5 | `board_slug_history` | Core | 1 | Admin Console slug redirects |
| 6 | `board_moderators` | Core / mod | 2 | DESIGN §8.2 |
| 7 | `threads` | Core | 1 | DESIGN §8.2 (+DESIGN §8.3, ADMIN §10.2, COMMUNITY §11) |
| 8 | `posts` | Core | 1 | DESIGN §8.2 (+ADMIN §10.2) |
| 9 | `reactions` | Engagement | 2 | DESIGN §8.2 |
| 10 | `thread_user` | Engagement | 1–2 | DESIGN §8.2 (is_subscribed removed — §7.4) |
| 11 | `subscriptions` | Notifications | 2 | DESIGN §8.3 |
| 12 | `notifications` | Notifications | 2 | DESIGN §8.2 (enum reconciled — §7.3) |
| 13 | `conversations` | DMs | 2 | DESIGN §8.2 |
| 14 | `conversation_participants` | DMs | 2 | DESIGN §8.2 |
| 15 | `dm_messages` | DMs | 2 | DESIGN §8.2 |
| 16 | `reports` | Moderation | 2 | DESIGN §8.2 (+ADMIN §10.2; status/reason — §7.5) |
| 17 | `moderation_log` | Moderation | 1 | DESIGN §8.2 (+ADMIN §10.2; actor — §7.6) |
| 18 | `oauth_identities` | Accounts | 2 | USER §7.1 |
| 19 | `user_preferences` | Accounts | 2 | USER §7.1 |
| 20 | `user_board_prefs` | Accounts | 2 | USER §7.1 |
| 21 | `blocks` | Accounts | 2 | USER §7.1 |
| 22 | `verifications` | Accounts / auth | 1 | USER §7.1 |
| 23 | `username_history` | Accounts | 1–2 | USER §7.1 |
| 24 | `settings` | Admin | 1 | ADMIN §10.1 |
| 25 | `bans` | Admin / mod | 2 | ADMIN §10.1 |
| 26 | `warnings` | Admin / mod | 2 | ADMIN §10.1 |
| 27 | `user_notes` | Admin / mod | 2 | ADMIN §10.1 |
| 28 | `board_members` | Admin | 2 | ADMIN §10.1 |
| 29 | `plugins` | Admin / integrations | 3 | ADMIN §10.1 |
| 30 | `webhooks` | Admin / integrations | 3 | ADMIN §10.1 |
| 31 | `api_tokens` | Admin / integrations | 5 | ADMIN §10.1 |
| 32 | `email_suppressions` | Email | 2 | ADMIN §10.1 |
| 33 | `email_deliveries` | Email | 2–3 | ADMIN §10.1 |
| 34 | `attachments` | Composer | 3 | COMPOSER §16.2 |
| 35 | `follows` | Community | 2 (P1 of community) | COMMUNITY §11 |
| 36 | `badges` | Community | 2 (P1 of community) | COMMUNITY §11 |
| 37 | `user_badges` | Community | 2 (P1 of community) | COMMUNITY §11 |
| 38 | `reputation_events` | Community | 4 | COMMUNITY §11 |
| 39 | `thread_status_history` | Workflow | 4 | PHASE_4_PLAN §8 |
| 40 | `thread_assignments` | Workflow | 4 | PHASE_4_PLAN §8 |
| 41 | `thread_assignment_history` | Workflow | 4 | PHASE_4_PLAN §8 |
| 42 | `conversation_events` | DMs | 4 | PHASE_4_PLAN §8 |
| 43 | `tags` | Community | 4 | PHASE_4_PLAN §8 |
| 44 | `tag_aliases` | Community | 4 | PHASE_4_PLAN §8 |
| 45 | `thread_tags` | Community | 4 | PHASE_4_PLAN §8 |
| 46 | `badge_rules` | Community | 4 | PHASE_4_PLAN §8 |
| 47 | `badge_award_history` | Community | 4 | PHASE_4_PLAN §8 |
| 48 | `thread_summaries` | Knowledge | 4 | PHASE_4_PLAN §8 |
| 49 | `thread_summary_sources` | Knowledge | 4 | PHASE_4_PLAN §8 |
| 50 | `related_threads` | Knowledge | 4 | PHASE_4_PLAN §8 |
| 51 | `post_revisions` | Knowledge | 4 | PHASE_4_PLAN §8 |
| 52 | `content_references` | Composer / Knowledge | 4 | PHASE_4_PLAN §8 |
| 53 | `thread_operations` | Moderation / Knowledge | 4 | PHASE_4_PLAN §8 |
| 54 | `thread_redirects` | Moderation / Knowledge | 4 | PHASE_4_PLAN §8 |
| 55 | `package_registries` | Ecosystem | 5 | PHASE_5_PLAN §8.2 #1 |
| 56 | `registry_trust_keys` | Ecosystem | 5 | PHASE_5_PLAN §8.2 #1 |
| 57 | `package_publishers` | Ecosystem | 5 | PHASE_5_PLAN §8.2 #2 |
| 58 | `packages` | Ecosystem | 5 | PHASE_5_PLAN §8.2 #2 |
| 59 | `package_releases` | Ecosystem | 5 | PHASE_5_PLAN §8.2 #2 |
| 60 | `installed_packages` | Ecosystem | 5 | PHASE_5_PLAN §8.2 #2/#3 |
| 61 | `installed_package_permissions` | Ecosystem | 5 | PHASE_5_PLAN §8.2 #4 |
| 62 | `package_history` | Ecosystem | 5 | PHASE_5_PLAN §8.2 #3 |
| 63 | `package_advisories` | Ecosystem | 5 | PHASE_5_PLAN §8.2 #5 |
| 64 | `local_package_blocks` | Ecosystem | 5 | PHASE_5_PLAN §3 (local emergency control) |
| 65 | `capabilities` | Governance | 5 | PHASE_5_PLAN §8.2 #8 |
| 66 | `roles` | Governance | 5 | PHASE_5_PLAN §8.2 #8 |
| 67 | `role_capabilities` | Governance | 5 | PHASE_5_PLAN §8.2 #8 |
| 68 | `role_assignments` | Governance | 5 | PHASE_5_PLAN §8.2 #9 |
| 69 | `role_assignment_history` | Governance | 5 | PHASE_5_PLAN §8.2 #9/#20 |
| 70 | `protected_owners` | Governance | 5 | PHASE_5_PLAN §8.2 #13 |
| 71 | `owner_transfer_history` | Governance | 5 | PHASE_5_PLAN §8.2 #13 |
| 72 | `webauthn_credentials` | Identity | 5 | PHASE_5_PLAN §8.2 #14 |
| 73 | `webauthn_challenges` | Identity | 5 | PHASE_5_PLAN §8.2 #14 |
| 74 | `identity_providers` | Identity | 5 | PHASE_5_PLAN §8.2 #15 |
| 75 | `provider_aliases` | Identity | 5 | PHASE_5_PLAN §8.2 #15 |
| 76 | `invitations` | Identity | 5 | PHASE_5_PLAN §8.2 #16 |
| 77 | `invitation_redemptions` | Identity | 5 | PHASE_5_PLAN §8.2 #16 |
| 78 | `user_totp_credentials` | Identity / auth | 5 | ADR 0004 B1 / PHASE_3_PLAN P3-12 carryover |
| 79 | `user_recovery_codes` | Identity / auth | 5 | ADR 0004 B1 / PHASE_3_PLAN P3-12 carryover |
| 80 | `mfa_login_challenges` | Identity / auth | 5 | ADR 0004 B1 / PHASE_3_PLAN P3-12 carryover |
| 81 | `service_secrets` | Integrations / secrets | 5 | B2 service-secret registry design |
| 82 | `service_secret_versions` | Integrations / secrets | 5 | B2 service-secret registry design |
| 83 | `webhook_deliveries` | Integrations / webhooks | 5 | B2 webhook delivery design |
| 84 | `link_previews` | Composer / previews | 4 | ADR 0003 carryover / migration 0058 |
| 85 | `polls` | Composer / engagement | 4 | ADR 0003 carryover / migration 0058 |
| 86 | `poll_options` | Composer / engagement | 4 | ADR 0003 carryover / migration 0058 |
| 87 | `poll_votes` | Composer / engagement | 4 | ADR 0003 carryover / migration 0058 |
| 88 | `custom_emoji` | Composer / reactions | 4 | ADR 0003 carryover / migration 0058 |
| 89 | `board_folders` | Accounts / boards | 4 | ADR 0003 carryover / migration 0058 |
| 90 | `board_folder_boards` | Accounts / boards | 4 | ADR 0003 carryover / migration 0058 |
| 91 | `saved_feed_filters` | Accounts / feeds | 4 | ADR 0003 carryover / migration 0058 |
| 92 | `since_last_read_context` | Reading context | 4 | ADR 0003 carryover / migration 0058 |
| 93 | `account_deletion_requests` | Accounts / lifecycle | 2 | ADR 0006 carryover / migration 0059 |
| 94 | `moderation_appeals` | Admin / moderation | 2 | ADR 0007 carryover / migration 0060 |
| 95 | `moderation_appeal_events` | Admin / moderation | 2 | ADR 0007 carryover / migration 0060 |
| 96 | `email_domain_status` | Admin / email | 2 | P2-04 carryover / migration 0061 |
| 97 | `thread_bookmark_folders` | Accounts / bookmarks | 4 | ADR 0003 carryover / migration 0062 |
| 98 | `thread_bookmark_folder_threads` | Accounts / bookmarks | 4 | ADR 0003 carryover / migration 0062 |
| 99 | `user_profile_fields` | Accounts / profile | 4 | ADR 0003 carryover / migration 0062 |
| 100 | `server_drafts` | Composer / drafts | 3 | ADR 0010 pull-forward / migration 0064 |
| 101 | `server_extension_handlers` | Ecosystem / runtime | 5 | ADR 0011 Gate B pull-forward / migration 0065 |
| 102 | `server_extension_jobs` | Ecosystem / runtime | 5 | ADR 0011 Gate B pull-forward / migration 0065 |
| 103 | `server_extension_runs` | Ecosystem / runtime | 5 | ADR 0011 Gate B pull-forward / migration 0065 |
| 104 | `server_extension_kv` | Ecosystem / runtime | 5 | ADR 0011 Gate B pull-forward / migration 0065 |

> "Phase" reflects the seven-phase delivery plan (PHASE_1 through PHASE_7), which subdivides the DESIGN.md §13 roadmap. See §6 for the full per-phase build cut and the crosswalk to DESIGN §13.
>
> Tables 55–77 are the **Phase 5 foundation** (migrations `0049`–`0053`): additive, **deploy-dark**, and **inert** — created so no Gate A feature depends on an undocumented shape (Milestone 1), but read/written by no application code until each subsystem's flag is enabled after its Milestone-0 trust approvals (see §5A and `PHASE_5_STATUS.md`).
>
> Tables 78–80 are the **Gate A TOTP/recovery prerequisite** (migration `0054`):
> additive, opt-in, and active only for accounts that enroll. They resolve ADR
> 0004 conflict B1 before passkey enforcement; ordinary users are not required to
> use MFA by default.
>
> Tables 81–82 are the **B2 encrypted service-secret registry** (migration `0055`):
> additive and deploy-dark. They are consumed only through `SecretVault` while the
> `service_secrets` flag controls write/rotation availability. Webhook endpoint
> config consumes it; provider and remote-app consumers remain deferred.
>
> Table 83 and the reconciled `webhooks` shape are the **B2 webhook delivery
> engine** (migration `0057`): deploy-dark behind `webhooks`, with endpoint
> signing secrets stored as SecretVault references and a durable retry/dead-letter
> ledger.
>
> The **B2 first-party hook registry** adds no schema: it is a code-only,
> deploy-dark producer/listener seam behind `first_party_hooks`. Its first
> listener maps public-board domain events into the existing webhook delivery
> ledger; `ping` remains an admin test event.
>
> Tables 84–92 and the reconciled `attachments`/`users`/`reactions` deltas are
> the **Phase 4 carryover foundation** (migration `0058`): additive,
> feature-flag-controlled shapes for previews, expanded files, polls, custom
> emoji, personal board organization, saved feeds, profile moderation, and
> since-last-read context.
>
> Tables 93–99 and the reconciled `users.status` / `email_deliveries.payload`
> deltas are the **Phase 2–4 carryover completion** (migrations `0059`–`0062`):
> additive account-lifecycle, moderation-appeals, email-domain, and
> bookmark/profile-field shapes (see §4B). `account_lifecycle`, `appeals`, and
> `custom_profile_fields` default dark; `bookmark_folders` graduated to
> default-on on 2026-07-01.
>
> Migration `0063` adds email retry/backoff columns to `email_deliveries`.
> Table 100 is deploy-dark server draft sync (`0064`, `server_drafts` flag).
> Tables 101–104 are the deploy-dark Phase 5 Gate B server-extension runtime
> evidence harness (`0065`, `server_extensions` flag).

---

## 1. Core forum (DESIGN.md §8)

```sql
-- USERS ---------------------------------------------------------------
-- Consolidated: DESIGN §8.2 base + DESIGN §8.3 (onboarding/digest) + USER §7.2 (profile/privacy/avatar)
-- + ADMIN §10.2 (suspended_until). Role value 'user' and NULLable password_hash per §7.1/§7.2 reconciliations.
CREATE TABLE users (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username             VARCHAR(32)     NOT NULL,
  email                VARCHAR(255)    NOT NULL,
  password_hash        VARCHAR(255)    NULL,                 -- Argon2id/bcrypt. NULLable: OAuth-only until "set password" (USER §2.4)
  display_name         VARCHAR(64)     NULL,
  role                 ENUM('user','moderator','admin') NOT NULL DEFAULT 'user', -- 'user' (was 'member') per DECISIONS §4
  title                VARCHAR(64)     NULL,                 -- cosmetic rank (COMMUNITY §8); never gates anything
  signature            TEXT            NULL,
  location             VARCHAR(64)     NULL,
  bio                  TEXT            NULL,                 -- USER §7.2
  website              VARCHAR(255)    NULL,                 -- USER §7.2 (rendered nofollow)
  pronouns             VARCHAR(32)     NULL,                 -- USER §7.2
  avatar_path          VARCHAR(255)    NULL,                 -- USER §7.2; ADDED in Phase 3 (uploads/Gravatar pipeline — PHASE_3_PLAN P3-12), not Phase 1
  avatar_source        ENUM('monogram','upload','gravatar','oauth') NOT NULL DEFAULT 'monogram', -- USER §7.2 (fallback chain); ADDED in Phase 2 (first set by OAuth avatar-import — PHASE_2_PLAN P2-10), not Phase 1
  post_count           INT UNSIGNED    NOT NULL DEFAULT 0,   -- denormalised
  reputation           INT             NOT NULL DEFAULT 0,   -- Σ reactions received (+ solved bonus) — COMMUNITY §2
  profile_visibility   ENUM('public','members') NOT NULL DEFAULT 'public',          -- USER §7.2 (server-enforced)
  allow_dms            ENUM('everyone','members','none') NOT NULL DEFAULT 'members', -- USER §7.2; default 'members' per DECISIONS §5 #8
  show_presence        TINYINT(1)      NOT NULL DEFAULT 1,   -- USER §7.2
  status               ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
  suspended_until      DATETIME        NULL,                 -- ADMIN §10.2 (auto-restore on expiry)
  email_verified_at    DATETIME        NULL,
  onboarded_at         DATETIME        NULL,                 -- DESIGN §8.3 (product tour, cross-device); ADDED in Phase 3 (PHASE_3_PLAN §8.1 / P3-11), not Phase 1
  timezone             VARCHAR(64)     NULL,                 -- DESIGN §8.3 (daily digests); added in Phase 2 (digest worker)
  digest_hour          TINYINT         NULL,                 -- DESIGN §8.3 (0–23 local); added in Phase 2 (digests)
  last_daily_digest_at DATETIME        NULL,                 -- DESIGN §8.3 (digest watermark: never duplicate/empty); added in Phase 2
  created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at         DATETIME        NULL,                 -- presence heartbeat (DESIGN §6.15)
  signature_removed_at DATETIME        NULL,                 -- Phase 4 carryover profile moderation (migration 0058)
  signature_removed_by BIGINT UNSIGNED NULL,                 -- FK users(id) ON DELETE SET NULL
  avatar_removed_at    DATETIME        NULL,                 -- Phase 4 carryover profile moderation (migration 0058)
  avatar_removed_by    BIGINT UNSIGNED NULL,                 -- FK users(id) ON DELETE SET NULL
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status_id (role, status, id),
  KEY idx_users_last_seen (last_seen_at),
  KEY idx_users_signature_removed_by (signature_removed_by),
  KEY idx_users_avatar_removed_by (avatar_removed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SESSIONS ------------------------------------------------------------
-- Canonical per the auth design (2026-06-20-auth-design.md §7.1). Opaque-token sessions:
-- id = SHA-256 of the raw cookie token; csrf_secret backs per-session CSRF; revoked_at powers logout/revocation.
CREATE TABLE sessions (
  id           CHAR(64)        NOT NULL,                     -- SHA-256 hash of the raw cookie token
  user_id      BIGINT UNSIGNED NOT NULL,
  csrf_secret  CHAR(64)        NOT NULL,                     -- per-session CSRF secret
  ip           VARBINARY(16)   NULL,                         -- login IP (ADMIN §5.4 ban-evasion); ADDED in the Phase 2 migration (first used by P2-08), not Phase 1; 90-day retention, Admin-only audited (purge job P3)
  user_agent   VARCHAR(255)    NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME        NULL,
  expires_at   DATETIME        NOT NULL,
  revoked_at   DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_active (expires_at, revoked_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CATEGORIES & BOARDS -------------------------------------------------
CREATE TABLE categories (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(64)     NOT NULL,
  position    INT             NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- NOTE: ADMIN §4.1 mentions a "default-collapsed" flag for categories; no column is specced. See §8.

-- Consolidated: DESIGN §8.2 base + ADMIN §10.2 (visibility/anon/approval/edit_window). post_min_role value 'user'.
CREATE TABLE boards (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id         BIGINT UNSIGNED NOT NULL,
  slug                VARCHAR(64)     NOT NULL,              -- the #channel handle
  name                VARCHAR(80)     NOT NULL,
  description         VARCHAR(255)    NULL,
  position            INT             NOT NULL DEFAULT 0,
  post_min_role       ENUM('user','moderator','admin') NOT NULL DEFAULT 'user', -- min role to post (DECISIONS §4)
  visibility          ENUM('public','hidden','private') NOT NULL DEFAULT 'public', -- ADMIN §10.2 / §4.3
  allow_anonymous     TINYINT(1)      NOT NULL DEFAULT 0,   -- ADMIN §10.2 (masked-identity posting)
  require_approval    TINYINT(1)      NOT NULL DEFAULT 0,   -- ADMIN §10.2 (hold queue)
  edit_window_seconds INT             NOT NULL DEFAULT 0,   -- ADMIN §10.2 (0 = unlimited)
  is_archived         TINYINT(1)      NOT NULL DEFAULT 0,
  thread_count        INT UNSIGNED    NOT NULL DEFAULT 0,   -- denormalised
  post_count          INT UNSIGNED    NOT NULL DEFAULT 0,   -- denormalised
  last_thread_id      BIGINT UNSIGNED NULL,
  last_post_at        DATETIME        NULL,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_boards_slug (slug),
  KEY idx_boards_cat_pos (category_id, position),
  CONSTRAINT fk_boards_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE board_slug_history (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id   BIGINT UNSIGNED NOT NULL,
  old_slug   VARCHAR(64)     NOT NULL,
  changed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_board_slug_history_old_slug (old_slug),
  KEY idx_board_slug_history_board (board_id),
  CONSTRAINT fk_board_slug_history_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE board_moderators (
  board_id  BIGINT UNSIGNED NOT NULL,
  user_id   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (board_id, user_id),
  CONSTRAINT fk_bmod_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  CONSTRAINT fk_bmod_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- THREADS & POSTS -----------------------------------------------------
-- Consolidated: DESIGN §8.2 base + DESIGN §8.3 (ft_threads_title) + ADMIN §10.2 (is_pending) + COMMUNITY §11 (accepted_answer_post_id).
CREATE TABLE threads (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id                BIGINT UNSIGNED NOT NULL,
  user_id                 BIGINT UNSIGNED NOT NULL,          -- author of the OP
  title                   VARCHAR(160)    NOT NULL,
  slug                    VARCHAR(180)    NOT NULL,
  is_pinned               TINYINT(1)      NOT NULL DEFAULT 0,
  is_locked               TINYINT(1)      NOT NULL DEFAULT 0,
  is_deleted              TINYINT(1)      NOT NULL DEFAULT 0,
  is_pending              TINYINT(1)      NOT NULL DEFAULT 0,-- ADMIN §10.2 (new-thread approval hold)
  accepted_answer_post_id BIGINT UNSIGNED NULL,             -- COMMUNITY §11 ("Solved" / DESIGN §6.18)
  reply_count             INT UNSIGNED    NOT NULL DEFAULT 0,-- denormalised (excludes OP)
  view_count              INT UNSIGNED    NOT NULL DEFAULT 0,
  last_post_id            BIGINT UNSIGNED NULL,
  last_post_user_id       BIGINT UNSIGNED NULL,
  last_post_at            DATETIME        NULL,
  created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_threads_inbox (board_id, is_pinned DESC, last_post_at DESC),
  KEY idx_threads_author (user_id),
  FULLTEXT KEY ft_threads_title (title),                    -- DESIGN §8.3 (global search §6.9); index BUILT in Phase 2 (search, P2-06), not Phase 1
  CONSTRAINT fk_threads_board FOREIGN KEY (board_id) REFERENCES boards(id),
  CONSTRAINT fk_threads_user  FOREIGN KEY (user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- FORESHADOWED (DESIGN §6.18, not yet committed): topic_status ENUM (Solved/Needs Answer/Decision/…). See §8.

-- Consolidated: DESIGN §8.2 base + ADMIN §10.2 (is_anonymous, is_pending).
CREATE TABLE posts (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id      BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,                  -- always the REAL author (even when is_anonymous)
  parent_post_id BIGINT UNSIGNED NULL,                      -- quote/reply target (DESIGN §6.4)
  body           MEDIUMTEXT      NOT NULL,                  -- canonical Markdown (COMPOSER.md)
  body_html      MEDIUMTEXT      NULL,                      -- cached sanitised render (DESIGN §9.5)
  is_op          TINYINT(1)      NOT NULL DEFAULT 0,
  is_anonymous   TINYINT(1)      NOT NULL DEFAULT 0,        -- ADMIN §10.2 (masked PUBLIC render only; mods reveal — audited)
  is_pending     TINYINT(1)      NOT NULL DEFAULT 0,        -- ADMIN §10.2 (approval hold)
  is_deleted     TINYINT(1)      NOT NULL DEFAULT 0,        -- soft delete
  ip             VARBINARY(16)   NULL,                      -- author IP at post time (DECISIONS §4 #5; ban-evasion ADMIN §5.4); ADDED in the Phase 2 migration (first used by P2-08), not Phase 1; 90-day retention, Admin-only audited
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at      DATETIME        NULL,
  edited_by      BIGINT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_posts_thread (thread_id, created_at),
  KEY idx_posts_author (user_id),
  FULLTEXT KEY ft_posts_body (body),                        -- search (DESIGN §6.9); index BUILT in Phase 2 (P2-06), not Phase 1
  CONSTRAINT fk_posts_thread FOREIGN KEY (thread_id) REFERENCES threads(id),
  CONSTRAINT fk_posts_user   FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ENGAGEMENT ----------------------------------------------------------
CREATE TABLE reactions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id    BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  emoji      VARCHAR(48)     NOT NULL,                      -- widened by migration 0058 for custom emoji shortcodes
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reaction (post_id, user_id, emoji),         -- one per (user, post, emoji)
  CONSTRAINT fk_react_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_react_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- NOTE: self-reactions are excluded from reputation in app logic, not by a DB constraint (COMMUNITY §2.1/§10).

-- Per-user thread state: read position + star. (is_subscribed REMOVED — superseded by `subscriptions`; see §7.4.)
CREATE TABLE thread_user (
  user_id           BIGINT UNSIGNED NOT NULL,
  thread_id         BIGINT UNSIGNED NOT NULL,
  last_read_post_id BIGINT UNSIGNED NULL,                   -- unread = thread.last_post_id > this
  is_starred        TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, thread_id),
  KEY idx_tu_starred (user_id, is_starred),
  CONSTRAINT fk_tu_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_tu_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- FORESHADOWED (DESIGN §6.18, not yet committed): snoozed_until DATETIME, assigned_to BIGINT UNSIGNED. See §8.

-- SUBSCRIPTIONS (DESIGN §8.3) — supersedes thread_user.is_subscribed; per-channel + frequency.
CREATE TABLE subscriptions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  target_type    ENUM('board','thread') NOT NULL,
  target_id      BIGINT UNSIGNED NOT NULL,
  email_enabled  TINYINT(1) NOT NULL DEFAULT 1,
  in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
  frequency      ENUM('instant','daily','off') NOT NULL DEFAULT 'instant', -- a thread setting overrides its board
  created_at     DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sub (user_id, target_type, target_id),
  KEY idx_sub_target (target_type, target_id),
  CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTIFICATIONS (DESIGN §8.2) — enum reconciled to the full union; column 'type'; read flag 'is_read' (see §7.3).
CREATE TABLE notifications (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,                 -- recipient
  type            ENUM('reply','mention','reaction','dm','mod',
                       'new_post','new_thread','follow','badge','solved',
                       'announcement') NOT NULL,                 -- 'announcement' = admin broadcast / "System / announcement" (ADMIN §7.4 Phase 2; USER §4.6); see §7 #13
  actor_id        BIGINT UNSIGNED NULL,
  thread_id       BIGINT UNSIGNED NULL,
  post_id         BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  is_read         TINYINT(1)      NOT NULL DEFAULT 0,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DIRECT MESSAGES -----------------------------------------------------
CREATE TABLE conversations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_message_at DATETIME        NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE conversation_participants (
  conversation_id      BIGINT UNSIGNED NOT NULL,
  user_id              BIGINT UNSIGNED NOT NULL,
  last_read_message_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (conversation_id, user_id),
  CONSTRAINT fk_cp_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_user FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dm_messages (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  body            TEXT            NOT NULL,
  body_html       MEDIUMTEXT      NULL,                       -- cached sanitised render, DM parity with posts.body_html (§7 #14); Phase 2
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dm_conv (conversation_id, created_at),
  CONSTRAINT fk_dm_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_dm_user FOREIGN KEY (user_id)         REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- REPORTS & MODERATION LOG --------------------------------------------
-- Consolidated: DESIGN §8.2 base + ADMIN §10.2 (assigned_to, reason_code) + 'triaged' state (ADMIN §3.2). See §7.5.
CREATE TABLE reports (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id BIGINT UNSIGNED NOT NULL,
  post_id       BIGINT UNSIGNED NULL,                          -- target post (NULL when a DM report); §7 #16
  dm_message_id BIGINT UNSIGNED NULL,                          -- target DM message (NULL when a post report); §7 #16; Phase 2 (P2-07)
  reason_code ENUM('spam','harassment','off_topic','nsfw','illegal','other') NULL, -- ADMIN §3.1 reasons (derived enum)
  reason      VARCHAR(255)    NULL,                         -- free-text (required for 'other')
  status      ENUM('open','triaged','resolved','dismissed') NOT NULL DEFAULT 'open', -- 'triaged' = claimed (ADMIN §3.2)
  assigned_to BIGINT UNSIGNED NULL,                         -- ADMIN §10.2 (queue claim)
  handled_by  BIGINT UNSIGNED NULL,
  notify_reporter TINYINT(1)  NOT NULL DEFAULT 0,            -- reporter outcome-notification opt-in (§7 #15; ADMIN §3.1/§11); Phase 2
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_reports_status (status, created_at),
  KEY idx_reports_dm (dm_message_id),
  CONSTRAINT fk_report_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_report_dm FOREIGN KEY (dm_message_id) REFERENCES dm_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- NOTE: "one open report per (user, post)" dedup (ADMIN §3.1) is enforced in app logic.

-- Consolidated: DESIGN §8.2 base + ADMIN §10.2 (before/after JSON, system actor). actor_id NULLable = system (see §7.6).
CREATE TABLE moderation_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id    BIGINT UNSIGNED NULL,                         -- NULL = system/automated action (ADMIN §3.8/§10.2)
  action      VARCHAR(40)     NOT NULL,                     -- pin, lock, delete_post, ban, anon_reveal, ...
  target_type ENUM('thread','post','user','board','category','setting','service_secret') NOT NULL,
  target_id   BIGINT UNSIGNED NOT NULL,
  reason      VARCHAR(255)    NULL,
  before_json JSON            NULL,                         -- ADMIN §10.2 (edit snapshot)
  after_json  JSON            NULL,                         -- ADMIN §10.2
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_modlog_target (target_type, target_id),
  KEY idx_modlog_actor (actor_id, created_at),
  CONSTRAINT fk_modlog_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 2. Accounts & auth (USER.md §7.1)

```sql
-- Linked third-party logins (a user may have several)
CREATE TABLE oauth_identities (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          BIGINT UNSIGNED NOT NULL,
  provider         ENUM('google','apple','github') NOT NULL,
  provider_user_id VARCHAR(191)    NOT NULL,                -- stable id (sub / numeric id)
  email            VARCHAR(255)    NULL,                    -- may be a relay/private address
  email_verified   TINYINT(1)      NOT NULL DEFAULT 0,
  avatar_url       VARCHAR(512)    NULL,                    -- cached provider avatar (USER §8); OAuth avatar-import sets users.avatar_source='oauth'. Build: Phase 2 (P2-10). Local copy via users.avatar_path arrives with the Phase 3 uploads pipeline. See §7 #12.
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provider_identity (provider, provider_user_id),
  KEY idx_oauth_user (user_id),
  CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-user preference blob (theme, reading, notifications, composing, privacy, leaderboard opt-out, ...)
CREATE TABLE user_preferences (
  user_id    BIGINT UNSIGNED NOT NULL,
  prefs      JSON            NOT NULL,                      -- defaults inherited from site settings
  updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-user board organization (favorite / mute / order) — powers the custom sidebar
CREATE TABLE user_board_prefs (
  user_id     BIGINT UNSIGNED NOT NULL,
  board_id    BIGINT UNSIGNED NOT NULL,
  is_favorite TINYINT(1)      NOT NULL DEFAULT 0,
  is_muted    TINYINT(1)      NOT NULL DEFAULT 0,
  position    INT             NULL,
  PRIMARY KEY (user_id, board_id),
  CONSTRAINT fk_ubp_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ubp_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Block list (blocked users can't DM/@mention; their notifications to you are suppressed)
CREATE TABLE blocks (
  user_id         BIGINT UNSIGNED NOT NULL,                 -- the blocker
  blocked_user_id BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, blocked_user_id),
  CONSTRAINT fk_block_user    FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_block_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Short-lived tokens: email verify, email change, password reset (store only the hash)
CREATE TABLE verifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  type       ENUM('email_verify','email_change','password_reset') NOT NULL,
  token_hash CHAR(64)        NOT NULL,
  new_email  VARCHAR(255)    NULL,                          -- for email_change
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME        NOT NULL,
  used_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_verif_token (token_hash),
  KEY idx_verif_user (user_id, type),
  CONSTRAINT fk_verif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- NOTE: the /unsubscribe one-click token (ADMIN §7.6) reuses this verifications-style signed-token pattern.

-- Username change history (redirects + moderation)
CREATE TABLE username_history (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  old_username VARCHAR(32)     NOT NULL,
  changed_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_uh_user (user_id),
  KEY idx_uh_old (old_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 3. Admin, moderation & email (ADMIN.md §10.1)

```sql
-- Typed key/value site configuration (theming tokens, registration mode, site_announcement banner, toggles, ...)
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
  board_id   BIGINT UNSIGNED NULL,                          -- required when scope='board'
  type       ENUM('post','full') NOT NULL DEFAULT 'post',
  reason     VARCHAR(255)    NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME        NULL,                          -- NULL = permanent
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
  author_id       BIGINT UNSIGNED NOT NULL,                 -- staff member
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

-- Outbound webhooks (HMAC-signed; Slack/Discord/Zapier)
CREATE TABLE webhooks (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                 VARCHAR(80)     NOT NULL,
  url                  VARCHAR(512)    NOT NULL,
  events               JSON            NOT NULL,            -- subscribed event names
  secret_ref           VARCHAR(64)     NOT NULL,            -- svcsec_* SecretVault reference, not plaintext
  is_active            TINYINT(1)      NOT NULL DEFAULT 1,
  consecutive_failures INT UNSIGNED    NOT NULL DEFAULT 0,
  disabled_at          DATETIME        NULL,
  disabled_reason      VARCHAR(190)    NULL,
  last_status          INT             NULL,                -- last delivery HTTP status
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
  event_id        VARCHAR(64)     NOT NULL,                 -- per-occurrence idempotency id
  payload         MEDIUMTEXT      NOT NULL,                 -- JSON request body
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
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)     NOT NULL,
  token_hash   CHAR(64)        NOT NULL,                    -- store only the hash
  scopes       JSON            NOT NULL,
  created_by   BIGINT UNSIGNED NOT NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME        NULL,
  expires_at   DATETIME        NULL,
  revoked_at   DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_api_token_hash (token_hash),
  KEY idx_api_token_created_by (created_by),
  KEY idx_api_token_active (revoked_at, expires_at),
  CONSTRAINT fk_api_token_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email suppression list (bounces, complaints, unsubscribes) — fan-out skips these
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
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,             -- migration 0063: automatic retry/backoff attempts already made
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,           -- max 1 preserves old single-attempt behavior
  last_attempt_at DATETIME NULL,
  next_attempt_at DATETIME NULL,                             -- NULL = immediately claimable when status='queued'
  error      VARCHAR(255) NULL,
  message_id VARCHAR(191) NULL,
  idempotency_key VARCHAR(191) NULL,                          -- DESIGN §9.6: post_id+':'+user_id for transactional 'instant' fan-out; NULL for digest/test/system
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_deliv_idem (idempotency_key),                 -- dedupes one send per (post,recipient); InnoDB allows multiple NULLs
  KEY idx_deliv_user (user_id, created_at),
  KEY idx_deliv_status (status, created_at),
  KEY idx_deliv_retry (status, next_attempt_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. Composer (COMPOSER.md §16.2)

```sql
-- Uploaded images referenced from Markdown; full Phase-3 lifecycle (resolves the
-- PHASE_3_PLAN §8.2 #1 schema gap). BUILT by migration 0043 (P3-04). The same
-- table backs per-post + per-DM media and operator brand assets (purpose).
CREATE TABLE attachments (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,                  -- owner/uploader
  post_id        BIGINT UNSIGNED NULL,                      -- set at finalize (post media)
  dm_message_id  BIGINT UNSIGNED NULL,                      -- set at finalize (DM media)
  purpose        ENUM('post','dm','brand_logo','brand_favicon','avatar') NOT NULL DEFAULT 'post',
  kind           ENUM('image','file') NOT NULL DEFAULT 'image',
  status         ENUM('temp','finalized','deleted') NOT NULL DEFAULT 'temp', -- visible only once finalized
  storage_key    VARCHAR(255)    NOT NULL,                  -- unguessable relative path under a non-exec/non-public media root
  sha256         CHAR(64)        NOT NULL,                  -- content hash (dedupe + integrity)
  mime           VARCHAR(100)    NOT NULL,
  size_bytes     INT UNSIGNED    NOT NULL,
  width          INT UNSIGNED    NULL,
  height         INT UNSIGNED    NULL,
  alt            VARCHAR(255)    NULL,
  visibility     ENUM('public','private') NOT NULL DEFAULT 'public', -- derived from parent at finalize
  scan_status    ENUM('pending','clean','quarantined','failed','skipped') NOT NULL DEFAULT 'clean', -- migration 0058 expanded-file gate
  scan_checked_at DATETIME       NULL,
  quarantined_at DATETIME        NULL,
  quarantine_reason VARCHAR(255) NULL,
  download_name  VARCHAR(255)    NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finalized_at   DATETIME        NULL,
  deleted_at     DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attach_storage_key (storage_key),
  KEY idx_attach_owner (user_id),
  KEY idx_attach_post (post_id),
  KEY idx_attach_dm (dm_message_id),
  KEY idx_attach_sweep (status, created_at),
  KEY idx_attach_scan (scan_status, created_at),
  CONSTRAINT fk_attach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- At-most-once logical submit (P3-03, §8.5). BUILT by migration 0044.
CREATE TABLE submission_idempotency (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  idem_key    CHAR(64)        NOT NULL,                     -- sha256 of the client token
  context     VARCHAR(32)     NOT NULL,                     -- thread | reply | dm
  result_type VARCHAR(32)     NOT NULL,                     -- thread | post | dm_message
  result_id   BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_idem (user_id, idem_key),
  KEY idx_idem_sweep (created_at),
  CONSTRAINT fk_idem_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> **Phase-3 build note (reconciled 2026-06-28).** `attachments` and `submission_idempotency` are built (migrations 0043/0044). The columns `threads.is_pending` / `posts.is_pending` / `boards.require_approval` (anti-abuse + board approval holds) and `users.avatar_path` / `users.onboarded_at` appear in the consolidated shapes above but were **not** migrated in Phases 1–2; they are created in Phase 3 by migrations 0045/0046/0042 respectively (per §7 #11: a column's presence in this file is not evidence its migration shipped). `posts.deleted_at` (soft-delete timestamp, gating the attachment-retention grace window) is added by migration 0047. TOTP/recovery is built by migration `0054` as a Phase 5 Gate A prerequisite. Webhook delivery is built deploy-dark by migration `0057`. Still **not built** (Gate B / later): `plugins`, appeals, automation-rule, server-`drafts`, bookmark-folder, and custom-profile-field tables.

---

## 5. Community layer (COMMUNITY.md §11)

```sql
-- Asymmetric follow graph (user→user in v1; tag/board P2)
CREATE TABLE follows (
  user_id     BIGINT UNSIGNED NOT NULL,                     -- the follower
  target_type ENUM('user','tag','board') NOT NULL DEFAULT 'user',
  target_id   BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, target_type, target_id),
  KEY idx_follow_target (target_type, target_id),
  CONSTRAINT fk_follow_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Badge catalogue (fixed set in v1; admin-defined P2)
CREATE TABLE badges (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(48)  NOT NULL,
  name        VARCHAR(64)  NOT NULL,
  description VARCHAR(255) NOT NULL,
  icon        VARCHAR(64)  NULL,
  kind        ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  is_enabled  TINYINT(1) NOT NULL DEFAULT 1,                 -- Phase 4 Gate A
  display_order INT NOT NULL DEFAULT 0,                      -- Phase 4 Gate A
  rule_version INT UNSIGNED NULL,                            -- Phase 4 Gate A custom-rule revision
  PRIMARY KEY (id),
  UNIQUE KEY uq_badge_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Awarded badges
CREATE TABLE user_badges (
  user_id    BIGINT UNSIGNED NOT NULL,
  badge_id   BIGINT UNSIGNED NOT NULL,
  awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  awarded_by BIGINT UNSIGNED NULL,                          -- set for manual grants
  PRIMARY KEY (user_id, badge_id),
  CONSTRAINT fk_ub_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ub_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reputation ledger (audit + time-windowed leaderboards). Built by migration 0048.
-- users.reputation is reconciled from active (non-reversed) applied_delta rows.
CREATE TABLE reputation_events (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  board_id        BIGINT UNSIGNED NULL,
  source_type     VARCHAR(32)     NOT NULL,                 -- reaction | accepted_answer | future correction source
  source_id       BIGINT UNSIGNED NULL,
  logical_key     VARCHAR(120)    NOT NULL,                 -- durable idempotency key
  delta           INT             NOT NULL,
  applied_delta   INT             NOT NULL,
  event_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reversed_at     DATETIME        NULL,
  reversed_by     BIGINT UNSIGNED NULL,
  reversal_reason VARCHAR(255)    NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reputation_logical (logical_key),
  KEY idx_rep_user_time (user_id, event_at),
  KEY idx_rep_board_time (board_id, event_at),
  CONSTRAINT fk_rep_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rep_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE SET NULL,
  CONSTRAINT fk_rep_reversed_by FOREIGN KEY (reversed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Phase 4 Gate A reconciliation (migration 0048)

Additive columns folded into existing tables:

- `threads.status ENUM('open','needs_answer','solved','decision_made','archived') NOT NULL DEFAULT 'open'`, `status_changed_at DATETIME NULL`, `status_changed_by BIGINT UNSIGNED NULL`, index `idx_threads_status`, FK `status_changed_by → users.id`.
- `posts.is_wiki TINYINT(1) NOT NULL DEFAULT 0`, index `idx_posts_wiki (thread_id, is_wiki)`.
- `thread_user.snoozed_until DATETIME NULL`, `inbox_note VARCHAR(120) NULL`, index `idx_tu_snooze (user_id, snoozed_until)`.
- `boards.assignment_mode ENUM('off','self','staff') NOT NULL DEFAULT 'off'`, `tags_enabled TINYINT(1) NOT NULL DEFAULT 1`, `wiki_enabled TINYINT(1) NOT NULL DEFAULT 0`.
- `conversations.kind ENUM('direct','group') NOT NULL DEFAULT 'direct'`, `title VARCHAR(120) NULL`, `owner_user_id BIGINT UNSIGNED NULL`, `created_by BIGINT UNSIGNED NULL`, index `idx_conversations_kind`, FKs to `users`.
- `conversation_participants.role ENUM('owner','member') NOT NULL DEFAULT 'member'`, `joined_after_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0`, `joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `left_at DATETIME NULL`, `removed_by BIGINT UNSIGNED NULL`, `notification_mode ENUM('normal','muted') NOT NULL DEFAULT 'normal'`, index `idx_cp_active_user`, FK `removed_by → users.id`.

New tables:

- `thread_status_history(id, thread_id, actor_id, previous_status, new_status, reason, created_at)` with thread/actor indexes and FKs; initial rows are backfilled during migration.
- `thread_assignments(thread_id, assigned_user_id, assigned_by, assigned_at)` with primary key `thread_id`, assignee index, and FKs to `threads`/`users`.
- `thread_assignment_history(id, thread_id, previous_user_id, assigned_user_id, actor_id, action, reason, created_at)` with thread/actor indexes and FKs to `threads`/`users`.
- `conversation_events(id, conversation_id, actor_id, event_type, subject_user_id, body, created_at)` with conversation index and FKs to `conversations`/`users`.
- `tags(id, slug, name, description, visibility, is_enabled, created_by, created_at, updated_at)` with unique `slug`, enabled/name index, and creator FK.
- `tag_aliases(alias_slug, tag_id, created_at)` with primary key `alias_slug` and FK to `tags`.
- `thread_tags(thread_id, tag_id, added_by, created_at)` with primary key `(thread_id, tag_id)`, tag index, and FKs to `threads`/`tags`/`users`.
- `badge_rules(id, badge_id, rule_type, threshold, board_id, repeatable, is_enabled, version, created_by, created_at, updated_at)` with badge index and FKs to `badges`/`boards`/`users`.
- `badge_award_history(id, user_id, badge_id, badge_rule_id, achievement_key, action, actor_id, reason, created_at)` with unique `(user_id, badge_id, achievement_key, action)`, user index, and FKs.
- `thread_summaries(id, thread_id, kind, status, body, body_html, version, author_id, reviewer_id, published_at, retired_at, created_at, updated_at)` with `(thread_id, status, version)` index and FKs.
- `thread_summary_sources(summary_id, post_id)` with primary key `(summary_id, post_id)` and FKs to `thread_summaries`/`posts`.
- `related_threads(id, source_thread_id, related_thread_id, relation_type, source, score, reason, status, curator_id, created_at)` with unique related-pair key, target index, and FKs.
- `post_revisions(id, post_id, editor_id, body, body_html, reason, created_at)` with `(post_id, id)` index and FKs.
- `content_references(id, source_type, source_id, target_type, target_id, token, resolved_at, unavailable, created_at)` with source and target indexes; app logic resolves/authorizes targets.
- `thread_operations(id, operation_type, actor_id, source_thread_id, destination_thread_id, status, dry_run_plan, before_snapshot, after_snapshot, failure_reason, created_at, applied_at)` with source index and FKs.
- `thread_redirects(old_thread_id, canonical_thread_id, operation_id, created_at)` with primary key `old_thread_id`, canonical index, and FKs.

Gate A closeout note (reconciled 2026-06-29): split/merge operations remain
schema-only and are explicitly deferred in
`docs/adr/0003-phase-4-closeout-deferrals.md`. `custom badge rules` and
`content_references` now have deploy-dark carryover implementation evidence, but
remain behind flags until the release evidence in
`docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md` is complete. Summary
retire/restore/source display, wiki revert, and tag merge/visibility behavior
are implemented in application code.

---

## 4A. Phase 4 carryover foundation (migration 0058)

Migration `0058` adds the ADR 0003 carryover shapes that were still missing from
the consolidated schema after Phase 4 Gate A. These tables are additive and were
introduced behind feature flags; behavior must still be proved by
service/controller tests before broad enablement.

Modified existing tables:

- `attachments` adds `scan_status`, `scan_checked_at`, `quarantined_at`, `quarantine_reason`, `download_name`, and `idx_attach_scan` for expanded file scanner/quarantine handling. File bytes are download-only and must pass the clean-scan gate before public delivery.
- `reactions.emoji` widens to `VARCHAR(48)` so custom emoji shortcodes fit without truncating built-in emoji values.
- `users` adds `signature_removed_at/by` and `avatar_removed_at/by` audit columns for profile moderation.

New tables:

- `link_previews(id, source_type, source_id, url, url_hash, final_url, status, title, description, image_url, site_name, http_status, metadata, error, fetched_at, purged_at, created_at, updated_at)` with unique `(source_type, source_id, url_hash)`, status queue index, and source lookup index.
- `polls(id, thread_id, question, mode, status, results_policy, created_by, closes_at, closed_at, created_at, updated_at)` with unique `thread_id`, status index, and thread/creator FKs.
- `poll_options(id, poll_id, body, position, created_at)` with poll-position index and poll FK.
- `poll_votes(id, poll_id, option_id, user_id, created_at)` with unique `(poll_id, option_id, user_id)` and poll/option/user FKs.
- `custom_emoji(id, shortcode, name, image_path, mime, is_enabled, allow_reactions, created_by, created_at, updated_at)` with unique shortcode, enabled/reaction index, and creator FK.
- `board_folders(id, user_id, name, position, created_at, updated_at)` with unique `(user_id, name)`, user-position index, and user FK.
- `board_folder_boards(folder_id, board_id, position, created_at)` with primary key `(folder_id, board_id)`, board lookup index, and folder/board FKs.
- `saved_feed_filters(id, user_id, name, filter_json, digest_enabled, position, created_at, updated_at)` with unique `(user_id, name)`, user-position index, and user FK.
- `since_last_read_context(id, user_id, thread_id, from_post_id, to_post_id, post_count, context_text, generated_at, expires_at)` with unique window key, thread-generation index, and user/thread/post FKs.

Carryover implementation note (2026-06-29): the `0058` shapes are no longer all
inert. Link previews, expanded files, polls, custom emoji, board folders, saved
feed filters, since-last-read context, related-topic refresh, profile media, and
the existing `0048` content-reference and badge-rule shapes have
service/controller/worker evidence. Appeals, account lifecycle/export/delete,
bookmark folders, custom profile fields, email retry/backoff, server drafts,
and scoped split/merge counter maintenance now have implementation evidence too
(migrations `0059`–`0064`, see §4B/§4C). Polls, board folders, bookmark folders,
and saved feed filters have since graduated to default-on; split/merge keeps the
global repair command as rehearsal/repair tooling, not as the normal operation
path.

---

## 4B. Phase 2–4 carryover completion (migrations 0059–0062)

Migrations `0059`–`0062` add the remaining ADR 0006/0007 and P2-04 carryover
shapes that PR #26 implemented. All are additive; the member-facing surfaces are
feature-flag controlled (`account_lifecycle`, `appeals`, and
`custom_profile_fields` default off; `bookmark_folders` graduated to default-on
on 2026-07-01). "Inert schema is not evidence" (DESIGN §13).

Modified existing tables:

- `users.status` **ENUM widened** to `('active','deactivated','pending_deletion','deleted','suspended','banned')` (migration `0059`) for self-service lifecycle states. Accounts are never hard-deleted; the grace-period purge anonymizes PII while preserving content + audit history.
- `email_deliveries` adds `payload JSON NULL AFTER subject` (migration `0061`) so a `kind='system'` broadcast carries a durable per-recipient payload the worker renders at send time.

New tables:

- `account_deletion_requests(id, user_id, requested_by, status, requested_at, purge_after, canceled_at, canceled_by, purged_at, reason)` (`0059`) with `KEY (user_id, status)`, due-purge `KEY (status, purge_after)`, and user/requester FKs (CASCADE) + `canceled_by` FK (SET NULL). `status ENUM('pending','canceled','purged')`; the `worker:purge-accounts` cron anonymizes rows past `purge_after`.
- `moderation_appeals(id, appellant_id, target_type, target_id, moderation_log_id, original_action, target_summary, reason, status, resolution_note, resolved_by, resolved_at, created_at, updated_at)` (`0060`) with appellant/status/target/log indexes; `target_type ENUM('post','user')`, `status ENUM('open','upheld','modified','reversed','dismissed')`; appellant FK (CASCADE), log/resolver FKs (SET NULL). Durable, never physically deleted by cleanup.
- `moderation_appeal_events(id, appeal_id, actor_id, event, note, created_at)` (`0060`) — append-only state-change log; `event ENUM('opened','upheld','modified','reversed','dismissed')`; appeal FK (CASCADE), actor FK (SET NULL).
- `email_domain_status(domain PK, dkim_selector, spf_status, dkim_status, details JSON, checked_at, updated_at)` (`0061`) caches SPF/DKIM verification state for the configured From domain; `spf_status`/`dkim_status ENUM('unknown','pass','fail')`. Workers refuse to send while the required domain is unverified.
- `thread_bookmark_folders(id, user_id, name, position, created_at, updated_at)` (`0062`) with unique `(user_id, name)`, user-position index, user FK (CASCADE) — private folders for bookmarked threads.
- `thread_bookmark_folder_threads(folder_id, thread_id, position, created_at)` (`0062`) PK `(folder_id, thread_id)`, thread index, folder/thread FKs (CASCADE).
- `user_profile_fields(id, user_id, label, value, position, created_at, updated_at)` (`0062`) with unique `(user_id, position)` and user FK (CASCADE) — bounded (≤3) extra public profile fields; `label VARCHAR(40)`, `value VARCHAR(160)`.

---

## 4C. Email retry and server draft pull-forward (migrations 0063–0064)

Migration `0063` keeps `email_deliveries.failed` terminal but adds automatic
retry/backoff metadata so transient delivery failures do not require manual
operator replay on the first failed transport call:

- `email_deliveries.attempt_count INT UNSIGNED NOT NULL DEFAULT 0`
- `email_deliveries.max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5`
- `email_deliveries.last_attempt_at DATETIME NULL`
- `email_deliveries.next_attempt_at DATETIME NULL`
- `KEY idx_deliv_retry (status, next_attempt_at, id)`

The default backoff sequence is 5 minutes, 15 minutes, 1 hour, then 6 hours.
Rows with `max_attempts=1` preserve the old single-attempt behavior. Admin email
views, worker stats, and CSV export surface the retry state.

Migration `0064` implements ADR 0010's deploy-dark server draft sync shape:

- `server_drafts(id, user_id, context_key, revision, title, body, metadata, updated_at, expires_at)` with unique `(user_id, context_key)`, user-updated and expiry indexes, and user FK (CASCADE).

`server_drafts` defaults dark. The app enforces 90-day retention, a 50-draft
per-user quota, optimistic revision conflicts (`409`), no-JS listing/discard on
`/drafts`, account export inclusion, and account-deletion purge.

---

## 5A. Phase 5 foundation — ecosystem, identity & governance (deploy-dark)

Migrations `0049`–`0053` (additive; one reversible conversion). This is the
**schema-reconciliation** slice of Phase 5 Milestone 1: the data shape exists so no
Gate A feature depends on an undocumented table/column, but it is **inert** — every
Phase 5 flag defaults dark (`FeatureFlags::DEFAULTS`) and no application code reads
or writes these tables yet. Per `PHASE_5_PLAN` §2/§7, the registry trust roots,
signing-key custody, WebAuthn RP ID, OIDC provider choice, isolation profile,
numeric budgets, and permission taxonomy are **owner-approved Milestone-0 policy**
and are deliberately **not** encoded here; private trust-root/signing keys never live
in the application DB (§8.2 #1). "Inert schema is not evidence" of a shipped feature
(DESIGN §13) — see `PHASE_5_STATUS.md` and `docs/adr/0004-phase-5-entry-and-carryover.md`.

Modified existing table:

- `oauth_identities.provider` **converted `ENUM('google','apple','github')` → `VARCHAR(64)`** (migration `0052`, §8.2 #15) so new providers arrive by configuration, not a schema ALTER. Existing rows keep their exact string values and the `uq_provider_identity (provider, provider_user_id)` unique key is retained. Added `provider_config_id BIGINT UNSIGNED NULL` + `idx_oauth_provider_config` + FK → `identity_providers(id)` ON DELETE SET NULL as inert registry linkage (uniqueness still derives from the provider string until the Milestone-5 repoint). This is the documented exception to "strictly additive" (§3 def-of-done; reversible in `down()` while the feature is dark).

New tables — **ecosystem / packages** (`0049`, §8.2 #1–5):

- `package_registries(id, source_id, display_name, base_url, is_enabled, last_snapshot_digest, last_snapshot_at, snapshot_expires_at, created_at, updated_at)` with unique `source_id`; canonical, globally-namespaced registry sources with snapshot freshness/expiry.
- `registry_trust_keys(id, registry_id, key_id, algorithm, public_key, status, valid_from, valid_until, revoked_at, revoked_reason, created_at)` with unique `(registry_id, key_id)` and registry FK; **PUBLIC** signing-key material only, with rotation/revocation.
- `package_publishers(id, publisher_uid, display_name, verified_at, status, created_at, updated_at)` with unique `publisher_uid`.
- `packages(id, package_uid, registry_id, publisher_id, name, type, trust_class, advisory_status, latest_release_id, created_at, updated_at)` with unique `package_uid`, registry/publisher FKs; registry identity, trust class never implied by installability. `latest_release_id` is a denormalised pointer (no FK — avoids a cycle with releases).
- `package_releases(id, package_id, version, digest, source_url, license, core_min, core_max, manifest_json, dependency_json, signature, signed_key_id, review_status, channel, advisory_status, published_at, created_at)` with unique `(package_id, version)`, digest index, package FK; **immutable** releases, review bound to an exact digest.
- `installed_packages(id, package_id, release_id, digest, source_registry_id, publisher_id, trust_class, review_status, state, health, compat_min, compat_max, installed_by, installed_at, updated_at)` with unique `package_id` and FKs; local install state kept **separate** from registry metadata so a registry rollback never rewrites an installed digest.
- `installed_package_permissions(id, installed_package_id, kind, permission_key, risk_class, declared, granted, granted_at, granted_by)` with unique `(installed_package_id, kind, permission_key)` and FKs; declared = manifest ceiling, granted = actual authority (preserved until re-consent).
- `package_history(id, package_id, installed_package_id, event, actor_id, prior_version, new_version, prior_digest, new_digest, permission_snapshot_json, approval_ref, failure_stage, detail, created_at)` with package/actor FKs; `installed_package_id` carries **no** FK so history survives uninstall.
- `package_advisories(id, advisory_uid, registry_id, package_id, affected_version_range, affected_digest, severity, action, summary, signed_evidence, issued_at, acknowledged_at, acknowledged_by, created_at)` with unique `advisory_uid` and FKs; caches the signed advisory the install relied on.
- `local_package_blocks(id, digest, package_uid, reason, created_by, created_at)` with digest/package indexes; registry-independent local emergency blocklist.

New tables — **governance / least-privilege** (`0050`, §8.2 #8/#9/#13):

- `capabilities(id, capability_key, namespace, scope_type, risk_class, is_delegable, is_protected, source, source_version, description, retired_at, created_at)` with unique `capability_key`; the catalogue is seeded by **`0066`** from `src/Security/CapabilityCatalog.php` (54 core keys); `role_capabilities` reproduces the cumulative guest/user/mod/admin authority and `protected_owners` is backfilled from existing active admins (F3/F5, deploy-dark).
- `roles(id, role_key, name, kind, is_protected, role_rank, version, description, created_by, created_at, updated_at)` with unique `role_key`; **seeds 4 protected system roles** `system.guest|user|moderator|admin` with `role_rank` 0/10/20/30 (maps the `boards.post_min_role` floor). (`role_rank`, not `rank` — reserved in MySQL 8.)
- `role_capabilities(role_id, capability_id, created_at)` PK `(role_id, capability_id)`, FKs to roles/capabilities.
- `role_assignments(id, subject_type, subject_id, role_id, scope_type, scope_id, grantor_id, reason, approval_ref, starts_at, ends_at, revoked_at, revoked_by, assignment_version, created_at)` with subject/role/scope/expiry indexes and role/grantor/revoker FKs; `subject_id`/`scope_id` are polymorphic (no FK); resolver enforces `ends_at` directly (expiry never waits on a cleanup job).
- `role_assignment_history(id, assignment_id, event, actor_id, subject_type, subject_id, role_id, scope_type, scope_id, before_json, after_json, reason, created_at)` — immutable before/after audit.
- `protected_owners(id, user_id, is_active, recovery_status, designated_by, designated_at, created_at)` with unique `user_id` and FKs; makes the protected-owner set explicit so the last-active-owner invariant is enforceable transactionally.
- `owner_transfer_history(id, from_user_id, to_user_id, actor_id, reason, created_at)` with FKs.

New tables — **identity / auth** (`0051`–`0054`, §8.2 #14/#15/#16 plus ADR 0004 B1):

- `webauthn_credentials(id, user_id, credential_id, public_key, sign_count, aaguid, transports, is_discoverable, is_backup_eligible, is_backed_up, nickname, created_at, last_used_at, revoked_at)` with unique `credential_id` (VARBINARY) and user FK; **PUBLIC** credential material only — private keys never reach the server.
- `webauthn_challenges(id, user_id, session_token_hash, purpose, challenge, created_at, expires_at, consumed_at)` with user FK; one-time, short-lived, purpose/session-bound; `user_id` NULL = usernameless/discoverable login.
- `identity_providers(id, provider_key, display_name, protocol, type, issuer, discovery_url, jwks_uri, jwks_cache_json, jwks_cached_at, client_id, client_secret_ref, claim_map_json, is_enabled, health_status, health_checked_at, created_at, updated_at)` with unique `provider_key`; **seeds google/apple/github** as dark `builtin` rows. `client_secret_ref` is a reference into the encrypted secret service — **never plaintext**.
- `provider_aliases(id, alias, provider_key, created_at)` with unique `alias`; maps historical provider strings to canonical keys so the enum→registry migration never duplicates/orphans an identity. Seeds google/apple/github.
- `invitations(id, token_hash, created_by, email, domain, onboarding_role_id, onboarding_board_id, max_uses, used_count, expires_at, revoked_at, revoked_by, created_at)` with unique `token_hash` (sha256 — **no raw token column**) and creator/revoker/role/board FKs; an invitation is onboarding evidence, not authority (`onboarding_role_id` is non-privileged only, enforced in code).
- `invitation_redemptions(id, invitation_id, user_id, ip, redeemed_at)` with FKs; `ip` packed via `inet_pton` (project convention). Redemption is atomic and cannot exceed `max_uses`.
- `user_totp_credentials(id, user_id, secret_ciphertext, secret_nonce, secret_tag, algorithm, digits, period_seconds, enabled_at, verified_at, disabled_at, last_used_step, created_at, updated_at)` with unique `user_id`; TOTP secrets are AES-256-GCM encrypted with the application key, never stored plaintext, and `last_used_step` prevents replay.
- `user_recovery_codes(id, user_id, batch_id, code_hash, used_at, created_at)` with unique `code_hash`; raw recovery codes are shown once and stored only as HMAC hashes.
- `mfa_login_challenges(id, user_id, token_hash, next_path, ip, user_agent, created_at, expires_at, consumed_at)` with unique hash-only token storage; challenges are one-time, short-lived, and consumed before session creation.

---

## 5B. B2 service-secret registry (migration 0055)

Migration `0055` adds the reversible-secret vault seam used by future provider,
webhook, and remote-app credentials. It also widens `moderation_log.target_type`
with `service_secret` so every high-impact store/rotate/revoke/destroy mutation can
write non-lossy audit in the same transaction.

### `service_secrets` — reference / identity (holds no ciphertext)

| column | type | notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| `secret_ref` | VARCHAR(64) | opaque handle, `svcsec_` + 32 hex (39 chars); UNIQUE; fits `client_secret_ref VARCHAR(190)` |
| `owner_type` | VARCHAR(32) | informational: `provider` / `webhook` / `remote_app` / `generic`. The authoritative link is the consumer's own `_ref` column, not a back-pointer here. |
| `owner_id` | BIGINT UNSIGNED NULL | informational owner row id when applicable |
| `label` | VARCHAR(190) NOT NULL | human description; never the secret |
| `status` | ENUM('active','revoked') NOT NULL DEFAULT 'active' | |
| `latest_version` | INT UNSIGNED NOT NULL DEFAULT 0 | monotonic high-water mark of the newest version issued; **status-independent** (never decremented, unchanged by revoke). Next rotation version = `latest_version + 1`. This is **not** a live pointer — "which version is live" is the single row with `state='current'`, and reads gate on parent `status='active'` + that `state`, never on this column. |
| `created_by` | BIGINT UNSIGNED NULL | FK `users(id)` ON DELETE SET NULL |
| `revoked_by` | BIGINT UNSIGNED NULL | FK `users(id)` ON DELETE SET NULL |
| `created_at` | DATETIME NOT NULL | UTC |
| `updated_at` | DATETIME NOT NULL | UTC |
| `revoked_at` | DATETIME NULL | |

Indexes: `UNIQUE KEY uq_service_secret_ref (secret_ref)`, `KEY idx_service_secret_owner (owner_type, owner_id)`. `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`.

### `service_secret_versions` — encrypted material (one row per version)

| column | type | notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| `secret_id` | BIGINT UNSIGNED NOT NULL | FK `service_secrets(id)` ON DELETE CASCADE |
| `version` | INT UNSIGNED NOT NULL | monotonic per secret |
| `ciphertext` | VARBINARY(4096) NOT NULL | AES-256-GCM output; emptied (zero-length) on destroy |
| `nonce` | VARBINARY(12) NOT NULL | per-encryption random nonce |
| `tag` | VARBINARY(16) NOT NULL | GCM auth tag |
| `cipher` | VARCHAR(32) NOT NULL DEFAULT 'aes-256-gcm' | forward-compat label |
| `key_version` | INT UNSIGNED NOT NULL DEFAULT 1 | forward-compat; only v1 now |
| `state` | ENUM('current','retired','destroyed') NOT NULL DEFAULT 'current' | |
| `created_at` | DATETIME NOT NULL | UTC |
| `retire_after` | DATETIME NULL | grace deadline; set when retired |
| `retired_at` | DATETIME NULL | |
| `destroyed_at` | DATETIME NULL | set when ciphertext emptied |

Indexes: `UNIQUE KEY uq_service_secret_version (secret_id, version)`, `KEY idx_service_secret_prune (state, retire_after)`. `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`.

Destroy semantics: prune keeps the version-history row for audit, sets
`state='destroyed'`/`destroyed_at`, and overwrites `ciphertext`/`nonce`/`tag` with
zero-length `VARBINARY` values so recoverable material and plaintext-length signal
are removed.

---

## 5C. Phase 5 Gate B server-extension runtime (migration 0065)

Migration `0065` implements ADR 0011's deploy-dark runtime evidence harness. It
does not make untrusted code part of web requests; jobs are async-only behind the
`server_extensions` flag and a host sandbox probe.

New tables:

- `server_extension_handlers(id, installed_package_id, handler_key, entrypoint, events_json, jobs_json, permissions_json, resource_limits_json, storage_quota_bytes, status, quarantine_reason, created_at, updated_at)` with unique `(installed_package_id, handler_key)`, status index, and install FK (CASCADE). `status ENUM('enabled','disabled','quarantined')`.
- `server_extension_jobs(id, handler_id, event_name, payload_json, status, attempts, max_attempts, available_at, locked_at, last_error, created_at, updated_at)` with claim index `(status, available_at, id)`, handler index, and handler FK (CASCADE). `status ENUM('queued','running','succeeded','failed','quarantined')`.
- `server_extension_runs(id, job_id, handler_id, status, exit_code, duration_ms, output_bytes, stdout_json, error, started_at, finished_at)` with job/handler indexes, job FK (SET NULL), and handler FK (CASCADE). `status ENUM('succeeded','failed','timeout','quarantined')`.
- `server_extension_kv(installed_package_id, kv_key, value_blob, bytes, updated_at)` with primary key `(installed_package_id, kv_key)` and install FK (CASCADE).

Public manifests use `server_extension.v1` and declare entrypoint, events/jobs,
permissions, resource limits, and storage quota. Outbound hosts are denied by
default and entrypoints may not escape the package root. Bubblewrap is the
primary local isolation profile; unsupported hosts fail closed and workers leave
jobs queued.

---

## 6. Phase map (suggested build cut)

This maps the consolidated tables onto the **seven-phase delivery plan** (PHASE_1 through PHASE_7), which subdivides the DESIGN.md §13 three-phase roadmap (DoD: *register → log in → read → start a thread → reply, server-rendered*) and the USER §8 / ADMIN §11 deltas. Phases 1–2 are fully consolidated below. **Phase 3 is partially consolidated:** `attachments`, `plugins`, `webhooks`, `webhook_deliveries`, `api_tokens`, `email_deliveries`, and the TOTP/recovery carryover now have DDL above; appeals, bookmark-folders, custom-profile-fields, and server drafts are now specced as DDL in **§4B/§4C** (migrations `0060`/`0062`/`0064`); its remaining automation-rule table is **identified as a schema gap in PHASE_3_PLAN §8.2 and is not yet specced as DDL**. Phases 4–7 list their domains here as **schema requirements**, with DDL defined in each phase plan and folded back here on acceptance.

- **Phase 1 (MVP backend):** `users`, `sessions`, `verifications`, `categories`, `boards`, `board_slug_history`, `threads`, `posts`, `settings`, `moderation_log`. → See **[PHASE_1_MIGRATIONS.md](PHASE_1_MIGRATIONS.md)** for the exact Phase‑1 column cut, migration order (`0001`–`0010`), and which columns are held back to Phases 2–3.
- **Phase 2 (community essentials):** `reactions`, `thread_user` (star), `subscriptions`, `notifications`, `conversations`/`conversation_participants`/`dm_messages`, `reports`, `board_moderators`, `bans`, `warnings`, `user_notes`, `board_members`, search FULLTEXT indexes, `oauth_identities`, `user_preferences`, `user_board_prefs`, `blocks`, `username_history`, `email_suppressions`, `email_deliveries`, `follows`, `badges`, `user_badges`.
- **Phase 3 (polish, trust & scale):** `attachments` (image uploads + lifecycle), `plugins` (first-party/vetted), `webhooks` + `webhook_deliveries` (durable delivery, built deploy-dark by `0057`), `api_tokens`; appeals, bookmark-folder, custom-profile-field, and server-draft tables are now specced as DDL in **§4B/§4C** (migrations `0060`/`0062`/`0064`); the automation-rule table remains a **schema gap in PHASE_3_PLAN §8.2** (to be specced as DDL at its Milestone 1, then folded back here). TOTP/recovery is now built as the Phase 5 Gate A prerequisite in migration `0054`, resolving ADR 0004 B1 before passkey enforcement.
- **Phase 4 (advanced community & content):** Gate A migration `0048` is reconciled above: topic status/history, snooze, assignment, group-DM intervals/events, tags, board/tag follows, reputation ledger, badge-rule schema, summaries/related/wiki revisions, reference metadata, and split/merge redirect/audit tables. Gate B / later Phase 4 schema remains in PHASE_4_PLAN until accepted.
- **Phase 5 (ecosystem, identity & governance):** **partially consolidated** — the foundation migrations `0049`–`0053` (signed-package/registry, capabilities/roles, passkey credentials, generic-OIDC provider registry, invitations) are reconciled in **§5A** above as additive deploy-dark tables, and the B2 service-secret registry (`0055`), API-token slice (`0056`), webhook delivery slice (`0057`), code-only first-party hook registry, and Gate B server-extension runtime tables (`0065`) are reconciled here. The remaining Phase 5 schema (theme packages, publisher/review portal, governance groups/approvals/access-review, service principals, verified profile links, richer custom fields — §8.2 #7/#10/#11/#17/#18/#19 plus ADR 0004 B2 follow-ups) stays in PHASE_5_PLAN / B2 follow-up specs until its workstream lands.
- **Phase 6 (realtime & scale):** transactional-outbox/event + job tables, external-search projection state, object-storage/media metadata, and feed-projection/checkpoint tables. DDL in PHASE_6_PLAN.
- **Phase 7 (platform expansion):** per-tenant `community_id` ownership, locale/translation packs, Web Push subscriptions, import source-ID mappings, community domains, and any federation tables. DDL in PHASE_7_PLAN.

> **Crosswalk to DESIGN §13 (three-phase strategic roadmap):** DESIGN's *Phase 3 / Later (P2)* bucket is delivered across Phases 3–7 above. Phases 4–7 schema is **defined in each phase plan's data-model section** (as requirements, not yet committed DDL) and folded back into this file on acceptance; only Phases 1–2 are fully consolidated here today, and Phase 3 only partially (see above).

> FK note: a few columns reference rows in not-yet-built tables (e.g. `notifications.conversation_id`, `posts`/`threads` self-references like `last_post_id`). Add the FK constraints in the migration that introduces the *referenced* table, or leave them as plain indexed columns until then — don't block Phase 1 on Phase 2 tables.

---

## 7. Reconciliation decisions (applied here; please review)

Where the docs genuinely disagreed, this file picks one answer. Each is reversible — flag any you'd rather flip.

1. **Role enum value `'member'` → `'user'`** on `users.role` and `boards.post_min_role` (+ defaults). DECISIONS §4 standardised the role on **"User"** and DESIGN claimed it was "updated to match," but the §8.2 DDL still read `'member'`. Source-doc DDL in DESIGN.md was also corrected.
2. **`users.password_hash` is NULLable.** USER §2.4/§7.2 require OAuth-only accounts to exist before a password is set; the DESIGN §8.2 base had it `NOT NULL`.
3. **`notifications` enum unified; column is `type`; read flag is `is_read`.** The base DDL (`type`, `is_read`, 5 values), the §8.3 prose (`kind`/`read_at`, +`new_post`/`new_thread`), and COMMUNITY §9 (`kind`, +`follow`/`badge`/`solved`) disagreed on both column names and contents. Canonical: column **`type`**, read flag **`is_read`** (keeps the existing `idx_notif_user` index coherent), enum = the **full union** (now 11 values, incl. `announcement` — see #13).
4. **`thread_user.is_subscribed` dropped.** DESIGN §8.3 + USER §7.3 mark it *superseded by `subscriptions`*. On a greenfield build there's nothing to migrate, so it's omitted rather than left as dead weight. (`is_starred` + `last_read_post_id` stay.)
5. **`reports`: added `'triaged'` status + a derived `reason_code` enum.** ADMIN §3.2's lifecycle is open→**triaged**→resolved/dismissed (the base enum lacked `triaged`); `reason_code` values are derived from ADMIN §3.1's fixed reason list (free-text `reason` retained for "other").
6. **`moderation_log.actor_id` is NULLable, NULL = system.** ADMIN §3.8/§10.2 require accountable *automated* actions; the base had `actor_id NOT NULL`.
7. **`sessions` DDL is canonical (reconciled 2026-06-20).** DECISIONS §5 #9 scheduled a `sessions` table for Phase 1; the auth slice (`2026-06-20-auth-design.md` §7.1) set the canonical DDL — adding `csrf_secret`, `expires_at NOT NULL`, `revoked_at`, and `idx_sessions_active` — and the §1 definition above is the canonical Phase 1 shape (target migration `0005_sessions.sql`, not yet built). (IP retention per ADMIN §5.5 remains a later purge-job seam.)
8. **`moderation_log.target_type` widened to include `category` and `setting`.** The planned admin console audits board/category structure changes and site-name changes, so the original four target types were too narrow for real operator actions.
9. **`email_deliveries.idempotency_key` added (`UNIQUE`).** DESIGN §9.6 mandates `idempotency_key = post_id + ':' + user_id` for transactional fan-out, and PHASE_2 (P2-00 / Milestone 0) treats the missing column as a blocker for email work — but the consolidated DDL had no such column. Added as `VARCHAR(191) NULL` with `UNIQUE KEY uq_deliv_idem` (NULL for digest/test/system sends; InnoDB permits multiple NULLs). (Reconciled 2026-06-26.)
10. **`posts.ip` added (`VARBINARY(16) NULL`).** DECISIONS §4 #5 commits to storing *post* IPs — not just the login IP in `sessions.ip` — as a ban-evasion signal (ADMIN §5.4); the consolidated `posts` table had no IP column, leaving that decision and feature with no schema home. Added with the same 90-day-retention / Admin-only-audited posture as `sessions.ip`; the purge job is a later seam (ADMIN §5.5). **Build phase: added in the Phase 2 migration** — the phase that first uses it (ban-evasion P2-08). It was previously slated to ship with `posts` in Phase 1, but no Phase 1 plan item built it; PHASE_2_PLAN §7.1 (group 4) now owns it. (Reconciled 2026-06-26; build phase clarified.)
11. **Forward-phase columns tagged with their build phase.** Several columns live in Phase 1 *table* definitions (so each table has one consolidated final shape) but are *built* later, and the phase plans say so: `users.onboarded_at` → **Phase 3** (PHASE_3_PLAN §8.1 / P3-11 creates it explicitly); `users.timezone` / `digest_hour` / `last_daily_digest_at` and the `ft_threads_title` / `ft_posts_body` FULLTEXT indexes → **Phase 2** (PHASE_1_PLAN defers FULLTEXT; PHASE_2_PLAN P2-06 builds it); `posts.ip` and `sessions.ip` → **Phase 2** (post/login IP, ban-evasion ADMIN §5.4; `posts.ip` per #10; both omitted from the Phase 1 migrations — PHASE_1_MIGRATIONS.md §3). These are now annotated inline rather than left implicitly Phase 1, honouring the Conventions note that inline comments mark each column's phase. The owning *table* still appears under its creation phase in §6. (Reconciled 2026-06-26.)
12. **`oauth_identities.avatar_url` added (`VARCHAR(512) NULL`).** DECISIONS §5 #4 schedules **OAuth avatar-import for Phase 2**, and PHASE_1_MIGRATIONS §4 puts `users.avatar_source` in Phase 2 — but the consolidated schema gave the imported provider avatar nowhere to live (`users.avatar_path` is Phase 3, for the local uploads/Gravatar pipeline). Added `oauth_identities.avatar_url` as the Phase-2 cache of the provider avatar: import sets `users.avatar_source='oauth'` and renders from it (monogram fallback). The local copy (`users.avatar_path`), user uploads, and Gravatar stay Phase 3 (PHASE_3_PLAN P3-12). PHASE_2_PLAN P2-10 / §7.1 group 5 now build `avatar_source` + `avatar_url`. (Reconciled 2026-06-26.)

13. **`notifications` enum gains `'announcement'`; admin announcements/broadcast get a schema home (no new table).** ADMIN §7.4 and PHASE_2_PLAN §3 (L92) commit Phase 2 to admin **announcements/broadcast** — a site-wide banner or pinned announcement plus an opt-in broadcast notification/email — and USER §4.6 lists a "System / announcement" notification type, but the `notifications` enum had no value for it. Added `'announcement'` to `notifications.type` for the in-app broadcast/system notice. The **banner** lives in `settings` (a `site_announcement` JSON key: active flag, message, dismissible); a **pinned announcement** is an ordinary pinned thread; the broadcast **email** reuses `email_deliveries.kind='system'`. No `announcements` table is required. Build phase: **Phase 2** (PHASE_2_PLAN §7). (Reconciled 2026-06-26.)

14. **`dm_messages.body_html` added (`MEDIUMTEXT NULL`).** The unified composer (COMPOSER) treats DM bodies as canonical Markdown like posts; caching the sanitised render mirrors `posts.body_html` and avoids re-sanitising on every read. The consolidated `dm_messages` had only `body`. Added as a nullable cache column (Phase 2, P2-07). (Reconciled 2026-06-26 during the Phase-2 build review.)

15. **`reports.notify_reporter` added (`TINYINT(1) NOT NULL DEFAULT 0`).** PHASE_2_PLAN §3 (L70) and ADMIN §3.1/§11 commit Phase 2 to **reporter outcome-notifications** ("notify me of the outcome" when a report resolves/dismisses), but the consolidated `reports` table had no opt-in flag for it. Added as a Phase-2 column (P2-08), mirroring how #9/#10/#12 gave other committed Phase-2 features a schema home. (Reconciled 2026-06-26 during the Phase-2 build review.)

16. **`reports` can target a DM message (`post_id` → NULLable, `dm_message_id` added).** PHASE_2_PLAN P2-07 commits Phase 2 to **DM reporting** ("report a specific DM; staff see only the reported message/context"), but the consolidated `reports` table was post-only (`post_id NOT NULL`, FK to `posts`). Made `post_id` nullable and added `dm_message_id BIGINT UNSIGNED NULL` + FK to `dm_messages` (migration `0039`), so a report targets exactly one of a post or a DM message. App logic enforces "one open report per (reporter, target)" for both. Build phase: **Phase 2** (P2-07 submission; P2-08 queue/triage). (Reconciled 2026-06-26.)

## 8. Foreshadowed but not yet committed

Mentioned in the docs as future schema, deliberately **not** added here until specced:

- **`categories` default-collapsed flag** — ADMIN §4.1 describes an admin-set default-collapsed flag; no column specced. If built, add it as a cheap `categories.is_collapsed_default TINYINT(1)` in the Phase 3 admin-polish pass. (Per-user collapse state is separate — it lives in `user_preferences`.)
- **`post_mentions`** lookup table — optional, to speed "who was mentioned" queries (COMPOSER §16.2).
- **post-submission idempotency** — COMPOSER §9.2/§14.3 dedupes double-submit + brief client retries via a **short-lived/transient** key; v1 commits no column. A durable `posts`/`dm_messages` idempotency column (e.g. `client_token`, `UNIQUE`) would be added here only if durable cross-retry dedupe is later required. (Distinct from `email_deliveries.idempotency_key`, which dedupes email fan-out.)

## 9. Changelog

| Version | Date | Notes |
|---|---|---|
| v1.26 | 2026-07-01 | Added owner-lifecycle locking support migration `0067`: `users` now has `idx_users_role_status_id (role, status, id)` so last-owner/admin `FOR UPDATE` guards can lock active admins through a narrow ordered index. |
| v1.25 | 2026-07-01 | Phase 5 Foundation F3/F5 seed migration `0066` (seed-only): populated the `0050` `capabilities` catalogue (54 core keys) + `role_capabilities` (cumulative system.guest/user/moderator/admin) from the code-owned `CapabilityCatalog`, and backfilled `protected_owners` from existing active admins. Deploy-dark behind `capabilities`; no shape change. |
| v1.24 | 2026-06-30 | Reconciled the Phase 2-4 completion pull-forward migrations `0063`-`0065`: email retry/backoff columns and retry index, deploy-dark `server_drafts`, and deploy-dark Phase 5 Gate B server-extension runtime tables (`server_extension_handlers`, `server_extension_jobs`, `server_extension_runs`, `server_extension_kv`). Updated the phase map and removed server drafts from foreshadowed gaps. |
| v1.23 | 2026-06-30 | Reconciled the **Phase 2–4 carryover completion** migrations `0059`–`0062` in new **§4B**: widened `users.status` ENUM (`deactivated`/`pending_deletion`/`deleted`), added `email_deliveries.payload JSON`, and new tables `account_deletion_requests` (`0059`), `moderation_appeals`/`moderation_appeal_events` (`0060`), `email_domain_status` (`0061`), `thread_bookmark_folders`/`thread_bookmark_folder_threads`/`user_profile_fields` (`0062`). Added table-index rows 93–99. Additive and feature-flag controlled. |
| v1.22 | 2026-06-29 | Reconciled Phase 4 carryover behavior notes after deploy-dark implementation evidence for content references, automated context, related-topic refresh, profile media/signature hardening, and the earlier `0058` carryover slices; no schema shape change. |
| v1.21 | 2026-06-29 | Added Phase 4 carryover migration `0058`: `link_previews`, expanded-file scanner/quarantine columns on `attachments`, polls, `custom_emoji`, personal `board_folders`/`saved_feed_filters`, since-last-read context, profile-moderation audit columns on `users`, and widened `reactions.emoji`. |
| v1.20 | 2026-06-29 | Documented B2 SP4 as code-only schema-neutral work: `first_party_hooks` gates first-party domain producers that enqueue public-board events through the existing webhook ledger; no plugin manifests, lifecycle tables, sandbox, service principals, or third-party PHP execution are added. |
| v1.19 | 2026-06-28 | Added B2 webhook delivery (`0057`): reconciled `webhooks` to use `secret_ref` (`svcsec_*`, no plaintext secret), added `webhook_deliveries` with retry/backoff/dead-letter and `(webhook_id,event_type,event_id)` idempotency, and widened `moderation_log.target_type` with `'webhook'`. |
| v1.18 | 2026-06-28 | Added the B2 `api_tokens` table (`0056`): scoped, hash-only (`CHAR(64)`, `uq_api_token_hash`) admin/service Bearer tokens — `scopes` JSON, `created_by` FK (CASCADE), expiry + revocation timestamps — plus `moderation_log.target_type='api_token'` for lifecycle/scope-denial audit. Backs the read-only `/api/v1` slice (B2 sub-project 2). |
| v1.17 | 2026-06-28 | Added the B2 encrypted service-secret registry (`0055`): `service_secrets` opaque references/status/latest-version metadata, `service_secret_versions` AES-256-GCM material with version/grace/destroy lifecycle, and `moderation_log.target_type='service_secret'` for non-lossy audit. |
| v1.16 | 2026-06-28 | Added the Gate A TOTP/recovery prerequisite (`0054`): encrypted `user_totp_credentials`, hash-only `user_recovery_codes`, and one-time `mfa_login_challenges`; documented that B1 is resolved before passkey enforcement and ordinary users are not required to enroll. |
| v1.15 | 2026-06-28 | **Phase 5 foundation (Milestone 1 schema reconciliation).** Documented additive deploy-dark migrations `0049`–`0053` in new **§5A**: registry/packages/releases/installs/permissions/history/advisories/local-blocks (§8.2 #1–5), capability registry + protected roles (4 seeded system anchors) + scoped assignments/audit + protected-owner authority (§8.2 #8/#9/#13), WebAuthn credentials/challenges (§8.2 #14), identity-provider registry + the `oauth_identities.provider` **ENUM→VARCHAR(64)** widen + `provider_config_id` linkage (§8.2 #15), and invitations/redemptions (§8.2 #16). Added table-index rows 55–77. Tables are inert (no app reads/writes; flags dark) and carry **no** Milestone-0 trust policy. |
| v1.13 | 2026-06-28 | Reconciled Phase 4 Gate A migration `0048`: documented additive status/snooze/assignment, board tag/wiki toggles, group-DM interval columns/events, canonical `reputation_events`, tag tables, badge-rule/audit tables, summaries/related/wiki revision tables, reference metadata, and split/merge operation/redirect tables. Removed now-committed status/snooze items from §8 foreshadowing. |
| v1.12 | 2026-06-26 | Gave the Phase-2 **admin announcements/broadcast** feature a schema home (§7 #13): added `'announcement'` to `notifications.type` (now 11 values) for the in-app broadcast/system notice; documented the banner in `settings` (`site_announcement`), the pinned announcement as a pinned thread, and the broadcast email via `email_deliveries.kind='system'` — no new table. Noted the `categories` default-collapsed flag as a Phase-3 cheap flag (§8); de-referenced the not-yet-created `2026-06-20-auth-design.md` (sessions DDL is consolidated in §1). |
| v1.11 | 2026-06-26 | Foreshadowed a **post-submission idempotency** column in §8 (COMPOSER §9.2/§14.3 uses a short-lived/transient dedupe; no column committed in v1). Doc-only; no table shape change. |
| v1.10 | 2026-06-26 | Added `oauth_identities.avatar_url` (`VARCHAR(512) NULL`) as the **Phase-2** cache for OAuth avatar-import, giving `users.avatar_source` a writer (§7 #12); inline-tagged `users.avatar_source` → Phase 2 and `users.avatar_path` → Phase 3 build phases. Companion phase-plan edits (no SCHEMA shape change beyond `avatar_url`): `sessions.ip` added to the Phase-2 build (PHASE_2_PLAN P2-08 / §7.1 grp 4), avatar uploads + `users.avatar_path` given a Phase-3 owner (PHASE_3_PLAN P3-12), and the 90-day IP-retention purge job assigned to PHASE_3_PLAN P3-05 — resolving review findings 1/2/4/5. |
| v1.9 | 2026-06-26 | Annotated `sessions.ip` → **Phase 2** (login-IP/ban-evasion, omitted from the Phase 1 migration like `posts.ip`); recorded in reconciliation #11. No shape change to the final consolidated table. |
| v1.8 | 2026-06-26 | Added a §6 pointer to the new **PHASE_1_MIGRATIONS.md** (the authoritative Phase‑1 column cut + migration order). No schema change. |
| v1.7 | 2026-06-26 | **Status-truth pass (nothing is built yet):** removed "shipped" wording implying existing code — the `0005_sessions.sql` / `0007_moderation_log.sql` references (§7 #7, table index, v1.1 entry) are **target** migrations, and §7 #8 now says the **planned** admin console. No DDL/scope changes. |
| v1.6 | 2026-06-26 | Consistency pass vs the phase plans: corrected §6 — only Phases **1–2** are fully consolidated; **Phase 3 is partial** (its TOTP/appeals/automation/`drafts`/bookmark/custom-field/webhook-ledger tables are schema *gaps* in PHASE_3_PLAN §8.2, not specced DDL) and Phases 4–7 hold *requirements*, not DDL. Tagged forward-phase columns with their real build phase as new §7 reconciliation #11 (`onboarded_at` → Phase 3; digest columns, `ft_*` FULLTEXT indexes, and `posts.ip` → Phase 2), and moved `posts.ip`'s build from Phase 1 to the Phase 2 migration (§7 #10) so it has an owner. Header bumped (was v1.4, behind its own v1.5 row). |
| v1.5 | 2026-06-26 | Consistency pass: set `users.allow_dms` default to `'members'` (was `'everyone'`) to match DECISIONS §5 #8; added `posts.ip VARBINARY(16) NULL` to give the "store post IPs" decision (DECISIONS §4 #5) a schema home, recorded as §7 reconciliation #10; flagged the §5 `reputation_events` block as a non-canonical Phase-4 preview (committed DDL owned by PHASE_4_PLAN §8.2). |
| v1.4 | 2026-06-26 | Added `email_deliveries.idempotency_key` (`VARCHAR(191) NULL`, `UNIQUE KEY uq_deliv_idem`) to close the DESIGN §9.6 / PHASE_2 Milestone-0 idempotency gap; recorded as §7 reconciliation #9; bumped the stale header (was "v1.2 · 2026-06-21"). |
| v1.3 | 2026-06-25 | Consistency pass: extended the §6 phase map to the full seven-phase delivery plan with a DESIGN §13 crosswalk; moved `attachments` to Phase 3 and `reputation_events` to Phase 4 to match the delivery plans; clarified the "Phase" column note. |
| v1.2 | 2026-06-21 | Added `board_slug_history` as the Phase 1 alias table for Admin Console board slug changes, preserving old `/c/{slug}` links with 301 redirects. |
| v1.1 | 2026-06-21 | Added the `0007_moderation_log.sql` table to the canonical Phase 1 schema (planned); widened `moderation_log.target_type` to include `category` and `setting`; recorded the actor FK (`ON DELETE SET NULL`); moved `moderation_log` into the explicit Phase 1 build cut. |
| v1.0 | 2026-06-19 | Initial consolidation of all 37 tables from DESIGN/USER/ADMIN/COMPOSER/COMMUNITY into one reference; folded "additions to existing tables" into each definition; recorded 7 reconciliation decisions (§7) and foreshadowed-but-unspecced schema (§8); added a per-phase build cut (§6). |
