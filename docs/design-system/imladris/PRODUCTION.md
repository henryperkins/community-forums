# Production — the runtime contract and the parity matrix

Two things live here: **what consuming the Imladris system means** (the rules a consumer must honour), and **which production surface each part of the system represents** (the parity matrix). Feature-flag truth itself lives in `production-contract.json`; the inspected commit lives in `manifest.json`.

---

## Part 1 — Runtime contract

Governing rule: **the design system owns presentation; RetroBoards owns behavior.** Conflicts resolve in this order: DECISIONS.md → product/surface specs → accepted ADRs → application contracts/tests → Imladris references. The system never removes, downgrades, enables, or redefines a forum feature; missing design coverage is added *here* before application adoption.

### Constraint class (from the app at commit `4efe4e33`)
- **CSP**: `default-src 'self'; base-uri 'self'; form-action 'self'; script-src 'self'; style-src 'self'` (+ `img-src 'self' data:`). No CDN of any kind. Previews and artifacts must work self-hosted; `tokens/fonts.css` therefore declares `@font-face` over bundled WOFF2 (`assets/fonts/`, OFL licenses alongside) — never `@import` from a font CDN.
- **Progressive enhancement**: every surface works with no JavaScript; a `has-js` class gates enhancements. Designs must include the no-JS state (e.g. the composer's plain Markdown textarea) — never JS-only anatomy.
- **Server-rendered**: vanilla PHP templates; the React primitives in `components/` are **design previews only**, never production implementation guidance.

### The composer (COMPOSER.md v0.8, ADR 0013/0020)
One shared shell, four mounts (reply / new_thread / dm / edit), identical feature surface. Canonical content is **Markdown in the textarea**; WYSIWYG mounts over it when `rich_composer` + `wysiwyg_composer` are on. Every form carries a CSRF `_token` and a fresh server-rendered `idempotency_key`. Send is a full navigation (no optimistic send — ADR 0020). Desktop Enter-to-send is context-aware (off in list/quote/code); Cmd/Ctrl+Enter always sends; touch soft-Enter = newline. Drafts persist locally per context key + `server_drafts` sync. The former "Posting as" strip / text-button toolbar anatomy is superseded (v0.7) and must not reappear.

### Theming
Light (parchment) is default; twilight is `[data-theme="dark"]`; system theme follows `prefers-color-scheme`. Consume **semantic tokens** (`--surface-raised`, `--brand`, `--accent-2`, `--on-done`, `--text-body`…), never raw primitives, so the register flips for free. `--text-body` is a **color**; the body font size is `--text-size-body`.

### Emoji
Decorative/status emoji in UI chrome: prohibited (status = word + colour). Authored-content emoji and the composer's emoji tooling (`:` autocomplete, picker dialog, custom emoji, GIPHY slash where configured): supported product features.

### Flags
Feature-flag truth lives in `production-contract.json`. Reserved-dark features (`server_extensions`, `governance`, `service_principals`, `verified_links`) receive **no invented UI** — only the disabled admin-nav entry that exists in production.

---

## Part 2 — Parity matrix (RetroBoards @ `4efe4e33`, main, 2026-07-14)

Classification: **core** (unflagged) · **GA** (flag default-on) · **dark** (implemented, default-off) · **reserved** (Gate B — no invented UI). DS column: where the surface is represented; *behavior-only* = no visual anatomy of its own (contracts, keys, headers). Every production surface is now represented or classified behavior-only — `manifest.json → unresolved_gaps` is `[]`.

| Surface | Routes / templates | Class | DS representation |
|---|---|---|---|
| Shell: topbar, rail, inbox panes | `home`, `inbox`, `layout`, partials `topbar` `sidebar` | core | `components/forum/*`, `templates/forum-inbox` + thread-view template; `ui_kits/retroboards` is a survey only |
| Boards, folders, saved feeds, bookmark folders | `board`, `feed`; `board_folders` `saved_feeds` `expanded_feeds` `bookmark_folders` | GA | `feature-ui/rail/` (one spec per flag) + `feature-ui/organize/` (the gathered surface). **No template owns this surface** — see `REDUNDANCY-AUDIT.md` §3 |
| Topic / posts / post toolbar | `thread`, partials `post` `post_toolbar` `thread_row` | core | `Post` + `ThreadRow` (one row, `presentation="default" \| "board"`, mirroring the partial), thread-view template |
| Composer (all 4 mounts) | partials `composer_shell` `composer` `new_thread_form` `dm_compose_fields` | core + `rich_composer` `wysiwyg_composer` `drafts` `server_drafts` `uploads` `custom_emoji` `slash_giphy` GA | `Composer` component + `components.css` shell block (verbatim CSS); states: toolbar/overflow, uploads, draft+conflict, preview, anonymous, error, submitting, locked |
| Reactions, stars, solved, regard | in `post`/`thread` | GA (`engagement`) | `CommendStar`, post specimens |
| Topic workflow, tags, split/merge | `tags/*`, mod tools partials; `topic_workflow` `tags` `split_merge` | GA | `templates/thread-view` — tag reads + composer tag input; assign / snooze / escalate; the split-or-merge modal |
| Polls | in thread + composer; `polls` | GA | `templates/thread-view` — choose-one, results, hidden-until-voted, close poll |
| Thread Intelligence (Living Briefs, memory, references, related) | partials `living_brief` `thread_memory_tools`; `community_memory` `automated_context` `content_references` | GA | `templates/living-brief` (the three provenance postures) + thread-view template; operator controls in `templates/admin-settings` |
| Link previews · expanded files · group DMs · custom CSS | behind flags | **dark** | custom CSS in `templates/admin-appearance` (behind its acknowledgement); others behavior-only until surfaced |
| Search, notifications, announcements, presence | `search` `notifications` partial `announcement_banner`; flags GA | GA | `templates/board-index` (search + notices), `templates/users-online` + `components/presence/`, `templates/admin-notifications` (announcements) |
| DMs | `dm/index` `new` `show` | GA | kit conversation + Composer dm mount |
| Feeds, follows, badges, reputation, leaderboard | `leaderboard`, partials `badges`; `badge_rules` `reputation_ledger` GA | GA | `feature-ui/rail/` (saved feeds), `templates/admin-features` (badge rules), `templates/user-profile` (regard + marks of esteem); the leaderboard lives **only** in `ui_kits/retroboards` — no template yet |
| Profiles (+gated), preferences, account lifecycle | `profile/*`, `account/*` (13 templates); `account_lifecycle` `custom_profile_fields` `profile_media` GA | GA/core | `templates/user-profile`, `templates/account-settings` · `ui_kits/system` (profile-gated) |
| Auth: login, register, forgot/reset, MFA, verify, passkeys | `auth/*`, `passkeys.js` | core | `ui_kits/auth` — login, passkey sign-in, step-up, register, invited registration, forgot, reset, MFA, verify |
| OAuth, invitations, providers | `oauth` `invitations` `provider_registry` GA | GA | `templates/admin-integrations` (sign-in providers + the disable path), `templates/admin-members` (invitations); `ui_kits/auth` OAuth buttons |
| Moderation: reports, approvals, appeals, anti-abuse | `mod/*`, `appeals/index`; `moderation_queue` `appeals` `anti_abuse` GA | GA | kit mod screens |
| Admin: dashboard, features, TI, structure, users, branding, tags, badges, email, announcements | `admin/*` | core/GA | the ten `templates/admin-*` templates, unified by `components/admin/AdminNav` — each carries its own drill-ins and validation |
| Platform: packages, themes, API tokens, webhooks, service secrets, hooks | `admin/*`; P5 Gate A flags GA | GA | `templates/admin-packages` (catalogue → plan → consent → enable, registry trust), `templates/admin-integrations` (tokens, webhooks, sign-in), `templates/admin-appearance` (themes + safe mode) |
| Extensions · governance · service principals · verified links | `admin/extensions` disabled entry | **reserved** | disabled nav entry only — by rule |
| Setup wizard, errors (incl. DB-down), privacy, unsubscribe, health, SEO | `setup/wizard`, `errors/error`, `privacy`, `unsubscribe` | core | `ui_kits/system` (setup wizard, error incl. database-down, privacy, unsubscribe); health/SEO behavior-only |
| CSRF, idempotency, sessions, rate limits | app-wide | core | behavior-only (Part 1 above) |

**Superseded anatomy check**: no "Posting as" strip, text-button toolbar, or standalone textarea/action-row composer remains in source or previews (verified by grep, 2026-07-14).
