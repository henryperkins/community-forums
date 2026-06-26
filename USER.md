# RetroBoards — User Account, Preferences & Profile Design

**Status:** v0.10 · **Owner:** Henry (lakefrontdigital.io) · **Last updated:** 2026-06-26
**Companion to [DESIGN.md](DESIGN.md) and [ADMIN.md](ADMIN.md).** DESIGN.md is the source of truth; ADMIN.md owns the operator surface; **this doc owns the member's own surface** — how a person signs in, configures their account, tailors their experience, and presents themselves. Same conventions (P0/P1/P2/P3; `Done (mockup)` / `Planned` / `Live`; InnoDB / `utf8mb4`).

## Scope

In scope: **third-party login**, **account settings** (security, email/password, sessions, data), **user preferences** (theme, reading options, board organization, bookmarks, notifications, privacy), and **user profiles** (avatars, signatures, bio, title).

**Explicitly deferred to the upcoming "community" pass** (the third beat): reputation systems, badges/trophies, following/followers, activity feeds and leaderboards, social graph, trust levels. This doc designs the *individual's* surface; the community pass designs how individuals relate. Where a profile element foreshadows community features (e.g. rank/title), it is stubbed here and finished there.

> **Naming:** **Resolved (v0.4): the role is "User"** across all docs (DESIGN.md updated to match); "member" appears only casually in prose.

## Contents

1. Overview
2. Authentication & Third-Party Login
3. Account Settings
4. User Preferences
5. User Profiles
6. Key Flows
7. Data-Model Additions (delta to DESIGN.md §8)
8. Roadmap Delta (user/account phasing)
9. Open Questions
10. Changelog

---

## 1. Overview

The member surface has three jobs: **get you in** (low-friction, trustworthy authentication), **let you tune it** (settings and preferences that make the forum *yours*), and **let you show up** (a profile that builds identity). The guiding principle from DESIGN.md applies throughout: familiar patterns, low friction, progressive disclosure. Account and profile changes are self-serve wherever safe; anything sensitive (email, password, deletion) is gated and verified.

## 2. Authentication & Third-Party Login

### 2.1 Methods

| Method | Priority | Status | Notes |
|---|---|---|---|
| Email + password | P0 | Planned | Baseline, specified in DESIGN.md §6.6. Argon2id hashing. |
| **Sign in with Google** | P1 | Planned | OIDC. Email provided + verified. |
| **Sign in with Apple** | P1 | Planned | OIDC. Private-relay email; name/email returned **only on first authorization**. |
| **Sign in with GitHub** | P1 | Planned | OAuth2. Email may be private/unverified — must request `user:email` and check. |
| Passkeys / WebAuthn | P2 | Planned | Modern passwordless; later. |

Email/password remains available even when OAuth exists, so no member is locked to a provider.

### 2.2 OAuth architecture — pluggable providers

Third-party login is built as a **provider abstraction** (and slots into the auth-provider extension point in ADMIN.md §8.3, so new providers ship as config/plugins, not core forks). Each provider implements a small interface:

```
interface OAuthProvider {
  redirectUrl(state, pkceChallenge): string      // begin
  exchange(code, pkceVerifier): TokenSet          // code → tokens
  identity(TokenSet): {                            // normalised identity
    provider, provider_user_id, email, email_verified, display_name, avatar_url
  }
}
```

The core handles the shared mechanics (the `state` anti-CSRF param, **PKCE**, nonce, redirect/callback routing, identity normalisation, account resolution). Providers only map their quirks into the normalised identity. We store **identities**, never provider passwords; provider access/refresh tokens are **not** persisted unless a feature needs them (then encrypted at rest).

### 2.3 Account resolution (the core decision tree)

On a successful provider callback, resolve to a local account:

