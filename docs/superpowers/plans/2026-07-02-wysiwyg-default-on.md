# Graduate `wysiwyg_composer` to Default-ON Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Flip the `wysiwyg_composer` feature flag to default-true so Milkdown is the out-of-the-box editor, following the in-repo graduation ritual (`polls`, `topic_workflow` precedents), with tests, browser-evidence wiring, and docs updated in step.

**Architecture:** No production code changes beyond one line in `FeatureFlags::DEFAULTS` — the layout gating, adapter, and composer bridge are already flag-driven. The work is: rewrite the two PHPUnit tests that assume dark, pin the browser-evidence seed to the textarea baseline (gate-a journeys drive `textarea.composer-input` directly), add a no-override browser proof of the GA default, and sweep five docs.

**Tech Stack:** Vanilla PHP 8.2 + PHPUnit 10 (strict mode), Playwright (`tests/browser`, workers:1), no new dependencies.

**Spec:** `docs/superpowers/specs/2026-07-02-wysiwyg-default-on-design.md`

## Global Constraints

- The working tree contains an **unrelated in-flight admin/anti-abuse workstream** (modified `src/Service/AdminService.php`, `src/Service/AntiAbuseService.php`, `templates/admin/dashboard.php`, `tests/Integration/Core/AppAdminModerationTest.php`, `tests/browser/a11y.spec.ts`, ~100 `docs/evidence/**/*.png`). **Never run `git add -A` / `git add .`** — stage only the exact paths listed in each commit step. Do not touch `tests/browser/a11y.spec.ts`.
- Branch: `graduate-wysiwyg-composer` (already created; spec committed as `590413d`).
- PHPUnit is strict: every test ≥1 assertion, no output, no warnings. Tests run against `DB_TEST_DATABASE` (default `retroboards_test`); the DB must be reachable before any phpunit run.
- Strict CSP posture unchanged: no inline `<script>`/`<style>` anywhere.
- All dates UTC; today is 2026-07-02.
- Full evidence-PNG regeneration is **out of scope** (deferred; recorded in the spec).

---

### Task 1: Flip the flag default with its two tests (one commit — the ritual requires source + tests land together)

**Files:**
- Modify: `src/Core/FeatureFlags.php:41`
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php:51-67`
- Modify: `tests/Integration/Core/AppComposerTest.php:172-194`

**Interfaces:**
- Produces: `FeatureFlags::DEFAULTS['wysiwyg_composer'] === true`; `templates/layout.php` (unchanged) consequently emits `data-wysiwyg-composer="1"`, `<link rel="stylesheet" href="/assets/wysiwyg-composer.css">`, and `<script type="module" src="/assets/wysiwyg-composer.js"></script>` on every page unless overridden.
- Consumes: existing `Tests\Support\TestCase` helpers `makeBoard`/`makeCategory`/`makeUser`/`actingAs`/`get`, and `SettingRepository::set('features', array)` which **replaces** the whole override map.

- [ ] **Step 1: Rewrite the AppFeatureFlagTest wysiwyg test to graduated expectations**

In `tests/Integration/Core/AppFeatureFlagTest.php`, replace the entire method `test_wysiwyg_composer_defaults_dark_and_is_independently_reversible` (lines 51-67) with:

```php
    public function test_wysiwyg_composer_is_available_by_default_and_can_be_disabled(): void
    {
        // wysiwyg_composer graduated to default-on (GA 2026-07-02): with no
        // features override, the Milkdown layer loads wherever the composer
        // renders (bundle tags + the body data attribute). An operator can
        // still roll the layer back via the features setting, and
        // rich_composer stays the broad kill switch (ADR 0013).
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('wysiwyg_composer', $flags->all());
        self::assertTrue($flags->enabled('wysiwyg_composer'));
        self::assertTrue($flags->enabled('rich_composer'));

        // Isolation: graduating wysiwyg_composer must not enable a dark neighbour.
        self::assertFalse($flags->enabled('group_dms'));

        // Available by default on a real page for a signed-in member.
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'wysiwyg-default']);
        $this->actingAs($this->makeUser(['username' => 'wysiwyg_default_user']));
        $page = $this->get('/c/wysiwyg-default');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('data-wysiwyg-composer="1"', $page->body());

        // Operator rollback: disabling the narrow flag removes the layer.
        $this->setFlags(['wysiwyg_composer' => false]);
        $disabled = $this->get('/c/wysiwyg-default');
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $disabled->body());

        // Kill-switch interplay: rich_composer=false keeps assets dark while
        // the narrow flag remains true by default (no wysiwyg key in the override).
        $this->setFlags(['rich_composer' => false]);
        $killed = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($killed->enabled('rich_composer'));
        self::assertTrue($killed->enabled('wysiwyg_composer'), 'the narrow flag stays true while the broad kill switch keeps assets dark');
        $killedPage = $this->get('/c/wysiwyg-default');
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $killedPage->body());
    }
