# P5-16 Browser Accessibility and No-JS Sweep

**Date:** 2026-07-09
**Requirement:** GA-DOD-17
**Status:** Captured.

## Commands

Commands were run from `tests/browser` in the isolated `phase5-p5-16-closeout`
worktree with `APP_KEY` unset to verify the local evidence harness default.

| Command | Result |
|---|---:|
| `env -u APP_KEY npm run evidence` | 1 skipped, 71 passed (3.3m) |
| `env -u APP_KEY npm run evidence:passkeys` | 6 passed (1.2m) |
| `env -u APP_KEY npm run evidence:integrations` | 16 passed (48.5s) |
| `env -u APP_KEY npm run evidence:packages` | 2 passed (14.0s) |
| `env -u APP_KEY npm run a11y` | 2 skipped, 26 passed (1.7m) |

## Evidence Assets

- Desktop captures refreshed under `docs/evidence/browser/desktop/`.
- Mobile captures refreshed under `docs/evidence/browser/mobile/`.
- The sweep covers Phase 5 admin provider, invitation, passkey/TOTP, API token,
  webhook, package integration, and package security paths together with the
  Phase 4 carryover responsive, keyboard, no-JS, and axe checks.

## Harness Notes

- Added a deterministic local test-only `APP_KEY` fallback in the Playwright
  server profile so isolated worktrees without `.env` still exercise encrypted
  service secret paths. Supplying `APP_KEY` still overrides the fallback.
- Stabilized the badge-rule browser case by selecting the seeded `Appreciated`
  badge instead of the first catalogue option, because the first option can be
  pre-held after shared invitation/account flows run during the mobile sweep.

Focused checks before the final capture:

| Command | Result |
|---|---:|
| `env -u APP_KEY bash -c 'bash prepare.sh && npx playwright test gate-a.spec.ts providers.spec.ts --project=desktop --grep "admin webhooks|provider console"'` | 2 passed (11.8s) |
| `env -u APP_KEY bash -c 'bash prepare.sh && npx playwright test gate-a.spec.ts --project=desktop --project=mobile --grep "phase 4 badge rules"'` | 2 passed (8.4s) |
