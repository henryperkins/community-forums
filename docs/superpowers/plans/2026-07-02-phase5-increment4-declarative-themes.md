# Phase 5 Increment 4 — Declarative Theme Packages (P5-03) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land P5-03 deploy-dark behind `package_themes`: a theme-type package (approved design tokens + scanned local assets, embedded in the signed `rb-release.v1` document) that an operator can **build deterministically, preview in isolation, activate transactionally with last-known-good capture, roll back in one action, and bypass through a flag-independent safe mode** — with the token whitelist, asset scanner, WCAG contrast gate, and CSP-safe external-stylesheet serving proving TM-TH-01…07.

**Architecture:** A pure token catalogue (`ThemeTokenPolicy`) owns the whitelist/grammars/contrast pairs; `ManifestValidator` grows a type-conditional `theme` block; `ThemeAssetScanner` neutralizes embedded assets (sniff + GD re-encode, raster-only); `ThemeBuildService` turns verified release bytes into a deterministic, digest-keyed stylesheet + DB-stored assets; `ThemeStateService` owns the site pointers (active / last-known-good / safe mode) and the §8.5 activation invariant. Serving is three tiny public routes (`/theme/{digest}.css`, `/theme/preview.css`, `/theme/asset/{digest}`) that fail dark; the shell links them from a try/catch-wrapped context builder next to `App::branding()`. Enabled-but-not-activated themes change nothing — activation is an explicit, reauthenticated operator action.

**Tech Stack:** PHP 8.2+ (ext-gd for re-encode, finfo for sniff), MySQL/MariaDB via `Database`, the in-process kernel harness (`Tests\Support\TestCase`), `SigningHarness`/`RegistryFixtures` (F6), Playwright + axe.

## Global Constraints

*Every task implicitly includes this section.*

- **Deploy-dark.** `package_themes` stays default `false` in `FeatureFlags::DEFAULTS` (it already is — do not touch the map). Every new route except the safe-mode recovery pair 404s when the flag is off, with regressions in `tests/Integration/Core/AppFeatureFlagTest.php`. The core-survival test (`test_core_forum_survives_with_every_feature_flag_disabled`) must stay green untouched.
- **Safe mode is flag-independent** (ledger `package_themes` rollback note; PHASE_5_PLAN §5 #15, §13.2): the recovery routes work with `package_themes` off, and `THEME_SAFE_MODE=1` in the environment forces safe mode with no DB write and no UI.
- **Gate A theme scope only** (PHASE_5_PLAN lines 120–123): tokens + approved local assets. **No stylesheet modules, no selectors, no `@import`, no remote/data URLs, no fonts fetched from anywhere, no JS/PHP/template replacement.** Restricted stylesheet modules are Gate B — do not build them.
- **Precedence decision (recorded here per the program-plan Inc 4 note, using recommended defaults):** operator-local configuration always beats package themes. Cascade: app.css baseline → **package theme tokens** (replaces the `classic|retro` preset layer while active) → operator `brand_color_*` hex overrides → operator custom CSS. Implementation: the shell links `/theme/{digest}.css` **before** `/brand.css`, and `BrandingController::css()` skips the retro preset block while a package theme is active. Built-in theme identities stay deterministic as `system:classic` / `system:retro` (the existing preset setting); safe mode / flag-off / no-active-theme all serve them unchanged.
- **§8.5 activation invariant:** "A theme becomes active only after package verification, validation, build, asset availability, and last-known-good capture succeed" — one transaction swaps the pointer; any earlier failure leaves the previous theme active.
- **Migration `0071` exactly** (`phase5_theme_packages` per the program plan §C). Additive `up()`; `down()` drops FKs before tables; seed rows use `INSERT IGNORE`. Hand-update `SCHEMA.md` (shape + §9 changelog + version bump). JSON-ish columns use the same column type as `0069`'s `settings_json` (check and match — the dev MariaDB reports JSON as LONGTEXT).
- **Write path.** Controllers thin (marshal → one service call → map exceptions); services own rules inside `$db->transaction(fn)`; repositories final, prepared statements, assoc arrays. Controllers catch `ValidationException` → re-render 422 with `errors` + `old`; `PackagePolicyException` renders its `code: message` inline (Inc 3 `policyMessage()` pattern).
- **Reauth:** activation, theme rollback, and safe-mode **exit** call `ReauthGate::requirePassword`. Preview, preview-clear, and safe-mode **entry** are deliberately low-friction (admin + CSRF only) — entry is the emergency brake.
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET`; never reuse a named placeholder; `UTC_TIMESTAMP()`/`gmdate()` only.
- **Strict CSP / no-JS-first.** No inline `<script>`/`<style>`. Built theme CSS is an external same-origin stylesheet; built CSS must contain **zero** external URLs (TM-TH-02) — the only `url(...)` the builder may emit points at `/theme/asset/{digest}`.
- **CSRF on every POST; no GET mutates state. Per-surface noindex** on every admin response including redirects.
- **Audit:** activate / rollback / deactivate / safe-mode enter + exit write `moderation_log` rows; package-scoped events also write `package_history` (ENUM widened in `0071`).
- **Determinism:** same (validated tokens, asset bytes, token schema version) → byte-identical CSS → same `css_digest`. No timestamps, randomness, or locale-dependent formatting in the builder.
- **Strict PHPUnit:** ≥1 assertion per test, no output, no warnings; per-test isolation is one rolled-back transaction (no savepoints — assert HTTP/observable behavior).
- **Never `git add -A`** — stage explicit paths in every commit; end commit messages with the `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>` trailer.

## Context — what already exists (read before Task 1)

- **`src/Security/Packages/ManifestValidator.php`** — fail-closed `rb-manifest.v2`; `TOP_KEYS` is a closed allow-list (`unknown_field` refusal); `TYPES` already contains `theme` but **no theme-specific validation exists**; produces readonly `PackageManifest` (`src/Security/Packages/PackageManifest.php`) whose constructor is called from exactly one place (the validator's return).
- **`src/Service/Packages/PackageArtifactStore.php`** — content-addressed `{digest}.json` cache of the exact signed release bytes; `has()/verify()/get()`.
- **`src/Service/Packages/PackageLifecycleService.php`** — `enable()` (line ~251) verifies the artifact and quarantines on tamper *before* `setState('enabled')`; `disable()`, `uninstall()`, `reverify()`. **No `type === 'theme'` branch anywhere.** `src/Service/Packages/PackageUpdateService.php` swaps releases on update/rollback. `src/Service/Packages/PackageHealthService.php` — `checkAll()` quarantines tamper, `enforcePolicy()` security-disables on advisory/blocklist; driven by `worker:packages` (`src/Worker/PackageHealthWorker.php`, no-ops while `package_registry` is dark).
- **`src/Controller/BrandingController.php`** — `/brand.css` emitter: retro preset block (line 45–47) then operator hex tokens then custom CSS append; `contrastToken()/contrastRatio()/luminance()` implement WCAG 4.5 (lines 246–277); `customCssError()` is the CSS-policy precedent. `App::branding()` (`src/Core/App.php:549-606`) builds the try/catch-wrapped `$branding` view global; `templates/layout.php:37-38` links `app.css` then conditionally `/brand.css?v=`.
- **`tests/Support/Phase5/SigningHarness.php`** — `mintManifest()` base is a **theme-type manifest with no theme block** (lines 115–147); `mintRelease()` nests it in signed `rb-release.v1` bytes; `RegistryFixtures::seed()/seedRelease()` hydrate `acme/midnight-theme` 1.0.0/1.1.0 into the DB (+ artifact files).
- **`tests/browser/seed.php`** — `$ensureRegistryFixtures` mints the root key, seeds the packages, writes artifacts, pre-installs `acme/consent-demo`; `$evidenceFeatures` enables flags for the evidence run.
- **Threat model:** `docs/phase5/threat-models/theme-phishing.md` + `fixtures.json` — TM-TH-01…07 `status: stub, owner: Inc4`; `tests/Unit/Core/ThreatModelIndexTest.php` requires implemented fixtures to name an existing test file.
- **Ledger:** `docs/phase5/requirement-ledger.json` GA-DOD-07 (`P5-03`, state R1, evidence []); `tests/Unit/Core/Phase5EvidenceMapTest.php` requires R3+ states to link existing files.
- **Budgets:** `src/Support/Phase5Budgets.php` has **no theme key**; `Phase5BudgetReportService::rows()` renders `PENDING (<measurable_at>)` for unmeasured keys; `bin/console verify:phase5-budgets` regenerates `docs/evidence/phase5/performance-budgets.md`. ADR 0004 D11 approves "install/update p95 10s for **declarative packages**" — the theme build+apply budget transcribes that umbrella (10 000 ms), it does not invent a new gate number.
- **Reference tests to mirror:** `tests/Integration/Core/AppBrandingThemeTest.php` (branding/custom-CSS surface), `tests/Integration/Service/PackageLifecycleServiceTest.php` + `tests/Integration/Core/AppPackageLifecycleTest.php` (lifecycle + admin surface), `tests/Unit/Security/Packages/ManifestValidatorTest.php`, `tests/Integration/Worker/PackageHealthWorkerTest.php` (name may differ — locate the Inc 3 health-worker test before extending).
- **`bin/console`** — `repair` (RepairService), `worker:packages`, `verify:phase5-budgets` wiring patterns.

## Locked interfaces (all tasks must match these exactly)

```
App\Security\Packages\ThemeTokenPolicy          (pure static)
  SCHEMA_VERSION = 1
  const TOKENS: array<string, 'color'|'length'|'font'|'asset'>   // exact set in Task 1
  static isKnown(string $token): bool
  static type(string $token): string                              // throws \InvalidArgumentException on unknown
  static validateValue(string $token, string $value, array $assetNames): ?string
        // null = valid; string = human refusal reason. Colors normalize via strtolower before storage.
  static contrastPairs(): array<array{fg:string,bg:string,min:float}>
  static baseline(string $variant): array<string,string>          // 'light'|'dark' → token→value (colors only)

App\Service\Packages\ThemeAssetScanner
  const KINDS = ['png','jpeg','gif','webp']
  const MAX_ASSET_BYTES = 131072            // 128 KiB decoded, per asset
  const MAX_TOTAL_BYTES = 262144            // 256 KiB decoded, per theme
  const MAX_ASSETS = 4
  scan(string $name, string $kind, string $bytes): array{mime:string,bytes:string,digest:string}
        // throws PackagePolicyException('theme_asset', …) on sniff mismatch, decode failure, SVG,
        // polyglot (GD cannot decode or finfo disagrees with kind), oversize. Returned bytes are
        // the GD re-encoded (neutralized) image; digest = sha256 of the returned bytes.

App\Service\Packages\ThemeBuildService
  __construct(Database, PackageThemeRepository, ThemeAssetScanner, ?Telemetry = null)
  ensureBuild(array $install, array $manifest, ?int $actorId = null): array   // build row; idempotent per (installed_package_id, source_digest)
  static emitCss(array $tokens, array $darkTokens, array $assetDigests): string  // pure, deterministic
        // $assetDigests: name → served digest. Emits :root{…}\n[data-theme="dark"]{…}\n@media(...)
  // ensureBuild throws PackagePolicyException codes: theme_missing (no theme block),
  // theme_token, theme_asset, theme_contrast, theme_schema (wrong schema_version)

App\Repository\PackageThemeRepository           (final, thin)
  findBuild(int $buildId): ?array
  findBuildFor(int $installedId, string $sourceDigest): ?array
  createBuild(array $row): int
  addAsset(int $buildId, string $name, string $mime, string $bytes, string $digest): int
  assetsFor(int $buildId): array                                  // no bytes column
  findAssetByDigest(string $digest): ?array                       // includes bytes
  findCssByDigest(string $cssDigest): ?array                      // newest build row with that css_digest
  state(): array{active_build_id:?int, lkg_build_id:?int, activated_at:?string, activated_by:?int}
  setState(?int $activeBuildId, ?int $lkgBuildId, ?int $actorId): void
  buildsForInstall(int $installedId): array

App\Service\Packages\ThemeStateService
  __construct(Database, PackageThemeRepository, InstalledPackageRepository, PackageRepository,
              PackageReleaseRepository, PackageArtifactStore, PackageSecurityGate, ThemeBuildService,
              WriteGate, ReauthGate, SettingRepository, ModerationLogRepository,
              PackageHistoryRepository, ?Telemetry = null)
  safeMode(): bool                                   // env override (config 'theme.safe_mode') || settings 'theme_safe_mode' === '1'
  setSafeMode(User $admin, bool $on, ?string $currentPassword = null): void   // exit requires password; enter does not
  activeBuild(): ?array                              // safe-mode/eligibility-checked: build row + css_digest, or null
  previewBuildFor(?int $buildId): ?array             // eligibility-checked lookup for the preview route
  preview(User $admin, int $installedId): array      // ensureBuild + return build (controller stores id in session)
  activate(User $admin, string $currentPassword, int $installedId): array    // §8.5 transaction; returns new build
  rollback(User $admin, string $currentPassword): array                      // swap active↔lkg; throws theme_no_lkg
  onInstallIneligible(int $installedId, string $reason, ?int $actorId = null): bool  // worker/lifecycle hook
  repair(): array{cleared_active:int, cleared_lkg:int}                       // used by RepairService

App\Controller\ThemeController                  (public serving; no auth)
  css(Request): Response          GET /theme/{digest}.css      — flag-gated, safe-mode-gated, immutable+ETag
  previewCss(Request): Response   GET /theme/preview.css       — flag-gated, session-gated, Cache-Control: no-store
  asset(Request): Response        GET /theme/asset/{digest}    — flag-gated, safe-mode-gated, immutable

App\Controller\AdminThemeController
  index                GET  /admin/themes
  preview              POST /admin/themes/{id}/preview          ({id} = installed_packages.id)
  clearPreview         POST /admin/themes/preview/clear
  activate             POST /admin/themes/{id}/activate         (current_password)
  rollback             POST /admin/themes/rollback              (current_password)
  safeModeForm         GET  /admin/themes/safe-mode             (flag-INDEPENDENT, variant=plain, never links package CSS)
  safeMode             POST /admin/themes/safe-mode             (flag-INDEPENDENT; enter: no password; exit: current_password)

Manifest theme block (inside rb-manifest.v2, required iff type === 'theme', forbidden otherwise):
  "theme": {
    "schema_version": 1,
    "tokens":      {"--accent": "#8f3d12", "--surface-texture": "parchment", …},
    "dark_tokens": {"--surface": "#141210", …},                  // optional
    "assets": [{"name":"parchment","kind":"png","sha256":"<hex64 of decoded bytes>","data_base64":"…"}]  // optional
  }
  PackageManifest gains trailing constructor param: public readonly ?array $theme = null
  (shape: array{schema_version:int, tokens:array<string,string>, dark_tokens:array<string,string>,
   assets:list<array{name:string,kind:string,sha256:string,bytes:string}>} — bytes already decoded)

Session key for preview: 'theme_preview_build' (int build id).  Settings key: 'theme_safe_mode' ('1' | '').
Config key: 'theme.safe_mode' ← env THEME_SAFE_MODE ('1' = forced safe mode).
View global: 'package_theme' = ['active_css_digest' => ?string, 'preview_css_digest' => ?string]
  (preview wins in the shell; both null when dark/safe/none).
package_history.event ENUM gains: 'theme_activate', 'theme_rollback', 'theme_deactivate' (0071).
moderation_log actions: theme_activate, theme_rollback, theme_deactivate, theme_safe_mode_enter,
  theme_safe_mode_exit, theme_preview (target_type 'package' for the first three, 'setting' for safe mode/preview).
Telemetry events: 'theme.lifecycle' {action: build|activate|rollback|deactivate|safe_mode_enter|safe_mode_exit, package, digest?}
Budget key: 'theme.build_apply_p95' target 10000 ms p95, measurable_at 'inc4' (D11 declarative-package umbrella).
```

Serving contract (documented in `docs/phase5/registry-protocol.md` in Task 11):

```
GET /theme/{css_digest}.css   200 text/css   Cache-Control: public, max-age=31536000, immutable   ETag: "{css_digest}"
    404 when: flag off · safe mode on · digest unknown · owning install not enabled
GET /theme/preview.css        200 text/css   Cache-Control: private, no-store
    404 when: flag off · safe mode on · no session preview · previewed install not eligible
GET /theme/asset/{digest}     200 image/*    Cache-Control: public, max-age=31536000, immutable   ETag: "{digest}"
    404 when: flag off · safe mode on · digest unknown
```

---

### Task 1: `ThemeTokenPolicy` — the pure token catalogue, value grammars, and contrast maths (TM-TH-01/02 value layer)

The whole threat model hangs on this class: token **names** come from a closed whitelist, token **values** match tight per-type grammars that structurally cannot express selectors, declarations, URLs, or imports. It also owns the WCAG contrast pairs and the baseline token values used to compute effective contrast for partial token sets.

**Files:**
- Create: `src/Security/Packages/ThemeTokenPolicy.php`
- Test: `tests/Unit/Security/Packages/ThemeTokenPolicyTest.php`

**Interfaces:**
- Consumes: nothing (pure static, mirrors `ApiScopes`/`CapabilityCatalog`).
- Produces: the locked `ThemeTokenPolicy` interface above. Baseline values are transcribed from `public/assets/app.css` `:root` and `[data-theme="dark"]` — **read the real current values while implementing; do not trust this plan's examples.**

- [ ] **Step 1: Transcribe the baselines.** Open `public/assets/app.css`, note the current values of `--surface, --surface-2, --surface-3, --border, --text, --text-muted, --text-strong, --text-inverse, --accent, --accent-contrast, --accent-2, --danger, --brand` in `:root` (light) and their overrides in `[data-theme="dark"]` (dark inherits light for tokens it doesn't override). Convert any non-hex color (e.g. `rgb()`/named) to 6-digit hex by hand. These become `BASELINE_LIGHT` / `BASELINE_DARK`.

- [ ] **Step 2: Write the failing unit test** `tests/Unit/Security/Packages/ThemeTokenPolicyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Packages;

use App\Security\Packages\ThemeTokenPolicy;
use PHPUnit\Framework\TestCase;

final class ThemeTokenPolicyTest extends TestCase
{
    public function test_catalogue_shape_and_types(): void
    {
        $tokens = ThemeTokenPolicy::TOKENS;
        self::assertArrayHasKey('--accent', $tokens);
        self::assertSame('color', ThemeTokenPolicy::type('--accent'));
        self::assertSame('font', ThemeTokenPolicy::type('--font-body'));
        self::assertSame('length', ThemeTokenPolicy::type('--radius'));
        self::assertSame('asset', ThemeTokenPolicy::type('--surface-texture'));
        self::assertFalse(ThemeTokenPolicy::isKnown('--not-a-token'));
        foreach ($tokens as $name => $type) {
            self::assertMatchesRegularExpression('/\A--[a-z][a-z0-9-]{1,40}\z/', $name);
            self::assertContains($type, ['color', 'length', 'font', 'asset']);
        }
        self::assertSame(1, ThemeTokenPolicy::SCHEMA_VERSION);
    }

    public function test_unknown_token_type_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ThemeTokenPolicy::type('--nope');
    }

    /** TM-TH-01: values that could smuggle selectors/declarations are refused. */
    public function test_selector_and_overlay_constructs_are_refused(): void
    {
        $hostile = [
            '#fff;}body{display:none',            // close the block, new selector
            '#ffffff}.login{visibility:hidden',
            'red;position:fixed',                  // extra declaration
            '#fff !important',
            'var(--text)',                         // indirection
            'calc(1px + 1px)',
            '#ffffff/*x*/',
            "#fff\n}",
            '#ffff',                               // malformed hex lengths
        ];
        foreach ($hostile as $value) {
            self::assertNotNull(ThemeTokenPolicy::validateValue('--accent', $value, []), $value);
        }
    }

    /** TM-TH-02: url()/@import/remote/data vectors are refused in every grammar. */
    public function test_url_import_remote_vectors_are_refused(): void
    {
        foreach (['url(https://evil.example/x.png)', 'url(//evil)', '@import "x"', 'url(data:text/html;base64,x)', 'image-set(url(x))'] as $value) {
            self::assertNotNull(ThemeTokenPolicy::validateValue('--accent', $value, []), $value);
            self::assertNotNull(ThemeTokenPolicy::validateValue('--font-body', $value, []), $value);
            self::assertNotNull(ThemeTokenPolicy::validateValue('--radius', $value, []), $value);
            self::assertNotNull(ThemeTokenPolicy::validateValue('--surface-texture', $value, []), $value);
        }
    }

    public function test_valid_values_pass_per_grammar(): void
    {
        self::assertNull(ThemeTokenPolicy::validateValue('--accent', '#8F3D12', []));   // case-insensitive input
        self::assertNull(ThemeTokenPolicy::validateValue('--radius', '7px', []));
        self::assertNull(ThemeTokenPolicy::validateValue('--radius', '0', []));
        self::assertNull(ThemeTokenPolicy::validateValue('--radius', '0.5rem', []));
        self::assertNull(ThemeTokenPolicy::validateValue('--font-body', '"EB Garamond", Georgia, serif', []));
        self::assertNull(ThemeTokenPolicy::validateValue('--surface-texture', 'parchment', ['parchment']));
        self::assertNotNull(ThemeTokenPolicy::validateValue('--surface-texture', 'missing', ['parchment']));
        self::assertNotNull(ThemeTokenPolicy::validateValue('--radius', '10000px', []));
        self::assertNotNull(ThemeTokenPolicy::validateValue('--font-body', 'Arial, javascript:x', []));
    }

    public function test_contrast_pairs_and_baselines(): void
    {
        $pairs = ThemeTokenPolicy::contrastPairs();
        self::assertNotEmpty($pairs);
        foreach ($pairs as $pair) {
            self::assertTrue(ThemeTokenPolicy::isKnown($pair['fg']));
            self::assertTrue(ThemeTokenPolicy::isKnown($pair['bg']));
            self::assertSame(4.5, $pair['min']);
        }
        foreach (['light', 'dark'] as $variant) {
            $baseline = ThemeTokenPolicy::baseline($variant);
            foreach ($pairs as $pair) {
                self::assertArrayHasKey($pair['fg'], $baseline, "$variant baseline missing {$pair['fg']}");
                self::assertArrayHasKey($pair['bg'], $baseline, "$variant baseline missing {$pair['bg']}");
                self::assertMatchesRegularExpression('/\A#[0-9a-f]{6}\z/', $baseline[$pair['fg']]);
            }
        }
    }
}
```

- [ ] **Step 3: Run to verify failure**

Run: `vendor/bin/phpunit tests/Unit/Security/Packages/ThemeTokenPolicyTest.php`
Expected: ERROR — `Class "App\Security\Packages\ThemeTokenPolicy" not found`.

- [ ] **Step 4: Implement** `src/Security/Packages/ThemeTokenPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\Packages;

