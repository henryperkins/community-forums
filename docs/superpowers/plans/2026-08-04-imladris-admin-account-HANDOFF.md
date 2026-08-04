# HANDOFF — Imladris admin/account migration, resuming at Slice 2's evidence

Written 2026-08-04. Branch **`feat/imladris-admin-account`** (3 commits, **never pushed**), cut from
`main` at `c476874`.

---

## The task

Migrate the Imladris design system into `/admin/*` and `/settings/*`. The governing rule:

> **Copy the design verbatim.** Structure, section order, component anatomy, class names, token
> usage, spacing, empty/loading/error states, microcopy register. The *only* sanctioned deviations
> are `feature-added`, `feature-removed`, `feature-changed`, `constraint`. Aesthetic preference is
> not one of them; anything unclassifiable is a plain `copy` difference and production changes.

Stage 1 (inventory + comparison) is **complete and reviewed**. Stage 2 (migration) is **3 of 19
slices in**.

## Read these first, in this order

1. `docs/adr/0024-imladris-admin-account-adoption.md` — the three operator decisions, 34 deduplicated
   constraints, every recorded gap, the mirror divergences, the delivery obligations.
2. `docs/superpowers/plans/2026-08-03-imladris-admin-account-adoption.md` — mapping table,
   classification counts, the 19-slice sequence, standing execution rules.
3. `docs/superpowers/plans/2026-08-03-imladris-admin-account-ledger.md` — the deviation ledger.
4. `docs/superpowers/plans/imladris-admin-account-stage1/README.md` — the raw per-screen workings.
   Slices 5–17 need the `D-` + `V-` + `R-` triple for their screen.

`CLAUDE.md` still governs everything (spec precedence, CSP, PE, flags, anti-draft-loss).

---

## Decisions already made — do not relitigate

| | Decision | Status |
|---|---|---|
| D1 | Adopt the design's horizontal area tier; **amend ADMIN.md §9.2/§9.4** rather than bend the design to the locked left-nav | Landed. ADR 0023's IA clause superseded *in part*; its three other findings stand. |
| D2 | An **eleventh area, `Moderation`, at tier index 1** | Landed in `_console.php`. `/mod/*` bodies are still Slice 18. |
| D3 | **Keep** search / bell / monogram / sign-out in the identity row, styled in the design's right-cluster idiom | Landed. |

Tier order: Overview · Moderation · Content · People · Members · Appearance · Notifications ·
Integrations · Packages · Features · Settings.

---

## What is done

**Slice 0 — adjudication** (`b61ca15`). ADR 0024; ADMIN.md §9.2 rewritten and §9.4 amended twice;
`LOCAL_RECONCILIATION.md` records the three upstream states the mirror refuses.

**Slice 1 — three live defects** (`8cc3894`), none of them design drift:
- Staff badge never flipped for anyone on the default `system` theme (`8ffefce` fixed only the
  explicit-`dark` path). Fixed in both `app.css` dark registers **plus** a guard test asserting the
  two blocks declare the same token set.
- Branding preview showed neither typed nor saved colour — duplicated `.brand-preview-*`, the later
  block painting `var(--brand)`, which `/brand.css` never emits.
- Thread Intelligence rail always read green regardless of state.

**Slice 2 — console chrome** (`b474e45`). `templates/admin/_console.php` + `_console_end.php`; a
fourth `layout.php` variant (`admin`); the tier CSS shipped via `composer build:imladris`; 39
templates retrofitted; 15 drill-ins demoted to `<h2 class="admin-record-title">` + `.admin-back`;
`_nav.php` deleted; the mobile drawer, scrim, focus trap and no-JS grid deleted with the rail they
served. **The admin nav is now entirely JavaScript-free.** Six PHPUnit tests rewritten.

### Verification state at handoff

- **PHPUnit 2445 / 17432 assertions / 2 skipped / 0 failures**, serial, `DB_TEST_DATABASE=retroboards_test_ds1`.
- `composer verify:imladris` 14/94. Digest refreshed twice, deliberately.
- CSP scan clean: `rg -n "<script|<style| on[a-z]+=" templates/ -S` → only `layout.php`'s external `src` tags.

---

## Resume here — Slice 2's outstanding browser work

Run from `tests/browser/` with these exports (isolation matters — a shared DB gets poisoned):

```bash
export PHP_INI_SCAN_DIR=""
export DB_DATABASE=retroboards_console_e2e
export RATELIMIT_PATH="$PWD/../../storage/ratelimit-console-e2e"
export PACKAGES_STORAGE_PATH="$PWD/../../storage/packages-console-e2e"
bash prepare.sh
npx playwright test admin-dashboard.spec.ts admin-remediation.spec.ts admin-features.spec.ts
```

Last result: **14 passed, 6 failed.** Five need spec updates for the new contract; one is
pre-existing and must not be "fixed" here.

