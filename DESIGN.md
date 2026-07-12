# RetroBoards — Product & Technical Design Document

**Status:** v0.13 · **Owner:** Henry (lakefrontdigital.io) · **Last updated:** 2026-07-12
**Stack:** PHP + MySQL (server-rendered) with progressive-enhancement JavaScript
**This document is the source of truth.** When a decision changes, update it here first. Code, tickets, and mockups defer to this file.

---

## Product Thesis — The Community Inbox

> **Outlook's productivity, Slack's conversational warmth, and Discourse's permanence.**

RetroBoards is a **Community Inbox**: a durable knowledge space with the speed and warmth of chat, the triage power of email, and the long-term structure of a forum. The default authenticated home is not a "forum homepage" — it is a **personalized forum inbox**. Three lineages combine:

- **Discourse bones** — durable topics, categories, tags, search, solved answers, moderation, canonical knowledge.
- **Slack polish** — immediacy, mentions, reactions, lightweight replies, a rich composer, conversational warmth.
- **Outlook discipline** — triage, split-pane reading, unread state, focused filters, keyboard-driven density.

The durable unit is the **topic**: the inbox is personal, the topic is durable, and the composer is immediate. This thesis is the lens for every decision below — it does not replace the spec, it explains *why* the three-pane shell, the unified composer, the triage filters, and the visible status states all belong together.

## How to use this document

