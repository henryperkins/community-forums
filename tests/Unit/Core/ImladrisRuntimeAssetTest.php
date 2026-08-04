<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\FeatureFlags;
use App\Support\ImladrisAssetBuilder;
use PHPUnit\Framework\TestCase;

final class ImladrisRuntimeAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_checked_in_runtime_asset_matches_the_allowlisted_design_system_sources(): void
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(self::ROOT . '/bin/build-imladris-assets.php')
            . ' --check';
        exec($command . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertFileExists(self::ROOT . '/public/assets/imladris.css');

        $css = (string) file_get_contents(self::ROOT . '/public/assets/imladris.css');
        self::assertStringContainsString('Generated from the allowlisted Imladris runtime sources', $css);
        self::assertStringContainsString('@font-face', $css);
        self::assertMatchesRegularExpression('/--text-body\s*:\s*var\(--ink-700\)/', $css);
        self::assertMatchesRegularExpression('/--text-size-body\s*:\s*1\.0625rem/', $css);
        self::assertDoesNotMatchRegularExpression('/https?:\/\//i', $css);
        self::assertStringNotContainsString('!important', $css);
        self::assertStringNotContainsString('animation-duration: 0.001ms', $css);
        self::assertStringNotContainsString('/* Source: _archive/', $css);
        self::assertStringNotContainsString('components/doc.css', $css);
    }

    public function test_production_contract_classifies_every_declared_feature_flag(): void
    {
        $path = self::ROOT . '/docs/design-system/imladris/production-contract.json';
        self::assertFileExists($path);

        $contract = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $contract['unresolved_gaps'] ?? null);

        $classified = array_values(array_unique(array_merge(
            $contract['flags']['default_on'] ?? [],
            $contract['flags']['implemented_dark'] ?? [],
            $contract['flags']['reserved_dark'] ?? [],
        )));
        $declared = array_keys(FeatureFlags::defaults());
        sort($classified);
        sort($declared);

        self::assertSame($declared, $classified);
    }

    public function test_imported_composer_contract_is_current_and_has_no_superseded_anatomy(): void
    {
        $composer = (string) file_get_contents(
            self::ROOT . '/docs/design-system/imladris/components/forum/Composer.jsx',
        );

        self::assertStringContainsString('composer-shell', $composer);
        self::assertStringContainsString('composer-box', $composer);
        self::assertStringContainsString('composer-upload-tray', $composer);
        self::assertStringNotContainsString('Posting as', $composer);
        self::assertStringNotContainsString('className="composer-id"', $composer);
    }

    public function test_reviewed_application_baseline_covers_forum_presentation_and_composer_contracts(): void
    {
        $path = self::ROOT . '/config/imladris-runtime-baseline.json';
        self::assertFileExists($path);

        $baseline = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('6d81da590a12bd09bb8d0e282c042aa03d755a94', $baseline['reconciled_through_commit'] ?? null);
        self::assertSame('COMPOSER.md v0.8', $baseline['composer_contract'] ?? null);
        self::assertContains('templates', $baseline['application_surface']['roots'] ?? []);
        self::assertContains('public/assets', $baseline['application_surface']['roots'] ?? []);
        self::assertContains('USER.md', $baseline['application_surface']['files'] ?? []);
        self::assertContains('ADMIN.md', $baseline['application_surface']['files'] ?? []);
        self::assertContains('COMMUNITY.md', $baseline['application_surface']['files'] ?? []);
        self::assertContains('COMPOSER.md', $baseline['application_surface']['files'] ?? []);
        self::assertContains('src/Core/FeatureFlags.php', $baseline['application_surface']['files'] ?? []);
        self::assertContains('public/assets/imladris.css', $baseline['application_surface']['excluded'] ?? []);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $baseline['application_surface']['sha256'] ?? '');

        $contractPath = self::ROOT . '/docs/design-system/imladris/production-contract.json';
        $contract = json_decode((string) file_get_contents($contractPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($baseline['reconciled_through_commit'], $contract['reconciled_through_commit'] ?? null);
        self::assertSame($baseline['composer_contract'], $contract['composer']['spec'] ?? null);
        self::assertSame(
            ['USER.md', 'ADMIN.md', 'COMMUNITY.md', 'COMPOSER.md'],
            $contract['surface_specs'] ?? null,
        );

        $runtime = json_decode(
            (string) file_get_contents(self::ROOT . '/resources/imladris/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('docs/design-system/imladris/production-contract.json', $runtime['design_contract']['source'] ?? null);
    }

    public function test_application_css_does_not_redeclare_design_system_foundations(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');

        self::assertDoesNotMatchRegularExpression('/\:root\s*\{[^}]*--parchment-50\s*:/s', $css);
        self::assertStringContainsString('font-size: var(--text-size-body)', $css);
        self::assertStringContainsString('background-image: var(--surface-texture, none)', $css);
    }

    public function test_shared_console_component_css_is_scoped_and_keeps_existing_layout_guardrails(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        $rule = static function (string $selector) use ($css): string {
            self::assertSame(
                1,
                preg_match('/' . preg_quote($selector, '/') . '\s*\{(?<declarations>[^}]*)\}/s', $css, $match),
                $selector . ' is missing from the application stylesheet.',
            );

            return $match['declarations'];
        };

        $globalCard = $rule('.card');
        self::assertMatchesRegularExpression('/background:\s*var\(--surface\)/', $globalCard);
        self::assertMatchesRegularExpression('/overflow-x:\s*auto/', $globalCard);

        $consoleCard = $rule('.admin-console .card');
        self::assertMatchesRegularExpression('/background:\s*var\(--surface-raised\)/', $consoleCard);
        self::assertMatchesRegularExpression('/border:\s*1px solid var\(--border-hair\)/', $consoleCard);
        self::assertMatchesRegularExpression('/border-radius:\s*var\(--radius-lg\)/', $consoleCard);
        self::assertMatchesRegularExpression('/padding:\s*18px 20px/', $consoleCard);
        self::assertMatchesRegularExpression('/box-shadow:\s*var\(--shadow-xs\)/', $consoleCard);
        self::assertMatchesRegularExpression('/overflow-x:\s*auto/', $consoleCard);
        self::assertDoesNotMatchRegularExpression('/overflow:\s*visible/', $consoleCard);

        $auditHeading = $rule('.admin-console .audit th');
        self::assertMatchesRegularExpression('/font-size:\s*\.66rem/', $auditHeading);
        self::assertMatchesRegularExpression('/letter-spacing:\s*\.12em/', $auditHeading);
        self::assertMatchesRegularExpression('/color:\s*var\(--text-faint\)/', $auditHeading);
        self::assertMatchesRegularExpression('/font-weight:\s*400/', $auditHeading);
        self::assertMatchesRegularExpression('/border-bottom:\s*1px solid var\(--border-soft\)/', $auditHeading);
        $auditMono = $rule('.admin-console .audit .mono');
        self::assertMatchesRegularExpression('/font-family:\s*var\(--font-mono\)/', $auditMono);
        self::assertMatchesRegularExpression('/font-size:\s*\.78rem/', $auditMono);
        $auditCode = $rule('.admin-console .audit code');
        self::assertMatchesRegularExpression('/font-size:\s*\.76rem/', $auditCode);
        self::assertMatchesRegularExpression('/color:\s*var\(--text-body\)/', $auditCode);
        self::assertMatchesRegularExpression('/border-radius:\s*var\(--radius-sm\)/', $auditCode);
        self::assertMatchesRegularExpression('/font-variant-numeric:\s*tabular-nums/', $rule('.admin-console .audit .numeric'));
        self::assertMatchesRegularExpression('/text-align:\s*right/', $rule('.admin-console .audit .numeric'));

        $tableScroll = $rule('.admin-console .table-scroll');
        self::assertMatchesRegularExpression('/position:\s*relative/', $tableScroll);
        self::assertMatchesRegularExpression('/overflow-x:\s*auto/', $tableScroll);
        self::assertMatchesRegularExpression('/outline:\s*2px solid var\(--accent\)/', $rule('.admin-console .table-scroll:focus-visible'));
        self::assertMatchesRegularExpression('/min-width:\s*760px/', $rule('.admin-console .table-scroll > .audit'));
        self::assertMatchesRegularExpression('/min-width:\s*940px/', $rule('.admin-console .table-scroll.table-scroll-wide > .audit'));

        $state = $rule('.admin-console .state');
        self::assertMatchesRegularExpression('/border-radius:\s*var\(--radius-pill\)/', $state);
        self::assertMatchesRegularExpression('/background:\s*var\(--surface-pending\)/', $state);
        self::assertMatchesRegularExpression('/color:\s*var\(--on-pending\)/', $state);
        self::assertMatchesRegularExpression('/display:\s*none/', $rule('.admin-console .state::before'));
        self::assertMatchesRegularExpression(
            '/\.admin-console \.state-active\s*,\s*\.admin-console \.state-sent\s*\{[^}]*background:\s*var\(--surface-done\)[^}]*color:\s*var\(--on-done\)/s',
            $css,
        );
        self::assertMatchesRegularExpression(
            '/\.admin-console \.state-queued[^}]*\.admin-console \.state-scheduled\s*\{[^}]*background:\s*var\(--surface-review\)[^}]*color:\s*var\(--on-review\)/s',
            $css,
        );
        self::assertMatchesRegularExpression(
            '/\.admin-console \.state-revoked[^}]*\.admin-console \.state-expired\s*\{[^}]*background:\s*color-mix\(in srgb, var\(--rust\) 12%, var\(--surface-raised\)\)[^}]*color:\s*var\(--danger\)/s',
            $css,
        );

        foreach (['.state-empty', '.admin-console .pager', '.admin-console .filter-actions',
            '.admin-console .confirm-card', '.admin-console .impact-list', '.admin-console .callout',
            '.admin-console .reauth-field', '.admin-console .check-grid', '.admin-console .spec-list',
            '.admin-console .admin-split', '.admin-console .admin-split--fixed'] as $selector) {
            $rule($selector);
        }

        $pager = $rule('.admin-console .pager');
        self::assertMatchesRegularExpression('/justify-content:\s*space-between/', $pager);
        self::assertMatchesRegularExpression('/gap:\s*14px/', $pager);
        self::assertMatchesRegularExpression('/margin-top:\s*16px/', $pager);
        $pagerLabel = $rule('.admin-console .pager-label');
        self::assertMatchesRegularExpression('/font-family:\s*var\(--font-label\)/', $pagerLabel);
        self::assertMatchesRegularExpression('/font-size:\s*\.76rem/', $pagerLabel);
        self::assertMatchesRegularExpression('/letter-spacing:\s*\.06em/', $pagerLabel);
        self::assertDoesNotMatchRegularExpression('/font-variant-numeric:\s*tabular-nums/', $pagerLabel);

        $filterResultCount = $rule('.admin-console .filter-result-count');
        self::assertMatchesRegularExpression('/font-size:\s*\.74rem/', $filterResultCount);
        self::assertMatchesRegularExpression('/letter-spacing:\s*\.04em/', $filterResultCount);
        self::assertDoesNotMatchRegularExpression('/text-transform:\s*uppercase/', $filterResultCount);

        $checkFieldset = $rule('.admin-console .check-grid fieldset');
        self::assertMatchesRegularExpression('/padding:\s*12px 14px 13px/', $checkFieldset);
        self::assertMatchesRegularExpression('/border-radius:\s*var\(--radius-md\)/', $checkFieldset);
        $checkLabel = $rule('.admin-console .check-grid label');
        self::assertMatchesRegularExpression('/padding:\s*4px 0/', $checkLabel);
        self::assertMatchesRegularExpression('/font-size:\s*\.88rem/', $checkLabel);
        self::assertMatchesRegularExpression('/line-height:\s*1\.4/', $checkLabel);
        self::assertMatchesRegularExpression('/color:\s*var\(--text-body\)/', $checkLabel);
        self::assertMatchesRegularExpression('/cursor:\s*pointer/', $checkLabel);

        self::assertMatchesRegularExpression('/grid-template-columns:\s*repeat\(auto-fit, minmax\(330px,\s*1fr\)\)/', $rule('.admin-console .admin-split'));
        self::assertMatchesRegularExpression('/grid-template-columns:\s*330px 1fr/', $rule('.admin-console .admin-split--fixed'));

        foreach (['.scribe-panel', '.scribe-panel-head', '.brand-cols', '.brand-preview', '.field-grid'] as $preserved) {
            self::assertStringContainsString($preserved, $css, $preserved . ' must remain available.');
        }
        self::assertStringNotContainsString('--gold-050', $css);
    }

    public function test_account_console_shell_pins_the_adjudicated_desktop_geometry_and_heading(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        $rule = static function (string $selector) use ($css): string {
            self::assertSame(
                1,
                preg_match('/' . preg_quote($selector, '/') . '\s*\{(?<declarations>[^}]*)\}/s', $css, $match),
                $selector . ' is missing from the application stylesheet.',
            );

            return $match['declarations'];
        };

        $screen = $rule('.settings-screen');
        self::assertMatchesRegularExpression('/max-width:\s*1064px/', $screen);
        self::assertMatchesRegularExpression('/padding:\s*30px 28px 132px/', $screen);

        self::assertMatchesRegularExpression('/margin-bottom:\s*24px/', $rule('.settings-head'));
        $eyebrow = $rule('.settings-head .eyebrow');
        self::assertMatchesRegularExpression('/font-size:\s*\.68rem/', $eyebrow);
        self::assertMatchesRegularExpression('/letter-spacing:\s*\.18em/', $eyebrow);
        self::assertMatchesRegularExpression('/text-transform:\s*uppercase/', $eyebrow);
        self::assertMatchesRegularExpression('/color:\s*var\(--gold-ink\)/', $eyebrow);

        $heading = $rule('.settings-head h1');
        self::assertMatchesRegularExpression('/margin:\s*7px 0 0/', $heading);
        self::assertMatchesRegularExpression('/font-family:\s*var\(--font-display\)/', $heading);
        self::assertMatchesRegularExpression('/font-size:\s*2\.4rem/', $heading);
        self::assertMatchesRegularExpression('/font-weight:\s*500/', $heading);
        self::assertMatchesRegularExpression('/line-height:\s*1\.1/', $heading);
        self::assertMatchesRegularExpression('/letter-spacing:\s*-\.01em/', $heading);
        self::assertMatchesRegularExpression('/color:\s*var\(--text-strong\)/', $heading);

        $intro = $rule('.settings-head p');
        self::assertMatchesRegularExpression('/margin:\s*8px 0 0/', $intro);
        self::assertMatchesRegularExpression('/max-width:\s*62ch/', $intro);
        self::assertMatchesRegularExpression('/font-size:\s*1rem/', $intro);
        self::assertMatchesRegularExpression('/line-height:\s*1\.55/', $intro);
        self::assertMatchesRegularExpression('/color:\s*var\(--text-muted\)/', $intro);
        self::assertMatchesRegularExpression('/text-wrap:\s*pretty/', $intro);

        $layout = $rule('.settings');
        self::assertMatchesRegularExpression('/grid-template-columns:\s*232px minmax\(0,\s*1fr\)/', $layout);
        self::assertMatchesRegularExpression('/gap:\s*30px/', $layout);
    }

    public function test_account_console_rail_pins_group_icon_inactive_hover_and_active_treatments(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        $rule = static function (string $selector) use ($css): string {
            self::assertSame(
                1,
                preg_match('/' . preg_quote($selector, '/') . '\s*\{(?<declarations>[^}]*)\}/s', $css, $match),
                $selector . ' is missing from the application stylesheet.',
            );

            return $match['declarations'];
        };

        $rail = $rule('.settings > .settings-rail');
        self::assertMatchesRegularExpression('/position:\s*sticky/', $rail);
        self::assertMatchesRegularExpression('/top:\s*calc\(var\(--topbar-h\) \+ 22px\)/', $rail);
        self::assertMatchesRegularExpression('/width:\s*232px/', $rail);

        $firstTitle = $rule('.settings-rail-title');
        self::assertMatchesRegularExpression('/padding:\s*0 0 6px 12px/', $firstTitle);
        self::assertMatchesRegularExpression('/font-size:\s*\.62rem/', $firstTitle);
        self::assertMatchesRegularExpression('/letter-spacing:\s*\.18em/', $firstTitle);
        self::assertMatchesRegularExpression('/text-transform:\s*uppercase/', $firstTitle);
        self::assertMatchesRegularExpression('/color:\s*var\(--text-faint\)/', $firstTitle);
        self::assertMatchesRegularExpression(
            '/padding:\s*14px 0 6px 12px/',
            $rule('.settings-rail-group + .settings-rail-group .settings-rail-title'),
        );

        $link = $rule('.settings-rail-link');
        self::assertMatchesRegularExpression('/display:\s*flex/', $link);
        self::assertMatchesRegularExpression('/align-items:\s*center/', $link);
        self::assertMatchesRegularExpression('/gap:\s*10px/', $link);
        self::assertMatchesRegularExpression('/width:\s*100%/', $link);
        self::assertMatchesRegularExpression('/padding:\s*8px 12px/', $link);
        self::assertMatchesRegularExpression('/border-left:\s*2px solid transparent/', $link);
        self::assertMatchesRegularExpression('/font-family:\s*var\(--font-label\)/', $link);
        self::assertMatchesRegularExpression('/font-size:\s*\.86rem/', $link);
        self::assertMatchesRegularExpression('/letter-spacing:\s*\.02em/', $link);
        self::assertMatchesRegularExpression('/color:\s*var\(--text-muted\)/', $link);
        self::assertMatchesRegularExpression('/text-decoration:\s*none/', $link);

        $hover = $rule('.settings-rail-link:not(.is-active):hover, .settings-rail-link:not(.is-active):focus-visible');
        self::assertMatchesRegularExpression('/background:\s*var\(--surface-sunken\)/', $hover);
        self::assertMatchesRegularExpression('/color:\s*var\(--text-body\)/', $hover);
        self::assertMatchesRegularExpression('/text-decoration:\s*none/', $hover);

        $active = $rule('.settings-rail-link.is-active');
        self::assertMatchesRegularExpression('/border-left-color:\s*var\(--gold-500\)/', $active);
        self::assertMatchesRegularExpression('/background:\s*var\(--brand-subtle\)/', $active);
        self::assertMatchesRegularExpression('/color:\s*var\(--on-brand-subtle\)/', $active);
        self::assertMatchesRegularExpression('/border-radius:\s*0 var\(--radius-md\) var\(--radius-md\) 0/', $active);

        $icon = $rule('.settings-rail .icon');
        self::assertMatchesRegularExpression('/width:\s*15px/', $icon);
        self::assertMatchesRegularExpression('/height:\s*15px/', $icon);
        self::assertMatchesRegularExpression('/stroke-width:\s*1\.7/', $icon);
    }

    public function test_account_console_mobile_rail_is_static_wrapped_and_touch_safe(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        $marker = '@media (max-width: 719px) {';
        $start = strpos($css, $marker);
        self::assertNotFalse($start, 'The account-console mobile media block is missing.');
        $nextMedia = strpos($css, "\n@media", $start + strlen($marker));
        $mobile = substr($css, $start, $nextMedia === false ? null : $nextMedia - $start);

        self::assertMatchesRegularExpression('/\.settings\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/s', $mobile);
        self::assertMatchesRegularExpression('/\.settings > \.settings-rail\s*\{[^}]*position:\s*static/s', $mobile);
        self::assertMatchesRegularExpression('/\.settings-rail-group\s*\{[^}]*display:\s*flex[^}]*flex-wrap:\s*wrap/s', $mobile);
        self::assertMatchesRegularExpression('/\.settings-rail-link\s*\{[^}]*min-height:\s*44px/s', $mobile);
        self::assertMatchesRegularExpression('/\.settings-rail \.subnav-action\s*\{[^}]*min-height:\s*44px/s', $mobile);
    }

    public function test_application_quiet_thread_rows_reset_design_system_hover_motion(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        self::assertSame(
            1,
            preg_match('/\.thread-row:hover\s*\{(?<declarations>[^}]*)\}/', $css, $matches),
            'The application quiet-row hover rule is missing.',
        );
        self::assertMatchesRegularExpression('/\btransform\s*:\s*none\s*;?/', $matches['declarations']);
    }

    public function test_every_required_runtime_variable_has_a_definition(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/imladris.css')
            . "\n"
            . (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        preg_match_all('/(--[a-z0-9-]+)\s*:/i', $css, $definitions);
        $defined = array_fill_keys($definitions[1], true);

        preg_match_all('/var\((--[a-z0-9-]+)(\s*,[^)]*)?\)/i', $css, $uses, PREG_SET_ORDER);
        $missing = [];
        foreach ($uses as $use) {
            if (isset($defined[$use[1]]) || ($use[2] ?? '') !== '') {
                continue;
            }
            $missing[$use[1]] = true;
        }

        self::assertSame([], array_keys($missing));
    }

    /**
     * A status pair is only correct if both registers define it. A pair built from
     * numbered primitives (--gold-700 ink on --gold-100 ground) satisfies a contrast
     * ratio in light and dark alike while never flipping — it stays a light-register
     * chip sitting on a twilight page. A ratio assertion cannot see that; this can.
     */
    public function test_status_ledger_pairs_are_defined_in_both_colour_registers(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/imladris.css');

        self::assertSame(1, preg_match('/:root\s*\{(?<light>[^}]*)\}/s', $css, $lightBlock));
        self::assertSame(1, preg_match('/\[data-theme="dark"\]\s*\{(?<dark>[^}]*)\}/s', $css, $darkBlock));

        foreach (['done', 'review', 'pending', 'info', 'staff'] as $status) {
            foreach (['--surface-' . $status, '--on-' . $status] as $token) {
                $pattern = '/' . preg_quote($token, '/') . '\s*:/';
                self::assertMatchesRegularExpression(
                    $pattern,
                    $lightBlock['light'],
                    $token . ' is missing from the light register.',
                );
                self::assertMatchesRegularExpression(
                    $pattern,
                    $darkBlock['dark'],
                    $token . ' is missing from the dark register.',
                );
            }
        }
    }

    /**
     * The application owns two dark registers, not one: an explicit `[data-theme="dark"]`
     * block and a `prefers-color-scheme` block for `[data-theme="system"]` — and
     * `layout.php` defaults every account to `system`. `imladris.css` carries no
     * `prefers-color-scheme` block at all, so a semantic token the application overrides
     * for twilight has to be overridden in *both* application blocks or it silently keeps
     * resolving to the light register for the default theme. That is how the staff badge
     * came to render an unflipped light chip on a twilight page for most users after the
     * fix that was supposed to flip it. Asserting the two blocks declare the same token
     * set catches the next one at authoring time.
     */
    public function test_both_application_dark_registers_declare_the_same_tokens(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');

        self::assertSame(
            1,
            preg_match('/\[data-theme="dark"\]\s*\{(?<block>[^}]*)\}/s', $css, $explicit),
            'The explicit twilight token block is missing.',
        );
        self::assertSame(
            1,
            preg_match(
                '/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*\[data-theme="system"\]\s*\{(?<block>[^}]*)\}/s',
                $css,
                $system,
            ),
            'The system-theme twilight token block is missing.',
        );

        $tokens = static function (string $block): array {
            preg_match_all('/(--[a-z0-9-]+)\s*:/i', $block, $found);
            $names = array_unique($found[1]);
            sort($names);

            return $names;
        };

        self::assertSame(
            $tokens($explicit['block']),
            $tokens($system['block']),
            'The [data-theme="dark"] and [data-theme="system"] dark registers declare different tokens; '
            . 'a token missing from either one resolves to the light register for that audience.',
        );
    }

    public function test_staff_badge_uses_the_flipping_semantic_pair_exactly_once(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');

        self::assertSame(
            1,
            preg_match_all('/\.badge-staff\b/', $css),
            'The staff badge must be declared exactly once in the application stylesheet.',
        );
        self::assertMatchesRegularExpression('/\.badge-staff[^{]*\{[^}]*color:\s*var\(--on-staff\)/s', $css);
        self::assertMatchesRegularExpression('/\.badge-staff[^{]*\{[^}]*background:\s*var\(--surface-staff\)/s', $css);
    }

    public function test_asset_builder_filters_spacing_contract_from_a_crlf_checkout(): void
    {
        $root = $this->makeAssetBuilderFixture(useCrlfTextSources: true);

        try {
            $class = 'Tests\\Unit\\Core\\CrlfFixture\\ImladrisAssetBuilder';
            if (!class_exists($class, false)) {
                $source = (string) file_get_contents(self::ROOT . '/src/Support/ImladrisAssetBuilder.php');
                $source = (string) preg_replace('/^<\?php\s*/', '', $source, 1);
                $source = str_replace(
                    'namespace App\\Support;',
                    'namespace Tests\\Unit\\Core\\CrlfFixture;',
                    $source,
                );
                $source = str_replace(["\r\n", "\r"], "\n", $source);
                eval(str_replace("\n", "\r\n", $source));
            }

            try {
                /** @var object{build:callable():list<string>} $builder */
                $builder = new $class($root);
                $files = $builder->build();
                $error = null;
            } catch (\RuntimeException $exception) {
                $files = [];
                $error = $exception->getMessage();
            }

            self::assertNull($error, (string) $error);
            self::assertContains('public/assets/imladris.css', $files);
            self::assertStringNotContainsString(
                'animation-duration: 0.001ms',
                (string) file_get_contents($root . '/public/assets/imladris.css'),
            );
            self::assertSame(
                "line one\nline two\n",
                file_get_contents($root . '/public/assets/fonts/imladris/LICENSES/test.txt'),
            );
            self::assertSame(
                "\x00\x01\r\n\x02\xff",
                file_get_contents($root . '/public/assets/fonts/imladris/test.woff2'),
            );
        } finally {
            $this->removeFixtureDirectory($root);
        }
    }

    public function test_asset_check_normalizes_text_outputs_but_keeps_fonts_byte_exact(): void
    {
        $root = $this->makeAssetBuilderFixture();

        try {
            $builder = new ImladrisAssetBuilder($root);
            $files = $builder->build();

            foreach ($files as $relative) {
                if (!in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), ['css', 'json', 'txt'], true)) {
                    continue;
                }
                $path = $root . '/' . $relative;
                $content = str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path));
                file_put_contents($path, str_replace("\n", "\r\n", $content));
            }

            self::assertSame([], $builder->check());

            $font = $root . '/public/assets/fonts/imladris/test.woff2';
            file_put_contents($font, (string) file_get_contents($font) . "\x03");
            self::assertContains(
                'Generated file is stale: public/assets/fonts/imladris/test.woff2',
                $builder->check(),
            );
        } finally {
            $this->removeFixtureDirectory($root);
        }
    }

    public function test_design_tool_uploads_do_not_publish_browser_captures(): void
    {
        $uploadRoot = 'docs/design-system/imladris/uploads';
        $allowed = [
            $uploadRoot . '/359C3D62-2E24-4AEC-B0AB-BF886AFBC174.png',
            $uploadRoot . '/577F8AEF-DE44-4290-BBFE-C5F94AF207C2.png',
            $uploadRoot . '/5EF4ED15-812F-4EC1-B78A-0DA477B2AF75.png',
            $uploadRoot . '/621F9E9A-DC24-4EDE-A9D9-C7039CF04EA4.png',
            $uploadRoot . '/IMG_0209.png',
        ];
        $command = 'git -C ' . escapeshellarg(self::ROOT)
            . ' ls-files -- ' . escapeshellarg($uploadRoot);
        exec($command, $tracked, $status);
        self::assertSame(0, $status, 'Unable to inspect tracked design-tool uploads.');

        $unexpected = array_values(array_filter(
            array_diff($tracked, $allowed),
            static fn (string $relative): bool => is_file(self::ROOT . '/' . $relative),
        ));
        sort($unexpected);
        self::assertSame([], $unexpected, 'Only explicitly reviewed upload assets may be tracked.');

        $gitignore = (string) file_get_contents(self::ROOT . '/.gitignore');
        self::assertStringContainsString(
            '/docs/design-system/imladris/uploads/',
            $gitignore,
            'Design-tool uploads must be ignored by default; add reviewed assets explicitly with git add -f.',
        );
    }

    private function makeAssetBuilderFixture(bool $useCrlfTextSources = false): string
    {
        $root = sys_get_temp_dir() . '/rb-imladris-eol-' . bin2hex(random_bytes(6));
        $files = [
            'docs/design-system/imladris/manifest.json' => json_encode([
                'unresolved_gaps' => [],
                'inspected_commit' => 'fixture',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
            'docs/design-system/imladris/production-contract.json' => json_encode([
                'unresolved_gaps' => [],
                'reconciled_through_commit' => 'fixture',
                'composer' => ['spec' => 'fixture'],
                'surface_specs' => [],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
            'config/imladris-runtime-baseline.json' => json_encode([
                'reconciled_through_commit' => 'fixture',
                'composer_contract' => 'fixture',
                'application_surface' => [
                    'roots' => [],
                    'files' => [],
                    'extensions' => [],
                    'excluded' => [],
                    'sha256' => hash('sha256', "\n"),
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
            'docs/design-system/imladris/tokens/fonts.css' => "@font-face { src: url('../assets/fonts/test.woff2'); }\n",
            'docs/design-system/imladris/tokens/colors.css' => ":root { --ink-700: #222; }\n",
            'docs/design-system/imladris/tokens/typography.css' => ":root { --text-size-body: 1.0625rem; }\n",
            'docs/design-system/imladris/tokens/spacing.css' => <<<'CSS'
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.001ms !important;
        transition-duration: 0.001ms !important;
        scroll-behavior: auto !important;
    }
}
CSS,
            'docs/design-system/imladris/components.css' => ".thread-row { display: flex; }\n",
            'docs/design-system/imladris/assets/fonts/LICENSES/test.txt' => "line one\nline two\n",
            'docs/design-system/imladris/assets/fonts/test.woff2' => "\x00\x01\r\n\x02\xff",
        ];

        foreach ($files as $relative => $content) {
            $path = $root . '/' . $relative;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            if ($useCrlfTextSources
                && in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), ['css', 'json', 'txt'], true)) {
                $content = str_replace(["\r\n", "\r"], "\n", $content);
                $content = str_replace("\n", "\r\n", $content);
            }
            file_put_contents($path, $content);
        }

        return $root;
    }

    private function removeFixtureDirectory(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($root);
    }
}
