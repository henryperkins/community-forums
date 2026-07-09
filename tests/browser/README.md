# Browser evidence (Playwright)

Captures Gate A screenshots at a **desktop (1280×800)** and **mobile (390×844)**
viewport by driving the real, server-rendered app in Chromium. This is the
"browser capture at desktop/mobile widths" evidence item from
`docs/history/PHASE_1-4_HISTORY.md#phase-3-status` (Gate A evidence checklist).

The baseline navigation still uses server-rendered POST->redirect paths, while
the Phase 3 journeys also exercise the enhanced JavaScript composer, drafts,
uploads, branding preview, and product-tour replay flows.

## What it captures

Written to `docs/evidence/browser/<viewport>/<page>.png`:

| | Page |
|---|---|
| 01 | Home (board list + sidebar) |
| 02 | Board view (`/c/general`) |
| 03 | Thread (posts, reaction bar, reply form) |
| 04 | Leaderboard |
| 05 | Login |
| 06 | Register |
| 07 | Admin dashboard |
| 08 | Admin structure (boards & categories) |
| 09 | **Admin board roster** (moderators + members) |
| 10 | Inbox |
| 11 | Notifications |
| 12 | Settings |
| 13 | Search |
| 14 | Private board, viewed by a member |
| 15 | Reading preferences default selects |
| 16 | Drafts view with server and browser-local draft lists |
| 17 | Composer upload tray with thumbnail/alt controls |
| 18 | Branding live preview |
| 19 | Product-tour replay dialog |
| 20 | Admin API token minted with show-once token |
| 21 | Admin API token revoked |
| 22 | Admin webhook registered with show-once signing secret |
| 23 | Admin webhook delivery log after `topic.created` worker delivery |

Additional evidence journeys append or reuse later numeric prefixes as the
harness grows. The current branch also captures:

- `20-announcement-banner`, `21-announcement-dismissed`
- `20-structure-before`, `21-structure-after-move`, `22-board-archived-readonly`, `23-board-unarchived`
- `22-admin-email-dashboard`, `23-admin-email-suppressed`, `24-admin-email-test-sent`
- `25-poll-voted` (Phase 4 carryover poll no-JS vote/result flow)
- `26-slash-menu`, `27-giphy-inserted` (Phase 4 carryover slash menu and direct GIPHY insertion)
- `28-server-draft-conflict` (`server_drafts` cross-device conflict controls; graduated to default-on 2026-07-02, now captured in the standard `evidence` run)
- `46-profile-media-avatar`, `47-profile-media-moderation` (`profile_media` member avatar/signature flow plus admin moderation controls; graduated to default-on 2026-07-03)
- `48-custom-emoji-admin`, `49-custom-emoji-thread` (`custom_emoji` admin catalogue, Markdown rendering, and reaction compatibility; graduated to default-on 2026-07-03)

Focused acceptance specs that do not write numbered screenshots:

- `wysiwyg-composer.spec.ts` gates the WYSIWYG layer (graduated to default-on 2026-07-02; the seed pins it off so gate-a keeps the textarea baseline): strict CSP asset load with no features override (proving the GA default mounts), textarea fallback, new-topic submit, source-mode round trip, no-op edit preservation, server-preview parity, rich reference chips, internal URL paste normalization, and mobile smoke.

## Run it locally

Prerequisites: PHP 8 + the `rb-mariadb` dev container (see the repo root README),
Node 20+, and Chromium for Playwright (`npx playwright install --with-deps chromium`).

```bash
cd tests/browser
npm install
npm run evidence       # prepare.sh resets+seeds retroboards_e2e, then runs Playwright
npm run evidence:dark  # legacy focused server-draft regression run with dark fixtures enabled
npm run a11y           # prepare.sh resets+seeds retroboards_e2e, then runs axe checks
npx playwright test wysiwyg-composer.spec.ts
```

`prepare.sh` drops and recreates the dedicated `retroboards_e2e` database (never
touching `retroboards` dev or `retroboards_test`), migrates it, and seeds a small
community (`seed.php`). Playwright then boots `php -S` against that DB and captures
the screenshots. Override the database or port with `DB_DATABASE` / `E2E_PORT`.
For a differently named local DB container/client, keep the normal app DB env and
set `DB_RESET_CONTAINER`, `DB_ROOT_PASSWORD`, and `DB_MYSQL_CLIENT`.

For the production-like closeout profile, start the compose stack first:

```bash
docker compose -f ../prodlike/compose.yml up -d --build
npm run evidence:prodlike
npm run evidence:dark:prodlike
npm run a11y:prodlike
```

Those scripts reset and seed the compose MariaDB database through localhost
`3321`, skip Playwright's built-in `php -S` web server, and target the Nginx/PHP-FPM
app at `http://127.0.0.1:8021`.

The webhook evidence step shells into the running `app` container for
`worker:webhooks`, so the delivery run uses the same `APP_KEY` and runtime profile
as the PHP-FPM app under test. In the production-like profile, the temporary
Playwright receiver binds on the host and the registered callback uses
`host.docker.internal`; `tests/prodlike/compose.yml` maps that name to Docker's
host gateway for the app container.

Because this profile occupies `8021`, run `tests/backup/rehearse.sh` with a
different `BACKUP_REHEARSAL_PORT` (for example `8031`) or stop the stack before
rehearsing backup/restore in the same checkout.

## CI

`.github/workflows/browser-evidence.yml` runs the same flow against an ephemeral
MariaDB 11 service and uploads the screenshots as a build artifact. It is
path-filtered — it runs on pushes that touch the app or this harness (and on
manual `workflow_dispatch`) — so the evidence is regenerated without a local
environment.

## Accessibility

`npm run a11y` runs `a11y.spec.ts` with `@axe-core/playwright` across the same
desktop and mobile projects. It opts into `RB_BROWSER_DARK_SURFACES=1` during
seeding so still-dark appeal and server-extension pages are available while the
now-default-on server-draft, badge-rules, slash/GIPHY, account-lifecycle,
profile-media, custom-emoji, and WYSIWYG composer surfaces are also scanned. It
currently checks the admin extension/badge-rules/profile-media/custom-emoji
surfaces, member appeals/drafts/account-lifecycle/profile-media surfaces, scoped
server-draft conflict, slash-combobox, custom-emoji post/reaction widgets, plus
the WYSIWYG toolbar/reference-picker/source-mode surfaces for serious or
critical WCAG 2A/2AA axe violations.