```

Note: `$board` is used only for its slug; the literal `'wysiwyg-default'` is intentional (mirrors `AppComposerTest`'s style). Keep the `use` statements as-is (both `FeatureFlags` and `SettingRepository` are already imported).

- [ ] **Step 2: Rewrite the AppComposerTest asset-emission test**

In `tests/Integration/Core/AppComposerTest.php`, replace the entire method `test_wysiwyg_flag_only_loads_editor_assets_when_rich_composer_is_enabled` (lines 172-194) with:

```php
    public function test_wysiwyg_editor_assets_load_by_default_and_honor_flag_and_kill_switch(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'wysiwyg-assets']);
        $user = $this->makeUser(['username' => 'wysiwygassets']);
        $this->actingAs($user);

        // GA default-on (2026-07-02): with no features override the Milkdown
        // bundle loads alongside the shared composer bridge.
        $defaultPage = $this->get('/c/wysiwyg-assets');
        self::assertStringContainsString('/assets/composer.js', $defaultPage->body());
        self::assertStringContainsString('/assets/wysiwyg-composer.css', $defaultPage->body());
        self::assertStringContainsString('<script type="module" src="/assets/wysiwyg-composer.js"></script>', $defaultPage->body());
        self::assertStringContainsString('data-wysiwyg-composer="1"', $defaultPage->body());

        // Operator rollback: the narrow flag removes only the WYSIWYG layer;
        // the enhanced Markdown composer keeps loading.
        (new SettingRepository($this->db))->set('features', ['wysiwyg_composer' => false]);
        $disabledPage = $this->get('/c/wysiwyg-assets');
        self::assertStringContainsString('/assets/composer.js', $disabledPage->body());
        self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $disabledPage->body());
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $disabledPage->body());

        // Broad kill switch: rich_composer=false keeps every enhanced asset
        // out even though wysiwyg_composer stays true by default.
        (new SettingRepository($this->db))->set('features', ['rich_composer' => false]);
        $killedPage = $this->get('/c/wysiwyg-assets');
        self::assertStringNotContainsString('/assets/composer.js', $killedPage->body());
        self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $killedPage->body());
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $killedPage->body());
    }
```

- [ ] **Step 3: Run both files — expect the two rewritten tests to FAIL (default still false)**

Run: `vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppComposerTest.php 2>&1 | tail -15`
Expected: FAILURES — `test_wysiwyg_composer_is_available_by_default_and_can_be_disabled` fails on `assertTrue($flags->enabled('wysiwyg_composer'))`; `test_wysiwyg_editor_assets_load_by_default_and_honor_flag_and_kill_switch` fails on the default-page `wysiwyg-composer.css` containment. All other tests in both files pass. If the DB is unreachable, start it first (see Task 4 Step 1) — do not proceed on a bootstrap error.

- [ ] **Step 4: Flip the default**

In `src/Core/FeatureFlags.php` line 41, replace:

```php
        'wysiwyg_composer' => false, // Milkdown WYSIWYG layer over canonical Markdown textarea; deploy-dark until evidence lands
