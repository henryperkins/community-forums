# Browser evidence (Playwright)

Captures Gate A screenshots at a **desktop (1280×800)** and **mobile (390×844)**
viewport by driving the real, server-rendered app in Chromium. This is the
"browser capture at desktop/mobile widths" evidence item from
`docs/PHASE_2_STATUS.md` (Gate A acceptance checklist).

The app is JavaScript-free for every Gate A action, so these journeys log in and
navigate via plain form posts — they double as proof the server-rendered
POST→redirect paths work in a real browser, not just the in-process PHPUnit suite.

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

## Run it locally

Prerequisites: PHP 8 + the `rb-mariadb` dev container (see the repo root README),
Node 20+, and Chromium for Playwright (`npx playwright install --with-deps chromium`).

```bash
cd tests/browser
npm install
npm run evidence       # prepare.sh resets+seeds retroboards_e2e, then runs Playwright
```

`prepare.sh` drops and recreates the dedicated `retroboards_e2e` database (never
touching `retroboards` dev or `retroboards_test`), migrates it, and seeds a small
community (`seed.php`). Playwright then boots `php -S` against that DB and captures
the screenshots. Override the database or port with `DB_DATABASE` / `E2E_PORT`.

## CI

`.github/workflows/browser-evidence.yml` runs the same flow against an ephemeral
MariaDB 11 service and uploads the screenshots as a build artifact. It is
path-filtered — it runs on pushes that touch the app or this harness (and on
manual `workflow_dispatch`) — so the evidence is regenerated without a local
environment.
