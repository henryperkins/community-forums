# RetroBoards — Community Layer Design

**Status:** v0.3 · **Owner:** Henry (lakefrontdigital.io) · **Last updated:** 2026-07-12
**Companion to [DESIGN.md](DESIGN.md), [ADMIN.md](ADMIN.md), [USER.md](USER.md), [COMPOSER.md](COMPOSER.md).** This is the third "pass" — the **community / social layer** that earlier docs deferred. Same conventions (P0/P1/P2; `Done (mockup)` / `Planned` / `Live`; vanilla PHP + MySQL, server-rendered + progressive enhancement).

## Scope & stance

This layer is deliberately **lightweight and Twitter-like** (Henry's call), not a heavy gamification engine. It makes the community feel social and motivating without turning participation into a treadmill.

**In scope:** reputation (a simple public appreciation count), reactions/likes, **following/followers**, an **activity feed**, a **minimal badge set**, and **light leaderboards**.

**Deliberately NOT in scope (and why):**

- **No Discourse-style trust levels that gate abilities.** We do **not** add a 0–4 ladder that unlocks powers. The only progression gate stays the existing **new-user anti-spam throttle** (ADMIN.md §3.8) — reputation is a *social* signal, never a permission. This keeps the model simple and avoids "grind to unlock."
- **No rich badge taxonomy, no point store, no streaks/loss mechanics.** A small set of honest badges only.
- **No competitive pressure by default.** Leaderboards are light and opt-out; nothing shames low activity.

> This pass finalises the stubs other docs left open: reputation (DESIGN.md §6.16), profile rank/badges (USER.md §5.1, §5.5), and the "community memory" gestures (DESIGN.md §6.18).

## Contents

1. Overview & Principles
2. Reputation
3. Reactions & Likes
4. Following & Followers
5. The Activity Feed (vs the Inbox)
6. Badges
7. Leaderboards
8. Community Profile Elements
9. Notifications Integration
10. Anti-Abuse & Wellbeing
11. Data Model
12. Permissions & Trust (what we intentionally don't gate)
13. Cross-Doc Deltas
14. Phasing & Open Questions
15. Changelog

---

## 1. Overview & Principles

The Community-Inbox thesis (DESIGN.md) splits cleanly: the **inbox** is *triage* (what needs me), while the **community layer** is *connection and discovery* (who I follow, what they're saying, what I've earned). Both sit on the same durable topics.

**Principles**

1. **Humane gamification.** Recognition, not addiction. No dark patterns: no streak-loss guilt, no daily-login pressure, no public shaming of inactivity.
2. **Social, not competitive.** The default experience celebrates contribution; competition (leaderboards) is opt-in and gentle.
3. **Reputation is a signal, never a gate.** It shows appreciation; it never unlocks powers (that's the new-user throttle's job, and moderation's).
4. **The feed complements the inbox — it doesn't replace it.** Following is for discovery; triage stays in the inbox.
5. **Cheap and durable.** Lightweight enough to run on a single VPS without specialised infrastructure; everything derives from data we already store.

### 1.1 Thread Intelligence and community memory

The Phase 4 community-memory foundation is human-controlled: sourced manual
summaries, curated related topics, wiki revisions, and deterministic return
context. ADR 0019 adds a bounded Thread Intelligence path for AI-generated
**Living Briefs** and related explanations on sufficiently active public
threads. `community_memory` and `automated_context` graduated together to
default-on on 2026-07-12 and remain independently reversible.

For members, a Living Brief is navigational context, not canonical authority. It
shows AI/curator attribution, version/time, and current readable sources; an
unsafe source suppresses the generated content. No empty brief renders before a
manual or AI publication, and failure/budget/pause preserves the last safe
version. Private content and member-specific return context never go to the
provider.

Curators (admins and in-scope board moderators) retain the final editorial
controls: publish/edit a sourced manual version, refresh, retire, restore, and
explicitly pause/resume automation. A human edit is the next generation's
baseline. Retirement pauses automation, restoration does not resume it, and
curated relationships outrank AI overlays. Provider processing, provenance,
retention, and recovery are detailed in USER.md §4.9, ADMIN.md §3.10, ADR 0019,
and `docs/runbooks/thread_intelligence.md`.

## 2. Reputation

A single, honest, public number: **how much your contributions have been appreciated.** This finalises the "simple, Twitter-like" reputation folded in at DESIGN.md v0.2.

### 2.1 How it accrues

- **`users.reputation` = lifetime reactions received** across your posts (each reaction = **+1**). This reuses the existing reactions system (DESIGN.md §8) — the reactions *are* the likes, so we need no separate "Like" primitive. *(This settles DESIGN.md open question #15.)*
- **Accepted/"solved" answers** (DESIGN.md §6.18) are worth a small bonus (e.g. +5) — answering questions well is the most valuable contribution.
- **No decay, no negative reputation.** Removing a post or reaction recomputes downward naturally (it's derived); we never punish with rep loss as a mechanic.

### 2.2 Display

- A small number on the **profile** and optionally beside the username in posts (a quiet karma indicator, like a like-count — not a giant badge).
- Maintained as a **denormalised counter** (`users.reputation`), updated on reaction add/remove and post delete/restore, so reads are free. A lightweight `reputation_events` ledger (§11) is optional for auditing/recompute.

### 2.3 What reputation does *not* do

It does **not** grant abilities, lift limits, or gate features. It's a social signal only (Principle 3). Established-member perks (signatures, links) come from the **post-count/age new-user gate** (ADMIN.md §3.8 / USER.md §5.3), not reputation.

## 3. Reactions & Likes

Reactions are the social primitive and the input to reputation.

- The mockup already has multi-emoji reactions (🔥 😂 💯 …). We **keep them** — they're fun and expressive — and **every reaction received counts as +1 reputation** (§2.1). No need to privilege a single heart.
- **One reaction per (user, post, emoji)** (the `reactions` unique key, DESIGN.md §8); toggling removes it.
- Reacting is a P1 feature (persisted); it's already in the mockup visually.
- **Self-reactions don't count** toward reputation (you can't like your own posts for rep).

## 4. Following & Followers

The Twitter-like social graph — the heart of this pass.

### 4.1 What you can follow

| Target | Effect | Phase |
|---|---|---|
| **A user** | Their new threads/posts appear in your **Following feed** (§5); optional "new post from someone you follow" notification (low-priority/digest). | P1 |
| A tag or board | Surfaces that area in your feed (overlaps "Watching", USER.md / DESIGN.md §6.18). | P2 |

### 4.2 Mechanics

- **Asymmetric** (Twitter-style): following someone doesn't require them to follow back.
- **Follower / following counts** on the profile; lists are viewable subject to profile privacy (USER.md §4.7).
- **Follow / Unfollow** button on profiles and (P2) on hover cards.
- **A new-follower notification** (in-app; email per the user's prefs).
- **Privacy & safety:** blocked users can't follow you; a private/members-only profile limits who sees your follower lists; you can remove a follower (P2).
- **No follow limits/ratios** gimmicks; basic rate-limiting only to stop follow-spam bots (ties to new-user throttle).

Backed by a `follows` table (§11).

## 5. The Activity Feed (vs the Inbox)

A clear split keeps the product coherent:

- **Inbox** (DESIGN.md) = *triage of what concerns you* — replies to you, mentions, watched/assigned topics.
- **Following feed** (this layer) = *discovery from who you follow* — a reverse-chronological stream of new threads and notable posts from the users (and P2: tags/boards) you follow. Each item is **topic-anchored**: it links straight to the durable thread, so the feed feeds the forum rather than competing with it.

The left rail carries both entry points ("Inbox" and "Following"). A feed item shows who + what (started a thread in `#board` / replied in {thread}), a snippet, time, and reactions; it respects blocks and profile privacy.

**Profile activity** (finalising the USER.md §5.1 stub): a member's own profile has Overview / Threads / Posts / Reactions tabs — their recent activity.

**Implementation (lightweight):** the feed is a **query at read time** over `follows` + recent posts (paginated, lightly cached) — no materialised fan-out feed table needed at our scale on a single VPS. If volume ever demands it, fan-out-on-write is a P2 swap behind the same interface. An optional global "Latest" feed (newest public threads) is P2; the board lists already cover most of that need.

## 6. Badges

A **small, honest** set — recognition, not a trophy farm. Badges are binary (earned or not); no points, levels, or streaks.

| Badge | Earned for | Award |
|---|---|---|
| Welcome | Verifying your account | auto |
| First Post / First Thread | Your first reply / topic | auto |
| Conversation Starter | Starting N threads | auto |
| Appreciated | Reaching 100 likes received | auto |
| Well-Liked | Reaching 1,000 likes received | auto |
| Problem Solver | Your first accepted/solved answer | auto |
| Trusted Answerer | N accepted answers | auto |
| Anniversary | One year as a member | auto |
| Staff / Founder | Role or early-member recognition | manual (admin) |

- **Automatic** badges award on their triggering event (post created, reaction milestone, accepted answer, anniversary job). **Manual** ones are admin-granted.
- **Display:** a compact badge row on the profile with hover tooltips — not a giant case.
- Admin-defined **custom badges** are **P2**; v1 ships this fixed set. Backed by `badges` + `user_badges` (§11).

## 7. Leaderboards

Gentle and **opt-out**, never in your face.

- A **"Top Contributors"** page: users ranked by reputation, with a time filter (this week / month / all-time). Optionally scoped per board.
- It's a **page you choose to visit**, not a banner on the home inbox. Users can **hide from leaderboards** (a privacy preference).
- **No prizes or competitive mechanics** — recognition only.
- *Implementation:* all-time is trivial (order by `users.reputation`); time-windowed ranking uses the `reputation_events` ledger or a periodic aggregation job (**P2**).

## 8. Community Profile Elements

This finalises the profile (USER.md §5). The profile now carries the full community surface:

- **Reputation** number (§2) and **badges** row (§6).
- **Followers / Following** counts + a **Follow / Unfollow** button (§4); lists subject to privacy.
- **Activity tabs** (§5).
- **Title / rank — resolved, lightweight & cosmetic.** A friendly title derived from simple reputation/post-count thresholds — e.g. **New → Member → Regular → Veteran → Legend** — or **admin-assigned**. It is **purely flavour**: it grants no powers and gates nothing (§12). Admins can override any user's title.
- **Actions:** Follow · Message (DM) · Block · Report.

> This closes the USER.md §5.5 "rank stub": the title exists and is shown, derived from thresholds, admin-overridable, and deliberately **non-functional** (no ability gating).

## 9. Notifications Integration

Reuses the notification system (DESIGN.md §6.10, §8.3); new kinds, member-controlled (USER.md §4.6):

| Trigger | Default channel | Notes |
|---|---|---|
| New follower | In-app (email opt) | "X started following you." |
| Your answer accepted ("solved") | In-app + email | High-value; also awards rep + a badge. |
| Badge earned | In-app | Quiet celebration. |
| New post from someone you follow | **Off by default** (or digest) | Prevents feed-notification spam; opt-in. |
| Reputation milestone | **Off by default** | Optional gentle "you reached 100 likes" (§14). |

The `notifications.type` enum gains `follow`, `badge`, `solved` (canonical column is `type`, not `kind` — see SCHEMA.md). Defaults are deliberately quiet — recognition shouldn't nag.

## 10. Anti-Abuse & Wellbeing

**Anti-abuse** (ties to ADMIN.md §3.8):

- **Reputation gaming** (vote-rings, reaction farming, alt-boosting): self-reactions never count; reactions are rate-limited; coordinated reaction patterns flag to mods. Because reputation is **derived**, removing abusive reactions/posts auto-corrects it. Reputation can't be purchased.
- **Follow spam:** follows are rate-limited; bot signals feed the new-user throttle.
- **Badge farming:** badges map to real milestones; manual badges are admin-only.
- **Moderator levers:** hide a user from leaderboards, clear a manually-granted badge, and (via removing posts/reactions) reduce inflated reputation. An Admin can zero reputation in egregious cases — **audited** (ADMIN.md §3.6). No routine manual rep editing.

**Wellbeing (humane design — non-negotiable):** no streaks, no daily-login pressure, no loss-aversion mechanics, no public shaming of low activity; leaderboards are opt-out; reputation never goes negative. The system recognises contribution positively and never manufactures anxiety to drive engagement.

## 11. Data Model

```sql
-- Asymmetric follow graph (user→user in v1; tag/board P2)
CREATE TABLE follows (
  user_id     BIGINT UNSIGNED NOT NULL,            -- the follower
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
  PRIMARY KEY (id),
  UNIQUE KEY uq_badge_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Awarded badges
CREATE TABLE user_badges (
  user_id    BIGINT UNSIGNED NOT NULL,
  badge_id   BIGINT UNSIGNED NOT NULL,
  awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  awarded_by BIGINT UNSIGNED NULL,                 -- set for manual grants
  PRIMARY KEY (user_id, badge_id),
  CONSTRAINT fk_ub_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ub_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional reputation ledger (audit + time-windowed leaderboards). users.reputation is the canonical counter.
CREATE TABLE reputation_events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  delta       INT NOT NULL,
  reason      ENUM('reaction','solved','adjust') NOT NULL,
  source_type ENUM('post','thread') NULL,
  source_id   BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_repev_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Additions to existing tables / notes:

| Table | Change | Why |
|---|---|---|
| `threads` | `accepted_answer_post_id BIGINT UNSIGNED NULL` | "Solved" answer (rep bonus + Problem Solver badge); also the DESIGN.md §6.18 "Solved" status. |
| `users` | `reputation` (already present) = Σ reactions received (§2) | Denormalised; no new column. |
| `users` | `title` (already present) = cosmetic rank from thresholds (§8) | No new column. |
| Leaderboard opt-out | stored in `user_preferences.prefs` (USER.md §4.7) | No new column. |
| The **feed** | derived (query over `follows` + recent posts) | No table in v1. |

## 12. Permissions & Trust (what we intentionally don't gate)

To be explicit: **there are no Discourse-style trust levels.** Reputation, titles, and badges are **cosmetic/social** and unlock nothing. The only progression gate in the product is the **new-user anti-spam throttle** (ADMIN.md §3.8), which lifts after a small post-count/age threshold — independent of reputation. Moderation authority comes solely from **role** (ADMIN.md §2). If heavier trust mechanics are ever wanted, they would attach to the existing capability model (ADMIN.md §2.6) — but that is deliberately **out of scope** for this lightweight layer.

## 13. Cross-Doc Deltas

- **DESIGN.md** — finalises §6.16 reputation (resolves open question #15: reputation = Σ reactions received, +1 each, no separate Like). Gives §6.18 "Solved" a concrete home (`threads.accepted_answer_post_id`) and §6.19 owns the graduated Thread Intelligence contract.
- **USER.md** — completes the profile community elements (§5.1) and resolves the rank/title stub (§5.5) as cosmetic. Adds follow/badge/solved notification types (§4.6), a leaderboard opt-out privacy pref (§4.7), and the Living Brief processor/provenance disclosure (§4.9).
- **ADMIN.md** — reputation/badges/leaderboards add moderation levers (§10 here) but **no** new role gating; §3.10 owns Thread Intelligence operator and curator recovery.
- **Schema** — new: `follows`, `badges`, `user_badges`, `reputation_events` (optional); `threads.accepted_answer_post_id`.

## 14. Phasing & Open Questions

### 14.1 Phasing

- **P1** (priority tier → **delivery Phase 2**) — reputation (reactions→rep, displayed); reactions persisted; **following/followers** (user→user) + **Following feed** (query-time) + new-follower notification; the **fixed badge set** (auto-awarded); cosmetic **titles**; **all-time leaderboard**; **accepted/solved answers** (mark + rep bonus + Problem Solver badge).
- **P2** (priority tier → **delivery Phase 4+**) — follow tags/boards; time-windowed leaderboards (`reputation_events`); admin-defined custom badges; global "Latest" feed; remove-a-follower; follow-activity notifications/digest; fan-out feed if scale demands; community-memory (summaries/related/wiki/split-merge).

The human-controlled community-memory foundation and Thread Intelligence
implementation now exist. ADR 0019's follow-on graduation made both owning
feature defaults `true` on 2026-07-12 without rewriting the original Phase 4
acceptance boundary.

### 14.2 Open questions

| # | Question | Owner | Lean |
|---|---|---|---|
| 1 | Reputation-milestone notifications ("you hit 100 likes")? | Product | Skip / opt-in (humane) |
| 2 | Titles derived from reputation, post-count, or both? | Product | **Resolved** (§8) — reputation/post-count thresholds, admin-overridable |
| 3 | Who can mark a thread "solved"? | Product | **Resolved** — OP + moderators (built in Phase 2; PHASE_2_PLAN §6) |
| 4 | Following feed: new threads + authored posts only, or every reply? | Product | New threads + authored posts (less noise) |

## 15. Changelog

| Version | Date | Notes |
|---|---|---|
| v0.3 | 2026-07-12 | Added §1.1 and reconciled the Living Brief member and curator workflows, processor boundary, provenance, retention, last-good behavior, and joint default-on graduation with independent rollback pins. |
| v0.2 | 2026-06-26 | Consistency pass: relabeled §14.1 "P1/P2" with their delivery phases (P1 priority → Phase 2, P2 priority → Phase 4+) to remove the priority-vs-phase ambiguity; marked §14.2 rows 2 (titles) and 3 (who marks "solved") **Resolved**, matching §8 and the Phase 2 build (DECISIONS §8 updated to match). |
| v0.1 | 2026-06-19 | Initial community-layer design — **lightweight / Twitter-like** (no Discourse trust-level gating). Reputation (Σ reactions received, resolves DESIGN.md Q15); reactions/likes; following/followers + activity feed (vs the inbox); a minimal fixed badge set; light opt-out leaderboards; cosmetic titles (resolves the rank stub); notifications integration; anti-abuse & humane-design wellbeing rules; data model (`follows`, `badges`, `user_badges`, `reputation_events`, `threads.accepted_answer_post_id`); explicit no-trust-gating stance; phasing & open questions. |
