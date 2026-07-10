# RetroBoards

**RetroBoards** is self-hostable forum / community software — a **Community Inbox**: durable forum topics (Discourse-style permanence) presented through the familiar Slack/email-style three-pane shell, with email-style triage. Stack: **vanilla PHP 8 + MySQL**, server-rendered with progressive-enhancement JavaScript, designed to run on a single VPS.

> **Status: Phase 5 (ecosystem, identity & governance) - Gate A accepted and default-on; Gate B reserved.** Phase 4 closed with explicit deferrals ([`docs/adr/0003-phase-4-closeout-deferrals.md`](docs/adr/0003-phase-4-closeout-deferrals.md)), and most of its deferred surfaces have since graduated default-ON. Accepted Phase 5 Gate A and B2 support flags now default on for any install without an explicit override (fresh and upgraded alike) and remain operator-reversible through `features.<flag>=false`: the signed package-registry protocol, install/update lifecycle, declarative theme packages, the integration runtime + security-response console, the database-backed capability resolver with its enforcement cutover (`CAPABILITIES_MODE`), WebAuthn passkeys, generic-OIDC identity providers with an operator console, encrypted service secrets, read-only API tokens, invitations, and outbound webhook delivery. (Opt-in TOTP/recovery also shipped with Gate A but is always available — it has no feature flag.) P5-16 closeout evidence is indexed at [`docs/evidence/phase5/gate-a-closeout.md`](docs/evidence/phase5/gate-a-closeout.md): PHP regression **1831 tests / 9396 assertions** across fresh and reused-schema runs (2026-07-09), browser evidence **71 passed / 1 skipped**, a11y **26 passed / 2 skipped**, resolver parity **1551/1551, 0 mismatches**, upgrade rehearsal **17/17** through migration `0076`, backup/restore rehearsal passed, and ADR 0017 records product-owner acceptance. Gate B workstreams remain reserved. See [`PHASE_5_STATUS.md`](PHASE_5_STATUS.md), [`docs/adr/0018-phase-5-gate-a-default-on.md`](docs/adr/0018-phase-5-gate-a-default-on.md), and [`docs/phase5/requirement-ledger.json`](docs/phase5/requirement-ledger.json); the Phase 4 record remains in [Phase 4 history](docs/history/PHASE_1-4_HISTORY.md#phase-4-status) and [`docs/evidence/phase4-gate-a.md`](docs/evidence/phase4-gate-a.md).

## Running Phase 1

Requirements: **PHP 8.2+** (with `pdo_mysql`, `mbstring`, `dom`, `openssl`, `curl` — cURL backs outbound webhook delivery and the optional Thread Intelligence AI generation), **Composer**, and **MySQL 8 / MariaDB 10.6+**. Thread Intelligence is configured entirely through the environment (`OPENAI_API_KEY` plus the `THREAD_INTELLIGENCE_*` values documented in `.env.example`); the credential is never stored in the database or shown in the UI.

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
| `PHASE_1_PLAN.md` … `PHASE_7_PLAN.md` (+ `docs/history/PHASE_1_MIGRATIONS.md`) | The seven-phase delivery sequence, entry/exit gates, and migration manifests. |

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