/**
 * Code-owned catalogue of the design tokens a declarative theme package may
 * set (P5-03 Gate A: tokens + approved local assets only — PHASE_5_PLAN §4
 * lines 120-123, §5 #14). Token NAMES are a closed whitelist and token VALUES
 * match per-type grammars that structurally cannot express selectors,
 * additional declarations, url()/@import, or script-like constructs
 * (TM-TH-01/TM-TH-02). Also owns the WCAG pairs the build gate enforces
 * (TM-TH-04) and the app.css baseline values used to compute effective
 * contrast for partial token sets. Mirrors ApiScopes/CapabilityCatalog:
 * static data, not a service.
 */
final class ThemeTokenPolicy
{
    public const SCHEMA_VERSION = 1;

    /** @var array<string, 'color'|'length'|'font'|'asset'> */
    public const TOKENS = [
        // Semantic surfaces / text / lines (the brand.css-compatible set).
        '--surface' => 'color', '--surface-2' => 'color', '--surface-3' => 'color',
        '--border' => 'color', '--text' => 'color', '--text-muted' => 'color',
        '--text-strong' => 'color', '--text-body' => 'color', '--text-faint' => 'color',
        '--text-inverse' => 'color', '--accent' => 'color', '--accent-contrast' => 'color',
        '--accent-2' => 'color', '--danger' => 'color',
        // Imladris brand ramp.
        '--brand' => 'color', '--brand-hover' => 'color', '--brand-press' => 'color',
        '--brand-subtle' => 'color', '--on-brand-subtle' => 'color',
        '--gold' => 'color', '--gold-soft' => 'color', '--gold-ink' => 'color',
        // Shape.
        '--radius-sm' => 'length', '--radius-md' => 'length', '--radius-lg' => 'length',
        '--radius-xl' => 'length', '--radius-pill' => 'length', '--radius' => 'length',
        // Typography (names only — CSP + the zero-url() grammar make remote fonts impossible).
        '--font-display' => 'font', '--font-label' => 'font', '--font-body' => 'font',
        '--font-mono' => 'font', '--font' => 'font',
        // Approved asset hook: consumed by app.css `body { background-image: var(--surface-texture, none) }`.
        '--surface-texture' => 'asset',
    ];

    private const GENERIC_FONTS = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy',
        'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded'];

    // Transcribed from public/assets/app.css (:root / [data-theme="dark"]) — keep in
    // lock-step; ThemeBaselineFidelityTest (Task 5) pins these against the real file.
    private const BASELINE_LIGHT = [/* filled in Step 1 from app.css — token => '#rrggbb' */];
    private const BASELINE_DARK = [/* filled in Step 1; dark block values over light for overridden tokens */];

    public static function isKnown(string $token): bool
    {
        return isset(self::TOKENS[$token]);
    }

    public static function type(string $token): string
    {
        if (!isset(self::TOKENS[$token])) {
            throw new \InvalidArgumentException("unknown theme token: $token");
        }
        return self::TOKENS[$token];
    }

    /**
     * @param list<string> $assetNames declared asset names (for 'asset' tokens)
     * @return ?string null when valid, else a human-readable refusal
     */
    public static function validateValue(string $token, string $value, array $assetNames): ?string
    {
        if (!isset(self::TOKENS[$token])) {
            return 'Unknown theme token.';
        }
        if (strlen($value) > 256 || $value === '') {
            return 'Token value must be 1-256 characters.';
        }
        // Structural guard shared by every grammar: nothing that can escape a
        // declaration or reference anything may appear at all (TM-TH-01/02).
        if (preg_match('/[{};\\\\<>@]|\/\*|url\s*\(|expression\s*\(|javascript\s*:|data\s*:|!\s*important/i', $value) === 1) {
            return 'Token value contains a forbidden construct.';
        }
        return match (self::TOKENS[$token]) {
            'color' => preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) === 1
                ? null : 'Colour tokens must be a 6-digit hex value like #8f3d12.',
            'length' => preg_match('/\A(0|(?:\d{1,3}|\d{1,2}\.\d{1,2})(?:px|rem|em))\z/', $value) === 1
                ? null : 'Length tokens must be 0 or a px/rem/em value (max 3 digits).',
            'font' => self::fontError($value),
            'asset' => preg_match('/\A[a-z0-9][a-z0-9-]{0,30}\z/', $value) === 1 && in_array($value, $assetNames, true)
                ? null : 'Asset tokens must name a declared theme asset.',
        };
    }

    private static function fontError(string $value): ?string
    {
        foreach (explode(',', $value) as $family) {
            $family = trim($family);
            if ($family === '') {
                return 'Font stacks cannot contain empty entries.';
            }
            if (in_array($family, self::GENERIC_FONTS, true)) {
                continue;
            }
            if (preg_match('/\A"[A-Za-z0-9][A-Za-z0-9 \-]{0,40}"\z/', $family) === 1) {
                continue;
            }
            if (preg_match('/\A[A-Za-z][A-Za-z0-9\-]{0,40}\z/', $family) === 1) {
                continue;
            }
            return 'Font stacks may only contain quoted family names and generic keywords.';
        }
        return null;
    }

    /** @return list<array{fg:string,bg:string,min:float}> */
    public static function contrastPairs(): array
    {
        return [
            ['fg' => '--text', 'bg' => '--surface', 'min' => 4.5],
            ['fg' => '--text', 'bg' => '--surface-2', 'min' => 4.5],
            ['fg' => '--text-muted', 'bg' => '--surface', 'min' => 4.5],
            ['fg' => '--accent-contrast', 'bg' => '--accent', 'min' => 4.5],
            ['fg' => '--text-inverse', 'bg' => '--brand', 'min' => 4.5],
        ];
    }

    /** @return array<string,string> */
    public static function baseline(string $variant): array
    {
        return $variant === 'dark'
            ? array_replace(self::BASELINE_LIGHT, self::BASELINE_DARK)
            : self::BASELINE_LIGHT;
    }
}
```

Fill `BASELINE_LIGHT`/`BASELINE_DARK` with the Step 1 transcription (only the tokens named by `contrastPairs()` are required, but transcribing all color tokens is fine).

- [ ] **Step 5: Run to verify pass**

Run: `vendor/bin/phpunit tests/Unit/Security/Packages/ThemeTokenPolicyTest.php`
Expected: OK.

- [ ] **Step 6: Commit**

```bash
git add src/Security/Packages/ThemeTokenPolicy.php tests/Unit/Security/Packages/ThemeTokenPolicyTest.php
git commit -m "feat(phase5): ThemeTokenPolicy - closed token whitelist, hostile-value grammars, WCAG pairs (Inc 4, TM-TH-01/02)"
```

---

### Task 2: Manifest `theme` block — type-conditional validation + harness/fixture updates

`rb-manifest.v2` grows a `theme` top-level key: **required** when `type === 'theme'`, **forbidden** otherwise. Structural asset validation (name/kind/base64/sha256/caps) happens here so a hostile document refuses before any bytes are decoded downstream; deep image scanning stays in Task 4. The test harness's base manifest gains a real theme block so every existing fixture keeps validating.

**Files:**
- Modify: `src/Security/Packages/ManifestValidator.php` (TOP_KEYS + `theme()` validation)
- Modify: `src/Security/Packages/PackageManifest.php` (trailing `?array $theme = null` + `@param` docblock)
- Modify: `tests/Support/Phase5/SigningHarness.php` (`mintManifest` base gains `theme` block; merge rule: `theme` merges one level deep like `core`)
- Test: `tests/Unit/Security/Packages/ManifestValidatorTest.php` (append), `tests/Unit/Support/SigningHarnessTest.php` (append)

**Interfaces:**
- Consumes: `ThemeTokenPolicy` (Task 1).
- Produces: `PackageManifest->theme` shape (locked block above): `assets` entries carry **decoded** `bytes` (the validator base64-decodes and digest-checks), `tokens`/`dark_tokens` normalized (colors lowercased). Harness default theme block:

```php
'theme' => [
    'schema_version' => 1,
    'tokens' => ['--accent' => '#8f3d12', '--surface' => '#fff7dc', '--text' => '#241706'],
    'dark_tokens' => [],
    'assets' => [],
],
```

*(These default values must pass the Task 1 grammars AND the Task 5 contrast gate against the baselines — verify `#241706` on `#fff7dc` ≥ 4.5 (it is, ≈13:1) and that `--accent-contrast`/`--brand` pairs fall back to baselines.)*