- **Priorities** use MoSCoW-style tags: **P0** (must-have, MVP cannot ship without it), **P1** (should-have, fast follow), **P2** (could-have / future, design for it but don't build yet).
- **Build status** tags: `Done (mockup)` = exists in the front-end prototype only; `Planned` = specified, not built; `Live` = built and shipped on the real stack.
- Sections 1–7 are the product spec. Sections 8–11 are the technical design. Sections 12–16 are planning and reference.

---

## Table of contents

1. Overview
2. Goals & Non-Goals
3. Design Principles
4. Personas & Roles
5. Core Concepts & Information Architecture
6. Feature Catalog (with priority & status)
7. User Stories & Key Flows
8. Data Model & MySQL Schema
9. Architecture
10. Permissions Matrix
11. Non-Functional Requirements
12. Success Metrics
13. Roadmap & Phasing
14. Open Questions
15. Glossary
16. Changelog

---

## 1. Overview

RetroBoards is self-hostable forum / community software. It captures the spirit of early-2000s message-board communities (the reference point is IGN's boards circa 2002 — deep, topic-organised, identity-driven discussion) but presents it through an interface people already know: a **hybrid of Slack and email**. The goal is that a brand-new user can read, find, and post within seconds, with no forum-specific learning curve.

The product maps classic forum concepts onto familiar messaging-app patterns:

- **Boards → channels** in a left sidebar, grouped into collapsible categories.
- **Threads → an email-style inbox** in the middle column (subject, author, snippet, time, reply count, unread/star).
- **Posts → a chat-style message stream** on the right, with a composer pinned to the bottom.

Two front-end explorations are **planned as Phase 0 artifacts** (not yet created — see §13). The **hybrid app** (`app.html` + `app.css`) is the chosen direction; the **retro tribute** (`index.html`, `board.html`, `thread.html`, `styles.css`) is kept as reference and a possible optional theme. Working brand name is **"RetroBoards"** — a placeholder, swappable throughout.

## 2. Goals & Non-Goals

### Goals

1. **Instant learnability.** A first-time visitor understands how to navigate and post without instructions, because the layout mirrors tools they use daily. Target: complete "read a thread and post a reply" in a usability test with zero guidance.
2. **Real community depth.** Support the things that made forums sticky: persistent identity, post history, reputation, signatures, pinned/locked topics, and active moderation.
3. **Self-hostable and ownable.** Run on a standard LAMP-style host. The operator owns the data; no third-party platform lock-in.
4. **Progressive, resilient front-end.** Core reading and posting work as server-rendered HTML; JavaScript enhances (live send, reactions, filters) but is never required for the basics.
5. **Clear identity state.** It is always obvious whether you are logged in or out, and what you can do in each state.

### Non-Goals (for v1)

1. **Not a real-time chat replacement.** Conversations are threaded and durable; we are not rebuilding Slack's live presence/typing-indicator stack in v1. (Revisit "live" feel in P2.)
2. **Not a pixel-perfect IGN clone.** We borrow the *spirit and IA*, not IGN's branding or exact layout.
3. **Not multi-tenant SaaS yet.** v1 is a single community per install. Multi-community/workspace is a P2 architectural consideration, not a build target.
4. **No native mobile apps.** Responsive web only; a PWA is a later consideration.
5. **No plugin/theme marketplace yet.** A theming/plugin system is P2; we will avoid decisions that make it impossible later.
6. **No federation / external import in v1.** (e.g. importing existing forum data) — later.

## 3. Design Principles

1. **Familiarity over novelty.** When in doubt, do what Slack, Gmail, or Discord do. Borrowed patterns cost users nothing to learn.
2. **Read-first, low-friction.** Guests can read everything. Friction (login) appears only at the moment of contribution, explained in context.
3. **Progressive disclosure.** Show the common path cleanly; tuck power features (moderation, advanced formatting) behind hover actions and menus.
4. **One source of identity state.** A single notion of "logged in" drives every affordance (composer vs. join-bar, top-bar identity, permissions).
5. **Own your data.** Plain stack, exportable data, no required external services for core function.
6. **Accessible and responsive by default.** Keyboard-navigable, semantic HTML, sufficient contrast, and a layout that collapses gracefully to one column on phones.

## 4. Personas & Roles

| Persona | Who they are | Primary needs |
|---|---|---|
| **Guest** | Unauthenticated visitor, often arriving from search | Read threads, evaluate the community, understand how to join |
| **User** | A registered member | Post threads/replies, react, star, DM, build identity/reputation |
| **Moderator** | Trusted member, scoped to one or more boards | Keep boards healthy: pin, lock, move, delete, handle reports |
| **Admin / Staff** | Site operator (initially Henry) | Everything: manage boards/categories, roles, site settings, all moderation |

Roles are cumulative in capability (Admin ⊇ Moderator ⊇ User ⊇ Guest). Moderator powers may be **scoped per board** (a user can moderate `#playstation-2` without moderating the whole site). See §10 for the full permissions matrix.

## 5. Core Concepts & Information Architecture

### 5.1 Concept mapping

| Forum concept | Messaging metaphor | Definition |
|---|---|---|
| Community / Workspace | The whole Slack workspace | A single RetroBoards install. One per deployment in v1. |
| Category | Sidebar section header | A named, ordered grouping of boards (e.g. "Gaming — Current Gen"). Collapsible. |
| Board | `#channel` | A topic area users post threads into (e.g. `#playstation-2`). Has a slug, description, moderators. |
| Thread (topic) | Email conversation / inbox row | A titled discussion started by a member, containing an ordered list of posts. |
| Post (reply) | Chat message | A single message in a thread. The first post is the **OP**. |
| Reaction | Emoji reaction | A lightweight emoji response to a post. |
| Star | Email star / save | A user's personal bookmark on a thread. |
| Subscription | "Get notified" | Opt-in notifications for new posts in a thread. |
| Direct Message | Slack DM | A private 1:1 (later N-person) conversation outside any board. |
| Presence | Online dot | Whether a user is currently active. |

### 5.2 Layout (the hybrid shell)

A single, full-height application screen with three columns, plus a top bar:

- **Top bar** — brand, global search, help, notifications bell, and the identity area (see §6.6).
- **Pane 1 — Sidebar (channels):** workspace header, quick filters (Threads, Mentions, Starred, Drafts), categories of boards with unread badges, and a Direct Messages section with presence dots. Sections collapse.
- **Pane 2 — Thread inbox:** the selected board's threads as scannable rows (avatar, subject, snippet, reply count, time, unread/star), with filter tabs (All / Unread / Starred / Mine) and a New Topic button.
- **Pane 3 — Conversation:** the open thread as a message stream with a composer (or a guest join-bar) at the bottom.

On screens ≤ 860px the layout collapses to one column: the sidebar becomes a slide-in drawer, and opening a thread slides the conversation over the inbox with a back button.

### 5.3 URL structure (server-rendered, SEO-friendly)

Even though the UI feels like a single-page app, every view has a real, shareable, crawlable URL rendered by the server. JavaScript enhances navigation but is not required.

```
/                          Home (board index / first board)
/c/{board-slug}            A board's thread inbox          e.g. /c/playstation-2
/c/{board-slug}?page=2     Pagination
/t/{thread-id}-{slug}      A thread (conversation)         e.g. /t/1042-vice-city-impressions
/u/{username}              A user profile
/dm/{conversation-id}      A direct-message conversation (auth only)
/search?q=...              Search results
/login  /register  /logout Auth
/settings                  Account & preferences (auth only)
/mod/...                   Moderation tools (mod/admin only)
```

### 5.4 Sample information architecture

The mockup is seeded with gaming-community sample content to evoke the reference era (categories like *Gaming — Current Gen*, boards like `#playstation-2`, `#xbox`, `#the-vault`). **This is illustrative seed data, not a fixed taxonomy** — categories and boards are admin-managed at runtime (see §6.2).

## 6. Feature Catalog

Legend: **Priority** = P0 / P1 / P2 / P3 (MoSCoW tiers, not delivery phases — DECISIONS §2). **Status** = `Done (mockup)`, `Planned`, `Live`. **Reality check (2026‑06‑26): nothing is built and no mockup artifacts exist yet, so every row below is effectively `Planned`.**

### 6.1 Navigation & Layout

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Three-pane hybrid shell | P0 | Planned | Sidebar · inbox · conversation. |
| Top bar (brand, search, bell, identity) | P0 | Planned | Search & bell are visual-only in mockup. |
| Responsive single-column mode | P0 | Planned | Drawer sidebar + list/thread slide on mobile. |
| Mobile rail as drawer (swipe-to-close) | P1 | Planned | `<md`: sidebar becomes a sheet/drawer with a sticky top bar (community name + close); `≥md` keeps the fixed rail. A header hamburger opens it; the current board name shows as the title. |
| Mobile "New thread" FAB | P1 | Planned | Bottom-of-screen floating button on mobile only; the inline button stays on desktop. |
| Larger mobile tap targets | P1 | Planned | Inbox rows ≥44px, full-bleed list, unread dot. (Swipe-revealed row actions deferred.) |
| Deep-linkable URLs per view | P0 | Planned | Server-rendered routes per §5.3. |
| Keyboard navigation & shortcuts | P1 | Planned | j/k between threads, `c`/`n` new thread, `r` reply, `Cmd/Ctrl+K` opens search. (Inside the composer `Cmd/Ctrl+K` inserts a link — COMPOSER.md §5.) |

### 6.2 Boards & Channels (sidebar)

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Categories with collapsible sections | P0 | Planned | Ordered; collapse state remembered per user (P1). |
| Boards as `#channels` | P0 | Planned | Slug, name, description. |
| Unread badges per board | P1 | Planned | Needs real read-state tracking (§8). |
| Direct Messages list + presence dots | P1 | Planned | Persistence is Planned. |
| Admin: create/edit/reorder categories & boards | P0 | Planned | Runtime management, not hard-coded. |
| Per-board permissions (who can post) | P1 | Planned | e.g. announcement boards are staff-post-only. |

### 6.3 Thread Inbox

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Thread rows (avatar, subject, snippet, count, time) | P0 | Planned | Email-style scannable list. |
| Filters: All / Unread / Starred / Mine | P1 | Planned | Client filter in mockup; server-backed when live. |
| Pinned / Hot / Locked indicators | P1 | Planned | Pinned & locked are mod actions; "hot" is derived from activity. |
| Star a thread | P1 | Planned | Per-user; persists when live. |
| Unread dot per thread | P1 | Planned | From `last_read_post_id` (§8). |
| New Topic (compose thread) | P0 | Planned | Button present; needs editor + persistence. Hidden for guests. |
| Pagination / infinite scroll | P0 | Planned | Threads list and posts list both paginate. |
| Sort tabs: Newest / Active (+ Unanswered) | P1 | Planned | **Newest** = `created_at` desc; **Active** = `last_post_at` desc; **Unanswered** toggle = `reply_count = 0`. Default: Active. Selected sort persisted in the URL (`?sort=active`). On `/` ("All boards") the tabs span all boards; on a board they're scoped to it. |

### 6.4 Conversation & Posts

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Message stream (avatar, name, time, body) | P0 | Planned | Chat-style. |
| Consecutive-message grouping | P1 | Planned | Same author within a window hides repeated avatar/name. |
| Role badges (OP, Staff) | P1 | Planned | Derived from author + thread. |
| Quote / reply-with-context | P1 | Planned | Stored via `parent_post_id` (§8). |
| Facepile of participants | P2 | Planned | Header avatars. |
| Day dividers | P2 | Planned | "Today", date separators. |
| Per-post actions (react, reply, more) | P1 | Planned | Hover toolbar. |
| Edit / delete own post | P0 | Planned | With edited-at marker; soft delete. |
| Spoiler tags | P1 | Planned | `::spoiler::` per board rules. |
| Permalink to a post | P1 | Planned | `/t/{id}-{slug}#p{post_id}`. |

### 6.5 Composer & Posting

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Composer with formatting toolbar | P0 | Planned | Toolbar visual; Enter-to-send, Shift+Enter newline wired. |
| "Posting as {you}" identity row | P1 | Planned | Reinforces logged-in state. |
| Working send (appends message) | P0 | Planned | In-memory only; server persistence Planned. |
| Post body format — **hybrid live-Markdown** | P0 | Planned | Markdown is canonical (rich surface, formats as you type); sanitised on render. Full spec: **COMPOSER.md**. |
| Sticky quick-reply composer | P0 | Planned | Pinned to the bottom of a thread (Slack-style); auto-grow textarea. **Enter** sends, **Shift+Enter** newline, **Esc** blurs, **Cmd/Ctrl+K** inserts a link (`r`/`c` focus the composer — COMPOSER.md §5). |
| Draft auto-save (per thread, per user) | P1 | Planned | Saved to `localStorage` keyed `draft:thread:{threadId}:{userId}`; restored on open, cleared on successful post. The "Drafts" quick-filter lists them. |
| Optimistic send | P1 | Planned | The new post inserts into the list immediately; rolls back with a toast on error. |
| Signed-out reply CTA | P0 | Planned | The guest **join-bar** (§6.6) is the inline "Sign in to reply" — no redirect. |
| Emoji picker | P1 | Planned | In the composer toolbar. (`@username` autocomplete **is** in scope — it lives in the composer; see the **@mentions** row below and **COMPOSER.md §6.1**.) |
| Attachments / images | P2 | Planned | Storage resolved — local-disk non-exec behind a storage interface, 5MB/image, png/jpg/webp/gif (DECISIONS §6 #6); only the **build** is deferred (Phase 3). |

### 6.6 Identity & Auth — **the logged-in vs logged-out experience**

This is a cornerstone and the most recently designed area. **Two unmistakable signals** communicate auth state:

1. **Top-bar identity.** Logged out → a "Guest" pill with **Log in / Sign up** buttons and a generic silhouette. Logged in → the user's avatar + name + green presence dot + caret, opening an account menu (Set status, Profile, Preferences, **Log out**), plus a notification dot on the bell.
2. **The composer.** Logged out → replaced by a "*You're browsing as a guest — Log in to reply*" **join-bar**, and the New Topic button is hidden (read-only). Logged in → the full composer labelled "Posting as {you}".

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Guest (read-only) vs User states | P0 | Planned | Driven by one flag / `.app.guest` class. |
| Login / Sign-up toggle (demo) | P0 | Planned | Real auth Planned. |
| Account dropdown menu | P1 | Planned | Profile, preferences, log out. |
| Registration (email + password) | P0 | Planned | Hash with Argon2/bcrypt; email verification P1. |
| Login / logout / sessions | P0 | Planned | Secure cookie sessions; CSRF-protected forms. |
| Password reset | P1 | Planned | Email token flow. |
| Email verification | P1 | Planned | Gate posting on verified email (anti-spam). |
| OAuth / social login | P1 | Planned | Google/Apple/GitHub; ships Phase 2 (USER §2.1; locked for v1, DECISIONS §5 #1). |

> **Acceptance criteria — Auth (P0)**
> - Given a visitor with no account, when they view any board or thread, then they can read all public content and see a Log in / Sign up affordance, and no composer.
> - Given a visitor on the register form, when they submit a unique email + valid password, then an account is created (password stored only as a hash) and they are logged in.
> - Given a logged-in user, when they reload or return within the session lifetime, then they remain logged in and the UI shows their identity.
> - Given a logged-in user, when they click Log out, then the session is invalidated and the UI returns to the guest state.
> - Negative: invalid credentials show a clear error and never reveal whether the email exists.

### 6.7 Reactions & Stars

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Emoji reactions on posts | P1 | Planned | Visual; persistence Planned. One per (user, post, emoji). |
| Star threads (personal bookmark) | P1 | Planned | Powers the Starred filter. |
| Subscribe to a thread | P1 | Planned | Drives reply notifications. |

### 6.8 Direct Messages

> **Confirmed in scope.** Private 1:1 messaging stays in RetroBoards' v1 community set (the adjacent plan deprioritised DMs; we keep them). Persisted via `conversations` / `conversation_participants` / `dm_messages` (§8). Group DMs remain P2.

| Feature | Priority | Status | Notes |
|---|---|---|---|
| 1:1 DM conversations | P1 | Planned | UI present; persistence Planned. |
| Group DMs | P2 | Planned | N-participant conversations. |
| DM notifications & unread | P1 | Planned | Shares the notification system. |

### 6.9 Search

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Global search bar | P1 | Planned | Always visible in the top bar on desktop; behind an icon on mobile. Debounced 200ms, min 2 chars. |
| Server search (threads + posts) | P1 | Planned | **MySQL FULLTEXT** on `threads.title` and `posts.body`. A `searchForum(q, boardId?)` query returns top **thread** matches (title + snippet) and **post** matches (thread + snippet); board-scoped when on a board. Public read. |
| Results popover | P1 | Planned | Dropdown grouped **"Threads"** / **"Posts in threads"**, each row linking to `/t/{id}`. |
| In-board search & ranking tuning | P2 | Planned | Scoped queries; relevance tuning beyond the default FULLTEXT score is out of scope for v1. |

### 6.10 Notifications & Mentions

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Bell with unread badge | P1 | Planned | Live unread count (realtime via **short-polling** in v1; SSE later — §9.6, DECISIONS §3 #4). |
| Subscriptions (thread & board) | P1 | Planned | Subscribe to a thread or a whole board, each with **In-app** and **Email** toggles. Backed by `subscriptions` (§8.3). |
| Subscribe controls | P1 | Planned | A Bell / Bell-off toggle with an In-app/Email popover on both the **thread** page and the **board** header. |
| New-post / new-thread notifications | P1 | Planned | On post insert, fan out (app-layer, in the write transaction) a notification to every subscriber of the thread or its board, **excluding the author** (§8.3, §9.6). |
| Notification dropdown | P1 | Planned | The bell opens the last 20; clicking one marks it read and navigates to the thread. |
| Notification settings page | P1 | Planned | `/settings/notifications` lists every subscription with per-row In-app/Email toggles + unsubscribe (USER.md §4.6). |
| Email notifications | P1 | Planned | Transactional email per recipient (idempotent), respecting the suppression list (ADMIN.md §7). |
| Per-subscription frequency | P1 | Planned | Each board/thread subscription is **Instant / Daily / Off**; a thread setting overrides its board (ADMIN.md §7.6, USER.md §4.6). |
| Daily digest email | P1 | Planned | Timezone-aware batched digest at the user's chosen hour; watermarked against duplicates (ADMIN.md §7.6). |
| Mark all read / clear | P1 | Planned | Bulk mark-as-read and clear-all in the bell dropdown. |
| Email delivery log | P2 | Planned | Per-send statuses (sent/bounced/suppressed/failed) + CSV export + test send (ADMIN.md §7.6). |
| @mentions | P1 | Planned | Lives in the composer (autocomplete, parse-on-submit → notify, block-list aware) — **COMPOSER.md §6.1**. |
| Mentions quick-filter | P2 | Planned | Sidebar entry (wired when @mentions land). |

### 6.11 Moderation

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Pin / unpin thread | P1 | Planned | Indicator exists in inbox. |
| Lock / unlock thread | P1 | Planned | Locked threads reject new posts. |
| Move thread between boards | P1 | Planned | Updates board counters. |
| Delete / restore post (soft) | P0 | Planned | Soft delete keeps an audit trail. |
| Reports queue | P1 | Planned | Members report posts; mods triage. |
| Per-board moderators | P1 | Planned | Scoped powers (§10). |
| Ban / suspend user | P1 | Planned | Admin + scoped mod actions. |
| Moderation audit log | P1 | Planned | Every mod action recorded (§8). |

### 6.12 Profiles & Reputation

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Profile page (`/u/{username}`) | P1 | Planned | Basic public profile: display name/username, bio, location, join date, post count, reputation; richer activity/tabs follow later. |
| Avatars | P1 | Planned | Mockup uses generated monogram avatars; uploads later. |
| Signatures | P2 | Planned | Classic forum touch; shown under posts. |
| Reputation / post count | P1 | Planned | Denormalised counters (§8). |
| Ranks / titles | P2 | Planned | Derived from post count or assigned. |

### 6.13 Settings & Preferences

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Account settings (profile basics + password) | P0 | Planned | `/settings/account` updates display name/bio/location; `/settings/security` changes password. Email change/reset stay deferred. |
| Notification preferences | P1 | Planned | Per-type toggles, email digest opt-in. |
| Display preferences | P2 | Planned | Density, theme (incl. retro skin). |

### 6.14 Theming

**Design system: a custom tokenised productivity UI.** Inspired by Fluent/Outlook density, Slack-style conversational affordances, and the *behaviour* of accessible primitives (dialogs, menus, popovers, comboboxes, command menus) — we adopt the **ideas** (tokens, density, accessible interaction patterns), **not** any React UI kit. Everything renders from CSS variables on our server-rendered, progressively-enhanced stack. Visual language: compact density, soft neutral surfaces, 1px dividers, clear unread/mention/status states, strong hover/focus, restrained rounded cards, sticky command bars, status chips, subtle motion — beauty from clarity and rhythm, not gradients or toy-like social UI.

Beyond the base palette, the token system carries **forum-state families** so unread, mention, solved, moderation, and decision states read clearly without noise:

```txt
surface.{base, raised, sunken, selected, hover, unread, mention, moderation}
border.{subtle, strong, focus, unread}
text.{primary, secondary, muted, inverse}
accent.{brand, category, tag, mention, unread, solved, warning, moderation, dm}
status.{solved, needsAnswer, decision, locked, archived, assigned}
density.{comfortable, compact}
composer.{idle, focus, error}
presence.{online, away, offline}
```

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Single tokenised theme (CSS variables) | P0 | Planned | Whole look re-skins from `:root`. |
| Retro 2002 theme as optional skin | P2 | Planned | The preserved `styles.css` look as a toggle. |
| Admin branding (name, colors, logo) | P1 | Planned | Make "RetroBoards" → operator's brand. |
| Plugin / theme system | P2 | Planned | Avoid decisions that preclude it. |

### 6.15 Presence — "Who's online"

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Online users count | P1 | Planned | The stat line already shows "Users Online: N". |
| Who's-online list | P1 | Planned | A roster of currently-online members (sidebar section or popover): avatars + names. |
| Presence tracking | P1 | Planned | Derived from `users.last_seen_at`, refreshed by a lightweight heartbeat on activity; "online" = seen within N minutes (configurable). No separate table. |
| Presence dots | P1 | Planned | Green / away / offline dots on avatars (sidebar DMs, profiles). |
| Respect privacy | P1 | Planned | Honours the per-user **Show online presence** toggle (USER.md §4.7); hidden users never appear online. |

Presence updates push via the same realtime mechanism as the bell (**short-polling** in v1; SSE later — §9.6, DECISIONS §3 #4).

### 6.16 Reputation (simple, Twitter-like)

A deliberately lightweight, public signal of appreciation — **not** the full reputation/badge/trust-level system (that's the community pass). Twitter-like: a simple count of the likes a member's content has earned.

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Reputation score | P1 | Planned | `users.reputation` = total reactions ("likes") received across a member's posts. Denormalised; recomputed on reaction add/remove and on post delete/restore. |
| Shown on profile & posts | P1 | Planned | A small number by the username / on the profile (a karma/like count). |
| Gated nothing in v1 | P1 | Planned | Purely social for now; trust levels, badges, and ranks-from-rep are the **community pass**. |

> **Interpretation (resolved — DECISIONS §3 #15, COMMUNITY §2.1):** "Twitter-like" reputation is the **sum of reactions received** (+1 each). There is **no separate "Like" primitive** — the reactions *are* the likes. A small accepted-answer ("solved") bonus is added per COMMUNITY §2.1; self-reactions are excluded in app logic.

Because reputation is **derived from reactions**, removing a post or reaction adjusts it automatically; moderation stays the lever (no separate "edit reputation" tool in v1).

### 6.17 New-user onboarding (product tour)

| Feature | Priority | Status | Notes |
|---|---|---|---|
| Interactive product tour | P2 | Planned | A lightweight client-side tour (e.g. driver.js, ~6kb) highlighting the rail/board list, inbox, search, composer, bell, and "New thread" — 6–7 steps, plain text + one tip each. |
| Trigger | P2 | Planned | First sign-in, or first visit if a local `tourCompleted` flag is unset; the server flag `users.onboarded_at` (§8.3) persists completion across devices. |
| Replay & skip | P2 | Planned | A "?" button in the header replays it anytime; "Skip" on every step. |

Targets real DOM nodes via `data-tour="…"` attributes on those regions. Full spec in USER.md §5.7.

### 6.18 Community Inbox — triage & status

The Community-Inbox thesis makes **triage** and **status** first-class. Beyond the v1 filters (§6.3), the inbox grows email-style triage, and topics carry visible status — rendered with the `status.*` / `surface.*` tokens (§6.14) so they're obvious without clutter.

- **Expanded inbox filters (P1/P2):** For You · Unread · Mentions · Replies to You · Watching · Needs Answer · Assigned · Decisions · Solved · Drafts · Snoozed · (Moderation, for staff). Triage without opening every topic.
- **Topic status states (P1/P2):** Solved · Needs Answer · Decision Made · Staff Notice · Pinned · Locked · Archived · Assigned · Escalated · Watching · Muted · Snoozed — shown as **status chips** in the inbox row and topic header.
- **Community memory (P2/later):** solved/canonical answers, topic summaries ("what changed since you last read"), related topics, wiki-style posts, topic split/merge — how old discussions become reusable knowledge.

These deepen the thesis without changing the v1 core; most are P1/P2 and additive to the schema (status flags / a `topic_status` enum on `threads`, plus `snoozed_until` and `assigned_to` on `thread_user`). Tracked in the roadmap (§13).

### 6.19 Thread Intelligence — sourced Living Briefs

Thread Intelligence is the bounded automatic-publication extension to the Phase
4 human-controlled community-memory foundation (ADR 0019). It is implemented
pre-flip, but `community_memory` and `automated_context` both still default
`false` pending the complete graduation evidence and a separate default-on
change.

- **Member workflow.** An eligible public thread may show a Living Brief above
  the posts. The brief identifies AI or curator authorship, update time, version,
  and current readable source posts; related-topic explanations link only to
  destinations the viewer may read. If no manual or AI summary exists, no empty
  brief renders and deterministic related-topic context remains the fallback.
- **Curator workflow.** An admin or in-scope board moderator may publish/edit a
  sourced manual brief, refresh, retire, restore, and explicitly pause/resume
  automation. A human edit becomes the next generation baseline. Retirement
  pauses the thread, restoration does not silently resume it, and curated
  related-topic rows always outrank AI overlays.
- **Processor boundary.** Generation uses only eligible public post evidence,
  an optional curator baseline, and bounded public related candidates. Private
  or hidden content, DMs, reports, moderation notes, account/session data,
  email/IP data, and credentials never cross the provider boundary. The initial
  OpenAI Responses request sets `store: false`; output is locally validated,
  sanitized, source-checked, moderated, and rechecked for visibility before one
  atomic publication.
- **Provenance and retention.** RetroBoards stores the validated canonical brief
  and bounded ledger metadata, not raw prompts, raw responses, duplicate post
  bodies, or unvalidated generated text. Published-generation provenance follows
  its source thread. Unpublished terminal attempts expire after 90 days;
  unresolved `dead`/`review_required` evidence remains through resolution and
  then for 90 quiet days.
- **Failure and operations.** Missing credentials, global/per-thread pause,
  budget exhaustion, provider failure, source staleness, validation failure, or
  moderation never replaces the last safe publication. The minutely worker,
  budget/recovery commands, safe feature pins, and data-preserving rollback are
  specified in `docs/runbooks/thread_intelligence.md`.

## 7. User Stories & Key Flows

### 7.1 User stories (by persona, priority order)

**Guest**

- As a guest, I want to read any board and thread without an account, so that I can judge whether the community is worth joining.
- As a guest, I want it to be obvious how to join and what I'm missing, so that signing up feels worthwhile rather than like a wall.
- As a guest who clicks reply, I want a clear in-context prompt to log in or sign up, so that I'm not confused about why I can't type.

**User**

- As a member, I want to start a thread in a board, so that I can begin a discussion.
- As a member, I want to reply to a thread, so that I can participate.
- As a member, I want to quote a specific post, so that my reply has context.
- As a member, I want to react to posts, so that I can respond lightly without a full reply.
- As a member, I want to star and subscribe to threads, so that I can find them again and get notified of replies.
- As a member, I want to edit or delete my own posts, so that I can fix mistakes.
- As a member, I want to see what's unread, so that I can catch up efficiently.
- As a member, I want a profile with my history and identity, so that I build a reputation.
- As a member, I want to DM another member, so that I can talk privately.

**Moderator**

- As a moderator of a board, I want to pin, lock, and move threads, so that I can keep the board organised.
- As a moderator, I want to review reported posts in a queue, so that I can act on problems quickly.
- As a moderator, I want to delete posts and see an audit trail, so that moderation is accountable.

**Admin**

- As an admin, I want to create and reorder categories and boards, so that I can shape the community.
- As an admin, I want to assign roles and per-board moderators, so that I can delegate.
- As an admin, I want to brand the install (name, colors), so that it's *our* community, not "RetroBoards".

### 7.2 Key flows

1. **First visit (guest).** Land on a board (deep link from search or home) → read threads and posts freely → top bar shows Guest + Log in/Sign up; conversation shows a join-bar instead of a composer → clicking reply/react/New Topic prompts sign-up.
2. **Register & first post.** Sign up (email + password) → logged in immediately → identity appears top-right, composer replaces the join-bar → type a reply → Enter sends → post appears, post count increments.
3. **Reply with quote.** Hover a post → Quote → composer pre-fills a quote block referencing that post (`parent_post_id`) → send.
4. **Start a topic.** New Topic → title + body editor → choose board (defaults to current) → submit → new thread created, becomes the OP, lands in the inbox at top.
5. **Moderate a report.** Member reports a post with a reason → it enters the reports queue → a board moderator reviews → deletes the post (soft) and/or locks the thread → action recorded in the moderation log; reporter optionally notified.

## 8. Data Model & MySQL Schema

### 8.1 Entity overview

Core entities and relationships:

- A **category** has many **boards**; a **board** has many **threads**; a **thread** has many **posts**.
- A **user** authors threads and posts, owns **reactions**, **stars/subscriptions** (`thread_user`), **notifications**, and **DM** participation.
- **board_moderators** grants a user scoped moderation on a board.
- **conversations** (+ participants + messages) model DMs separately from boards.
- **reports** and **moderation_log** support accountable moderation.
- Counters (`post_count`, `reply_count`, `thread_count`, `last_post_*`) are **denormalised** for cheap reads and updated on write (in a transaction or via triggers/app logic). Unread is derived from `thread_user.last_read_post_id` vs `thread.last_post_id`.

Conventions: InnoDB, `utf8mb4`, `BIGINT UNSIGNED` surrogate keys, UTC `DATETIME`, soft-deletes where history matters. Lengths/types below are sensible starting points, not final.

### 8.2 Schema (DDL)

```sql
-- USERS ---------------------------------------------------------------
CREATE TABLE users (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username        VARCHAR(32)     NOT NULL,
  email           VARCHAR(255)    NOT NULL,
  password_hash   VARCHAR(255)    NULL,                -- Argon2id / bcrypt; NULLable — OAuth-only accounts may have none (SCHEMA §7 #2)
  display_name    VARCHAR(64)     NULL,
  role            ENUM('user','moderator','admin') NOT NULL DEFAULT 'user', -- value 'user' per DECISIONS §4 (was 'member'); see SCHEMA.md
  title           VARCHAR(64)     NULL,                -- rank/title, e.g. "Veteran"
  signature       TEXT            NULL,
  location        VARCHAR(64)     NULL,
  avatar_path     VARCHAR(255)    NULL,
  post_count      INT UNSIGNED    NOT NULL DEFAULT 0,  -- denormalised
  reputation      INT             NOT NULL DEFAULT 0,
  status          ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME      NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CATEGORIES & BOARDS -------------------------------------------------
CREATE TABLE categories (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(64)     NOT NULL,
  position    INT             NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE boards (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id   BIGINT UNSIGNED NOT NULL,
  slug          VARCHAR(64)     NOT NULL,              -- the #channel handle
  name          VARCHAR(80)     NOT NULL,
  description   VARCHAR(255)    NULL,
  position      INT             NOT NULL DEFAULT 0,
  post_min_role ENUM('user','moderator','admin') NOT NULL DEFAULT 'user', -- role vocabulary standardised on 'user' (DECISIONS §4)
  is_archived   TINYINT(1)      NOT NULL DEFAULT 0,
  thread_count  INT UNSIGNED    NOT NULL DEFAULT 0,    -- denormalised
  post_count    INT UNSIGNED    NOT NULL DEFAULT 0,    -- denormalised
  last_thread_id BIGINT UNSIGNED NULL,
  last_post_at  DATETIME        NULL,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_boards_slug (slug),
  KEY idx_boards_cat_pos (category_id, position),
  CONSTRAINT fk_boards_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE board_moderators (
  board_id  BIGINT UNSIGNED NOT NULL,
  user_id   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (board_id, user_id),
  CONSTRAINT fk_bmod_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  CONSTRAINT fk_bmod_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- THREADS & POSTS -----------------------------------------------------
CREATE TABLE threads (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id         BIGINT UNSIGNED NOT NULL,
  user_id          BIGINT UNSIGNED NOT NULL,           -- author of the OP
  title            VARCHAR(160)    NOT NULL,
  slug             VARCHAR(180)    NOT NULL,
  is_pinned        TINYINT(1)      NOT NULL DEFAULT 0,
  is_locked        TINYINT(1)      NOT NULL DEFAULT 0,
  is_deleted       TINYINT(1)      NOT NULL DEFAULT 0,
  reply_count      INT UNSIGNED    NOT NULL DEFAULT 0, -- denormalised (excludes OP)
  view_count       INT UNSIGNED    NOT NULL DEFAULT 0,
  last_post_id     BIGINT UNSIGNED NULL,
  last_post_user_id BIGINT UNSIGNED NULL,
  last_post_at     DATETIME        NULL,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_threads_inbox (board_id, is_pinned DESC, last_post_at DESC),
  KEY idx_threads_author (user_id),
  CONSTRAINT fk_threads_board FOREIGN KEY (board_id) REFERENCES boards(id),
  CONSTRAINT fk_threads_user  FOREIGN KEY (user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE posts (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id      BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,
  parent_post_id BIGINT UNSIGNED NULL,                 -- quote/reply target
  body           MEDIUMTEXT      NOT NULL,             -- raw Markdown (canonical; DECISIONS §3 #2)
  body_html      MEDIUMTEXT      NULL,                 -- sanitised render cache
  is_op          TINYINT(1)      NOT NULL DEFAULT 0,
  is_deleted     TINYINT(1)      NOT NULL DEFAULT 0,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at      DATETIME        NULL,
  edited_by      BIGINT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  ip             VARBINARY(16)   NULL,                 -- author IP (DECISIONS §4 #5; ban-evasion); built Phase 2 — SCHEMA §7 #10
  PRIMARY KEY (id),
  KEY idx_posts_thread (thread_id, created_at),
  KEY idx_posts_author (user_id),
  FULLTEXT KEY ft_posts_body (body),                   -- v1 search
  CONSTRAINT fk_posts_thread FOREIGN KEY (thread_id) REFERENCES threads(id),
  CONSTRAINT fk_posts_user   FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ENGAGEMENT ----------------------------------------------------------
CREATE TABLE reactions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id    BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  emoji      VARCHAR(16)     NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reaction (post_id, user_id, emoji),
  CONSTRAINT fk_react_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_react_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- per-user thread state: read position, star, subscribe
CREATE TABLE thread_user (
  user_id           BIGINT UNSIGNED NOT NULL,
  thread_id         BIGINT UNSIGNED NOT NULL,
  last_read_post_id BIGINT UNSIGNED NULL,              -- unread = thread.last_post_id > this
  is_starred        TINYINT(1)      NOT NULL DEFAULT 0,
  -- is_subscribed dropped — superseded by the `subscriptions` table (SCHEMA §7 #4; see §8.3)
  PRIMARY KEY (user_id, thread_id),
  KEY idx_tu_starred (user_id, is_starred),
  CONSTRAINT fk_tu_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_tu_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
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
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dm_conv (conversation_id, created_at),
  CONSTRAINT fk_dm_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_dm_user FOREIGN KEY (user_id)         REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTIFICATIONS, REPORTS, MODERATION ----------------------------------
CREATE TABLE notifications (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,            -- recipient
  type            ENUM('reply','mention','reaction','dm','mod','new_post','new_thread','follow','badge','solved','announcement') NOT NULL, -- canonical column 'type' (not 'kind'); full union incl. 'announcement' (admin broadcast/system) — see SCHEMA.md §7 #13
  actor_id        BIGINT UNSIGNED NULL,
  thread_id       BIGINT UNSIGNED NULL,
  post_id         BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  is_read         TINYINT(1)      NOT NULL DEFAULT 0,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reports (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id BIGINT UNSIGNED NOT NULL,
  post_id     BIGINT UNSIGNED NOT NULL,
  reason      VARCHAR(255)    NULL,
  status      ENUM('open','triaged','resolved','dismissed') NOT NULL DEFAULT 'open', -- 'triaged' added per SCHEMA §7 #5 (+ derived reason_code)
  handled_by  BIGINT UNSIGNED NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_reports_status (status, created_at),
  CONSTRAINT fk_report_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE moderation_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id    BIGINT UNSIGNED NULL,                    -- NULL = system/automated action (SCHEMA §7 #6)
  action      VARCHAR(40)     NOT NULL,                -- pin, lock, delete_post, ban, ...
  target_type ENUM('thread','post','user','board','category','setting') NOT NULL, -- widened per SCHEMA §7 #8
  target_id   BIGINT UNSIGNED NOT NULL,
  reason      VARCHAR(255)    NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_modlog_target (target_type, target_id),
  KEY idx_modlog_actor (actor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> **Session handling.** RetroBoards uses opaque-token sessions backed by a **`sessions` table** (the hashed cookie token as the id, a per-session CSRF secret, device list, and revocation), which **ships in Phase 1** (DECISIONS §5 #9; canonical DDL in SCHEMA.md §1; migration `0005` in PHASE_1_MIGRATIONS) — this is what powers "log out everywhere" and the security-activity view. Cookies are `HttpOnly`, `Secure`, `SameSite=Lax`, rotated on login, with idle + absolute timeouts. **Guests have no row anywhere** — a guest is simply a request without a valid session.

### 8.3 Additions (v0.2 — folded-in features)

```sql
-- Subscriptions: notify me about a thread or a whole board.
-- Supersedes thread_user.is_subscribed; supports per-channel (in-app / email) toggles.
CREATE TABLE subscriptions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  target_type    ENUM('board','thread') NOT NULL,
  target_id      BIGINT UNSIGNED NOT NULL,
  email_enabled  TINYINT(1) NOT NULL DEFAULT 1,
  in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
  frequency      ENUM('instant','daily','off') NOT NULL DEFAULT 'instant',  -- a thread setting overrides its board
  created_at     DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sub (user_id, target_type, target_id),
  KEY idx_sub_target (target_type, target_id),
  CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Changes to the existing schema:

| Table | Change | Why |
|---|---|---|
| `threads` | Add `FULLTEXT KEY ft_threads_title (title)` | Global search over titles (§6.9); `posts` already has `ft_posts_body`. |
| `notifications` | Canonical column is **`type`** (not `kind`) and read-state is **`is_read`** (not `read_at`); enum reconciled to the full union `('reply','mention','reaction','dm','mod','new_post','new_thread','follow','badge','solved','announcement')` in §8.2 / **SCHEMA.md**. | Drives the bell dropdown + unread badge (§6.10); `announcement` powers admin broadcasts (Phase 2). |
| `users` | Add `onboarded_at DATETIME NULL` | Product-tour completion, cross-device (§6.17). |
| `users` | `reputation` (already present) = denormalised count of reactions received (§6.16) | Maintained on reaction add/remove and post delete/restore. |
| `users` | `timezone VARCHAR(64) NULL`, `digest_hour TINYINT NULL` (0–23 local), `last_daily_digest_at DATETIME NULL` | Timezone-aware daily digests + watermark so a digest is never duplicated or empty (ADMIN.md §7.6). |
| `email_deliveries` | **new table** (ADMIN.md §10) | Per-send delivery log (statuses + errors) for the activity view + CSV export. |
| `thread_user` | `is_subscribed` **superseded** by `subscriptions` | Migrate existing thread subscriptions in; keep `is_starred` + `last_read_post_id`. |
| Presence | **No new table** — "online" derived from `users.last_seen_at` within a configurable window (§6.15). | |

> Notification-email **suppressions** live in `email_suppressions` (ADMIN.md §10).

## 9. Architecture

### 9.1 Stack & shape

- **PHP 8.x** application, **MySQL 8 / MariaDB** database, behind Apache or nginx. Targets a standard LAMP-style host.
- **Server-rendered first.** Every view is a real URL (§5.3) returning complete HTML. JavaScript is a progressive enhancement, never a requirement for reading or posting.
- **Data access via PDO** with prepared statements exclusively. A thin model/repository layer per entity; no ORM required for v1.
- **Framework: vanilla PHP + a micro-router** — no Slim/Laravel (resolved, DECISIONS §3 #1 [Henry]: portable, own-every-line, runs on any VPS). The design assumes a small front controller.

### 9.2 Request lifecycle

```
Request → public/index.php (front controller)
        → Router (maps method + path to a Controller action)
        → Controller (auth/permission checks, validates input)
        → Model/Repository (PDO prepared statements)
        → View (PHP template partials) → HTML response
```

State-changing requests (POST) are CSRF-protected, perform the write inside a transaction (including denormalised counter updates), then redirect (PRG pattern) or return JSON for the enhanced path.

### 9.3 The mockup *is* the view layer

The hybrid prototype (once the Phase 0 mockup is built — §13) maps one-to-one onto server templates: reuse `app.css` as-is and split `app.html` into partials:

| Mockup region | Server template/partial | Data it needs |
|---|---|---|
| Top bar + identity | `layout.php` / `partials/topbar.php` | current user (or guest) |
| Sidebar | `partials/sidebar.php` | categories → boards, unread counts, DMs |
| Thread inbox | `partials/thread-list.php` | threads for board + per-user read/star state |
| Conversation | `partials/conversation.php` | thread + posts + authors + reactions |
| One message | `partials/post.php` | post + author + reactions |
| Composer / join-bar | `partials/composer.php` | auth state, target thread |

The current client-side JS (channel switching, opening threads, filters, send) becomes the **enhancement layer**: it calls small JSON endpoints and patches the DOM. With JS disabled, the same actions work via plain links and form POSTs.

### 9.4 Internal JSON endpoints (enhancement path)

```
GET  /c/{slug}?page=n           → thread list (HTML fragment or JSON)
GET  /t/{id}?page=n             → posts page
POST /threads                   → create thread          (auth)
POST /t/{id}/reply              → create post            (auth)
POST /posts/{id}/edit           → edit own post          (auth/owner)
POST /posts/{id}/delete         → soft delete            (owner or mod)
POST /posts/{id}/react          → toggle reaction        (auth)
POST /t/{id}/star               → toggle star            (auth)
POST /t/{id}/subscribe          → toggle subscription    (auth)
POST /posts/{id}/report         → file a report          (auth)
POST /mod/t/{id}/pin|lock|move  → moderation             (mod/admin)
GET  /notifications             → list + unread count    (auth)
```

### 9.5 Post rendering & sanitisation

Posts are stored **raw** (`posts.body`) and rendered to a cached, **sanitised** `posts.body_html` using an allowlist (no raw HTML, no scripts). The markup language is **Markdown** (canonical; hybrid live-Markdown — DECISIONS §3 #2, COMPOSER.md). Spoiler tags and @mention **links** render at render time, but **mention notification rows are created once at submit** (inside the write transaction, §9.6) — never at render.

### 9.6 Search, notifications & realtime (v0.2)

- **Search.** `searchForum(q, boardId?)` runs two FULLTEXT `MATCH … AGAINST` queries — one over `threads.title`, one over `posts.body` — and returns the top thread and post hits with snippets, board-scoped when applicable. Public read; debounced client-side (200ms, min 2 chars).
- **Notification fan-out.** On post insert, **inside the write transaction**, look up subscribers of the thread and its board (`subscriptions`), exclude the author, and insert `notifications` rows. For subscribers with `email_enabled`, enqueue one transactional email per recipient with `idempotency_key = post_id + ':' + user_id`, skipping suppressed addresses (ADMIN.md §7). App-layer fan-out is chosen over DB triggers for portability (§14).
- **Realtime delivery.** The bell's unread badge and the who's-online roster update via **short-polling** in v1 (SSE later if needed — DECISIONS §3 #4); a `GET /notifications` endpoint backs both the dropdown and the badge count. No WebSocket dependency for v1.
- **Presence.** A cheap `last_seen_at` heartbeat on authenticated requests; "online" = within a configurable window. The who's-online list is a filtered read, gated by each user's presence-privacy setting.

## 10. Permissions Matrix

"Mod" = a moderator **on boards they are assigned to** (via `board_moderators`); a site-wide `role='moderator'` applies everywhere. Admin can do everything.

| Action | Guest | User | Mod (scoped) | Admin |
|---|:--:|:--:|:--:|:--:|
| Read public boards & threads | ✓ | ✓ | ✓ | ✓ |
| Register / log in | ✓ | — | — | — |
| Create thread / reply | — | ✓ | ✓ | ✓ |
| React / star / subscribe | — | ✓ | ✓ | ✓ |
| Edit / delete **own** post | — | ✓ | ✓ | ✓ |
| Send direct messages | — | ✓ | ✓ | ✓ |
| Report a post | — | ✓ | ✓ | ✓ |
| Pin / lock / move thread | — | — | ✓ (own boards) | ✓ |
| Delete **any** post (soft) | — | — | ✓ (own boards) | ✓ |
| Handle reports queue | — | — | ✓ (own boards) | ✓ |
| Ban / suspend user | — | — | ✓ (own boards) | ✓ |
| Assign board moderators | — | — | — | ✓ |
| Create / edit boards & categories | — | — | — | ✓ |
| Site settings & branding | — | — | — | ✓ |
| Assign global roles | — | — | — | ✓ |

## 11. Non-Functional Requirements

**Security (P0).**

- All queries use **PDO prepared statements** — no string-built SQL.
- Passwords hashed with `password_hash()` (Argon2id preferred, bcrypt fallback). Never store or log plaintext.
- **CSRF tokens** on every state-changing form/endpoint.
- Output escaping with `htmlspecialchars()` by default; post HTML passes an **allowlist sanitiser**.
- Session cookies: `HttpOnly`, `Secure`, `SameSite=Lax`; rotate on login; idle + absolute timeouts.
- **Rate limiting** on login, registration, and posting; email verification gates first post to curb spam.
- Security headers: HSTS, a strict **Content-Security-Policy**, `X-Content-Type-Options`, `Referrer-Policy`.
- Uploads (later) validated by type/size and served from a non-executable path.

**Performance (P0/P1).** Indexes per §8; pagination on all lists; denormalised counters updated transactionally; batch author/reaction lookups to avoid N+1; cache rendered `body_html`; static assets cacheable / CDN-friendly.

**Accessibility (P1).** Semantic HTML and ARIA landmarks for the three panes; full keyboard navigation; focus management for the drawer and account menu; WCAG AA contrast (theme tokens already chosen with this in mind); honour `prefers-reduced-motion`.

**Responsive (P0).** One-column layout ≤ 860px (drawer sidebar, list→thread slide) — already implemented in the mockup.

**SEO (P1).** Server-rendered, crawlable; semantic URLs; per-thread `<title>`, meta description, and OpenGraph; `sitemap.xml`; canonical URLs; fully readable without JavaScript. (Forums live and die by search traffic — this matters.)

**Privacy (P1).** Minimal PII (email only required); self-service password reset, account export, and deletion; no third-party trackers by default.

**Observability (P1).** Structured error logging; basic request/DB metrics; a health endpoint.

## 12. Success Metrics

**Leading indicators** (days–weeks):

- **Activation:** % of new registrations that make a first post; **time-to-first-post**.
- **Guest → member conversion:** % of guest sessions that register.
- **Engagement:** weekly active posters; posts per active member; % of new threads that get a reply within 24h.
- **Learnability:** in a moderated usability test, % who complete "read a thread and post a reply" unaided, and time-on-task. (Directly measures Goal 1.)

**Lagging indicators** (weeks–months):

- **Retention:** D7 / D30 returning-member rate; returning-poster rate.
- **Health:** reports per 1,000 posts; median time to resolve reports.
- **Sentiment:** qualitative feedback / NPS.

Targets are set after a baseline (the install is new). Use a "success" and a "stretch" threshold for each, and record the measurement method when instrumentation lands.

## 13. Roadmap & Phasing

**Phase 0 — Mockups — ⬜ Not started.** Retro tribute and the chosen hybrid app (logged-in/out states), front-end only — not yet created.

**Phase 0 artifacts (planned):** the mockup files (`app.html`, `app.css`, `index.html`, `board.html`, `thread.html`, `styles.css`) are **not yet created**; once they exist, visual changes should include browser screenshots or Playwright/browser smoke.

**Phase 1 — MVP backend (P0). _Definition of done: a real user can register, log in, read boards, start a thread, and reply — server-rendered._**

The core forum flow is **not yet built** — auth, posting, and the first admin/operator slice are all planned for Phase 1; no code exists on the stack yet.

**Completion-evidence rule:** anything marked `Live` must be accompanied by the tests, smoke checks, or Playwright/browser verification that prove the claim. UI-visible work needs browser verification in addition to server-side tests.

- MySQL schema (§8) + migrations + seed (categories/boards). **Planned.**
- Auth: register, login, logout, sessions, CSRF (acceptance criteria in §6.6). **Planned.**
- Basic account settings + public profile: `/settings/account`, `/settings/security`, and `/u/{username}`. **Planned.**
- Boards/categories rendered from DB; first-run setup creates the first admin, site name, and starter boards; admin launch slice covers create/edit/hide/delete-empty plus site naming. Full board/category reorder and board archive remain follow-up work, scheduled for **Phase 2** (ADMIN §11).
- Threads + posts: create, read (paginated), edit/delete own, soft delete. **Planned.**
- Admin inline moderation: pin/unpin, lock/unlock threads, soft-delete any post — every action audited to `moderation_log`. **Planned.**
- Account-state write gate: suspended users keep login + read but are 403-blocked on every write; stale sessions for banned users are blocked the same way. **Planned.**
- Post rendering + sanitisation; guest read-only with join-bar. **Planned.**
- Wire the mockup as templates (§9.3). **Planned.**

**Phase 1 target evidence (to be produced — none exists yet)**

| Phase 1 slice | Target proof |
|---|---|
| Schema/foundation/read path | `composer test`; migration and seeder integration tests; `/healthz` HTTP smoke returning `200` with DB `ok`. |
| Auth/session/CSRF | `tests/Integration/Controller/AuthControllerTest.php`; `tests/Integration/Core/AppTest.php`; Playwright/browser smoke for `/login` and `/register`. |
| Basic account settings/public profile | `tests/Integration/Core/AppUserSettingsTest.php`; `tests/Unit/Core/LayoutRenderTest.php`; full `composer test`. |
| Posting/edit/delete | `tests/Integration/Core/AppPostingTest.php`; thread/post controller, service, and repository integration tests; full `composer test`. |
| First-run setup | `tests/Integration/Core/AppSetupTest.php`; `tests/Integration/Service/SetupServiceTest.php`; HTTP smoke for `/setup` before/after initialization. |
| Admin board/category/settings | `tests/Integration/Core/AppAdminTest.php`; category/board/settings repository tests; admin HTTP/browser smoke as an authenticated admin. |
| Inline moderation/write gates/private reads | `tests/Integration/Core/AppModerationTest.php`; `tests/Integration/Core/AppWriteGateTest.php`; `tests/Integration/Core/AppPrivateBoardAccessTest.php`. |
| Server-rendered shell/theme wiring | layout/view unit tests; Playwright/browser smoke on auth and app routes when UI-visible changes land. |

**Phase 2 — Community essentials (P1).**

- Reactions, stars, subscriptions (persisted) + unread tracking.
- Notifications + @mentions; bell inbox.
- Search (MySQL FULLTEXT).
- Direct messages (persisted).
- Moderation: pin/lock/move, soft-delete any, reports queue, per-board moderators, audit log.
- Profiles & reputation (post counts, ranks).
- Community identity — the lightweight community-pass slice: following/followers + Following feed, the fixed badge set, accepted/"solved" answers, cosmetic titles, and an all-time leaderboard. _(Time-windowed leaderboards, admin-defined custom badges, and custom roles stay deferred to Phase 4+ — see §13.1 and DECISIONS §7.)_
- Presence / "who's online" via short-polling + `users.last_seen_at` (per §13.1).

**Phase 3 — Polish & scale (P1/P2).**

- Settings & preferences (appearance/reading/composing); server-side draft sync. _(Notification preferences moved to Phase 2; localStorage drafts ship Phase 1–2 — COMPOSER.md.)_
- Rich composer: chosen markup, spoilers, attachments.
- Configurable rate limiting / anti-spam hardening (tunable per-action limits, new-user throttles, spam scoring). _(Baseline auth/registration rate limiting and security headers ship in **Phase 1** as P0 — see §11; presence / "who's online" moved to Phase 2 — see §13.1.)_
- Performance/caching pass; accessibility audit; SEO polish.
- Admin branding (retire the "RetroBoards" placeholder).

**Later (P2).** Retro theme as a toggle; plugin/theme system; real-time ("live") via SSE/WebSockets; PWA / mobile; data import from existing forums; multi-community; i18n.

> **Delivery phasing:** This three-phase view is strategic. Execution is sequenced across **seven delivery phases** (PHASE_1 through PHASE_7): **Phase 1** MVP, **Phase 2** community essentials, **Phase 3** polish/trust/scale, **Phase 4** advanced community & content, **Phase 5** ecosystem/identity/governance, **Phase 6** realtime & scale, **Phase 7** platform expansion — the last five subdivide "Phase 3" and "Later (P2)" above. SCHEMA.md §6 holds the per-table phase cut.

### 13.1 Folded-in feature plan (v0.2)

Features adopted from the adjacent project, mapped onto our phases (translated to PHP/MySQL):

- **Their Phase 1 — mobile polish, global search, sort tabs** → our **Phase 2** (search needs the FULLTEXT indexes and a live inbox). Mobile drawer/FAB/tap-targets land alongside.
- **Their Phase 2 — composer.** Phase 1 ships the **no-JS server-rendered Markdown posting box** (`<textarea>` + sanitised render, edit/delete); Phase 2 adds **@mentions** and the **DM** mount. The **unified rich hybrid-Markdown composer** (Milkdown spike, toolbar, live formatting, optimistic send, localStorage Drafts/recovery, preview) is **Phase 3 Gate A** (COMPOSER.md §17.1, PHASE_3_PLAN). COMPOSER's P0/P1/P2 are *priority* tiers, not phases (DECISIONS §2), so a "P0" composer feature is MVP-critical in priority but delivered with the Phase 3 composer.
- **Their Phase 3 — notifications (in-app + email) + subscriptions** → our **Phase 2** (largest backend change; requires the email domain set up — ADMIN.md §7).
- **Their Phase 4 — new-user product tour** → our **Phase 3** (needs the final DOM to target).
- **Private messaging** → **Phase 2** (confirmed in scope, §6.8).
- **Who's-online / presence** → **Phase 2** (§6.15).
- **Community pass — lightweight slice** → **Phase 2** (§6.16, COMMUNITY.md §14.1): simple reputation, following/followers + Following feed, the fixed badge set, accepted/"solved" answers, cosmetic titles, and an all-time leaderboard. Only the heavier slice defers (to Phase 4): time-windowed leaderboards, admin-defined custom badges, and custom roles (DECISIONS §7).

## 14. Open Questions

> **Resolved in [DECISIONS.md](DECISIONS.md)** (the authoritative decisions log). The table below is retained for context; see DECISIONS.md §3 for the rulings.

| # | Question | Owner | Blocking? |
|---|---|---|---|
| 1 | Framework: vanilla PHP + micro-router, or Slim/Laravel? | Eng | Phase 1 start |
| 2 | Post markup. **Resolved: hybrid live-Markdown** (Markdown canonical) — COMPOSER.md. | Product + Eng | **Resolved** |
| 3 | Unread model: per-thread `last_read_post_id` (chosen) vs per-post receipts — confirm it scales for our sizes. | Eng | Phase 2 |
| 4 | "Live" feel: polling vs SSE vs WebSockets, and when. | Eng | Phase 2/3 |
| 5 | Search: stay on MySQL FULLTEXT or adopt Meilisearch/Elastic later? | Eng | Phase 2 |
| 6 | DMs in Phase 2 or defer to keep MVP lean? | Product | Phase 2 |
| 7 | Email provider for verification/notifications. | Henry | Phase 1/2 |
| 8 | Hosting target (shared host vs VPS vs container) — affects deploy & sessions. | Henry | Phase 1 |
| 9 | Keep the retro look as a switchable theme, or reference only? | Product | Phase 3 |
| 10 | Single community in v1, or design for multi-community now? | Product | Phase 1 (architectural) |
| 11 | Anonymous/guest posting ever, or always require an account? | Product | Phase 1 |
| 12 | Stack divergence: adjacent build is Postgres/Supabase/React/Lovable; these docs stay **PHP/MySQL (translate)** — confirmed. Revisit only if we consolidate stacks. | Henry | **Resolved** |
| 13 | Realtime mechanism for the notification bell + presence: SSE vs short-polling vs WebSockets (ties to Q4). | Eng | Phase 2 |
| 14 | Notification fan-out: app-layer in the write transaction (chosen) vs DB triggers — confirm at scale. | Eng | Phase 2 |
| 15 | Reputation input: single aggregate of all reactions received (chosen) vs a dedicated "Like". | Product | Phase 2 |

## 15. Glossary

- **Board (`#channel`)** — a topic area users post threads into.
- **Category** — an ordered, collapsible grouping of boards in the sidebar.
- **Thread / Topic** — a titled discussion; its first post is the **OP**.
- **Post / Reply / Message** — one entry in a thread.
- **OP** — original post (and, by extension, its author).
- **Reaction** — an emoji response to a post.
- **Star** — a personal bookmark on a thread (powers the Starred filter).
- **Subscription** — opt-in notifications for a thread's new posts.
- **Presence** — whether a user is currently active (online dot).
- **Postbit** — the user-info panel attached to a post (avatar, rank, join date, post count) — terminology from the retro tribute.
- **Join-bar** — the guest's read-only prompt that replaces the composer.

## 16. Changelog

| Version | Date | Notes |
|---|---|---|
| v0.13 | 2026-07-12 | Added §6.19 for the implemented pre-flip Thread Intelligence member, curator, processor, provenance, retention, failure, and operator contracts. Both production feature defaults remain `false` pending graduation. |
| v0.12 | 2026-06-26 | **Cross-doc review fixes.** §8.2/§8.3 added `'announcement'` to the `notifications` enum (admin broadcast/system — SCHEMA §7 #13); §8.2 rewrote the session-handling note — the `sessions` table **ships in Phase 1** (canonical DDL in SCHEMA §1), not an optional "add if needed"; §1 + §9.3 stopped describing the mockup files as already existing (they are Phase 0 artifacts, not yet created — matching §6/§13); §13.1 clarified composer phasing — Phase 1 ships the no-JS Markdown box, Phase 2 adds @mentions/DM, the unified rich composer is Phase 3 (COMPOSER §17.1 / PHASE_3_PLAN). |
| v0.11 | 2026-06-26 | **Stale-decision wording pass.** Reworded settled-but-still-"open" areas to match DECISIONS: **§9.1** framework is now stated as resolved — **vanilla PHP + micro-router**, no Slim/Laravel (§3 #1) — not an open question; **§9.5 + §8.2** markup is **Markdown canonical** (hybrid live-Markdown — §3 #2, COMPOSER.md), dropping the "Markdown vs BBCode" / "raw (Markdown/BBCode)" references; **§9.5** @mention notification rows are created **once at submit** (write-transaction, §9.6), with only the link rendered at render time; **§9.6** (and the §6.10 bell / §6.15 presence echoes) realtime is **short-polling v1**, SSE later (§3 #4), not an undecided "SSE or short-polling (§14)"; **§6.5** attachment storage is **resolved** (local-disk non-exec behind a storage interface, 5MB, png/jpg/webp/gif — §6 #6) with only the build deferred to Phase 3; **§13** Phase 3 "drafts" narrowed to **server-side draft sync** (localStorage drafts are Phase 1–2). Added the missing **`posts.ip`** column (`VARBINARY(16) NULL`) to the §8.2 DDL (DECISIONS §4 #5; SCHEMA §7 #10). No scope changes. |
| v0.10 | 2026-06-26 | **Status-truth pass (nothing is built yet).** Reset every §6 Feature-Catalog status to `Planned` (35 `Done (mockup)` + 2 `Live` rows); reset the §13 Phase 1 roadmap items from **Live** to **Planned**; corrected Phase 0 from "✅ Done" to "Not started" (the `app.html`/`styles.css` mockup artifacts do not exist); reworded the "core forum flow is now live end-to-end" claim to "not yet built"; relabeled the §13 "Phase 1 completion evidence / Live slice" table as **target** evidence. No feature/scope changes. |
| v0.9 | 2026-06-26 | Consistency pass: added **P3** to the §6 priority legend (it was P0/P1/P2 only, yet P3 is used for 2FA/appeals — DECISIONS §2/§7); aligned the §6.6 **OAuth** priority to **P1** to match USER §2.1 and "locked for v1" (was P2 "optional later"; delivery stays Phase 2); bumped the stale header (was v0.7, behind its own v0.8 row). |
| v0.8 | 2026-06-26 | Cross-doc consistency pass. **§13/§13.1:** aligned Phase 2 scope with DECISIONS §7 + COMMUNITY §14.1 + the Phase 2 plan — follows, the fixed badge set, accepted/"solved" answers, cosmetic titles, and the all-time leaderboard ship in **Phase 2**; only time-windowed leaderboards/custom badges/custom roles defer (Phase 4+). Relabeled the §13 delivery list from "P1…P7" to "Phase 1…7" (P-numbers are priority tiers, not phases — DECISIONS §2). **§6.16:** replaced the "rep input open — supports either" note with the resolution (Σ reactions received, no separate Like — DECISIONS §3 #15). **§8.2:** annotated the DDL to match SCHEMA §7 reconciliations (`password_hash` NULL #2, `reports.status` +`triaged` #5, `moderation_log.actor_id` NULL #6, `target_type` +`category`/`setting` #8, `thread_user.is_subscribed` dropped #4). |
| v0.7 | 2026-06-26 | Consistency pass: corrected the §6.5 emoji-picker note that still called `@username` autocomplete "out of scope" (mentions are P1 and composer-owned since v0.3 — see §6.10 / COMPOSER.md §6.1); clarified §13 that *baseline* auth/registration rate limiting + security headers are **Phase 1** (P0, §11) while the *configurable* anti-spam service is Phase 3; bumped the stale header (was "Draft v0.1 · 2026-06-21"). |
| v0.6 | 2026-06-25 | Consistency pass: moved presence and notification preferences into the §13 Phase 2 list to match §13.1 and the Phase 2 plan; added a delivery-phasing note crosswalking the three-phase roadmap to the seven delivery phases (PHASE_1 through PHASE_7). |
| v0.1 | 2026-06-19 | Initial draft. Captures the hybrid Slack/email direction, the logged-in/out experience, full feature catalog, data model + MySQL schema, architecture, permissions, NFRs, metrics, roadmap, and open questions. |
| v0.2 | 2026-06-19 | Folded in the adjacent project's 4-phase plan — mobile polish, global search (+ `threads.title`/`posts.body` FULLTEXT), Newest/Active/Unanswered sort tabs, quick-reply composer (drafts, optimistic send, Esc/Cmd-K), notifications + subscriptions, new-user product tour — translated to PHP/MySQL. Added presence/"who's online" and a simple Twitter-like reputation; confirmed private messaging in scope. New: §6.15–6.17, §8.3, §9.6, §13.1, open questions 12–15. |
| v0.3 | 2026-06-19 | Composer pass: **COMPOSER.md** added (the unified New Thread / Reply / DM input). Resolved markup (open Q2) = **hybrid live-Markdown**; promoted **@mentions** to P1 (composer-owned); reconciled `Cmd/Ctrl+K` (link inside the composer, search outside); pointed §6.5 at COMPOSER.md. The `attachments` table is defined in COMPOSER.md §16. |
| v0.4 | 2026-06-19 | Framework integration pass: formalized RetroBoards as a **Community Inbox** — Slack/email familiarity over durable forum topics. Added productivity-design-system guidance: tokenized, accessible, dense, Outlook-like triage with Slack-style conversational affordances. Clarified that external UI-library research informs component behavior and tokens, not a required React dependency. Composer library recommendation added to COMPOSER.md as a spike order: Milkdown first, Tiptap/ProseMirror fallback, CodeMirror-style live Markdown fallback. |
| v0.5 | 2026-06-19 | Notification-completeness pass (audited against an adjacent implementation): added **per-subscription frequency** (Instant/Daily/Off, thread overrides board), **timezone-aware daily digests** (`users.timezone`/`digest_hour`/`last_daily_digest_at` + digest template), **mark-all-read/clear**, and an **email delivery log** (`email_deliveries`). Deliverability ops (bounce/complaint webhooks, suppression cascade + recovery, `/unsubscribe` token page, CSV export, test send, digest preview) specced in ADMIN.md §7.6 / USER.md §4.6. |
