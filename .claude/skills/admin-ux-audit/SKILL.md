---
name: admin-ux-audit
description: Use when asked to audit, review, or find UX gaps/inconsistencies in the admin dashboard, operator console, or moderation UI (/admin, /mod) — e.g. "review the admin UI", after admin-surface changes, or before a phase gate.
---

# Admin UX Audit

Evidence-driven audit of the operator surface (`/admin` + `/mod`). **Review only — change no code.** Deliverable: a findings report in the final message; screenshots in the session scratchpad.

## Ground truth & prior art

- Spec: `ADMIN.md` (permissions §2, moderation §3, boards §4, users §5, theming §6). `DECISIONS.md` wins conflicts. A gap = UI diverges from spec or blocks a real operator task.
- Before calling anything "missing", check `docs/adr/` and the `PHASE_*_PLAN.md` carryover ledgers. Tracked deferral → cite the ADR. Untracked deferral → that's a finding in itself (no-silent-deferrals rule).
- **Re-verify prior audits FIRST.** `admin_ui_review.md` (repo root) is the last report — it landed *in the same commit as its fixes* (`e0b0c16`), so never assume a listed finding is resolved: re-test each one empirically and mark it fixed / still broken / regressed before hunting new ones. `git log --oneline -15` shows anything newer.
- Code map for `file:line` citations: controllers are in `src/Controller/` (not `src/Http/…`) — `Admin*.php` plus, for `/mod`, `ModerationController`, `ReportController`, `ApprovalController`, `AppealController`, `UserModerationController`; templates in `templates/admin/` + `templates/mod/`; business rules in `src/Service/`.

## Live drive (required — template reading alone is not evidence)

The Playwright MCP plugin fails on this host (wants the Google Chrome channel). Use the checked-in drivers with the bundled Chromium:

- `tests/browser/audit-drive.mjs` — `shot|snap|post|rawpost|probe|nojs|mobile|guestshot|guestprobe` (usage header in the file)
- `tests/browser/audit-form.mjs` — real form submits that capture 422 re-renders with preserved input
- Node 24 is via nvm: `export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"` first. Both drivers hardcode `BASE = http://127.0.0.1:8000` and `OUT = /tmp/audit-evidence` — adjust if serving elsewhere, and reference/copy screenshots from OUT when writing the report.

Server: reuse the dev server on :8000 if `storage/local-server.pid` is live. For a clean seeded DB use the e2e stack instead: `retroboards_e2e` must pre-exist (the `retro` DB user cannot CREATE DATABASE — root/sudo is the operator's job), then `bash tests/browser/prepare.sh`, then serve on a spare port with the env `playwright.config.ts` uses (`DB_DATABASE=retroboards_e2e`, `RATELIMIT_PATH`, `PACKAGES_STORAGE_PATH`, `SESSION_SECURE=false`, `MAIL_DRIVER=array`, `APP_URL`).

Personas (all `password123`): `admin`, `alice` (board-scoped moderator — the reduced console view), `bob` (regular user — verify admin surfaces are hidden/403), guest. Emails: `CREDS` map in `audit-drive.mjs` (dev DB) or the `tests/browser/seed.php` header (e2e DB).

Do not run `npm run evidence` casually — it rewrites ~70 git-tracked PNGs (`git restore docs/evidence/browser/` if you do).

## Walk — every operator task end-to-end, as the persona who'd do it

dashboard → users + user record (warn/note/suspend/ban/lift) → `/mod` queues (reports, approvals, appeals) + thread tools (pin/lock/move/split/merge) → structure (create/edit/reorder/archive boards & categories) → tags (create/update/merge) → badge rules (create/preview/enable/backfill/revoke) → announcements → branding → themes (preview/activate/rollback/safe-mode) → packages/registries/extensions lifecycle → email ops (test/suppress/requeue/export) → webhooks (create/test/rotate/replay/delete) → API tokens (mint/revoke) → roles + simulator → `_nav.php` coverage (every section reachable? dead links when a flag is dark?).

## Probe error paths on every form

Malformed input (especially free-text dates), over-length values, stale/nonexistent IDs, double-submit, empty selection. Required behavior: 422 re-render with typed input preserved (anti-draft-loss pattern) — never a redirect that drops text, never a 500. Plus: a no-JS pass on core flows (progressive enhancement is a hard requirement), a 390px-viewport pass, and flag-gated sections checked both ON and OFF via the `features` JSON override in the `settings` table (no flags UI exists; snapshot the row first and restore it after — the dev DB is shared and another session may be using it).

## Report format

Severity **critical / major / minor / polish** — do NOT use P0–P3 (those are MoSCoW roadmap tiers, DECISIONS §2, not bug severity). Per finding: route + repro steps, screenshot ref, `file:line` cause, spec citation (or "unspecified — judgment call"), classification: defect | spec gap | tracked deferral (ADR #) | untracked deferral | polish. Open with a summary table and the prior-findings re-verification table; close with the top 5 fixes by leverage.

## Common mistakes

| Mistake | Reality |
|---|---|
| Planning around the Playwright MCP plugin | Broken on this host — use `audit-drive.mjs` |
| Deduping against session memory only | `admin_ui_review.md` at repo root is the shipped report |
| Treating anything missing as a defect | Check ADRs/phase plans first; classify it |
| Template reading as evidence | The prior audit's top defects (suspend 500, draft-loss family) only surfaced through live bad-input probing |
| P0–P3 as bug severity | Those are roadmap priority tiers |