```

with:

```php
        'wysiwyg_composer' => true,  // Milkdown WYSIWYG layer over canonical Markdown textarea — GA default-on (2026-07-02; reversible via features override)
```

- [ ] **Step 5: Run both files again — expect PASS**

Run: `vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppComposerTest.php 2>&1 | tail -5`
Expected: OK, all tests in both files pass (AppFeatureFlagTest ~9 tests, AppComposerTest ~its full count). If any *other* test in these files now fails, it assumed the flag dark — fix it in this task before committing.

- [ ] **Step 6: Commit (exact paths only)**

```bash
git add src/Core/FeatureFlags.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppComposerTest.php
git commit -m "feat(composer): graduate wysiwyg_composer to default-ON

Milkdown is now the out-of-the-box editor layer (GA 2026-07-02).
Operators can still disable via the features override; rich_composer
remains the broad kill switch (ADR 0013).

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: Browser-evidence wiring — pin the gate-a textarea baseline, prove the GA default with no override

**Files:**
- Modify: `tests/browser/seed.php:53-68` (the `$evidenceFeatures` array)
- Modify: `tests/browser/wysiwyg-composer.spec.ts:22-29` (flag helper) and `:117` (CSP/mount test)

**Interfaces:**
- Consumes: `setWysiwygComposer` writes the `features` override via `runPhp`; `prepare.sh` (also wrapped by `prepare-prodlike.sh`) seeds `$evidenceFeatures`, so one pin covers gate-a, a11y, and server-drafts runs.
- Produces: `setWysiwygComposer(enabled: boolean | null)` — `null` **removes** the `wysiwyg_composer` key so the `FeatureFlags` default applies. `a11y.spec.ts` is NOT touched (its wysiwyg scan already resets the flag to `false` in a `finally`, and the admin workstream has uncommitted edits there).

- [ ] **Step 1: Pin the evidence seed to the textarea baseline**

In `tests/browser/seed.php`, insert after the line `    'package_themes' => true, // Inc 4 (P5-03): package theme preview/activate/safe-mode/rollback evidence`:

```php
    'wysiwyg_composer' => false, // GA default-on (2026-07-02) but pinned OFF for the evidence baseline: gate-a + server-drafts journeys drive textarea.composer-input directly (fill/drop/toBeVisible), which a mounted Milkdown hides; the rich surface's browser evidence lives in wysiwyg-composer.spec.ts + the a11y.spec.ts scans, which toggle the flag per test
```

- [ ] **Step 2: Extend the flag helper with an unset mode**

In `tests/browser/wysiwyg-composer.spec.ts`, replace the helper (lines 22-29):