| Spec | Why it fails | What it should assert now |
|---|---|---|
| `admin-dashboard.spec.ts:74` (`expectGroupedDirectory`, `:60-72`) | `.admin-nav-group-title` and the 26-destination `[data-admin-nav]` list no longer exist | 11 tier items in order inside `<nav class="admin-tier">`; `[data-admin-tier]` replaces `[data-admin-nav]`; the axe `include` must widen past `.admin` — the tier is full-bleed **outside** `<main>` |
| `admin-dashboard.spec.ts:208` (no-JS expanded directory) | The no-JS expanded grid was deleted with the drawer | A no-JS walk of the tier + tab strip, which now needs no JS at all |
| `admin-dashboard.spec.ts:115` (mobile drawer) | Drawer, scrim and focus trap deleted | The tier scrolls horizontally; assert 44px touch targets and `overflow-x` rather than drawer mechanics |
| `admin-features.spec.ts:84` (desktop + mobile) | Asserts a heading named `Feature flags`; the heading is now the **area's** `Features & badges`, with `Feature flags` demoted to a tab label | Assert the area heading, and the active tab `<span class="admin-tab is-active" aria-current="page">Feature flags</span>` |
| `admin-remediation.spec.ts:315` | **PRE-EXISTING — not caused by this work.** Verified by running the same spec at baseline `b61ca15` in a clean worktree with its own DB: identical failure. It hangs clicking `details.composer-details > summary` on a **forum board page**, unrelated to the console. | Leave alone. Investigate separately. |

`api-tokens.spec.ts:32` and `gate-a.spec.ts:73` also reference `[data-admin-nav-toggle]` — check
whether those blocks are now dead and update them in the same commit.

After the specs are green, capture evidence to `docs/evidence/<slice>/{desktop,mobile}/` in light
and twilight, then Slices 3 → 19 per the plan's §6 table.

> **26 screenshots under `docs/evidence/browser/{desktop,mobile}/` are dirty in the working tree.**
> That run had 6 failing specs, so they are a partial capture, not evidence — `git checkout` them
> and re-shoot once the specs above are green. ADR 0024 already records that the ADR 0023 `r2-*.png`
> set is superseded and owes a re-shoot.

---

## Landmines (each cost real time to find)

1. **Never run two PHPUnit jobs against one test DB.** It produced 77 then 9 phantom failures —
   lock timeouts and deadlock tests — that vanished on a serial re-run. Use a private
   `DB_TEST_DATABASE`, and grant it first: the `retro` user has no rights to a new DB name
   (`docker exec rb-mariadb sh -lc 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "CREATE DATABASE …; GRANT ALL ON ….* TO \"retro\"@\"%\";"'`).
2. **PowerShell's cwd is not the repo root.** `vendor/bin/phpunit` fails; use
   `php "C:\Users\htper\community-forums\vendor\phpunit\phpunit\phpunit"` with `Set-Location` first.
   Do not use `composer test` — it hits Composer's 300s timeout.
3. **`git add config/` sweeps in unrelated work.** `config/config.php`, `src/Core/Database.php`,
   `Dockerfile`, `deploy/entrypoint.sh`, `worker/`, `wrangler.jsonc` and
   `docs/runbooks/deployment-cloudflare.md` are **in-flight Cloudflare deployment work in the
   working tree. Do not commit them.** One already had to be amended back out.
4. **`public/assets/imladris.css` and `resources/imladris/` are generated.** Hand-editing fails
   `check:imladris` and is overwritten. Design-sourced CSS goes in
   `docs/design-system/imladris/components.css` then `composer build:imladris`. The application
   complement goes in `app.css`, unlayered.
5. **`app.css` is unlayered and beats every `@layer imladris.*` rule** regardless of specificity —
   181 of 211 design class names are contested. "The design system already styles this" is usually
   wrong.
6. **A new semantic colour token lands in three places**: `tokens/colors.css :root`,
   `tokens/colors.css [data-theme="dark"]`, and `app.css`'s
   `@media (prefers-color-scheme: dark) { [data-theme="system"] }` block — `layout.php` defaults to
   `system`, and `imladris.css` has no `prefers-color-scheme` block. Missing the third is exactly
   the staff-badge bug. `--gold-050` does not exist; use `--gold-soft`.
7. **Amending ADMIN.md/USER.md/COMMUNITY.md/COMPOSER.md or anything under `templates/`,
   `public/assets/` trips `verify:imladris`.** That gate is a design-contract review step, not
   noise. Refresh `application_surface.sha256` from
   `php bin/build-imladris-assets.php --print-application-digest`, then `composer build:imladris`,
   **once per merge on `main` by the merger** — never on a slice branch.
8. **Never recolour `.pill-admin`** — 41 call sites in three meanings, including the
   execution-disabled emergency brake at `package_security.php:18`.
9. **`theme_safe_mode.php` stays `variant=plain`** and carries no console chrome. It is the page you
   reach when a theme has broken the site.
10. **Anti-draft-loss**: 32 distinct 422 paths. A 422 that forgets to pass `area`/`tab` to
    `_console` renders an unlit tier — the one regression this restructure can introduce.

---

## Design-source provenance

The local mirror was refreshed 2026-08-03 from live Claude Design project
`c3e02753-607c-40b6-994c-9ba1a65bb367` (DesignSync, read-only). **It has ten `templates/admin-*`
screens, not the six the original brief listed**; `ui_kits/admin/` and
`feature-ui/{polls,tags,moderation}/` were retired upstream; `PRODUCTION_PARITY.md` is superseded by
`PRODUCTION.md`. Before trusting the mirror again, re-check it against the live project — it went
stale once already, mid-analysis.

Three upstream states are deliberately **not** taken (see `LOCAL_RECONCILIATION.md`):
`tokens/colors.css` (the mirror's WCAG-AA staff pair), `production-contract.json` (upstream
regressed `group_dms` and dropped a commit value a test pins), and `manifest.json` (its
`unresolved_gaps` are what this adoption closes — adopt the upstream form at closeout, not before).