- [ ] **Step 1: Write the failing validator tests** — append to `tests/Unit/Security/Packages/ManifestValidatorTest.php` (mirror its existing helper style; it builds manifests via `SigningHarness::generate()->mintManifest()` or a local array helper — reuse whichever exists):

```php
public function test_theme_manifest_requires_theme_block(): void
{
    $manifest = SigningHarness::generate()->mintManifest();
    unset($manifest['theme']);
    $this->expectExceptionCode(0);
    try {
        (new ManifestValidator())->validate($manifest, 'acme/midnight-theme', '1.0.0');
        self::fail('expected refusal');
    } catch (PackagePolicyException $e) {
        self::assertSame('theme_missing', $e->code);
    }
}

public function test_non_theme_manifest_refuses_theme_block(): void
{
    $root = SigningHarness::generate();
    $manifest = $root->mintManifest(['type' => 'automation', 'uid' => 'acme/auto']);
    $manifest['theme'] = ['schema_version' => 1, 'tokens' => ['--accent' => '#112233']];
    try {
        (new ManifestValidator())->validate($manifest, 'acme/auto', '1.0.0');
        self::fail('expected refusal');
    } catch (PackagePolicyException $e) {
        self::assertSame('theme_forbidden', $e->code);
    }
}

public function test_theme_block_validates_tokens_schema_and_assets(): void
{
    $root = SigningHarness::generate();
    $validator = new ManifestValidator();

    $bad = static function (array $theme) use ($root, $validator): string {
        $manifest = $root->mintManifest(['theme' => $theme]);
        try {
            $validator->validate($manifest, 'acme/midnight-theme', '1.0.0');
            return 'PASSED';
        } catch (PackagePolicyException $e) {
            return $e->code;
        }
    };

    self::assertSame('theme_schema', $bad(['schema_version' => 2]));
    self::assertSame('theme_token', $bad(['tokens' => ['--nope' => '#112233']]));
    self::assertSame('theme_token', $bad(['tokens' => ['--accent' => '#fff;}body{display:none']]));
    self::assertSame('theme_token', $bad(['tokens' => []]));                       // empty tokens: nothing to apply
    self::assertSame('theme_token', $bad(['dark_tokens' => ['--accent' => 'url(https://x)']]));
    // Structural asset rules: bad kind, bad base64, digest mismatch, dup name, too many, too big.
    $png = base64_encode(random_bytes(64)); // content is irrelevant here — Task 4 does deep scanning
    self::assertSame('theme_asset', $bad(['assets' => [['name' => 'x', 'kind' => 'svg', 'sha256' => str_repeat('a', 64), 'data_base64' => $png]]]));
    self::assertSame('theme_asset', $bad(['assets' => [['name' => 'x', 'kind' => 'png', 'sha256' => str_repeat('a', 64), 'data_base64' => '!!!']]]));
    self::assertSame('theme_asset', $bad(['assets' => [['name' => 'x', 'kind' => 'png', 'sha256' => str_repeat('a', 64), 'data_base64' => $png]]])); // wrong digest
    $bytes = random_bytes(64);
    $entry = ['name' => 'x', 'kind' => 'png', 'sha256' => hash('sha256', $bytes), 'data_base64' => base64_encode($bytes)];
    self::assertSame('theme_asset', $bad(['assets' => [$entry, $entry]]));          // duplicate name
    self::assertSame('theme_asset', $bad(['assets' => array_map(
        static fn (int $i): array => ['name' => "a$i", 'kind' => 'png', 'sha256' => hash('sha256', "b$i"), 'data_base64' => base64_encode("b$i")],
        range(0, 4),
    )]));                                                                            // > MAX_ASSETS
    self::assertSame('theme_token', $bad(['tokens' => ['--accent' => '#112233', '--surface-texture' => 'ghost']])); // asset token w/o declared asset
}

public function test_valid_theme_manifest_exposes_normalized_theme(): void
{
    $root = SigningHarness::generate();
    $bytes = random_bytes(64);
    $manifest = $root->mintManifest(['theme' => [
        'tokens' => ['--accent' => '#8F3D12', '--surface-texture' => 'parchment'],
        'assets' => [['name' => 'parchment', 'kind' => 'png', 'sha256' => hash('sha256', $bytes), 'data_base64' => base64_encode($bytes)]],
    ]]);
    $validated = (new ManifestValidator())->validate($manifest, 'acme/midnight-theme', '1.0.0');
    self::assertNotNull($validated->theme);
    self::assertSame('#8f3d12', $validated->theme['tokens']['--accent']);           // color normalized
    self::assertSame($bytes, $validated->theme['assets'][0]['bytes']);              // decoded bytes exposed
    self::assertSame(1, $validated->theme['schema_version']);
}
```

Add the harness assertion to `tests/Unit/Support/SigningHarnessTest.php`:

```php
public function test_mint_manifest_theme_block_merges_one_level_deep(): void
{
    $root = SigningHarness::generate();
    $manifest = $root->mintManifest(['theme' => ['tokens' => ['--accent' => '#112233']]]);
    self::assertSame('#112233', $manifest['theme']['tokens']['--accent']);
    self::assertSame(1, $manifest['theme']['schema_version']);                      // preserved from base
    $auto = $root->mintManifest(['type' => 'automation', 'theme' => null]);
    self::assertArrayNotHasKey('theme', $auto);
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Unit/Security/Packages/ManifestValidatorTest.php tests/Unit/Support/SigningHarnessTest.php`
Expected: failures on the new tests (`unknown_field` instead of `theme_*`, missing theme key in harness output).

- [ ] **Step 3: Implement.** In `ManifestValidator`:
  - Add `'theme'` to `TOP_KEYS`.
  - Add constants: `MAX_THEME_ASSETS = 4`, `MAX_THEME_ASSET_BYTES = 131072`, `MAX_THEME_TOTAL_BYTES = 262144`, `THEME_ASSET_KINDS = ['png','jpeg','gif','webp']`, `THEME_ASSET_NAME_PATTERN = '/\A[a-z0-9][a-z0-9-]{0,30}\z/'`.
  - In `validate()`, after `$support = …`: `$theme = $this->theme($manifest['theme'] ?? null, $type);` and pass `$theme` as the new trailing constructor arg.
  - Implement:

