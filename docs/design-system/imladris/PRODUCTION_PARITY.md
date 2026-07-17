# Production parity matrix — RetroBoards @ `4efe4e33` (main, 2026-07-14)

Classification: **core** (unflagged) · **GA** (flag default-on) · **dark** (implemented, default-off) · **reserved** (Gate B — no invented UI). DS column: where the surface is represented; *behavior-only* = no visual anatomy of its own (contracts, keys, headers). Every production surface is now represented or classified behavior-only — `manifest.json → unresolved_gaps` is `[]`.

| Surface | Routes / templates | Class | DS representation |
|---|---|---|---|
| Shell: topbar, rail, inbox panes | `home`, `inbox`, `layout`, partials `topbar` `sidebar` | core | `components/forum/*`, `ui_kits/retroboards`, thread-view template |
| Boards, folders, saved feeds | `board`, `feed`; `board_folders` `saved_feeds` `expanded_feeds` | GA | `feature-ui/rail/`, kit rail |
| Topic / posts / post toolbar | `thread`, partials `post` `post_toolbar` `thread_row` | core | `Post`/`ThreadRow` components, thread-view template |
| Composer (all 4 mounts) | partials `composer_shell` `composer` `new_thread_form` `dm_compose_fields` | core + `rich_composer` `wysiwyg_composer` `drafts` `server_drafts` `uploads` `custom_emoji` `slash_giphy` GA | `Composer` component + `components.css` shell block (verbatim CSS); states: toolbar/overflow, uploads, draft+conflict, preview, anonymous, error, submitting, locked |
| Reactions, stars, solved, regard | in `post`/`thread` | GA (`engagement`) | `CommendStar`, post specimens |
| Topic workflow, tags, split/merge | `tags/*`, mod tools partials; `topic_workflow` `tags` `split_merge` | GA | `feature-ui/moderation/`, `feature-ui/tags/` |
| Polls | in thread + composer; `polls` | GA | `feature-ui/polls/` |
| Thread Intelligence (Living Briefs, memory, references, related) | partials `living_brief` `thread_memory_tools`; `community_memory` `automated_context` `content_references` | GA | `feature-ui/conversation/` |
| Link previews · expanded files · group DMs · custom CSS | behind flags | **dark** | `feature-ui/conversation/` (link_previews); others behavior-only until surfaced |
| Search, notifications, announcements, presence | `search` `notifications` partial `announcement_banner`; flags GA | GA | kit screens |
| DMs | `dm/index` `new` `show` | GA | kit conversation + Composer dm mount |
| Feeds, follows, badges, reputation, leaderboard | `leaderboard`, partials `badges`; `badge_rules` `reputation_ledger` GA | GA | `feature-ui/account/`, leaderboard specimens |
| Profiles (+gated), preferences, account lifecycle | `profile/*`, `account/*` (13 templates); `account_lifecycle` `custom_profile_fields` `profile_media` GA | GA/core | kit account screens · `ui_kits/system` (profile-gated) |
| Auth: login, register, forgot/reset, MFA, verify, passkeys | `auth/*`, `passkeys.js` | core | `ui_kits/auth` — login, passkey sign-in, step-up, register, invited registration, forgot, reset, MFA, verify |
| OAuth, invitations, providers | `oauth` `invitations` `provider_registry` GA | GA | `ui_kits/admin` providers + invitations; `ui_kits/auth` OAuth buttons |
| Moderation: reports, approvals, appeals, anti-abuse | `mod/*`, `appeals/index`; `moderation_queue` `appeals` `anti_abuse` GA | GA | kit mod screens |
| Admin: dashboard, features, TI, structure, users, branding, tags, badges, email, announcements | `admin/*` | core/GA | `ui_kits/admin` — all sections incl. features console (+corrupt-overrides + readiness), Thread Intelligence, packages (+detail/plan/consent/security/publisher), registry trust, themes (+safe mode), roles (+edit/simulator), providers (+disable), invitations |
| Platform: packages, themes, API tokens, webhooks, service secrets, hooks | `admin/*`; P5 Gate A flags GA | GA | `ui_kits/admin` packages, themes, registry trust, API tokens, webhooks |
| Extensions · governance · service principals · verified links | `admin/extensions` disabled entry | **reserved** | disabled nav entry only — by rule |
| Setup wizard, errors (incl. DB-down), privacy, unsubscribe, health, SEO | `setup/wizard`, `errors/error`, `privacy`, `unsubscribe` | core | `ui_kits/system` (setup wizard, error incl. database-down, privacy, unsubscribe); health/SEO behavior-only |
| CSRF, idempotency, sessions, rate limits | app-wide | core | behavior-only (RUNTIME_CONTRACT.md) |

**Superseded anatomy check**: no "Posting as" strip, text-button toolbar, or standalone textarea/action-row composer remains in source or previews (verified by grep, 2026-07-14).
