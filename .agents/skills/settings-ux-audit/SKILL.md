---
name: settings-ux-audit
description: Use when asked to audit, review, or find UX gaps/inconsistencies in the member account surface — the user menu, /settings pages, profile, sessions/security, or notification preferences — e.g. "review the settings UX" or after account-surface changes.
---

# Settings & User-Menu UX Audit

Evidence-driven audit of the signed-in member surface: topbar user menu, `/settings/*`, profile, notifications. **Review only — change no code.** Deliverable: a findings report in the final message; screenshots in the session scratchpad.

## Ground truth & prior art

- Spec: `USER.md` — §3.1 settings information architecture (Account, Security, Connections, Preferences, Notifications, Privacy, Data & Account), §2 OAuth, §4 preferences, §5 profiles. `DECISIONS.md` wins conflicts; `COMPOSER.md` covers composing preferences.
- Before calling anything "missing", check `docs/adr/` — especially 0006 (export/delete policy), 0007 (appeals), 0009 (custom CSS), 0010 (server-draft sync), 0013 (wysiwyg) — and `PHASE_*_PLAN.md` carryover. Tracked deferral → cite the ADR; untracked deferral → a finding in itself.
- No prior settings audit exists as of 2026-07-02 — check the repo root for newer `*review*.md`/`*audit*.md` and re-verify those first if found. The admin audit's defect families (draft-loss on ValidationException, stale-ID 500s, phantom UI) recur across surfaces — probe for the same families here.

## Scope — routes ↔ nav ↔ templates (orphans in either direction are findings)

- Shell: `templates/partials/topbar.php` (user menu, bell, Log out) and `settings_nav.php`, cross-checked against the `/settings` route block in `App::buildRouter()` (`grep -n "'/settings" src/Core/App.php`) and the 13 templates in `templates/account/`. Note `/settings` and `/settings/account` both render `account/settings.php` — there is no `account.php`. Controllers live in `src/Controller/` (not `src/Http/…`): `AccountController`, `SettingsController`, `OAuthController`, `BlockController`, `PersonalOrganizationController`.
- Flows: avatar upload/remove; TOTP enroll/confirm/recovery-rotate/disable; session revoke — including the current session; OAuth link/unlink/set-password; export; deactivate/reactivate; deletion request/cancel; notification per-type × per-channel matrix; board folders / bookmark folders / saved feeds; blocks; preferences reset/export; onboarding replay.
- Adjacent entry points from the menu: `/u/{username}` (+followers/following, gated view), `/notifications`, `/drafts`, `/inbox`.

## Live drive (required — template reading alone is not evidence)

Same mechanics as the `admin-ux-audit` skill: the Playwright MCP plugin is broken on this host — use `tests/browser/audit-drive.mjs` (`shot|snap|post|rawpost|probe|nojs|mobile|guestshot`) and `audit-form.mjs` (captures 422 re-renders) with nvm Node 24 (`export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"`); reuse the dev server on :8000 (`storage/local-server.pid`) or seed `retroboards_e2e` via `bash tests/browser/prepare.sh`; never run `npm run evidence` casually (rewrites tracked PNGs). The drivers hardcode screenshots to `/tmp/audit-evidence`; if toggling flags, snapshot and restore the `settings` table's `features` JSON (the dev DB is shared).

Persona variants matter more than on the admin surface: a plain member (`bob`, `password123`), an OAuth-linked account (connections/set-password path), a TOTP-enrolled account, a deactivated account mid-reactivation, and a guest (every `/settings/*` route must redirect to `/login?next=…`, never 500).

## Probe error paths on every form

Malformed/over-length input, stale IDs, double-submit, empty selection → must 422 re-render with typed input preserved (anti-draft-loss), never redirect-and-drop, never 500. Settings-specific probes: wrong current password on email/password change; oversized or wrong-type avatar; wrong TOTP code; verify other sessions are revoked after a credential change (`revokeOtherSessionsFor`); appearance changes must be flash-free (server stamps `data-theme` on `<html>`). Add a no-JS pass (progressive enhancement is a hard requirement), a 390px-viewport pass, and flag checks (`server_drafts`/`drafts` ON; `custom_css` dark by default; `appeals` default-ON since 2026-07-02 and `group_dms` since 2026-07-18 — dark features must fail dark, with no dead nav links either way).

## Report format

Severity **critical / major / minor / polish** — NOT P0–P3 (MoSCoW roadmap tiers, DECISIONS §2). Per finding: route + repro steps, screenshot ref, `file:line` cause, spec citation (USER.md §, or "unspecified — judgment call"), classification: defect | spec gap | tracked deferral (ADR #) | untracked deferral | polish. Open with a summary table (include the route↔nav↔template orphan matrix); close with the top 5 fixes by leverage.

## Common mistakes

| Mistake | Reality |
|---|---|
| Planning around the Playwright MCP plugin | Broken on this host — use `audit-drive.mjs` |
| Auditing with one persona | OAuth-linked, TOTP-enrolled, deactivated, and guest states expose different UI |
| Treating anything missing as a defect | ADR 0006/0007/0009/0010 deliberately scope this surface; classify |
| Template reading as evidence | Draft-loss and stale-ID 500s only surface through live bad-input probing |
| P0–P3 as bug severity | Those are roadmap priority tiers |