```
identity = provider.identity(tokens)

1. If an oauth_identity row exists for (provider, provider_user_id):
      → log in that user.                                  # returning user
2. Else if a logged-in user is linking accounts:
      → attach identity to the current user (§2.4).         # explicit link
3. Else if identity.email is present AND email_verified
      AND a local user has that verified email:
      → DO NOT auto-merge silently. Prompt:                 # collision
        "An account with this email exists — log in to link it,"
        protecting against provider-email spoofing.
4. Else:
      → create a new user from the identity (§2.5).         # new signup
```

Auto-linking by email is only ever *offered*, never done without the user proving control of the existing account — an unverified or attacker-controlled provider email must not take over an account.

### 2.4 Linking & multiple identities

- A user may link **several** providers plus email/password; all are rows in `oauth_identities` (§7) tied to one account.
- **Unlink** is allowed only while **at least one login method remains** (another provider or a set password). Removing the last method is blocked with a prompt to set a password first.
- OAuth-only accounts can **"set a password"** at any time to add the email/password method.

### 2.5 New-account creation from a provider

- Create the user; mark email verified **iff** the provider asserts a verified email.
- **Username:** suggest from `display_name`/email local-part, ensure uniqueness, and let the user confirm/change on a brief post-signup step (don't silently assign an ugly handle).
- Import the provider **avatar_url** as the initial avatar (user can change it; see §5.2).
- Apply the same anti-abuse gates as email signup (new-user throttle, ADMIN.md §3.8).

### 2.6 Provider specifics

| Provider | Protocol | Email | Verified? | Key quirks to handle |
|---|---|---|---|---|
| Google | OIDC | Yes | Yes | Cleanest case. Use `sub` as `provider_user_id`. |
| Apple | OIDC (Sign in with Apple) | Maybe **private relay** | Yes | **Name & email returned only on first auth — capture immediately.** Relay address forwards email; user may hide real email. Handle the relay as the email of record. |
| GitHub | OAuth2 | Maybe private | **Maybe not** | Request `user:email`; fetch the primary email; **treat unverified GitHub email as unverified** (don't satisfy collision-merge on it). Use the numeric `id` as `provider_user_id`. |

### 2.7 Security

- **PKCE + `state` + `nonce`** on every flow; reject callbacks failing any.
- Provider email is trusted for verification **only** when the provider marks it verified; otherwise the user must verify via our own email flow before privileged actions.
- **Revocation:** unlinking a provider revokes our stored tokens (if any) and the identity row.
- **Banned/suspended** users are blocked at resolution regardless of provider (state-first, ADMIN.md §2.4) — OAuth is not a ban bypass.
- Apple relay emails and "hide my email" are respected; we never expose a user's real email obtained via a provider.
- Rate-limit callback endpoints; log auth events to the user's security activity (§3.3).

### 2.8 Edge cases

- **Provider returns no email** (possible with Apple/GitHub) → proceed with identity but prompt for an email to enable notifications/recovery; mark unverified until confirmed.
- **Provider email later changes** → identity is keyed on `provider_user_id`, not email, so login still works; offer to update the contact email.
- **Provider disabled by admin** → existing linked users fall back to other methods; show a clear message if it was their only method (prompt password reset via email).
- **Last-identity removal / orphan prevention** → enforced in §2.4.

## 3. Account Settings

### 3.1 Settings information architecture

A `/settings` area with a left-nav (mirrors the admin Console pattern, ADMIN.md §9, but for one's own account). Sections:

| Section | Contains | Covered in |
|---|---|---|
| **Account** | Display name, username, email, quick links to avatar/signature/bio | §3.2 |
| **Security** | Password, 2FA, sessions/devices, security activity | §3.3 |
| **Connections** | Linked third-party logins | §3.4 / §2.4 |
| **Preferences** | Appearance, reading, board organization, composing | §4 |
| **Notifications** | Per-type × per-channel controls | §4.6 |
| **Privacy** | Profile visibility, presence, DMs, block list | §4.7 |
| **Data & Account** | Export, deactivate, delete | §3.5 |

### 3.2 Account

| Field | Behaviour |
|---|---|
| Display name | Free-text, length-capped, profanity-filtered (optional). Changeable anytime. |
| **Username** | Change **allowed but rate-limited** (e.g. once / 30 days); old handle reserved for a period and `/u/{old}` 301-redirects; change history kept for moderation. Uniqueness enforced. |
| **Email** | Change requires verifying the **new** address before it becomes active; the old address is notified of the change (security). |
| Avatar / Signature / Bio | Editable here or on the profile (§5); same data. |

### 3.3 Security

- **Change password** (requires current password). **Set password** for OAuth-only accounts (§2.4).
- **Two-factor (TOTP)** with recovery codes — **P3 (delivered in Phase 3)** (DECISIONS §5 #12).
- **Active sessions & devices:** list each session (device, browser, IP/region, last active, current) with **revoke** per-session and **"log out everywhere else."** (Backed by the `sessions` table, DESIGN.md §8.)
- **Security activity:** recent logins, new-device sign-ins, password/email changes, provider link/unlink — also surfaced as notifications.
- **Recovery:** email-based password reset; recovery codes if 2FA enabled.

### 3.4 Connections

Manage linked logins (Google/Apple/GitHub + email/password): add a provider, remove a provider, see when each was linked and last used. The **keep-at-least-one-method** rule (§2.4) is enforced here.

### 3.5 Data & Account

- **Export my data** (self-serve): request → generated archive → download. Mirrors the admin export (ADMIN.md §5.5) but user-initiated.
- **Deactivate** (reversible): hides the profile and posts attribution ("Inactive user") without deleting; user can reactivate by logging in.
- **Delete account** (self-serve): confirm → soft-delete → grace period → purge. Per policy, the user's posts are **anonymised to a "Deleted user" tombstone** by default (preserving thread integrity) rather than removed, with PII purged. Consistent with ADMIN.md §5.5.

## 4. User Preferences

These make the forum *theirs*. Defaults are inherited from site settings (ADMIN.md §6/§9); the user overrides what they care about.

**User preferences are how the global community becomes a personal inbox:** what I follow, what interrupts me, what I hide, how dense the interface feels, and how I compose (the Community-Inbox thesis, DESIGN.md).

### 4.1 Appearance

| Preference | Options |
|---|---|
| Theme | System · Light · Dark |
| Skin | Default (Hybrid) · Retro 2002 (when available, P2) |
| Density | Comfortable · Compact |
| Font size | Small · Default · Large |
| Reduce motion | On · Off (also respects OS `prefers-reduced-motion`) |

Appearance prefs are **client-applied** (instant) and override the site default for that user only.

### 4.2 Reading

| Preference | Options / notes |
|---|---|
| **Threads per page** | 25 · 50 · 100 |
| **Posts per page** | 10 · 20 · 40 |
| Default thread sort | Last post · Newest · Most replies |
| Open threads at | Last-read position · Top |
| Timezone & time format | Auto-detect; 12h/24h; relative ("2m ago") vs absolute |
| Show signatures | On · Off |
| Show avatars | On · Off |
| Show reactions | On · Off |
| Media | Autoplay off by default; lazy-load; expand inline vs click |
| Links | Open external links in a new tab (on/off) |

Pagination and sort are **server-enforced** (they shape queries); the rest are display toggles.

### 4.3 Board organization

The member shapes their own sidebar:

- **Favorite / pin boards** → surfaced in a "Favorites" group at the top of the sidebar.
- **Mute / hide boards** → excluded from the sidebar and from unread counts (useful for boards you never read).
- **Reorder** favorites; **collapse** categories with the state remembered per user.
- **Custom groups / folders** of boards — **P2**.

Backed by `user_board_prefs` (§7).

### 4.4 Bookmarks & saved

> **Reconciliation:** "bookmark a thread" and the **star** from DESIGN.md (`thread_user.is_starred`) are the **same action** — we surface it as **Save/Star** consistently and do not create a parallel concept. "Subscribe" (get notified) stays distinct from "Save" (bookmark for later).

- **Saved threads** = starred threads (`thread_user.is_starred`).
- **Favorite boards** = `user_board_prefs.is_favorite` (§4.3).
- A unified **"Saved"** view lists saved threads and favorite boards.
- **Bookmark folders / tags** for organising saves — **P2**.

### 4.5 Composing

- Default post format (Markdown — the site's canonical markup; DESIGN.md §14, DECISIONS §3 #2).
- **Attach my signature** by default (per-post override).
- Draft auto-save behaviour; **Enter to send** vs Enter-for-newline preference (the composer default is Enter-to-send).

### 4.6 Notification preferences (member control)

The member controls *what reaches them*; ADMIN.md §7 owns templates and routing. A matrix:

| Event type | In-app | Email | Digest |
|---|:--:|:--:|:--:|
| Reply to my thread | ✓ | ✓ | — |
| Reply after my post (participating) | ✓ | opt | digest |
| @mention | ✓ | ✓ | — |
| Reaction to my post | ✓ | — | digest |
| Direct message | ✓ | ✓ | — |
| Subscribed-thread activity | ✓ | opt | digest |
| System / announcement | ✓ | ✓ | — |

Plus: **email digest cadence** (off / daily), **quiet hours**, **per-thread mute**, and a global "pause all email" switch.

**Subscriptions (v0.2).** Beyond the per-type matrix above, a member can **subscribe to a specific thread or a whole board**, each with independent **In-app** and **Email** toggles (a Bell control on the thread page and the board header — DESIGN.md §6.10). A dedicated **`/settings/notifications`** page lists every active subscription with per-row toggles and one-click unsubscribe. Subscriptions are stored in `subscriptions` (DESIGN.md §8.3), which **supersedes** the old per-thread `is_subscribed` flag.

**Per-subscription frequency & digests (v0.4).** Each subscription's frequency is **Instant / Daily / Off**, set per board or thread — a **thread overrides its board**, and "Off" silences that target. Daily activity rolls into a **timezone-aware daily digest** sent at the member's chosen **digest hour** (with their timezone, §4.2). `/settings/notifications` also offers a **digest preview** (what the next digest will include), a **test send** to verify deliverability, and — if an address was auto-suppressed after a bounce — a **re-enable** action once the inbox is working again. (Infra in ADMIN.md §7.6.)

### 4.7 Privacy

| Preference | Options |
|---|---|
| Profile visibility | Public · Members-only |
| Show online presence | On · Off |
| Allow DMs from | Everyone · Members · No one |
| Discoverable by email | On · Off |
| **Block list** | Blocked users can't DM or @mention you, and their notifications to you are suppressed; optionally hide their posts behind a "blocked" stub. |

### 4.8 Storage & application

Preferences live in `user_preferences` (§7) with defaults inherited from site settings. Client-only prefs (theme, density, font) apply immediately in the browser; server-side prefs (pagination, sorts, privacy, DMs, blocks) are enforced server-side so they hold across devices and can't be bypassed.

## 5. User Profiles

### 5.1 Profile page anatomy (`/u/{username}`)

- **Header:** avatar, display name, `@username`, title/rank, presence dot (if shown), "Member since {date}", location, last-seen.
- **Stats (light):** post count, threads started, and **reputation** — a simple, Twitter-like count of likes/reactions received (DESIGN.md §6.16). Online/offline presence shows by the name when the member allows it (§4.7). *(Badges, trust levels, and the fuller reputation system are the community pass — §5.5.)*
- **Actions:** Message · Block · Report. *(Follow is stubbed — community pass.)*
- **Tabs:** Overview · Threads started · Posts · Reactions · About.
- **Activity:** a light recent-activity list (richer feeds are a community-pass feature).
- **Signature** preview; **About** (bio/fields).

What's visible respects the profile's privacy setting (§4.7): **guests** may see a reduced public profile or none (members-only); **email is never shown** to anyone; blocked users see a limited view.

### 5.2 Avatars

| Aspect | Design |
|---|---|
| **Sources** | Generated **monogram** (the mockup default) · **uploaded image** · **Gravatar** (optional, by email hash) · **OAuth provider import** (on signup). |
| **Fallback chain** | upload → OAuth import → Gravatar → monogram (always a sensible default, never broken). |
| **Upload** | Crop-to-square UI; allowed types png/jpg/webp; max file size + dimensions; auto-resize and generate thumbnail sizes; stored via the media/CDN integration (ADMIN.md §8.7). |
| **Moderation** | Avatars are public content — reportable; a mod can remove one (reverts to monogram). NSFW/abuse handled like any content (ADMIN.md §3). |
| **Animated** | Not in v1 (performance + abuse); reconsider P2. |

`avatar_source` is tracked so we know whether to refresh from Gravatar/OAuth or keep the upload.

### 5.3 Signatures

- **Rich but limited:** same allowlist sanitiser as posts; **character cap** and a **rendered-height cap**; at most one small image; links are `nofollow`; never scripts.
- **Display:** under each post, honouring the viewer's "show signatures" preference (§4.2); oversized sigs collapse with a "show signature" toggle.
- **Anti-spam:** signatures are **disabled for brand-new accounts** until a threshold (post count / age) — a classic spam vector. Admin-configurable.
- **Moderation:** a mod/admin can edit or clear a signature (ADMIN.md §5).

### 5.4 Bio & fields

Short **bio** (length-capped, sanitised), **location**, **website** (`nofollow`), optional **pronouns**. Admin-defined **custom profile fields** are **P2** (ADMIN.md §9 Settings).

### 5.5 Title / rank (stub for the community pass)

A profile shows a **title/rank**, derived from **reputation/post-count thresholds** and **admin-overridable** (COMMUNITY §8, DECISIONS §8). A **simple reputation score** (likes received — DESIGN.md §6.16) is folded into v0.2 and shown on the profile. The richer mechanics — **badges, trophies, trust levels, and ranks-derived-from-reputation — are designed in the community pass.** Designing the storage now (`users.title`, `users.reputation`) keeps that pass additive.

### 5.6 Editing & ownership

| Self-edit (User) | Staff-controlled (Mod/Admin) |
|---|---|
| Display name, avatar, signature, bio, location, website, pronouns | Role & per-board mod scope; account status; username-change history; rank when post-count-derived |

Self-service edits are immediate (subject to sanitisation and the new-user gates); staff edits follow ADMIN.md §5 and are audited.

### 5.7 New-user onboarding (product tour)

A first-run interactive tour helps newcomers learn the Slack/email-style layout fast (DESIGN.md §6.17).

- **Library:** a lightweight client-side tour (e.g. driver.js, ~6kb, no framework peer issues) run as a singleton controller.
- **Steps (6–7):** highlight the **board rail/list**, the **thread inbox**, the **search bar**, the **quick-reply composer**, the **notification bell**, and the **"New thread"** button. Each step is plain text + a one-line tip — no screenshots.
- **Targets:** real DOM nodes via `data-tour="…"` attributes added to those regions.
- **Trigger:** on first sign-in, or first visit if `localStorage.tourCompleted !== 'v1'`. For signed-in users, the server flag **`users.onboarded_at`** (§7.2) persists completion across devices.
- **Controls:** a **"?"** button in the header replays the tour anytime; **"Skip"** is available on every step.

## 6. Key Flows

1. **Sign up with Google.** Click → Google consent → callback (verified email) → create account → confirm/choose username → land logged in. (Cleanest path.)
2. **Sign in with Apple (first time).** Apple consent → **capture name + email on this first response** (Apple won't send them again) → relay or real email stored as contact → account created/linked.
3. **Sign in with GitHub.** GitHub consent (`user:email`) → fetch primary email → if **unverified**, treat as unverified (prompt to verify before privileged actions) → log in/create.
4. **Link a second provider.** Settings → Connections → "Add Google/Apple/GitHub" → provider flow → identity attached to current account.
5. **Email collision.** A provider's verified email matches an existing local account → we **offer** to link, requiring the user to log into that account (never auto-merge) → identities joined.
6. **Change email.** Settings → Account → new email → verification sent to the **new** address → confirm → switch; old address notified.
7. **Set a password (OAuth-only).** Settings → Security → "Set password" → now email/password is an available method; unlink rules updated.
8. **Upload an avatar.** Profile/Settings → upload → crop square → save → resized/stored; fallback chain updated; reportable thereafter.
9. **Edit signature.** Settings/Profile → edit (sanitised, capped) → save; **new accounts** see "available after N posts."
10. **Organize the sidebar.** Right-click/menu a board → Favorite / Mute; drag to reorder favorites; collapse a category — all remembered per user.
11. **Tune reading.** Settings → Preferences → theme = Dark, threads per page = 50 → applies immediately (theme) and on next list load (pagination).
12. **Export / delete account.** Settings → Data & Account → export (download archive) or delete (confirm → grace period → purge, posts anonymised by default).

## 7. Data-Model Additions (delta to DESIGN.md §8)

Same conventions (InnoDB, `utf8mb4`, `BIGINT UNSIGNED` keys).

### 7.1 New tables

```sql
-- Linked third-party logins (a user may have several)
CREATE TABLE oauth_identities (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          BIGINT UNSIGNED NOT NULL,
  provider         ENUM('google','apple','github') NOT NULL,
  provider_user_id VARCHAR(191)    NOT NULL,            -- stable id from provider (sub / numeric id)
  email            VARCHAR(255)    NULL,                -- may be a relay/private address
  email_verified   TINYINT(1)      NOT NULL DEFAULT 0,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provider_identity (provider, provider_user_id),
  KEY idx_oauth_user (user_id),
  CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-user preference blob (theme, reading, notifications, composing, ...)
CREATE TABLE user_preferences (
  user_id    BIGINT UNSIGNED NOT NULL,
  prefs      JSON            NOT NULL,                  -- defaults inherited from site settings
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

-- Block list
CREATE TABLE blocks (
  user_id         BIGINT UNSIGNED NOT NULL,             -- the blocker
  blocked_user_id BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, blocked_user_id),
  CONSTRAINT fk_block_user    FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_block_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One table for short-lived tokens: email verify, email change, password reset
CREATE TABLE verifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  type       ENUM('email_verify','email_change','password_reset') NOT NULL,
  token_hash CHAR(64)        NOT NULL,                  -- store only the hash
  new_email  VARCHAR(255)    NULL,                      -- for email_change
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME        NOT NULL,
  used_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_verif_token (token_hash),
  KEY idx_verif_user (user_id, type),
  CONSTRAINT fk_verif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: username change history (redirects + moderation)
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

### 7.2 Additions to existing tables

| Table | Add | Why |
|---|---|---|
| `users` | `bio TEXT NULL`, `website VARCHAR(255) NULL`, `pronouns VARCHAR(32) NULL` | Profile fields (`location`, `title`, `signature`, `avatar_path` already in DESIGN.md). |
| `users` | `avatar_source ENUM('monogram','upload','gravatar','oauth') DEFAULT 'monogram'` | Drives the avatar fallback chain (§5.2). |
| `users` | `profile_visibility ENUM('public','members') DEFAULT 'public'`, `allow_dms ENUM('everyone','members','none') DEFAULT 'members'`, `show_presence TINYINT(1) DEFAULT 1` | Privacy gates checked **server-side per interaction** — promoted to columns (not JSON) for cheap enforcement. |
| `users` | `password_hash` becomes **NULLable** | OAuth-only accounts may have no password until they set one (§2.4). |
| `users` | `onboarded_at DATETIME NULL` | Product-tour completion, cross-device (§5.7). |

### 7.3 Reconciliation notes

- **Saved/bookmarked threads reuse `thread_user.is_starred`** (DESIGN.md §8) — no new table. Favorite **boards** use `user_board_prefs`.
- **Active sessions/devices** (§3.3) build on the **`sessions`** table, which **ships in Phase 1** (SCHEMA §7 #7 / `0005_sessions.sql`); only the device-management UI is Phase 2.
- **Notification preferences** (§4.6) live inside `user_preferences.prefs`; ADMIN.md §7 owns the templates and routing those preferences gate.
- **Subscriptions** (thread/board, with in-app/email toggles, §4.6) live in `subscriptions` (DESIGN.md §8.3) and **supersede** `thread_user.is_subscribed`.

## 8. Roadmap Delta (user/account phasing)

Mapped onto DESIGN.md §13 (whose strategic "Phase 3" and "Later (P2)" buckets subdivide into delivery Phases 3–7 — see SCHEMA §6):

- **Phase 1 (MVP) — planned, not yet built.** Email/password auth and the first member account slice: `/settings/account` updates display name, bio, and location; `/settings/security` changes password; `/u/{username}` renders a basic public profile with join date, post count, and reputation. The `verifications` table and `users.email_verified_at` ship in Phase 1, but the password-reset and change-email-verification *flows* are scheduled for **Phase 2** (sending requires the Phase 2 email worker); avatars, signatures, privacy controls, and reading prefs remain later work.
- **Phase 2 (community essentials window).** **Password-reset and registration email-verification flows** (email worker online); **OAuth (Google/Apple/GitHub)** + Connections/linking (`oauth_identities`); **board organization** (favorite/mute/reorder, `user_board_prefs`); **Saved** view; **notification preferences** matrix; **privacy** prefs + **block list** (`blocks`); **device-management UI** + security activity over the Phase 1 `sessions` table (revoke / log-out-everywhere); self-serve export/delete.
- **Phase 3 (polish).** 2FA/TOTP; **avatar uploads + Gravatar** (both ride the Phase 3 `attachments` pipeline — SCHEMA §6; monogram in Phase 1, OAuth-import with OAuth in Phase 2); bookmark folders; retro skin per-user; advanced composing prefs; deactivate (vs delete); custom profile fields (with ADMIN).
- **Later — delivery Phase 5 (ecosystem & identity).** Passkeys/WebAuthn; additional OAuth providers (generic OIDC); verified website links; richer custom fields. _(Priority tier P2; see PHASE_5_PLAN and DESIGN §13.)_

## 9. Open Questions

> **Resolved in [DECISIONS.md](DECISIONS.md) §5.** Retained below for context.

| # | Question | Owner | Blocking? |
|---|---|---|---|
| 1 | Provider set **Google / Apple / GitHub** confirmed for v1 (others later)? | Product | **Decided** — locked |
| 2 | Username **changes**: allowed at all, and cadence + redirect/reservation policy? | Product | Phase 1 |
| 3 | Treat Apple **private-relay** address as the email of record; what do we display? | Eng / Product | Phase 2 |
| 4 | **Avatar uploads** in Phase 1, or monogram-only first (defer upload moderation/storage)? | Product / Eng | Decided — monogram P1; Gravatar + uploads **Phase 3** (DECISIONS §5 #4) |
| 5 | Signature: new-user threshold value; allow an image at all? | Product | Phase 1 |
| 6 | Default **profile visibility**: public vs members-only? | Product | Decided — public (DECISIONS §5 #6) |
| 7 | Default **presence/online** visibility: on or off? | Product | Decided — on, user can hide (DECISIONS §5 #7) |
| 8 | Default **Allow-DMs**: everyone vs members? | Product | Decided — members (DECISIONS §5 #8) |
| 9 | Ship the **`sessions`** table in Phase 1 to enable device management early, or defer to Phase 2? | Eng | Decided — table Phase 1 (SCHEMA §7 #7); device UI Phase 2 |
| 10 | **Emailless OAuth accounts** (Apple hide-email / GitHub no email): require an email for recovery, or allow without? | Product / Eng | Phase 2 |
| 11 | Keep both **username + display name**, or username only? | Product | Phase 1 |
| 12 | **2FA** timing — when? | Product / Eng | Decided — P3 / **Phase 3** (DECISIONS §5 #12) |

## 10. Changelog

| Version | Date | Notes |
|---|---|---|
| v0.10 | 2026-06-26 | Wording/citation pass: dropped digest **"weekly"** cadence (§4.6 — daily-only per SCHEMA `subscriptions.frequency`); dropped **"optional"** from the Phase-1 `sessions` table (§3.3); fixed SCHEMA citations **§7.7 → §7 #7** (§7.3, §9 row 9); settled §4.5 default post format to **Markdown-canonical** (DECISIONS §3 #2, dropped the BBCode either/or); §5.5 title/rank now **reputation/post-count thresholds, admin-overridable** (COMMUNITY §8, DECISIONS §8). |
| v0.9 | 2026-06-26 | **Status-truth pass (nothing is built yet):** reworded §8 Phase 1 from "auth is live / now live" to planned, and "monogram shipped Phase 1" → "monogram in Phase 1"; reworded the v0.5 entry below from "Shipped" to "Specified (design only — not built)". No scope changes. |
| v0.8 | 2026-06-26 | Consistency pass: mapped §8's "Later (P2)" account items (passkeys, more providers, verified links, richer custom fields) to **delivery Phase 5** and noted DESIGN §13 now subdivides into Phases 3–7 (they previously had no home phase past Phase 3); added **P3** to the header conventions legend; bumped the stale header (was v0.6, behind its own v0.7 row). |
| v0.7 | 2026-06-26 | Consistency pass: set `allow_dms` default to `'members'` (§7.2) per DECISIONS §5 #8; corrected 2FA from "P2" to **P3 / Phase 3** (§3.3 and §9 row 12); gave avatar uploads a definite owner — **Phase 3** on the attachments pipeline (§8, §9 row 4); replaced the blocking-phase that read like an answer with the actual decided value in §9 rows 4/6/7/8/12. |
| v0.6 | 2026-06-25 | Consistency pass: scheduled password-reset and email-verification *flows* to Phase 2 (storage already ships Phase 1); clarified that the `sessions` table is Phase 1 (per SCHEMA §7.7) and only the device-management UI is Phase 2 (§7.3, §8, §9). |
| v0.1 | 2026-06-19 | Initial user/account/profile design. Third-party login (Google/Apple/GitHub) with pluggable providers, account resolution & linking, provider edge cases; account settings (security, sessions, data); preferences (appearance, reading, board organization, bookmarks, notifications, privacy/block list); profiles (avatars, signatures, bio, rank stub); key flows; data-model delta; roadmap delta; open questions. Community features (reputation, badges, following, feeds) deferred to the community pass. |
| v0.2 | 2026-06-19 | Folded-in features: subscription-based **notification preferences** + `/settings/notifications` (§4.6); **new-user onboarding product tour** (§5.7, `users.onboarded_at`); **simple reputation** + presence surfaced on profiles (§5.1, §5.5). A simple slice of reputation is now in v0.2; badges/trust levels remain the community pass. |
| v0.3 | 2026-06-19 | Framework integration: preferences framed as the **personal inbox** (§4); role naming **standardised on "User"**. |
| v0.4 | 2026-06-19 | Notification-completeness pass (§4.6): per-subscription **Instant/Daily/Off** frequency (thread overrides board), timezone-aware **daily digest** at a chosen hour, **digest preview**, **test send**, and **suppression recovery** (re-enable a bounced address). |
| v0.5 | 2026-06-22 | **Specified** the first self-serve account/profile slice (design only — not built): `/settings/account` updates display name, bio, and location; `/settings/security` changes password; `/u/{username}` renders a basic public profile. Email-change verification, password reset, avatars, signatures, privacy, and preferences remain deferred. |
