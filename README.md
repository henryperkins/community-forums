# RetroBoards

**RetroBoards** is self-hostable forum / community software — a **Community Inbox**: durable forum topics (Discourse-style permanence) presented through the familiar Slack/email-style three-pane shell, with email-style triage. Stack: **vanilla PHP 8 + MySQL**, server-rendered with progressive-enhancement JavaScript, designed to run on a single VPS.

> **Status: Phase 2 (community essentials) implemented — M0–M6 on disk.** Building on the Phase 1 MVP backend, Phase 2 adds engagement (reactions/stars/unread), notifications + email worker, mentions, FULLTEXT search, direct messages, scoped moderation + reports, and — in **Milestone 5** — the community-identity and account layer: follows + a query-time Following feed, badges, accepted/"solved" answers, an all-time leaderboard, cosmetic titles, member privacy/preference/block/board controls, active-session management, OAuth sign-in/linking (Google/GitHub/Apple) with avatar import, and privacy-respecting presence. **Milestone 6** closed out the phase: a Phase-1→Phase-2 upgrade rehearsal (`bin/console verify:upgrade`, 17/17 data-preservation checks), feature-flag rollback tests, a query/index review, and the evidence + ops docs. The automated suite is green at **215 tests / 694 assertions**. Phases 3–7 remain plans. See [`docs/PHASE_2_STATUS.md`](docs/PHASE_2_STATUS.md) for the Phase 2 evidence index + Gate A/B checklist, [`docs/PHASE_2_RUNBOOK.md`](docs/PHASE_2_RUNBOOK.md) for operations, and [`docs/PHASE_1_COMPLETION.md`](docs/PHASE_1_COMPLETION.md) for the Phase 1 evidence index.

## Running Phase 1

Requirements: **PHP 8.2+** (with `pdo_mysql`, `mbstring`, `dom`, `openssl`), **Composer**, and **MySQL 8 / MariaDB 10.6+**.

```bash
composer install                      # install league/commonmark + phpunit
cp .env.example .env                  # then set DB_* and run: php bin/console key:generate
php bin/console migrate               # apply the 10 Phase-1 migrations
php -S 127.0.0.1:8000 -t public public/index.php
```

Open <http://127.0.0.1:8000> — a fresh install redirects to `/setup`, where you create the first admin, name the community, and get starter boards. Useful commands:

```bash
php bin/console migrate:status        # show applied migrations
php bin/console migrate:fresh         # drop all tables and re-migrate (destructive)
php bin/console migrate:rollback      # roll back (greenfield only)
composer test                         # run the full PHPUnit suite
```

Tests run against a separate database (`retroboards_test` by default; override with `DB_TEST_DATABASE`).

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