```ts
function setWysiwygComposer(enabled: boolean): void {
  runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};
$settings->set('features', $features);
`);
}
```

with:

```ts
function setWysiwygComposer(enabled: boolean | null): void {
  // null removes the override so the FeatureFlags DEFAULTS value applies —
  // used to prove the GA default mounts Milkdown without any features row.
  const mutation = enabled === null
    ? "unset($features['wysiwyg_composer']);"
    : `$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};`;
  runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
${mutation}
$settings->set('features', $features);
`);
}
```

- [ ] **Step 3: Make the CSP/mount test prove the GA default**

In the same file, in `test('wysiwyg assets load under strict CSP without violations', ...)` replace line 117:

```ts
  setWysiwygComposer(true);
```

with:

```ts
  setWysiwygComposer(null); // no override: proves the GA default mounts Milkdown
```

All other tests keep their explicit `true`/`false` calls (the seed pin means earlier tests in the file have already written `false`; `null` here genuinely exercises the default).

- [ ] **Step 4: Commit (exact paths only)**

```bash
git add tests/browser/seed.php tests/browser/wysiwyg-composer.spec.ts
git commit -m "test(browser): pin gate-a textarea baseline; prove wysiwyg GA default with no override

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

(Playwright execution happens in Task 4 with the prepared e2e DB.)

---

### Task 3: Docs sweep — runbook, inventory, phase status, CLAUDE.md, COMPOSER.md

**Files:**
- Modify: `docs/runbooks/wysiwyg_composer.md` (full rewrite below)
- Modify: `docs/evidence/deploy-dark-features.md` (header date, wysiwyg row, Notes bullet)
- Modify: `PHASE_5_STATUS.md` (Status paragraph sentence)
- Modify: `CLAUDE.md` (feature-flags paragraph sentence)
- Modify: `COMPOSER.md` (§17.1 delivery note + changelog v0.6)

**Interfaces:**
- Consumes: exact current sentences quoted in each step (verify with grep before editing; if a sentence moved, match on content, not line number).
- Produces: no doc anywhere still claims `wysiwyg_composer` is deploy-dark.

- [ ] **Step 1: Rewrite the runbook banner and sections**

Replace the full contents of `docs/runbooks/wysiwyg_composer.md` with:

```markdown
# Runbook - WYSIWYG Composer (`wysiwyg_composer`)

`wysiwyg_composer` gates only the Milkdown editor layer. **Default-ON as of
2026-07-02** (graduated out of deploy-dark; fully reversible via the
`features` override). `rich_composer=false` remains the broad kill switch and
prevents all enhanced composer assets from loading. Follows the same
conventions as `docs/runbooks/polls.md` and `docs/runbooks/topic_workflow.md`.

> **Golden rule:** for any editor logic defect, **disable the
> `wysiwyg_composer` flag first** (the Milkdown bundle stops loading and the
> composer falls back to the enhanced Markdown textarea; posting keeps
> working), then investigate. Disabling is non-destructive - posts, drafts,
> and uploads are untouched because the Markdown `<textarea>` is the only
> submit source.

## Roll back (disable)

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["wysiwyg_composer"]=false; $r->set("features",$f);'
```

Existing posts and drafts remain Markdown and need no migration; the enhanced
Markdown textarea composer keeps serving.

## Re-enable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); unset($f["wysiwyg_composer"]); $r->set("features",$f);'
```

Removing the override restores the default (ON). Setting the key to `true`
explicitly is equivalent.

## Emergency Disable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["rich_composer"]=false; $r->set("features",$f);'
```

This disables `composer.js`, the suggestion picker, and the WYSIWYG bundle.
Server-rendered textarea posting remains available.

## Verify

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppComposerSuggestTest.php tests/Integration/Core/AppMentionLinkRenderTest.php tests/Integration/Core/AppFeatureFlagTest.php
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer"
```

Evidence covered by the browser gate:

- strict CSP asset load with no inline-script/style violations, exercised
  with **no features override** (proves the GA default mounts Milkdown)
- WYSIWYG new-topic submit, source-mode round trip, edit no-op preservation,
  preview parity, chips, internal URL paste, and mobile smoke
- textarea fallback for the `wysiwyg_composer=false` rollback
- axe scans for the enhanced toolbar, WYSIWYG surface, reference picker, and
  source-mode form

The gate-a screenshot suite intentionally pins `wysiwyg_composer=false` in
`tests/browser/seed.php`: those journeys capture the progressive-enhancement
textarea baseline (and drive `textarea.composer-input` directly), while this
spec owns the rich-surface evidence.

## Known Limits

Markdown remains canonical. Legacy Markdown may normalize after a user edits
through the rich surface, but a no-op edit must not rewrite the stored body.

Do not delete or hide the textarea in templates: it is the submit source,
source-mode editor, and no-JS fallback.
```

- [ ] **Step 2: Update the deploy-dark inventory**

In `docs/evidence/deploy-dark-features.md`:

(a) Replace the header date lines:

```markdown
**Date:** 2026-07-02 (graduation readiness ranking added; Phase 5 rows
reconciled with `PHASE_5_STATUS.md`)
```

with:

```markdown
**Date:** 2026-07-02 (`wysiwyg_composer` graduated; graduation readiness
ranking added; Phase 5 rows reconciled with `PHASE_5_STATUS.md`)
```

(b) Replace the `wysiwyg_composer` table row (currently beginning `| `wysiwyg_composer` | Optional Milkdown WYSIWYG layer`) with:

```markdown
| `wysiwyg_composer` | Milkdown WYSIWYG layer over the canonical Markdown textarea | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; reversible via `features` override; `rich_composer=false` remains the emergency kill switch). Acceptance evidence: ADR 0013, runbook `docs/runbooks/wysiwyg_composer.md`, `AppComposerTest`, `AppComposerSuggestTest`, `AppMentionLinkRenderTest`, `MarkdownRoundTripTest`, `npm run check:wysiwyg`, browser `wysiwyg-composer.spec.ts` (CSP + GA-default mount with no override, source mode, no-op edit, preview parity, chips, internal URL paste, mobile smoke, textarea fallback), and `a11y.spec.ts` WYSIWYG toolbar/picker/source scans. Retained here for traceability. |
```

(c) In the Notes bullets (after the `tags`/`expanded_feeds`/`reputation_ledger` graduation bullet), add:

```markdown
- `wysiwyg_composer` graduated out of deploy-dark on 2026-07-02: its
  `FeatureFlags` default is now `true` (its acceptance evidence had already
  landed with PR #33). The browser-evidence seed intentionally pins it OFF so
  the gate-a screenshot journeys keep capturing the textarea
  progressive-enhancement baseline; `wysiwyg-composer.spec.ts` proves the GA
  default mounts Milkdown with no features override.
```

- [ ] **Step 3: Record the graduation in PHASE_5_STATUS.md**

Append to the end of the `**Status:**` paragraph (after `...remains gated until each workstream has release evidence.`):

```markdown
 The WYSIWYG composer stream that shipped alongside Inc 4 (PR #33) graduated on 2026-07-02: `wysiwyg_composer` is now default-ON (`docs/runbooks/wysiwyg_composer.md`); the gate-a browser-evidence seed pins the textarea baseline, and `wysiwyg-composer.spec.ts` proves the GA default with no override.
```

`**Last updated:**` already reads 2026-07-02 — leave it.

- [ ] **Step 4: Update the CLAUDE.md flags paragraph**

In `CLAUDE.md`, in the paragraph beginning `Every post-MVP subsystem is gated by a flag.`, replace:

```markdown
`group_dms`, `badge_rules`, `community_memory`, and `content_references` remain default OFF (deploy-dark).
```

with:

```markdown
`badge_rules` graduated to default-ON on 2026-07-02; `wysiwyg_composer` (Phase 5 composer stream) graduated to default-ON on 2026-07-02 (see `docs/runbooks/wysiwyg_composer.md`); `group_dms`, `community_memory`, and `content_references` remain default OFF (deploy-dark).
```

(The `badge_rules` clause fixes a pre-existing staleness: it graduated with PR #32 today — `AppFeatureFlagTest::test_phase4_gate_a_flags_have_expected_default_posture` asserts it default-ON. Verify with `grep -n 'badge_rules' docs/evidence/deploy-dark-features.md` that the inventory agrees; if its row says otherwise, drop the `badge_rules` clause from this edit and note the discrepancy in the final report instead.)

- [ ] **Step 5: Update COMPOSER.md §17.1 and changelog**

(a) In §17.1, replace:

```markdown
the optional Milkdown WYSIWYG adapter is deploy-dark behind `wysiwyg_composer` per ADR 0013.
```

with:

```markdown
the Milkdown WYSIWYG adapter (ADR 0013) shipped deploy-dark behind `wysiwyg_composer` and graduated to **default-ON on 2026-07-02**.
```

(b) In the changelog table (after the v0.5 row), add:

```markdown
| v0.6 | 2026-07-02 | `wysiwyg_composer` graduated to **default-ON** (GA 2026-07-02; reversible via `features` override; `rich_composer` remains the broad kill switch). §17.1 delivery note updated. Browser evidence split recorded: gate-a screenshots keep the textarea baseline via a seed pin; `wysiwyg-composer.spec.ts` proves the GA default mounts with no override. |
```

- [ ] **Step 6: Confirm no doc still claims deploy-dark**

Run: `grep -rn 'deploy-dark' --include='*.md' . 2>/dev/null | grep -i wysiwyg | grep -v superpowers | grep -v node_modules`
Expected: only historical/changelog phrasing ("shipped deploy-dark ... graduated") — no line asserting the flag *is* deploy-dark now.

- [ ] **Step 7: Commit (exact paths only)**

```bash
git add docs/runbooks/wysiwyg_composer.md docs/evidence/deploy-dark-features.md PHASE_5_STATUS.md CLAUDE.md COMPOSER.md
git commit -m "docs(composer): record wysiwyg_composer graduation to default-ON

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: Verification gates

**Files:** none modified (verification only; fixes discovered here belong to the task that owns the file).

**Interfaces:**
- Consumes: everything above; local MariaDB with `retroboards_test` (PHPUnit) and `retroboards_e2e` (Playwright); root `node_modules` for `check:wysiwyg`; `tests/browser/node_modules` for Playwright.

- [ ] **Step 1: Ensure the test DB is reachable**

Run: `mysql -e 'SELECT 1' 2>/dev/null || sudo systemctl start mariadb; php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); echo "db ok\n";'`
Expected: no connection error. (Local stack per dev-environment-setup: PHP 8.3 + MariaDB 10.11; there is no `rb-mariadb` docker container on this machine.)

- [ ] **Step 2: Full PHPUnit suite — catches any test that silently assumed the flag dark**

Run: `composer test 2>&1 | tail -6`
Expected: `OK (1268 tests, ~6619 assertions)` (assertion count may differ slightly from the rewritten tests; test count stays 1268 — both tests were rewritten in place, none added). Any failure = a test assuming the old default; fix it in Task 1's files and amend that commit.

- [ ] **Step 3: Deterministic bundle check (no bundle changes expected)**

Run: `npm run check:wysiwyg 2>&1 | tail -3`
Expected: vite build completes and `git diff --exit-code` passes (exit 0) — the graduation touches no client source.

- [ ] **Step 4: Targeted Playwright — wysiwyg spec (incl. new GA-default proof) + composer a11y scans**

Run:
```bash
cd tests/browser && npm run prepare-db && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer" 2>&1 | tail -6
```
Expected: all matched tests pass, including `wysiwyg assets load under strict CSP without violations` now running with **no** features override, and `wysiwyg kill switch keeps textarea composer fallback` proving the rollback path. (If `prepare-db` is not a defined script, use `bash prepare.sh` — check `tests/browser/package.json` scripts first.)

- [ ] **Step 5: Confirm nothing unrelated is staged and the branch is clean of admin-workstream files**

Run: `git status --porcelain | grep -v '^ M docs/evidence' | grep -v '^??' | grep '^[MARC]'`
Expected: empty output (no staged files remain; the admin workstream's unstaged modifications are untouched).

---

## Deferred follow-ups (recorded in the spec; do not do them in this plan)

1. Full evidence-PNG regeneration once the admin/anti-abuse workstream lands (composer screenshots will then show Milkdown or the pinned baseline — decide which gate-a should capture, and make its `textarea.composer-input` interactions wysiwyg-aware if Milkdown-on is chosen).
2. The four theme-related fail-dark follow-ups from the PR #33 review (unrelated to this graduation).