```php
/** @return ?array{schema_version:int,tokens:array<string,string>,dark_tokens:array<string,string>,assets:list<array{name:string,kind:string,sha256:string,bytes:string}>} */
private function theme(mixed $theme, string $type): ?array
{
    if ($type !== 'theme') {
        if ($theme !== null) {
            $this->refuse('theme_forbidden', 'Only theme packages may declare a theme block.');
        }
        return null;
    }
    if (!is_array($theme)) {
        $this->refuse('theme_missing', 'Theme packages must declare a theme block.');
    }
    if ($this->unknownKeys($theme, ['schema_version', 'tokens', 'dark_tokens', 'assets']) !== []) {
        $this->refuse('theme_schema', 'Theme block contains unknown fields.');
    }
    if (($theme['schema_version'] ?? null) !== ThemeTokenPolicy::SCHEMA_VERSION) {
        $this->refuse('theme_schema', 'Theme schema_version ' . ThemeTokenPolicy::SCHEMA_VERSION . ' is required.');
    }

    $assets = [];
    $names = [];
    $total = 0;
    $rawAssets = $theme['assets'] ?? [];
    if (!is_array($rawAssets) || !array_is_list($rawAssets)) {
        $this->refuse('theme_asset', 'Theme assets must be a list.');
    }
    if (count($rawAssets) > self::MAX_THEME_ASSETS) {
        $this->refuse('theme_asset', 'Themes may declare at most ' . self::MAX_THEME_ASSETS . ' assets.');
    }
    foreach ($rawAssets as $asset) {
        if (!is_array($asset) || $this->unknownKeys($asset, ['name', 'kind', 'sha256', 'data_base64']) !== []) {
            $this->refuse('theme_asset', 'Theme asset entries allow only name/kind/sha256/data_base64.');
        }
        $name = is_string($asset['name'] ?? null) ? $asset['name'] : '';
        if (preg_match(self::THEME_ASSET_NAME_PATTERN, $name) !== 1 || in_array($name, $names, true)) {
            $this->refuse('theme_asset', 'Theme asset names must be unique lowercase slugs.');
        }
        $kind = is_string($asset['kind'] ?? null) ? $asset['kind'] : '';
        if (!in_array($kind, self::THEME_ASSET_KINDS, true)) {
            $this->refuse('theme_asset', 'Theme asset kind must be one of: ' . implode(', ', self::THEME_ASSET_KINDS) . '.');
        }
        $encoded = is_string($asset['data_base64'] ?? null) ? $asset['data_base64'] : '';
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || $bytes === '') {
            $this->refuse('theme_asset', 'Theme asset data must be valid base64.');
        }
        if (strlen($bytes) > self::MAX_THEME_ASSET_BYTES) {
            $this->refuse('theme_asset', 'Theme assets are limited to ' . self::MAX_THEME_ASSET_BYTES . ' bytes each.');
        }
        $total += strlen($bytes);
        if ($total > self::MAX_THEME_TOTAL_BYTES) {
            $this->refuse('theme_asset', 'Theme assets are limited to ' . self::MAX_THEME_TOTAL_BYTES . ' bytes in total.');
        }
        $sha = is_string($asset['sha256'] ?? null) ? strtolower($asset['sha256']) : '';
        if (!hash_equals(hash('sha256', $bytes), $sha)) {
            $this->refuse('theme_asset', 'Theme asset sha256 does not match the decoded bytes.');
        }
        $names[] = $name;
        $assets[] = ['name' => $name, 'kind' => $kind, 'sha256' => $sha, 'bytes' => $bytes];
    }

    $tokens = $this->themeTokens($theme['tokens'] ?? null, $names, true);
    $darkTokens = $this->themeTokens($theme['dark_tokens'] ?? [], $names, false);

    return ['schema_version' => ThemeTokenPolicy::SCHEMA_VERSION, 'tokens' => $tokens, 'dark_tokens' => $darkTokens, 'assets' => $assets];
}

/** @param list<string> $assetNames @return array<string,string> */
private function themeTokens(mixed $tokens, array $assetNames, bool $required): array
{
    if (!is_array($tokens) || ($required && $tokens === []) || array_is_list($tokens) && $tokens !== []) {
        $this->refuse('theme_token', $required ? 'Theme tokens must be a non-empty object.' : 'Theme dark_tokens must be an object.');
    }
    $out = [];
    foreach ($tokens as $name => $value) {
        if (!is_string($name) || !is_string($value) || !ThemeTokenPolicy::isKnown($name)) {
            $this->refuse('theme_token', 'Unknown theme token: ' . (is_string($name) ? $name : '?') . '.');
        }
        if (($error = ThemeTokenPolicy::validateValue($name, $value, $assetNames)) !== null) {
            $this->refuse('theme_token', 'Token ' . $name . ': ' . $error);
        }
        $out[$name] = ThemeTokenPolicy::type($name) === 'color' ? strtolower($value) : $value;
    }
    return $out;
}
```

  - `PackageManifest`: add `public readonly ?array $theme = null` as the final constructor parameter with the shape docblock.
  - `SigningHarness::mintManifest`: add the default `theme` block (interface note above) to `$base`, and extend the merge rule so `'theme'` merges one level deep like `core`/`permissions`, with `'theme' => null` in overrides **removing** the key (used to mint non-theme manifests — also make the automation/remote_app paths in existing tests work by removing the block automatically when overrides set a non-theme `type` and don't explicitly pass `theme`):

```php
foreach ($overrides as $key => $value) {
    if ($key === 'theme') {
        if ($value === null) { unset($base['theme']); continue; }
        $base['theme'] = is_array($value) ? array_replace($base['theme'], $value) : $value;
        continue;
    }
    if (in_array($key, ['core', 'permissions'], true) && is_array($value)) {
        $base[$key] = array_replace($base[$key], $value);
        continue;
    }
    $base[$key] = $value;
}
if (($base['type'] ?? 'theme') !== 'theme' && !array_key_exists('theme', $overrides)) {
    unset($base['theme']);
}
```

- [ ] **Step 4: Run the new tests, then the whole unit + integration package suites** (the harness change feeds every Inc 2/3 fixture):

Run: `vendor/bin/phpunit tests/Unit/Security/Packages tests/Unit/Support/SigningHarnessTest.php`
Expected: OK.
Run: `vendor/bin/phpunit --testsuite unit && vendor/bin/phpunit tests/Integration/Service/PackageLifecycleServiceTest.php tests/Integration/Service/PackageUpdateServiceTest.php tests/Integration/Core/AppPackageLifecycleTest.php tests/Integration/Service/PackageAcquisitionServiceTest.php`
Expected: OK — if any Inc 3 test asserted an exact manifest array or digest, update the fixture expectation (digests legitimately change because the signed bytes now include the theme block).

- [ ] **Step 5: Commit**

```bash
git add src/Security/Packages/ManifestValidator.php src/Security/Packages/PackageManifest.php tests/Support/Phase5/SigningHarness.php tests/Unit/Security/Packages/ManifestValidatorTest.php tests/Unit/Support/SigningHarnessTest.php
git commit -m "feat(phase5): rb-manifest.v2 theme block - type-conditional, token/asset structural validation (Inc 4)"
```

---

### Task 3: Migration `0071_phase5_theme_packages` + `SCHEMA.md` + `PackageThemeRepository`

The §8.2 #7 theme data model: build rows (one per install × source digest), DB-stored neutralized assets (so backup/restore carries the whole last-known-good theme, PHASE_5_PLAN §9 "Backup/restore"), and a single-row pointer table for active/LKG. Plus the `package_history.event` ENUM widen for the three theme events.

**Files:**
- Create: `database/migrations/0071_phase5_theme_packages.php`
- Create: `src/Repository/PackageThemeRepository.php`
- Modify: `SCHEMA.md` (three table shapes + `package_history` ENUM note + §9 changelog + version bump by one — read the current version header first)
- Test: `tests/Integration/Core/AppPhase5ThemeSchemaTest.php`

**Interfaces:**
- Consumes: migration runner conventions (`return new class { up(\PDO) / down(\PDO) }`, `<<<'SQL'` nowdocs), `0069`'s `settings_json` column type (match it for the `*_json` columns).
- Produces: the locked `PackageThemeRepository` interface; tables:

```sql
CREATE TABLE package_theme_builds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    installed_package_id BIGINT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED NOT NULL,
    release_id BIGINT UNSIGNED NOT NULL,
    source_digest CHAR(64) NOT NULL,
    token_schema_version SMALLINT UNSIGNED NOT NULL,
    tokens_json LONGTEXT NOT NULL,
    validation_json LONGTEXT NOT NULL,
    css MEDIUMTEXT NOT NULL,
    css_digest CHAR(64) NOT NULL,
    built_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    UNIQUE KEY uniq_theme_build (installed_package_id, source_digest),
    KEY idx_theme_build_css_digest (css_digest),
    CONSTRAINT fk_theme_build_install FOREIGN KEY (installed_package_id) REFERENCES installed_packages (id) ON DELETE CASCADE,
    CONSTRAINT fk_theme_build_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    CONSTRAINT fk_theme_build_release FOREIGN KEY (release_id) REFERENCES package_releases (id) ON DELETE CASCADE,
    CONSTRAINT fk_theme_build_user FOREIGN KEY (built_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE package_theme_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    build_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(32) NOT NULL,
    mime VARCHAR(64) NOT NULL,
    bytes MEDIUMBLOB NOT NULL,
    byte_len INT UNSIGNED NOT NULL,
    digest CHAR(64) NOT NULL,
    UNIQUE KEY uniq_theme_asset_name (build_id, name),
    KEY idx_theme_asset_digest (digest),
    CONSTRAINT fk_theme_asset_build FOREIGN KEY (build_id) REFERENCES package_theme_builds (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE theme_state (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    active_build_id BIGINT UNSIGNED NULL,
    lkg_build_id BIGINT UNSIGNED NULL,
    activated_by BIGINT UNSIGNED NULL,
    activated_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    CONSTRAINT fk_theme_state_active FOREIGN KEY (active_build_id) REFERENCES package_theme_builds (id) ON DELETE SET NULL,
    CONSTRAINT fk_theme_state_lkg FOREIGN KEY (lkg_build_id) REFERENCES package_theme_builds (id) ON DELETE SET NULL,
    CONSTRAINT fk_theme_state_user FOREIGN KEY (activated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO theme_state (id) VALUES (1);

ALTER TABLE package_history MODIFY event ENUM('plan','install','consent','enable','disable','pin','unpin',
    'update','update_staged','rollback','quarantine','reverify','uninstall','export','purge',
    'theme_activate','theme_rollback','theme_deactivate') NOT NULL;
```

**Copy the existing `package_history.event` ENUM values from `0069` verbatim before appending the three theme values** — the list above is from the Inc 3 report; the migration must restate the deployed list exactly, plus the three additions. `down()`: `DELETE FROM package_history WHERE event IN ('theme_activate','theme_rollback','theme_deactivate')`, revert the ENUM, then `DROP TABLE theme_state, package_theme_assets, package_theme_builds` (that order — FKs).

- [ ] **Step 1: Write the failing shape test** `tests/Integration/Core/AppPhase5ThemeSchemaTest.php` (mirror `AppPhase5FoundationSchemaTest` — it queries `information_schema`):

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppPhase5ThemeSchemaTest extends TestCase
{
    public function test_theme_tables_match_documented_shape(): void
    {
        self::assertSame(
            ['css', 'css_digest', 'created_at', 'built_by', 'id', 'installed_package_id', 'package_id', 'release_id', 'source_digest', 'token_schema_version', 'tokens_json', 'validation_json'],
            $this->columns('package_theme_builds'),
        );
        self::assertSame(
            ['build_id', 'byte_len', 'bytes', 'digest', 'id', 'mime', 'name'],
            $this->columns('package_theme_assets'),
        );
        self::assertSame(
            ['activated_at', 'activated_by', 'active_build_id', 'id', 'lkg_build_id', 'updated_at'],
            $this->columns('theme_state'),
        );
    }

    public function test_theme_state_seed_row_exists_and_is_empty(): void
    {
        $row = $this->db()->selectOne('SELECT * FROM theme_state WHERE id = 1');
        self::assertNotNull($row);
        self::assertNull($row['active_build_id']);
        self::assertNull($row['lkg_build_id']);
    }

    public function test_package_history_enum_gains_theme_events(): void
    {
        $column = $this->db()->selectOne(
            "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package_history' AND COLUMN_NAME = 'event'",
        );
        self::assertStringContainsString("'theme_activate'", (string) $column['t']);
        self::assertStringContainsString("'theme_rollback'", (string) $column['t']);
        self::assertStringContainsString("'theme_deactivate'", (string) $column['t']);
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $rows = $this->db()->select(
            'SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t ORDER BY COLUMN_NAME',
            ['t' => $table],
        );
        return array_map(static fn (array $r): string => (string) $r['c'], $rows);
    }
}
```

*(Match the exact `TestCase` DB-helper method names used by `AppPhase5FoundationSchemaTest` — `$this->db()->select/selectOne` may differ; copy its idiom.)*

- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit tests/Integration/Core/AppPhase5ThemeSchemaTest.php` → tables missing.

- [ ] **Step 3: Write the migration** using the DDL above (nowdocs, one statement per `exec`), copying the `0069` ENUM list verbatim + three additions; run `php bin/console migrate` against the dev DB, then re-run the test.
Expected: OK (the PHPUnit bootstrap re-migrates the test DB automatically).

- [ ] **Step 4: Implement `PackageThemeRepository`** (final class, `__construct(private Database $db)`, prepared statements, assoc arrays) with the locked method set. Notes:
  - `assetsFor()` selects `id, build_id, name, mime, byte_len, digest` (never `bytes` — keep row sets small); `findAssetByDigest()` selects bytes too, newest first.
  - `findCssByDigest()` returns the newest build with that `css_digest` joined to `installed_packages.state` as `install_state` (the serving gate uses it).
  - `state()` reads row 1; `setState()` is a single UPDATE of row 1 setting `active_build_id`, `lkg_build_id`, `activated_by`, `activated_at = UTC_TIMESTAMP()`, `updated_at = UTC_TIMESTAMP()`.
  - `createBuild(array $row)` inserts all build columns; caller passes JSON strings for `tokens_json`/`validation_json`.

- [ ] **Step 5: Repository smoke assertions** — append to `AppPhase5ThemeSchemaTest`:

```php
public function test_repository_round_trips_a_build_with_assets_and_state(): void
{
    $fixtures = $this->seedRegistryFixtureWithInstall();   // helper: RegistryFixtures::seed + an installed_packages row (copy the idiom from PackageLifecycleServiceTest's setUp)
    $repo = new \App\Repository\PackageThemeRepository($this->database());
    $buildId = $repo->createBuild([
        'installed_package_id' => $fixtures['installed_id'],
        'package_id' => $fixtures['package_id'],
        'release_id' => $fixtures['release_id'],
        'source_digest' => str_repeat('a', 64),
        'token_schema_version' => 1,
        'tokens_json' => '{"--accent":"#8f3d12"}',
        'validation_json' => '{"contrast":[]}',
        'css' => ':root{--accent:#8f3d12;}',
        'css_digest' => hash('sha256', ':root{--accent:#8f3d12;}'),
        'built_by' => null,
    ]);
    $repo->addAsset($buildId, 'parchment', 'image/png', 'PNGBYTES', hash('sha256', 'PNGBYTES'));
    self::assertSame($buildId, (int) $repo->findBuildFor($fixtures['installed_id'], str_repeat('a', 64))['id']);
    self::assertSame('PNGBYTES', $repo->findAssetByDigest(hash('sha256', 'PNGBYTES'))['bytes']);
    self::assertCount(1, $repo->assetsFor($buildId));
    self::assertArrayNotHasKey('bytes', $repo->assetsFor($buildId)[0]);
    $repo->setState($buildId, null, null);
    self::assertSame($buildId, (int) $repo->state()['active_build_id']);
    self::assertSame('installed', $repo->findCssByDigest(hash('sha256', ':root{--accent:#8f3d12;}'))['install_state']);
}
```

*(Adapt the fixture helper to whatever `PackageLifecycleServiceTest` actually does to create an install row — reuse, don't reinvent.)*

- [ ] **Step 6: Update `SCHEMA.md`** — add the three shapes to §5A (or the packages section), the ENUM note, table-index rows, §9 changelog entry, version bump by one.

- [ ] **Step 7: Full check + commit**

Run: `vendor/bin/phpunit tests/Integration/Core/AppPhase5ThemeSchemaTest.php tests/Unit/Core/MigrationLedgerTest.php`
Expected: OK.

```bash
git add database/migrations/0071_phase5_theme_packages.php src/Repository/PackageThemeRepository.php tests/Integration/Core/AppPhase5ThemeSchemaTest.php SCHEMA.md
git commit -m "feat(phase5): 0071 theme package schema - builds, DB-stored assets, active/LKG state (Inc 4)"
```

---

### Task 4: `ThemeAssetScanner` — sniff + GD re-encode neutralization (TM-TH-03)

Raster-only asset policy: `finfo` must agree with the declared kind, GD must decode, and the stored bytes are GD's **re-encode** (which destroys any appended/embedded payload — the polyglot defense). SVG has no safe declarative subset worth shipping in Gate A: it is refused by the kind allowlist (that *is* the "neutralized by scan" fixture for SVG-with-script).

**Files:**
- Create: `src/Service/Packages/ThemeAssetScanner.php`
- Test: `tests/Unit/Service/Packages/ThemeAssetScannerTest.php`

**Interfaces:**
- Consumes: ext-gd (`imagecreatefromstring`, `imagepng/imagejpeg/imagegif/imagewebp`), `finfo`.
- Produces: `scan(name, kind, bytes): array{mime,bytes,digest}` per the locked interface; `PackagePolicyException('theme_asset', …)` on refusal.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Packages;

use App\Security\Packages\PackagePolicyException;
use App\Service\Packages\ThemeAssetScanner;
use PHPUnit\Framework\TestCase;

final class ThemeAssetScannerTest extends TestCase
{
    private function png(int $w = 4, int $h = 4): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, (int) imagecolorallocate($im, 200, 180, 140));
        ob_start();
        imagepng($im);
        return (string) ob_get_clean();
    }

    public function test_valid_png_is_reencoded_and_digest_pinned(): void
    {
        $scanner = new ThemeAssetScanner();
        $out = $scanner->scan('parchment', 'png', $this->png());
        self::assertSame('image/png', $out['mime']);
        self::assertSame(hash('sha256', $out['bytes']), $out['digest']);
        self::assertNotFalse(imagecreatefromstring($out['bytes']));
    }

    /** TM-TH-03: a PNG with an appended script payload is neutralized — output bytes differ and carry no payload. */
    public function test_polyglot_payload_is_destroyed_by_reencode(): void
    {
        $payload = '<script>alert(1)</script><?php system($_GET[0]); ?>';
        $polyglot = $this->png() . $payload;
        $out = (new ThemeAssetScanner())->scan('sneaky', 'png', $polyglot);
        self::assertStringNotContainsString('<script>', $out['bytes']);
        self::assertStringNotContainsString('<?php', $out['bytes']);
        self::assertNotSame(hash('sha256', $polyglot), $out['digest']);
    }

    /** TM-TH-03: SVG (with or without script) is refused outright — kind allowlist. */
    public function test_svg_with_script_is_refused(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>fetch("https://evil")</script></svg>';
        try {
            (new ThemeAssetScanner())->scan('vector', 'svg', $svg);
            self::fail('expected refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_asset', $e->code);
        }
    }

    public function test_kind_sniff_mismatch_is_refused(): void
    {
        try {
            (new ThemeAssetScanner())->scan('fake', 'png', 'GIF89a' . str_repeat('x', 64));
            self::fail('expected refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_asset', $e->code);
        }
    }

    public function test_undecodable_bytes_are_refused(): void
    {
        try {
            (new ThemeAssetScanner())->scan('noise', 'png', "\x89PNG\r\n\x1a\n" . random_bytes(64));
            self::fail('expected refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_asset', $e->code);
        }
    }

    public function test_oversize_is_refused(): void
    {
        $scanner = new ThemeAssetScanner();
        try {
            $scanner->scan('big', 'png', str_repeat('a', ThemeAssetScanner::MAX_ASSET_BYTES + 1));
            self::fail('expected refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_asset', $e->code);
        }
    }
}
```

- [ ] **Step 2: Run to verify failure** — class not found.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Security\Packages\PackagePolicyException;

/**
 * Neutralizes declared theme assets (TM-TH-03): raster-only allowlist, finfo
 * sniff must agree with the declared kind, and the stored bytes are a full GD
 * re-encode so appended/embedded payloads (polyglots, EXIF scripts, PHP tails)
 * cannot survive. SVG is refused outright — no safe declarative subset ships
 * in Gate A. Mirrors the AttachmentService upload discipline.
 */
final class ThemeAssetScanner
{
    public const KINDS = ['png', 'jpeg', 'gif', 'webp'];
    public const MAX_ASSET_BYTES = 131072;
    public const MAX_TOTAL_BYTES = 262144;
    public const MAX_ASSETS = 4;

    private const MIME_BY_KIND = [
        'png' => 'image/png', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
    ];

    /** @return array{mime:string,bytes:string,digest:string} */
    public function scan(string $name, string $kind, string $bytes): array
    {
        if (!in_array($kind, self::KINDS, true)) {
            $this->refuse($name, 'kind must be one of ' . implode(', ', self::KINDS));
        }
        if ($bytes === '' || strlen($bytes) > self::MAX_ASSET_BYTES) {
            $this->refuse($name, 'must be 1 to ' . self::MAX_ASSET_BYTES . ' bytes');
        }
        $sniffed = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if ($sniffed !== self::MIME_BY_KIND[$kind]) {
            $this->refuse($name, 'content does not match its declared kind');
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            $this->refuse($name, 'could not be decoded as an image');
        }
        ob_start();
        $ok = match ($kind) {
            'png' => imagepng($image),
            'jpeg' => imagejpeg($image, null, 85),
            'gif' => imagegif($image),
            'webp' => imagewebp($image),
        };
        $reencoded = (string) ob_get_clean();
        imagedestroy($image);
        if (!$ok || $reencoded === '') {
            $this->refuse($name, 'could not be re-encoded');
        }
        if (strlen($reencoded) > self::MAX_ASSET_BYTES) {
            $this->refuse($name, 'is too large after re-encoding');
        }

        return ['mime' => self::MIME_BY_KIND[$kind], 'bytes' => $reencoded, 'digest' => hash('sha256', $reencoded)];
    }

    private function refuse(string $name, string $why): never
    {
        throw new PackagePolicyException('theme_asset', 'Theme asset "' . $name . '" ' . $why . '.');
    }
}
```

