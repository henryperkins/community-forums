# RetroBoards

**RetroBoards** is self-hostable forum / community software — a **Community Inbox**: durable forum topics (Discourse-style permanence) presented through the familiar Slack/email-style three-pane shell, with email-style triage. Stack: **vanilla PHP 8 + MySQL**, server-rendered with progressive-enhancement JavaScript, designed to run on a single VPS.

> **Status: planning / design.** Nothing is built yet — there is no application code, and the Phase 0 mockup artifacts (`app.html`, `app.css`, …) do not exist yet. Every document below is a plan.

## This is an orientation pointer, not a source of ground truth

The authoritative documents are:

| Document | Owns |
|---|---|
| [DECISIONS.md](DECISIONS.md) | Locked decisions; the resolved stack; the **replaceable-interface seams** (email, search, media storage, feed — §2); the priority-tier-vs-delivery-phase rule. **Authoritative on any conflict.** |
| [DESIGN.md](DESIGN.md) | The product & technical design — the product source of truth; the **roadmap** and **completion-evidence policy** (§13). |
| [SCHEMA.md](SCHEMA.md) | The consolidated database schema (final table shapes) and the per-phase build cut (§6). |
| [USER.md](USER.md) · [ADMIN.md](ADMIN.md) · [COMMUNITY.md](COMMUNITY.md) · [COMPOSER.md](COMPOSER.md) | The member, operator, community-layer, and composer surfaces. |
| `PHASE_1_PLAN.md` … `PHASE_7_PLAN.md` (+ `PHASE_1_MIGRATIONS.md`) | The seven-phase delivery sequence, entry/exit gates, and migration manifests. |

**Precedence:** when this README and any document above disagree, the document above wins — and **DECISIONS.md wins over all**. In particular, the replaceable-interface seams live in **DECISIONS §2**, and the roadmap and completion-evidence policy in **DESIGN §13** — not here.

## The seven delivery phases (at a glance)

1. **Phase 1** — MVP backend (auth, posting, read path, first-run setup, inline moderation).
2. **Phase 2** — community essentials (reactions/stars/unread, notifications + email, search, DMs, reports/moderators, OAuth, the community layer, announcements).
3. **Phase 3** — polish, trust & scale (the unified rich composer, uploads, anti-spam, TOTP, appeals, branding, plugins/webhooks/API, a11y/SEO/perf).
4. **Phase 4** — advanced community & content (topic status/triage, group DMs, tags/feeds, reputation ledger + leaderboards, community memory).
5. **Phase 5** — ecosystem, identity & governance (signed packages, custom roles/capabilities, passkeys, generic OIDC, invitations).
6. **Phase 6** — realtime & scale (capacity-triggered: SSE, external search, Redis, object storage/CDN, read replicas, materialized feeds).
7. **Phase 7** — platform expansion (i18n, PWA/offline, Web Push, import/export, multi-community/tenancy, optional public federation).

"v1" means the **Phase 1–2** initial release (DECISIONS §7).
