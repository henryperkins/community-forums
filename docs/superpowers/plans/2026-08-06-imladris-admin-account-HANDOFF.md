# HANDOFF — finish `feat/imladris-admin-account` and merge it into main

Written 2026-08-06 by the session that ran the Cloudflare perf work. This is the
prompt/handoff for the session that finishes the branch. Branch:
**`feat/imladris-admin-account`** — worktree
**`.worktrees/imladris-admin-account-session`** — 5 commits ahead of origin
(4 were never pushed; `565aa10` is the newest), 18 ahead of main.

## The task

Finish the Imladris admin/account migration (Stage 2 of ADR 0024), ship the
missing evidence, then merge the branch into `main` cleanly with the evidence
the repo demands. Governing rule (do not relitigate):

> **Copy the design verbatim.** Structure, section order, component anatomy,
> class names, token usage, spacing, empty/loading/error states, microcopy
> register. The *only* sanctioned deviations are `feature-added`,
> `feature-removed`, `feature-changed`, `constraint`. Aesthetic preference is
> not one of them.

## Read these first, in this order

1. `docs/adr/0024-imladris-admin-account-adoption.md` — the operator decisions,
   34 constraints, recorded gaps, mirror divergences, **delivery obligations**.
2. `docs/superpowers/plans/2026-08-03-imladris-admin-account-adoption.md` — §6
   is the 19-slice sequence; §7 the standing execution rules. Slices 12–18
   remain.
3. `docs/superpowers/plans/2026-08-03-imladris-admin-account-ledger.md` — the
   deviation ledger; §6 carries the standing rules Stage 2 runs under.
4. `docs/superpowers/plans/imladris-admin-account-stage1/README.md` — raw
   per-screen workings; each remaining slice needs its `D-` + `V-` + `R-` triple
   (e.g. `D-admin-integrations.md`).
5. `docs/superpowers/plans/2026-08-04-imladris-admin-account-HANDOFF.md` — the
   prior handoff; still accurate about tooling and the slice 0–2 verification
   state.

`AGENTS.md` at repo root governs everything (spec precedence, CSP, PE, flags,
anti-draft-loss, "done requires evidence").

## Where the branch stands

| Slice | Area | Status |
|---|---|---|
| 0–10 | adjudication → settings/TI | **Done, with evidence** (`docs/evidence/imladris-admin-account-slice-{2..10}/` each have `design-qa.md` + desktop/mobile/twilight captures) |
| 11 | admin-members (users, user_record, bulk_confirm, invitations) | **Code done** (`565aa10`), **evidence MISSING** — no `slice-11` dir, no `design-qa.md` |
| 12 | admin-integrations (api_tokens, webhooks*, providers*) | **Not started** |
| 13 | admin-features (features, badge_rules*, custom_emoji) | **Not started** |
| 14 | admin-packages (9 package/registry/extension templates + 2 partials) | **Not started** |
| 15 | account A — substrate, Profile, Security | **Not started** |
| 16 | account B — 8 panes (privacy, appearance, preferences, composing, notifications, connections, sessions, blocks) | **Not started** |
| 17 | account C — Boards, Drafts, Lifecycle (+ `composer.js`) | **Not started** |
| 18 | `/mod/*` chrome (D2 made Moderation an eleventh tier; bodies still old rail) | **Not started** |
| 19 | closeout — de-fiction pass, baseline digest, full evidence sweep | **Not started** |

The two mandated new specs (`content-console.spec.ts`, `account-console.spec.ts`)
and `role-assignments.spec.ts` already exist on the branch.
`role-assignments` **is** already wired into `npm run evidence`
(`tests/browser/package.json`, `CAPABILITIES_MODE=enforce` — ADR 0024
obligation 2 is met). `content-console` and `account-console` are **not** in
the aggregate evidence script — run them per-slice as the plan's evidence
columns dictate, and decide at closeout whether they join `npm run evidence`.

## Standing rules that are easy to violate

1. **Never touch `config/imladris-runtime-baseline.json` on this branch.** It is
   refreshed **once per merge, on `main`, as the immediately-following commit,
   by the merger** (ledger §6 rule 5 / ADR 0024 obligation 4). A slice branch
   containing a change to it is a merge blocker.