*(If `imagewebp` is unavailable in the local GD build, the constructor may probe `function_exists` and the scanner refuse webp with `theme_asset` — check `php -m`/`gd_info()` first; the test then asserts refusal instead. Keep the constant list unchanged either way — the manifest layer stays stable.)*

- [ ] **Step 4: Run to verify pass** — `vendor/bin/phpunit tests/Unit/Service/Packages/ThemeAssetScannerTest.php` → OK.

- [ ] **Step 5: Commit**

```bash
git add src/Service/Packages/ThemeAssetScanner.php tests/Unit/Service/Packages/ThemeAssetScannerTest.php
git commit -m "feat(phase5): ThemeAssetScanner - sniff + GD re-encode neutralization, raster-only (Inc 4, TM-TH-03)"
```

---

### Task 5: `ThemeBuildService` — contrast gate, deterministic CSS emit, persisted builds (TM-TH-02/04/07 build layer)

Turns a validated manifest theme block into a persisted build: scan assets → compute effective token maps over the app.css baselines → WCAG gate → emit deterministic CSS → store build + assets in one transaction. `emitCss` is pure and static so determinism is unit-provable.

**Files:**
- Create: `src/Service/Packages/ThemeBuildService.php`
- Test: `tests/Unit/Service/Packages/ThemeBuildCssTest.php` (pure emit + contrast), `tests/Integration/Service/ThemeBuildServiceTest.php` (persistence + idempotency), `tests/Unit/Core/ThemeBaselineFidelityTest.php` (baselines match app.css)

**Interfaces:**
- Consumes: `ThemeTokenPolicy`, `ThemeAssetScanner`, `PackageThemeRepository` (Task 3), `Database`, `Telemetry?`.
- Produces: `ensureBuild(array $install, array $manifest, ?int $actorId): array` returning the build row (existing row when `(installed_package_id, source_digest)` already built); `PackagePolicyException` codes `theme_missing|theme_schema|theme_token|theme_asset|theme_contrast`. `$manifest` is the **decoded manifest array from the verified release document** (`manifest` key inside the artifact bytes), not a `PackageManifest` object — the service re-runs `ManifestValidator` internally so a build can never run on unvalidated input:

```php
$validated = $this->manifests->validate($manifest, (string) $package['package_uid'], $version)->theme;
```

*(Add `ManifestValidator` to the constructor — order: `Database, PackageThemeRepository, ManifestValidator, ThemeAssetScanner, ?Telemetry`. Update the locked interface accordingly — this is the corrected signature.)*

CSS emit format (exact):

```
:root{--accent:#8f3d12;--surface-texture:url("/theme/asset/<digest>");…}
[data-theme="dark"]{--surface:#141210;…}
@media (prefers-color-scheme: dark){:root[data-theme="system"]{--surface:#141210;…}}
```

- Tokens sorted by their order in `ThemeTokenPolicy::TOKENS` (catalogue order — stable across PHP versions; do NOT sort alphabetically).
- The dark blocks are emitted only when `dark_tokens` is non-empty, and both dark blocks carry the same declarations.
- Asset tokens emit `url("/theme/asset/{digest}")` where digest is the **re-encoded** asset digest. No other `url(` may ever appear (assert in tests).
- No comments, no timestamps, no whitespace variation: single-line blocks joined by `\n`.

- [ ] **Step 1: Write the failing pure tests** `tests/Unit/Service/Packages/ThemeBuildCssTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Packages;

use App\Service\Packages\ThemeBuildService;
use PHPUnit\Framework\TestCase;

final class ThemeBuildCssTest extends TestCase
{
    public function test_emit_is_deterministic_and_catalogue_ordered(): void
    {
        $a = ThemeBuildService::emitCss(['--surface' => '#fff7dc', '--accent' => '#8f3d12'], [], []);
        $b = ThemeBuildService::emitCss(['--accent' => '#8f3d12', '--surface' => '#fff7dc'], [], []);
        self::assertSame($a, $b);                                            // input order irrelevant
        self::assertSame(':root{--surface:#fff7dc;--accent:#8f3d12;}', $a);  // catalogue order: --surface precedes --accent
    }

    public function test_dark_tokens_emit_both_dark_scopes(): void
    {
        $css = ThemeBuildService::emitCss(['--accent' => '#8f3d12'], ['--surface' => '#141210'], []);
        self::assertStringContainsString('[data-theme="dark"]{--surface:#141210;}', $css);
        self::assertStringContainsString('@media (prefers-color-scheme: dark){:root[data-theme="system"]{--surface:#141210;}}', $css);
    }

    /** TM-TH-02: the only url() the builder can emit is the local asset route. */
    public function test_asset_tokens_emit_local_urls_only(): void
    {
        $digest = str_repeat('ab', 32);
        $css = ThemeBuildService::emitCss(['--surface-texture' => 'parchment'], [], ['parchment' => $digest]);
        self::assertStringContainsString('--surface-texture:url("/theme/asset/' . $digest . '");', $css);
        self::assertSame(0, preg_match('/url\(\s*["\']?(?!\/theme\/asset\/)/i', $css));
        self::assertStringNotContainsString('http', $css);
        self::assertStringNotContainsString('@import', $css);
    }
}
```

- [ ] **Step 2: Write the failing baseline-fidelity test** `tests/Unit/Core/ThemeBaselineFidelityTest.php` — pins `ThemeTokenPolicy::baseline()` against the real `public/assets/app.css` so the transcription can never drift:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Security\Packages\ThemeTokenPolicy;
use PHPUnit\Framework\TestCase;

final class ThemeBaselineFidelityTest extends TestCase
{
    public function test_policy_baselines_match_app_css(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../../../public/assets/app.css');
        foreach (['light' => ':root', 'dark' => '[data-theme="dark"]'] as $variant => $selector) {
            $block = $this->block($css, $selector);
            foreach (ThemeTokenPolicy::baseline($variant) as $token => $value) {
                if ($variant === 'dark' && !str_contains($block, $token . ':')) {
                    continue; // dark inherits light for tokens the dark block doesn't override
                }
                if (preg_match('/' . preg_quote($token, '/') . '\s*:\s*(#[0-9a-fA-F]{6})\b/', $block, $m) === 1) {
                    self::assertSame(strtolower($m[1]), $value, "$variant $token");
                }
            }
        }
        self::assertStringContainsString('background-image: var(--surface-texture, none)', $css);
    }