2. **`.admin-bar`/`.admin-tier` CSS ships from `composer build:imladris`**, never
   hand-written. `resources/imladris/` and `public/assets/imladris.css` are
   **outputs**; the application complement (`.admin-console`, `.admin-tabs`,
   `.admin-pane`, every ≤860px rule) is hand-authored unlayered in `app.css`.
3. **Every slice ends with the gates** (plan §6 / §7):
   - CSP scan: `rg -n "<script|<style| on[a-z]+=" templates/ -S`
   - `vendor/bin/phpunit` read to completion on a **private**
     `DB_TEST_DATABASE` (start the DB first: `docker start rb-mariadb`)
   - named Playwright specs on **desktop and mobile**
   - a `javaScriptEnabled:false` pass over every touched route
   - screenshots to `docs/evidence/<slice>/` + a `design-qa.md` (model it on
     `imladris-admin-account-slice-10/design-qa.md`)
   - axe under `data-theme="system"` with `prefers-color-scheme: dark`
4. **Browser-evidence DB isolation matters**: from `tests/browser/`, export
   `PHP_INI_SCAN_DIR=""` and `DB_DATABASE=retroboards_console_e2e` — a shared
   DB gets poisoned. There is no PHPUnit CI; `npm run evidence` (the only
   GitHub workflow) is separate from `composer test`.
5. **Deviations go in the ledger, never silently.** One ADR, one plan doc, one
   ledger; no slice opens a second ADR.
6. **CSP is strict** (`script-src 'self'`, no nonce): no inline
   `<script>`/`<style>`/`on*=` anywhere; PE JS stays external.
7. **Anti-draft-loss**: failed writes re-render the form at 422 carrying
   `->errors` + `->old`; never redirect-and-drop.

## Execution order

1. **Close Slice 11's evidence gate first** — the current tip's unfinished
   obligation: run the six gates against `/admin/users`, `/admin/invitations`,
   user record, bulk confirm (both flows, incl. the no-JS 422), capture
   `docs/evidence/imladris-admin-account-slice-11/`, write `design-qa.md`
   reviewed against `D-admin-members.md` / `V-admin-members.md` /
   `R-admin-members.md`, commit.
2. **Push the branch** (`git push -u origin feat/imladris-admin-account`) so
   work is never local-only again.
3. **Slices 12 → 13 → 14 → 15 → 16 → 17 → 18** in sequence. They are
   independent once 2–3 landed (they did); keep the order anyway so the ledger
   and evidence stay chronological. One commit per slice, gates green, evidence
   committed with it.
4. **Slice 19 closeout**: de-fiction pass — the four test-pinned fiction strings
   (`Removed by a warden`, `Commends`, `Private counsel`, `sort=commends`) and
   the `Regard` chrome need an **owner decision before merging** (ADR 0024
   obligation 5; `profile/show.php` renders `Regard`, so fix both surfaces or
   neither); full evidence sweep; unpinned fiction strings.
5. **Merge into main**:
   - Branch fully green: `composer test` clean, `composer check:imladris`
     clean, evidence committed.
   - `git checkout main && git pull` — main has moved since the branch was cut
     (PR #58 etc.); merge cleanly, do not force.
   - `git merge --no-ff feat/imladris-admin-account` (repo pattern uses explicit
     merge commits). Expect the branch to carry duplicate commits of already-
     merged main work (`16fb994`, `90f4080` — same content as main's `debdf59`/
     `a7a2636` under different hashes); identical blobs merge without conflict.
   - **Immediately-following commit on main**: refresh
     `config/imladris-runtime-baseline.json` per ADR 0024 obligation 4 (this is
     the merger's job, not the branch's).
   - Push main. Run the full suite once more from `main` if practical.
   - Keep `feat/imladris-admin-account` around until the evidence is confirmed
     on main, then delete it.

## Definition of done

- Slices 11–19 all have code **and** evidence (`design-qa.md` + captures).
- Ledger and ADR statuses updated; fiction decision recorded (ADR or ledger).
- `main` contains the whole migration via a merge commit, the baseline digest
  refresh commit immediately after, and the full PHPUnit suite + `npm run
  evidence` are green.