    private function block(string $css, string $selector): string
    {
        $start = strpos($css, $selector);
        self::assertNotFalse($start, $selector);
        $open = strpos($css, '{', $start);
        $close = strpos($css, '}', (int) $open);
        return substr($css, (int) $open, (int) $close - (int) $open);
    }
}
```

- [ ] **Step 3: Run both** — fail (class/method missing, and app.css lacks the `--surface-texture` consumer).

- [ ] **Step 4: Add the approved asset hook to `public/assets/app.css`** — one declaration on the existing `body` rule (find the `body {` block that sets `font-family: var(--font)` around line 115):

```css
    background-image: var(--surface-texture, none);
```

Run `vendor/bin/phpunit tests/Integration/Core/AppImladrisFidelityTest.php` — if it pins the body rule or a token inventory, update its expectation in the same commit.

- [ ] **Step 5: Implement `ThemeBuildService`**

```php
<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\Telemetry;
use App\Repository\PackageThemeRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\ThemeTokenPolicy;

/**
 * Builds a declarative theme package into a served stylesheet (P5-03):
 * re-validate the manifest, neutralize assets (TM-TH-03), enforce the WCAG
 * gate over effective values (TM-TH-04, hard block — PHASE_5_PLAN §9 "Theme
 * accessibility"), emit deterministic CSS (TM-TH-07's cache key is the sha256
 * of these bytes), and persist build + assets transactionally. Idempotent per
 * (installed_package_id, source_digest).
 */
final class ThemeBuildService
{
    public function __construct(
        private Database $db,
        private PackageThemeRepository $themes,
        private ManifestValidator $manifests,
        private ThemeAssetScanner $scanner,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /**
     * @param array<string,mixed> $install installed_packages row (+ package_uid resolvable by caller into $uid)
     * @param array<string,mixed> $manifest decoded manifest array from the verified release document
     * @return array<string,mixed> the build row
     */
    public function ensureBuild(array $install, string $uid, array $manifest, ?int $actorId = null): array
    {
        $installedId = (int) $install['id'];
        $sourceDigest = (string) $install['digest'];
        $existing = $this->themes->findBuildFor($installedId, $sourceDigest);
        if ($existing !== null) {
            return $existing;
        }

        $version = is_string($manifest['version'] ?? null) ? $manifest['version'] : '';
        $theme = $this->manifests->validate($manifest, $uid, $version)->theme;
        if ($theme === null) {
            throw new PackagePolicyException('theme_missing', 'This package does not declare a theme.');
        }

        // Neutralize assets first; token url() emission uses the re-encoded digests.
        $scanned = [];
        foreach ($theme['assets'] as $asset) {
            $scanned[$asset['name']] = $this->scanner->scan($asset['name'], $asset['kind'], $asset['bytes']);
        }

        $contrast = $this->assertContrast($theme['tokens'], $theme['dark_tokens']);
        $assetDigests = array_map(static fn (array $a): string => $a['digest'], $scanned);
        $css = self::emitCss($theme['tokens'], $theme['dark_tokens'], $assetDigests);
        $cssDigest = hash('sha256', $css);

        $buildId = $this->db->transaction(function () use ($install, $installedId, $sourceDigest, $theme, $scanned, $contrast, $css, $cssDigest, $actorId): int {
            $buildId = $this->themes->createBuild([
                'installed_package_id' => $installedId,
                'package_id' => (int) $install['package_id'],
                'release_id' => (int) $install['release_id'],
                'source_digest' => $sourceDigest,
                'token_schema_version' => $theme['schema_version'],
                'tokens_json' => json_encode(['tokens' => $theme['tokens'], 'dark_tokens' => $theme['dark_tokens']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'validation_json' => json_encode(['contrast' => $contrast], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'css' => $css,
                'css_digest' => $cssDigest,
                'built_by' => $actorId,
            ]);
            foreach ($scanned as $name => $asset) {
                $this->themes->addAsset($buildId, (string) $name, $asset['mime'], $asset['bytes'], $asset['digest']);
            }
            return $buildId;
        });

        $this->telemetry?->emit('theme.lifecycle', ['action' => 'build', 'package' => $uid, 'digest' => $cssDigest]);

        $build = $this->themes->findBuild($buildId);
        \assert($build !== null);
        return $build;
    }

    /**
     * TM-TH-04: WCAG 4.5 over EFFECTIVE values (package tokens over the app.css
     * baseline, dark over light) so a partial token set cannot dodge the gate.
     *
     * @param array<string,string> $tokens
     * @param array<string,string> $darkTokens
     * @return list<array{variant:string,fg:string,bg:string,ratio:float}>
     */
    private function assertContrast(array $tokens, array $darkTokens): array
    {
        $report = [];
        foreach (['light' => $tokens, 'dark' => array_replace($tokens, $darkTokens)] as $variant => $overrides) {
            $effective = array_replace(ThemeTokenPolicy::baseline($variant), array_filter(
                $overrides,
                static fn (string $t): bool => ThemeTokenPolicy::type($t) === 'color',
                ARRAY_FILTER_USE_KEY,
            ));
            foreach (ThemeTokenPolicy::contrastPairs() as $pair) {
                $ratio = self::contrastRatio($effective[$pair['fg']], $effective[$pair['bg']]);
                $report[] = ['variant' => $variant, 'fg' => $pair['fg'], 'bg' => $pair['bg'], 'ratio' => round($ratio, 2)];
                if ($ratio < $pair['min']) {
                    throw new PackagePolicyException('theme_contrast', sprintf(
                        'Contrast %s on %s is %.2f:1 in the %s variant; %.1f:1 is required.',
                        $pair['fg'], $pair['bg'], $ratio, $variant, $pair['min'],
                    ));
                }
            }
        }
        return $report;
    }

    /**
     * Pure, deterministic emit — catalogue order, single-line blocks, zero
     * external URLs (TM-TH-02). @param array<string,string> $assetDigests name → digest
     */
    public static function emitCss(array $tokens, array $darkTokens, array $assetDigests): string
    {
        $emit = static function (array $set) use ($assetDigests): string {
            $out = '';
            foreach (array_keys(ThemeTokenPolicy::TOKENS) as $name) {
                if (!array_key_exists($name, $set)) {
                    continue;
                }
                $value = $set[$name];
                if (ThemeTokenPolicy::type($name) === 'asset') {
                    $value = 'url("/theme/asset/' . $assetDigests[$value] . '")';
                }
                $out .= $name . ':' . $value . ';';
            }
            return $out;
        };

        $css = ':root{' . $emit($tokens) . '}';
        if ($darkTokens !== []) {
            $dark = $emit($darkTokens);
            $css .= "\n" . '[data-theme="dark"]{' . $dark . '}';
            $css .= "\n" . '@media (prefers-color-scheme: dark){:root[data-theme="system"]{' . $dark . '}}';
        }
        return $css;
    }

    private static function contrastRatio(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $linear = array_map(
            static fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
            [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255],
        );
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
```

*(Note the corrected signature `ensureBuild(array $install, string $uid, array $manifest, ?int $actorId = null)` — callers resolve `$uid` from the package row. `assert()` never gates behavior.)*

- [ ] **Step 6: Write the failing integration test** `tests/Integration/Service/ThemeBuildServiceTest.php` — copy `PackageLifecycleServiceTest`'s setUp idiom (RegistryFixtures + install row + service construction from the container or by hand):

```php
public function test_build_persists_idempotently_and_enforces_contrast(): void
{
    [$service, $install, $uid, $manifest] = $this->themeFixture();   // helper per PackageLifecycleServiceTest idiom
    $build = $service->ensureBuild($install, $uid, $manifest, null);
    self::assertSame($build['id'], $service->ensureBuild($install, $uid, $manifest, null)['id']);   // idempotent
    self::assertSame(hash('sha256', (string) $build['css']), (string) $build['css_digest']);

    $lowContrast = $manifest;
    $lowContrast['theme']['tokens'] = ['--text' => '#cccccc', '--surface' => '#dddddd'];
    // fresh install row with a different digest so the unique key doesn't dedupe
    [$service2, $install2, $uid2, $manifest2] = $this->themeFixture(['manifest' => ['theme' => ['tokens' => ['--text' => '#cccccc', '--surface' => '#dddddd']]]]);
    try {
        $service2->ensureBuild($install2, $uid2, $manifest2, null);
        self::fail('expected contrast refusal');
    } catch (\App\Security\Packages\PackagePolicyException $e) {
        self::assertSame('theme_contrast', $e->code);                 // TM-TH-04
    }
}

public function test_built_css_has_no_external_urls_and_assets_are_stored(): void
{
    [$service, $install, $uid, $manifest] = $this->themeFixture(['with_asset' => true]);
    $build = $service->ensureBuild($install, $uid, $manifest, null);
    $css = (string) $build['css'];
    self::assertSame(0, preg_match('/url\(\s*["\']?(?!\/theme\/asset\/)/i', $css));                 // TM-TH-02
    self::assertStringNotContainsString('https://', $css);
    $assets = $this->themeRepo()->assetsFor((int) $build['id']);
    self::assertCount(1, $assets);
    self::assertNotNull($this->themeRepo()->findAssetByDigest((string) $assets[0]['digest']));
}
```

The `themeFixture()` helper mints a release whose manifest theme block optionally includes a real GD-generated PNG asset (reuse the `ThemeAssetScannerTest::png()` generator inline) + `--surface-texture` token, seeds it via `RegistryFixtures`, writes the artifact, and creates the `installed_packages` row exactly like `PackageLifecycleServiceTest` does (state `installed`, digest = release digest).

- [ ] **Step 7: Run all Task 5 tests** — `vendor/bin/phpunit tests/Unit/Service/Packages/ThemeBuildCssTest.php tests/Unit/Core/ThemeBaselineFidelityTest.php tests/Integration/Service/ThemeBuildServiceTest.php` → OK.

- [ ] **Step 8: Commit**

```bash
git add src/Service/Packages/ThemeBuildService.php public/assets/app.css tests/Unit/Service/Packages/ThemeBuildCssTest.php tests/Unit/Core/ThemeBaselineFidelityTest.php tests/Integration/Service/ThemeBuildServiceTest.php
git commit -m "feat(phase5): deterministic theme build - contrast hard-gate, catalogue-ordered CSS, DB assets (Inc 4, TM-TH-02/04)"
```

---

### Task 6: `ThemeStateService` + public serving routes + shell integration + safe mode (TM-TH-05/06/07 runtime layer)

The site pointer machine and the three public routes. Activation implements §8.5 exactly; safe mode is env-or-setting and flag-independent; the shell links the active (or per-admin preview) stylesheet from a try/catch-wrapped context builder; `BrandingController::css()` suppresses the retro preset while a package theme is active (the recorded precedence decision).

**Files:**
- Create: `src/Service/Packages/ThemeStateService.php`
- Create: `src/Controller/ThemeController.php`
- Modify: `src/Core/App.php` (container bindings; `shareViewGlobals` theme context; routes)
- Modify: `templates/layout.php` (theme `<link>` before the brand.css line)
- Modify: `src/Controller/BrandingController.php` (preset suppression)
- Modify: `config/config.php` (`theme.safe_mode` from env `THEME_SAFE_MODE`)
- Test: `tests/Integration/Core/AppThemePackageTest.php` (new), `tests/Integration/Core/AppFeatureFlagTest.php` (append)

**Interfaces:**
- Consumes: Tasks 1–5; `PackageSecurityGate::assertEnableable`, `PackageArtifactStore::verify/get`, `ReauthGate`, `WriteGate`, `SettingRepository`, `ModerationLogRepository`, `PackageHistoryRepository`, `Session` (controller-side for preview).
- Produces: the locked `ThemeStateService` + `ThemeController` interfaces, the `package_theme` view global, and the serving contract from the header. Route registration order matters: `/theme/preview.css` before `/theme/{digest}.css`.

Key implementation rules:

1. **`safeMode()`** = `config('theme.safe_mode') === true || settings->getString('theme_safe_mode', '') === '1'` — both wrapped so a missing settings table means `false` (never throws; the serving routes and context builder call it on every request).
2. **`activeBuild()`** returns null fast when the state row has no `active_build_id`; otherwise joins the build to `installed_packages.state` and returns null unless `state === 'enabled'` (read-time fail-closed: a quarantined/force-disabled package stops serving even if the worker hook lagged — PHASE_5_PLAN §5 #17).
3. **`activate()`** (§8.5, in this order):
   - `writeGate->assertCanWrite`, `reauth->requirePassword`
   - install row must exist, `state === 'enabled'`, manifest type `theme` (read type from the package row / release manifest)
   - `gate->assertEnableable(package, release)` (advisory/blocklist/review re-check)
   - `artifacts->verify(digest)` — on mismatch throw `PackagePolicyException('artifact_tampered', …)` (do NOT quarantine here; the health worker owns quarantine)
   - `ensureBuild(...)` (validation + assets + contrast + build)
   - one transaction: read current state row → `lkg := current active ?? current lkg` (keep the old LKG when activating from empty so rollback still has a target) → `setState(newBuildId, lkg, actorId)` → `package_history` `theme_activate` (new_version/new_digest = build css_digest) → `moderation_log` `theme_activate`
   - telemetry `theme.lifecycle {action: activate}`
4. **`rollback()`**: reauth; state must have `lkg_build_id` (else `PackagePolicyException('theme_no_lkg', …)`); LKG build's install must still be `enabled` and its stored css must re-hash to `css_digest` (else `theme_lkg_invalid` — fail closed, TM-TH-07); transaction swaps `active ↔ lkg` + history `theme_rollback` + audit; telemetry.
5. **`onInstallIneligible(installedId, reason, actorId)`**: if the active build belongs to that install → fall back to `lkg` **if** the LKG build's install is `enabled` and is a different install, else to null; clear `lkg` too when it belongs to the target install; write `theme_deactivate` history (on the affected package) + audit + telemetry; return whether anything changed. No-op (return false) when the active theme is unaffected.
6. **`preview()`**: admin-only path (controller enforces); install must be `enabled` theme-type and pass `assertEnableable` + artifact verify; `ensureBuild`; returns the build. The **controller** stores `(int) $build['id']` in the session under `theme_preview_build` — the service stays session-free. `previewBuildFor(?int)` re-checks eligibility at serve time and returns null for ineligible/missing builds.
7. **`repair()`**: clear `active_build_id` when its build row is missing or its install is not `enabled`; same for `lkg_build_id`; used by `RepairService` in Task 8.
8. **`ThemeController`**: every method first 404s unless `FeatureFlags->enabled('package_themes')`; `css`/`asset` additionally 404 in safe mode; `previewCss` 404s in safe mode or when the session has no `theme_preview_build` or `previewBuildFor` returns null. `css` validates `{digest}` against `/\A[a-f0-9]{64}\z/` then `findCssByDigest`; must also 404 unless `install_state === 'enabled'`. Headers per the serving contract (`ETag: "<digest>"`, immutable for css/asset; `no-store` for preview). Reuse the exact digest as ETag; no conditional-request handling needed (immutable + unique URLs).
9. **`App::buildContainer` bindings** (unconditional, like every package service):

```php
$c->bind(ThemeAssetScanner::class, fn () => new ThemeAssetScanner());
$c->bind(PackageThemeRepository::class, fn (Container $c) => new PackageThemeRepository($c->get(Database::class)));
$c->bind(ThemeBuildService::class, fn (Container $c) => new ThemeBuildService(
    $c->get(Database::class), $c->get(PackageThemeRepository::class), $c->get(ManifestValidator::class),
    $c->get(ThemeAssetScanner::class), $c->get(Telemetry::class),
));
$c->bind(ThemeStateService::class, fn (Container $c) => new ThemeStateService(/* per locked ctor */));
```

10. **`shareViewGlobals`** — after the `$branding` line, build the theme context (own try/catch; every failure → nulls):

```php
$packageTheme = ['active_css_digest' => null, 'preview_css_digest' => null];
try {
    if (!empty($features['package_themes'])) {
        $themeState = $container->get(ThemeStateService::class);
        if (!$themeState->safeMode()) {
            $active = $themeState->activeBuild();
            $packageTheme['active_css_digest'] = $active !== null ? (string) $active['css_digest'] : null;
            $previewId = $session->get('theme_preview_build');
            if ($previewId !== null && ($sessionUser = $session->user()) !== null && $sessionUser->isAdmin()) {
                $preview = $themeState->previewBuildFor((int) $previewId);
                $packageTheme['preview_css_digest'] = $preview !== null ? (string) $preview['css_digest'] : null;
            }
        }
    }
} catch (Throwable) {
    $packageTheme = ['active_css_digest' => null, 'preview_css_digest' => null];
}
```

Share as `'package_theme' => $packageTheme`. *(Match `Session`'s real accessor for arbitrary keys — check how existing code stores non-user session values, e.g. the OAuth state or tour code, and use the same method names.)*

11. **`templates/layout.php`** — insert between the app.css line (37) and the brand.css line (38):

```php
    <?php $pt = $package_theme ?? ['active_css_digest' => null, 'preview_css_digest' => null]; ?>
    <?php if (!empty($pt['preview_css_digest'])): ?><link rel="stylesheet" href="/theme/preview.css?v=<?= $e($pt['preview_css_digest']) ?>">
    <?php elseif (!empty($pt['active_css_digest'])): ?><link rel="stylesheet" href="/theme/<?= $e($pt['active_css_digest']) ?>.css"><?php endif; ?>
```

12. **`BrandingController::css()` preset suppression** — replace the `if ($preset === 'retro')` condition with `if ($preset === 'retro' && !$this->packageThemeActive())`, where:

```php
private function packageThemeActive(): bool
{
    try {
        $flags = $this->container->get(FeatureFlags::class);
        if (!$flags->enabled('package_themes')) {
            return false;
        }
        $themes = $this->container->get(\App\Service\Packages\ThemeStateService::class);
        return !$themes->safeMode() && $themes->activeBuild() !== null;
    } catch (\Throwable) {
        return false;
    }
}
```

13. **Routes** (in `buildRouter`, next to the package routes; ThemeController routes are public — register them near `/brand.css`):

```php
$r->get('/theme/preview.css', [ThemeController::class, 'previewCss']);
$r->get('/theme/{digest}.css', [ThemeController::class, 'css']);
$r->get('/theme/asset/{digest}', [ThemeController::class, 'asset']);
```

14. **`config/config.php`** — add next to the `packages.*` block: `'theme' => ['safe_mode' => Env::get('THEME_SAFE_MODE', '') === '1'],` *(match the file's existing Env-helper idiom exactly).*

- [ ] **Step 1: Write the failing kernel tests** `tests/Integration/Core/AppThemePackageTest.php`. Build a `themePackage()` helper that (a) enables `package_registry` + `package_themes` via the `features` setting, (b) seeds `RegistryFixtures` with the artifact dir pointed at a scratch `PACKAGES_STORAGE_PATH` (copy `AppPackageLifecycleTest`'s idiom), (c) drives the real admin routes to install → consent → enable `acme/midnight-theme` as an admin (or seeds the install/permission rows directly like the browser seed does — prefer route-driven), then exposes `$installedId`. Tests:

```php
public function test_no_served_theme_until_explicit_activation(): void
{
    [$admin, $installedId] = $this->themePackage();
    $home = $this->actingAs($admin)->get('/');
    self::assertStringNotContainsString('/theme/', $home->body());      // enabled ≠ activated
}

public function test_activation_serves_immutable_digest_addressed_css(): void
{
    [$admin, $installedId] = $this->themePackage();
    $this->actingAs($admin)->post("/admin/themes/{$installedId}/activate", ['current_password' => 'password']);
    $home = $this->get('/');                                            // signed-out shell gets it too
    self::assertMatchesRegularExpression('#/theme/[a-f0-9]{64}\.css#', $home->body());
    preg_match('#/theme/([a-f0-9]{64})\.css#', $home->body(), $m);
    $css = $this->get('/theme/' . $m[1] . '.css');
    self::assertSame(200, $css->status());
    self::assertSame(hash('sha256', $css->body()), $m[1]);              // served bytes == advertised digest
    self::assertStringContainsString('immutable', (string) $css->header('Cache-Control'));
    self::assertStringContainsString(':root{', $css->body());
}

/** TM-TH-06: a second user sees nothing while an admin previews. */
public function test_preview_is_isolated_per_admin_session(): void
{
    [$admin, $installedId] = $this->themePackage();
    $this->actingAs($admin)->post("/admin/themes/{$installedId}/preview", []);
    self::assertStringContainsString('/theme/preview.css', $this->actingAs($admin)->get('/')->body());

    $other = $this->makeUser();
    $shell = $this->actingAs($other)->get('/');
    self::assertStringNotContainsString('/theme/preview.css', $shell->body());
    self::assertStringNotContainsString('/theme/', $shell->body());
    self::assertSame(404, $this->actingAs($other)->get('/theme/preview.css')->status());
    $this->assertGuestGets404OnPreviewCss();                            // helper: fresh jar GET /theme/preview.css → 404
}

/** TM-TH-05: safe mode blanks package CSS site-wide while the hostile theme stays "active". */
public function test_safe_mode_serves_system_theme_while_theme_active(): void
{
    [$admin, $installedId] = $this->themePackage();
    $this->actingAs($admin)->post("/admin/themes/{$installedId}/activate", ['current_password' => 'password']);
    preg_match('#/theme/([a-f0-9]{64})\.css#', $this->get('/')->body(), $m);

    $this->actingAs($admin)->post('/admin/themes/safe-mode', []);        // enter: no password
    self::assertStringNotContainsString('/theme/', $this->get('/')->body());
    self::assertSame(404, $this->get('/theme/' . $m[1] . '.css')->status());

    $exit = $this->actingAs($admin)->post('/admin/themes/safe-mode', ['exit' => '1']);   // exit without password fails
    self::assertSame(422, $exit->status());
    $this->actingAs($admin)->post('/admin/themes/safe-mode', ['exit' => '1', 'current_password' => 'password']);
    self::assertStringContainsString('/theme/' . $m[1] . '.css', $this->get('/')->body());
}

/** TM-TH-07: after one-action rollback the served stylesheet digest equals the LKG digest. */
public function test_rollback_serves_exactly_the_lkg_bytes(): void
{
    [$admin, $installedId] = $this->themePackage();
    $this->actingAs($admin)->post("/admin/themes/{$installedId}/activate", ['current_password' => 'password']);
    preg_match('#/theme/([a-f0-9]{64})\.css#', $this->get('/')->body(), $first);

    $this->stageAndActivateSecondVersion($admin, $installedId);          // helper: seedRelease 1.1.0 (different tokens) → update via routes → activate again
    preg_match('#/theme/([a-f0-9]{64})\.css#', $this->get('/')->body(), $second);
    self::assertNotSame($first[1], $second[1]);

    $this->actingAs($admin)->post('/admin/themes/rollback', ['current_password' => 'password']);
    preg_match('#/theme/([a-f0-9]{64})\.css#', $this->get('/')->body(), $after);
    self::assertSame($first[1], $after[1]);                              // served digest == LKG digest
    $css = $this->get('/theme/' . $after[1] . '.css');
    self::assertSame(hash('sha256', $css->body()), $after[1]);
}

public function test_brand_css_suppresses_retro_preset_while_package_theme_active(): void
{
    [$admin, $installedId] = $this->themePackage();
    $this->settings()->set('brand_theme_preset', 'retro');
    self::assertStringContainsString('--surface:#fff7dc', $this->get('/brand.css')->body());
    $this->actingAs($admin)->post("/admin/themes/{$installedId}/activate", ['current_password' => 'password']);
    self::assertStringNotContainsString('--surface:#fff7dc', $this->get('/brand.css')->body());
    // operator hex overrides still win over the package (precedence decision):
    $this->settings()->set('brand_color_primary', '#2f6fed');
    $this->settings()->set('brand_version', 'x');
    self::assertStringContainsString('--accent:#2f6fed', $this->get('/brand.css')->body());
}

public function test_theme_asset_route_serves_neutralized_bytes(): void
{
    [$admin, $installedId] = $this->themePackage(['with_asset' => true]);
    $this->actingAs($admin)->post("/admin/themes/{$installedId}/activate", ['current_password' => 'password']);
    preg_match('#/theme/asset/([a-f0-9]{64})#', $this->get('/theme/' . $this->activeCssDigest() . '.css')->body(), $m);
    $asset = $this->get('/theme/asset/' . $m[1]);
    self::assertSame(200, $asset->status());
    self::assertSame(hash('sha256', $asset->body()), $m[1]);
    self::assertSame('image/png', (string) $asset->header('Content-Type'));
}
```

*(Helper notes: `password` must match whatever `makeAdmin()` sets — check `TestCase::makeAdmin`; `$this->settings()` = `SettingRepository` from the app container — copy `AppBrandingThemeTest`'s access pattern; `activeCssDigest()` greps `/`. The env-override variant of safe mode is unit-scoped: set the config key through whatever config-override hook the TestCase provides, or assert `ThemeStateService::safeMode()` directly with a stubbed config — check how `AppApiTokensSchemaTest`/webhook tests override config, and mirror.)*

- [ ] **Step 2: Append flag-gating tests to `AppFeatureFlagTest`** (mirror `test_capabilities_flag_gates_role_routes`):

```php
public function test_package_themes_flag_gates_theme_routes(): void
{
    $admin = $this->makeAdmin();
    self::assertSame(404, $this->actingAs($admin)->get('/admin/themes')->status());
    self::assertSame(404, $this->get('/theme/' . str_repeat('a', 64) . '.css')->status());
    self::assertSame(404, $this->get('/theme/preview.css')->status());
    self::assertSame(404, $this->get('/theme/asset/' . str_repeat('a', 64))->status());
    // Safe-mode recovery stays reachable while the flag is off (flag-independent):
    self::assertSame(200, $this->actingAs($admin)->get('/admin/themes/safe-mode')->status());
}
```

- [ ] **Step 3: Run to verify failure** — routes 404 everywhere / classes missing.

- [ ] **Step 4: Implement** `ThemeStateService`, `ThemeController`, bindings, routes, layout, config, branding suppression per the numbered rules above. `ThemeController` skeleton:

```php
final class ThemeController extends Controller
{
    public function css(Request $request): Response
    {
        $this->gate();
        $themes = $this->container->get(ThemeStateService::class);
        $digest = (string) $request->route('digest');
        if ($themes->safeMode() || preg_match('/\A[a-f0-9]{64}\z/', $digest) !== 1) {
            throw new NotFoundException();
        }
        $row = $this->container->get(PackageThemeRepository::class)->findCssByDigest($digest);
        if ($row === null || ($row['install_state'] ?? '') !== 'enabled') {
            throw new NotFoundException();
        }
        return new Response((string) $row['css'], 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"' . $digest . '"',
        ]);
    }
    // previewCss(): flag gate → safe-mode 404 → session 'theme_preview_build' → previewBuildFor → no-store response
    // asset(): flag gate → safe-mode 404 → hex-64 gate → findAssetByDigest → immutable response with stored mime
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_themes')) {
            throw new NotFoundException();
        }
    }
}
```

*(Match `Request`'s real route-param accessor — check how `AdminPackagesController::show` reads `{id}` and use the same method.)*

- [ ] **Step 5: Run the new tests + adjacent suites**

Run: `vendor/bin/phpunit tests/Integration/Core/AppThemePackageTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppBrandingThemeTest.php`
Expected: OK — AppThemePackageTest's admin-route tests still fail on the missing admin controller (Task 7); if so, keep only the serving/flag tests green here and move the admin-route tests into the Task 7 step (author them all now, `markTestSkipped` is forbidden — instead reference: write the file complete in this task but run the admin-route subset after Task 7; the intermediate commit may carry the failing-tests-not-yet-run caveat by simply not running them: use `--filter` for the passing subset and note the full run happens in Task 7).

**Correction for cleanliness:** author in this task only the tests that pass without the admin controller (`test_no_served_theme…`, flag gating, brand-css suppression via direct `ThemeStateService::activate()` service calls instead of routes). Author the route-driven versions in Task 7 where the controller exists. Adjust the code above accordingly when splitting — the assertions stay identical, only the driving mechanism changes (service call vs POST).

- [ ] **Step 6: Commit**

```bash
git add src/Service/Packages/ThemeStateService.php src/Controller/ThemeController.php src/Core/App.php templates/layout.php src/Controller/BrandingController.php config/config.php tests/Integration/Core/AppThemePackageTest.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "feat(phase5): theme state machine + digest-addressed CSP-safe serving + flag-independent safe mode (Inc 4, TM-TH-05/07)"
```

---

### Task 7: No-JS admin surface — `/admin/themes` overview, preview/activate/rollback/safe-mode forms

One page + one plain-variant recovery page. Follows `AdminPackageLifecycleController` exactly: `requireAdmin()` → flag gate → noindex on every response; `ValidationException` → 422 re-render; `PackagePolicyException` → inline `code: message`.

**Files:**
- Create: `src/Controller/AdminThemeController.php`
- Create: `templates/admin/themes.php`, `templates/admin/theme_safe_mode.php`
- Modify: `src/Core/App.php` (admin routes — register `POST /admin/themes/preview/clear` and `/admin/themes/rollback` and `/admin/themes/safe-mode` **before** `/admin/themes/{id}/…` so the literal segments can't be captured; `{id}` compiles to `\d+` so order is belt-and-braces, keep it anyway)
- Test: finish `tests/Integration/Core/AppThemePackageTest.php` (the route-driven tests from Task 6) + admin authorization tests

**Interfaces:**
- Consumes: `ThemeStateService` (Task 6), `PackageThemeRepository`, `InstalledPackageRepository` (list theme-type installs — check its existing query surface; add a `themeInstalls()` method to `PackageThemeRepository` instead if the install repo has no type filter: `SELECT ip.*, p.package_uid, p.name FROM installed_packages ip JOIN packages p ON p.id = ip.package_id WHERE p.type = 'theme' AND ip.state IN ('installed','enabled','disabled') ORDER BY p.package_uid`).
- Produces: the admin routes in the locked interface; templates.

Controller behaviors:
- `index`: overview data — safe-mode flag, state row joined to build + package rows (active + LKG), eligible installs list with per-install latest build (via `buildsForInstall`), session preview id. Render `admin/themes`.
- `preview`: `ThemeStateService::preview($admin, $id)` → `Session` put `theme_preview_build` → redirect `/admin/themes` with flash `Previewing <name> in this session only.`
- `clearPreview`: session forget + redirect.
- `activate`: service call with `current_password`; catches `ValidationException` (bad password → 422 re-render index data with `errors['current_password']`) and `PackagePolicyException` (422, `errors['theme'] = code . ': ' . message`); success → session forget preview + flash `Theme activated.`
- `rollback`: same pattern; flash `Rolled back to the last-known-good theme.`
- `safeModeForm`: **no flag gate** — `requireAdmin()` + noindex + render `admin/theme_safe_mode` with `variant=plain` (the template calls `$this->section('variant', 'plain')` equivalent — copy how error/setup pages select the plain variant). The page must not reference `/theme/…` (it renders under the hostile theme; TM-TH-05).
- `safeMode` (POST, **no flag gate**): `exit=1` → `setSafeMode($admin, false, $currentPassword)`; else `setSafeMode($admin, true)`; redirect back to `/admin/themes/safe-mode` with flash.

`templates/admin/themes.php` structure (no-JS, `$this->csrfField()` in every form, escape everything):

```php
<?php $this->layout('layout'); ?>
<?php $this->section('title', 'Themes'); ?>
<?php /* h1 + admin tab nav (copy templates/admin/packages.php header block) */ ?>
<section><!-- Safe mode banner: current state + link to /admin/themes/safe-mode --></section>
<section><!-- Active theme: package uid/name/version, css digest (code), activated at/by; LKG line;
              Roll back form (POST /admin/themes/rollback: current_password + submit) when LKG exists --></section>
<section><!-- Installed theme packages table: uid, version, install state, latest build state/digest;
              per row: Preview form (POST /admin/themes/{id}/preview) when enabled;
              Activate form (POST /admin/themes/{id}/activate: password) when enabled;
              "Enable it from Packages first" hint linking /admin/packages/{package_id} otherwise --></section>
<section><!-- Preview state: active session preview name + End preview form (POST /admin/themes/preview/clear) --></section>
<?php /* errors: render $errors['current_password'] / $errors['theme'] inline near the relevant form */ ?>
```

`templates/admin/theme_safe_mode.php`: plain variant; states whether safe mode is ON (and whether forced by environment — show `THEME_SAFE_MODE=1` note when config-forced, in which case the exit form is replaced by explanatory text); enter form (single button); exit form (password + button). This page is intentionally dependency-free: no package CSS, no images.

- [ ] **Step 1: Write the failing route-driven tests** — move/author the Task 6 route-driven bodies (activation/preview/safe-mode/rollback flows) plus:

```php
public function test_admin_theme_routes_require_admin_and_are_noindexed(): void
{
    [$admin, $installedId] = $this->themePackage();
    $user = $this->makeUser();
    self::assertSame(403, $this->actingAs($user)->get('/admin/themes')->status());
    self::assertSame(403, $this->actingAs($user)->post("/admin/themes/{$installedId}/preview", [])->status());
    $index = $this->actingAs($admin)->get('/admin/themes');
    self::assertSame(200, $index->status());
    self::assertSame('noindex', (string) $index->header('X-Robots-Tag'));
}

public function test_activation_requires_password_and_consented_enabled_install(): void
{
    [$admin, $installedId] = $this->themePackage();
    self::assertSame(422, $this->actingAs($admin)->post("/admin/themes/{$installedId}/activate", ['current_password' => 'wrong'])->status());
    // disable the package, then activation refuses:
    $this->actingAs($admin)->post("/admin/packages/{$this->packageId}/disable", []);
    $refused = $this->actingAs($admin)->post("/admin/themes/{$installedId}/activate", ['current_password' => 'password']);
    self::assertSame(422, $refused->status());
    self::assertStringContainsString('invalid_state', $refused->body());
}
```

*(The disable route is on the **package** id, not the install id — reuse the ids the fixture helper returns; check `AdminPackageLifecycleController` route semantics while writing the helper.)*

- [ ] **Step 2: Run to verify failure** — 404 (routes absent).

- [ ] **Step 3: Implement** controller + templates + routes per the behavior list. Route block (admin section of `buildRouter`):

```php
$r->get('/admin/themes', [AdminThemeController::class, 'index']);
$r->get('/admin/themes/safe-mode', [AdminThemeController::class, 'safeModeForm']);
$r->post('/admin/themes/safe-mode', [AdminThemeController::class, 'safeMode']);
$r->post('/admin/themes/preview/clear', [AdminThemeController::class, 'clearPreview']);
$r->post('/admin/themes/rollback', [AdminThemeController::class, 'rollback']);
$r->post('/admin/themes/{id}/preview', [AdminThemeController::class, 'preview']);
$r->post('/admin/themes/{id}/activate', [AdminThemeController::class, 'activate']);
```

- [ ] **Step 4: Run the whole `AppThemePackageTest` + flag test** — everything from Tasks 6–7 green now.

- [ ] **Step 5: Add `/admin/themes` to the admin nav** — find where `/admin/packages` is linked (sidebar/admin tab partial, e.g. the nav the packages templates share) and add a `Themes` link gated the same way (`features['package_themes']`). Assert in `AppThemePackageTest` that `/admin/themes` appears on `/admin/packages` for an admin when the flag is on (copy the nav-assertion idiom from AppPackageLifecycleTest if one exists; otherwise assert the link inside `/admin/themes` breadcrumbs only — do not invent a new nav system).

- [ ] **Step 6: Commit**

```bash
git add src/Controller/AdminThemeController.php templates/admin/themes.php templates/admin/theme_safe_mode.php src/Core/App.php tests/Integration/Core/AppThemePackageTest.php
git commit -m "feat(phase5): no-JS theme admin - preview/activate/rollback/safe-mode forms (Inc 4, TM-TH-06)"
```

---

### Task 8: Lifecycle + worker + repair integration — a broken/blocked/removed theme fails safe

Wire `onInstallIneligible` into every path that makes an install unusable: operator disable, uninstall (disable-first already funnels it), quarantine (lifecycle `reverify` and health-worker tamper), and advisory/blocklist force-disable. Add the `RepairService` mirror.

**Files:**
- Modify: `src/Service/Packages/PackageLifecycleService.php` (constructor gains `?ThemeStateService $themes = null`; call the hook in `disable()` and in the `quarantine()` helper)
- Modify: `src/Service/Packages/PackageHealthService.php` (same: after `quarantine()` and `securityDisable()`)
- Modify: `src/Service/Packages/PackageUpdateService.php` (**only if** its rollback/activate path can leave `state != 'enabled'` — read it; if activation keeps `enabled`, no hook is needed because the served build still points at the *old* digest whose bytes live in `package_theme_builds`, which is correct-by-design drift)
- Modify: `src/Core/App.php` (pass `ThemeStateService` into the three services), `bin/console` (`repair` + `worker:packages` construction sites — mirror App.php wiring)
- Modify: `src/Service/RepairService.php` (add `repairThemeState()` calling `ThemeStateService::repair()`; register in the repair run list + `bin/console repair` output)
- Test: `tests/Integration/Service/ThemeLifecycleIntegrationTest.php` (new) + extend the Inc 3 health-worker test file (locate: `grep -rl PackageHealthService tests/Integration`)

**Interfaces:**
- Consumes: `ThemeStateService::onInstallIneligible(int, string, ?int): bool`, `::repair()`.
- Produces: no new public interfaces; `RepairService::repairThemeState(): array{cleared_active:int, cleared_lkg:int}`.

Wiring rule: the hook call is always `$this->themes?->onInstallIneligible($installedId, $reason, $actorId)` **inside the same code path but OUTSIDE the surrounding transaction when one exists** (the hook runs its own transaction; nesting `$db->transaction` is forbidden — check how Inc 3 sequences quarantine + history writes and place the call after the state-changing transaction commits).

- [ ] **Step 1: Write the failing tests** `tests/Integration/Service/ThemeLifecycleIntegrationTest.php`:

```php
public function test_disabling_the_active_theme_package_deactivates_serving(): void
{
    [$admin, $installedId] = $this->activatedThemeFixture();          // helper reuses AppThemePackageTest idiom
    self::assertNotNull($this->themeState()->activeBuild());
    $this->lifecycle()->disable($admin->entity(), $installedId);      // match the real disable() signature
    self::assertNull($this->themeState()->activeBuild());
    $state = $this->themeRepo()->state();
    self::assertNull($state['active_build_id']);                      // pointer cleared, not just masked
}

public function test_quarantine_by_health_worker_deactivates_and_falls_back_to_lkg(): void
{
    // activate v1, then update+activate v2 (so LKG = v1 build), then tamper v2's artifact bytes on disk
    [$admin, $installedId, $v1Digest] = $this->twoVersionActivatedFixture();
    $this->tamperArtifactFor($installedId);
    $this->healthService()->checkAll();
    $active = $this->themeState()->activeBuild();
    // v2 install quarantined; both builds belong to the same install → full deactivation (LKG same install):
    self::assertNull($active);
}

public function test_force_disable_via_local_block_deactivates(): void
{
    [$admin, $installedId] = $this->activatedThemeFixture();
    $this->blocklist()->block($admin->entity(), 'acme/midnight-theme', null, 'emergency');   // match LocalBlocklistService::block signature
    $this->healthService()->enforcePolicy();
    self::assertNull($this->themeState()->activeBuild());
}

public function test_repair_clears_dangling_pointers(): void
{
    [$admin, $installedId] = $this->activatedThemeFixture();
    // corrupt: mark install disabled directly, leaving the pointer in place
    $this->installs()->setState($installedId, 'disabled');
    self::assertNull($this->themeState()->activeBuild());              // read-time gate already refuses
    $result = $this->repairService()->repairThemeState();
    self::assertSame(1, $result['cleared_active']);
    self::assertNull($this->themeRepo()->state()['active_build_id']);  // and repair reconciles the row
}
```

*(Resolve every helper against the real Inc 3 test files: `lifecycle()`, `healthService()`, `blocklist()`, `installs()` construction all exist there — copy, don't guess. `->entity()`/User handling: match how those tests obtain the `App\Domain\User` for service calls.)*

Also extend the health-worker test: after quarantining an active theme install, `worker:packages` output still matches its existing shape and the theme pointer is cleared (assert via a fresh `PackageThemeRepository`).

- [ ] **Step 2: Run to verify failure** — hooks absent: pointers survive disable/quarantine.

- [ ] **Step 3: Implement** the nullable-collaborator wiring + hook calls + `RepairService::repairThemeState()` + `bin/console repair` line (mirror how other repair sections print counts). In `App::buildContainer`, pass `$c->get(ThemeStateService::class)` to the three services (unconditional — the hook itself no-ops when no theme is active; do NOT flag-condition the injection, the state row is the authority). In `bin/console`, update the hand-built `$packageHealth` closure and any lifecycle construction to pass the theme service (build it from the same helpers).

**Circular-dependency check:** `ThemeStateService` must NOT depend on `PackageLifecycleService`/`PackageHealthService`/`PackageUpdateService` (it doesn't, per the locked constructor). If PHP wiring still recurses (container closures resolve lazily, so it won't), fall back to a setter: `setThemeService(?ThemeStateService)` called after construction — but prefer the constructor.

- [ ] **Step 4: Run** the new test file + the full package test set + `php bin/console repair` (dev DB) smoke:

Run: `vendor/bin/phpunit tests/Integration/Service tests/Integration/Core/AppThemePackageTest.php && DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} php bin/console repair | tail -5`
Expected: green; repair prints the theme-state line with zero clears.

- [ ] **Step 5: Commit**

```bash
git add src/Service/Packages/PackageLifecycleService.php src/Service/Packages/PackageHealthService.php src/Service/Packages/PackageUpdateService.php src/Service/RepairService.php src/Core/App.php bin/console tests/Integration/Service/ThemeLifecycleIntegrationTest.php
git commit -m "feat(phase5): fail-safe theme deactivation on disable/quarantine/force-disable + repair mirror (Inc 4)"
```

---

### Task 9: D11 budget — `theme.build_apply_p95` measured on synthetic theme lifecycles

Adds the theme budget key (transcribing ADR 0004 D11's approved "install/update p95 10 s for declarative packages" umbrella — no new gate number is invented) and measures build+activate through the real services.

**Files:**
- Modify: `src/Support/Phase5Budgets.php` (one row), `src/Service/BaselineMetricsService.php` (a `measureThemeBuildApply()` mirroring `measureInstallUpdate()`), `src/Service/Phase5BudgetReportService.php` (optional deps + `theme.build_apply_p95` branch), `bin/console` (`verify:phase5-budgets` wiring — pass the theme services)
- Test: `tests/Integration/Service/ThemeBudgetTest.php` (mirror `PackageInstallBudgetTest`)
- Regenerate: `docs/evidence/phase5/performance-budgets.md`

**Interfaces:**
- Consumes: `ThemeBuildService`, `ThemeStateService` (or drive `activate()` at the service layer with a seeded admin), `SigningHarness`/`RegistryFixtures`.
- Produces: budget row `'theme.build_apply_p95' => ['metric' => 'Theme build + activate (declarative package)', 'target' => 10000, 'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc4']` and a docblock line noting the D11 umbrella; report `data_fixture: 'synthetic theme package build/activate samples'`.

- [ ] **Step 1: Failing test** asserting (a) the key exists in `Phase5Budgets::all()` with target 10000/p95, (b) `Phase5BudgetReportService::rows()` reports it `MEASURED` (not `PENDING (inc4)`) when constructed with the theme deps, (c) the measured p95 is `< target` (generous — the sample is local).
- [ ] **Step 2: Run** — fail (unknown key).
- [ ] **Step 3: Implement.** `measureThemeBuildApply(int $samples = 8)`: per sample, mint a distinct theme release (vary a token value per index — e.g. shade the accent by index; **no `random_*`** so the fixture stays deterministic), seed + install + enable via the same synthetic path `measureInstallUpdate` uses, then time `ensureBuild` + `activate` (µs → ms, p95 over samples). Reuse its temp `PackageArtifactStore` pattern. Keep the sample loop free of `Date`/randomness pitfalls (match the existing measurement style).
- [ ] **Step 4: Run** the test, then regenerate the evidence file:

Run: `vendor/bin/phpunit tests/Integration/Service/ThemeBudgetTest.php && APP_ENV=testing php bin/console verify:phase5-budgets && grep theme docs/evidence/phase5/performance-budgets.md`
Expected: `theme.build_apply_p95 … MEASURED (PASS)`.

- [ ] **Step 5: Commit**

```bash
git add src/Support/Phase5Budgets.php src/Service/BaselineMetricsService.php src/Service/Phase5BudgetReportService.php bin/console tests/Integration/Service/ThemeBudgetTest.php docs/evidence/phase5/performance-budgets.md
git commit -m "feat(phase5): measure theme.build_apply_p95 against the D11 declarative budget (Inc 4)"
```

---

### Task 10: Browser + axe evidence (desktop + mobile)

The no-JS journey through real Chrome: preview isolation, activation, safe mode, rollback — shots `39–42` on both viewports, axe on the two new admin pages.

**Files:**
- Modify: `tests/browser/seed.php` (theme block in the midnight-theme fixture — it flows automatically once `SigningHarness::mintManifest` carries the default block, but the seeded artifact/digest values change: re-check the seeded install rows still line up; give `acme/midnight-theme` a **visually obvious** token set — e.g. `--surface:#1d232e`-ish dark palette with passing contrast — and one PNG asset; enable `package_themes` in `$evidenceFeatures`; pre-install/consent/enable the midnight-theme install row mirroring the consent-demo seeding so the journey starts at preview)
- Modify: `tests/browser/gate-a.spec.ts` (new test `'theme packages: preview, activate, safe mode, and LKG rollback (Inc 4)'`)
- Modify: `tests/browser/a11y.spec.ts` (axe over `/admin/themes` + `/admin/themes/safe-mode`)

**Interfaces:**
- Consumes: the evidence harness conventions (login helper, screenshot naming `NN-slug.png`, desktop+mobile projects — read the Inc 3 test in `gate-a.spec.ts` first and copy its structure).
- Produces: `docs/evidence/browser/{desktop,mobile}/39-admin-themes-preview.png`, `40-admin-theme-active.png`, `41-admin-theme-safe-mode.png`, `42-admin-theme-rollback.png`.

- [ ] **Step 1: Extend `seed.php`** per above (follow `$ensureRegistryFixtures`; verify the theme block survives into the stored `manifest_json` and artifact file).
- [ ] **Step 2: Write the spec** — journey: login admin → `/admin/themes` → POST preview (submit the form) → assert `<link href="/theme/preview.css` present + screenshot 39 → activate with password → assert `/theme/<digest>.css` link + screenshot 40 → **second context** (fresh incognito context, signed-out): assert no `/theme/` link while the first context previews/activates intermediate states (minimum: after preview, before activate) → safe-mode page → enter safe mode → assert shell has no `/theme/` link + screenshot 41 → exit with password → update to 1.1.0 via `/admin/packages` (the Inc 3 journey already proves update; here just drive it) → activate new build → rollback → assert the first digest is served again + screenshot 42.
- [ ] **Step 3: Run** `cd tests/browser && npm run evidence` — expect all green including the Inc 2/3 package journeys (the fixture digest changes from Task 2 ripple here; fix any seeded expectations). Then `npm run a11y` — no serious/critical violations on the two new pages.
- [ ] **Step 4: Commit**

```bash
git add tests/browser/seed.php tests/browser/gate-a.spec.ts tests/browser/a11y.spec.ts docs/evidence/browser
git commit -m "test(phase5): browser + axe evidence for theme preview/activate/safe-mode/rollback (Inc 4)"
```

---

### Task 11: Closeout — protocol/runbook docs, threat-model flips, ledger, status, final gates

**Files:**
- Modify: `docs/phase5/registry-protocol.md` (Manifest v2 table: `theme` block spec incl. grammars/caps; new section `## Theme Builds & Serving (Inc 4)` documenting emit format, digest cache key, the three routes, safe-mode semantics, precedence decision)
- Create: `docs/runbooks/package_themes.md` (enable/disable/rollback contract from the ledger; staff-preview staged rollout per §13.1 step 4; safe-mode entry: admin UI, `THEME_SAFE_MODE=1`, and `UPDATE settings … theme_safe_mode` SQL of last resort; LKG rollback; quarantine interplay; repair; telemetry events)
- Modify: `docs/runbooks/package_registry.md` (Quarantine/Emergency sections: one line each pointing at theme deactivation + the themes runbook)
- Modify: `docs/phase5/threat-models/fixtures.json` (TM-TH-01…07 → `implemented` + test files: 01 `tests/Unit/Security/Packages/ThemeTokenPolicyTest.php`, 02 `tests/Unit/Service/Packages/ThemeBuildCssTest.php`, 03 `tests/Unit/Service/Packages/ThemeAssetScannerTest.php`, 04 `tests/Integration/Service/ThemeBuildServiceTest.php`, 05/06/07 `tests/Integration/Core/AppThemePackageTest.php`)
- Modify: `docs/phase5/requirement-ledger.json` (GA-DOD-07 → `R3` with evidence: the five test files above + `docs/evidence/browser/desktop/40-admin-theme-active.png` + `docs/runbooks/package_themes.md` + `docs/evidence/phase5/performance-budgets.md`)
- Modify: `docs/evidence/deploy-dark-features.md` (the `package_themes` row: now implemented deploy-dark, rollback contract unchanged)
- Modify: `PHASE_5_STATUS.md` (Inc 4 section + header/suite numbers + evidence index entries)
- Test: `vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php tests/Unit/Core/Phase5EvidenceMapTest.php` enforce the two JSON files.

- [ ] **Step 1: Flip the JSON files**, run the two enforcement tests — green means every referenced path exists.
- [ ] **Step 2: Write the docs** (protocol, runbook, cross-links, deploy-dark inventory, status).
- [ ] **Step 3: Final gates** (all must pass; record outputs in PHASE_5_STATUS):

```bash
RB_TEST_FRESH=1 composer test          # fresh-schema full suite
composer test                          # reused-schema run 1
composer test                          # reused-schema run 2 (reference-table seed gotcha guard)
APP_ENV=testing php bin/console verify:phase5-budgets
APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force
DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} php bin/console worker:packages
cd tests/browser && npm run evidence && npm run a11y
```

- [ ] **Step 4: Commit**

```bash
git add docs/phase5/registry-protocol.md docs/runbooks/package_themes.md docs/runbooks/package_registry.md docs/phase5/threat-models/fixtures.json docs/phase5/requirement-ledger.json docs/evidence/deploy-dark-features.md PHASE_5_STATUS.md
git commit -m "docs(phase5): Inc 4 closeout - TM-TH-01..07 implemented, GA-DOD-07 -> R3, theme runbook + protocol + status"
```

- [ ] **Step 5: Adversarial review.** Run an independent review of the full increment diff (dimensions: token/CSS policy escape vectors, preview/session isolation, safe-mode independence, transactional invariants, doc accuracy, determinism) and fix confirmed findings in follow-up commits — the Inc 1–3 precedent.

---

## Self-review notes (already folded in)

- **Spec coverage check** against PHASE_5_PLAN §4 lines 120–123 + §8.2 #7 + §8.5 + §9 theme rows + TM-TH-01…07 + program-plan Inc 4 scope: every clause maps to a task (tokens/assets → 1–5; preview isolation → 6–7; deterministic build/cache key → 5–6; contrast hard-block → 5; safe mode flag-independent + env → 6–7; LKG one-action rollback → 6–7; asset scan → 4; CSP/cache → 5–6; activation invariant → 6; §13.2 "without loading package assets" → safe-mode 404s on all three routes; budgets → 9; browser/a11y/no-JS → 7/10; runbook/ledger/threat flips → 11; repair → 8; audit/telemetry → 6/8).
- **Gate B exclusion honored:** no stylesheet modules, no selector grammar anywhere.
- **Known correction markers:** Task 5 corrects `ensureBuild`'s signature (adds `string $uid`); Task 6 Step 5 splits route-driven tests into Task 7 — both corrections are stated inline where they apply.
- **Type consistency:** `PackagePolicyException` codes used across tasks: `theme_missing, theme_forbidden, theme_schema, theme_token, theme_asset, theme_contrast, theme_no_lkg, theme_lkg_invalid, artifact_tampered, invalid_state` — all thrown and asserted with the same strings.
- **Implementer read-first list** (things this plan tells you to verify rather than trust: exact `TestCase` helper names, `Session` accessor names, `Request` route-param accessor, 0069 ENUM list, 0069 `settings_json` column type, `AppImladrisFidelityTest` expectations, `makeAdmin()` password, config Env idiom, admin nav partial, health-worker test filename, `LocalBlocklistService::block` signature, browser login helper).
