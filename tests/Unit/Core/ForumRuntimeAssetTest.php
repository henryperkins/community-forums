<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class ForumRuntimeAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_generated_forum_assets_match_the_reviewed_sources(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(self::ROOT . '/bin/build-forum-css.php') . ' --check 2>&1';
        exec($command, $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
    }

    public function test_forum_theme_has_final_cascade_authority_without_font_downloads(): void
    {
        $base = (string) file_get_contents(self::ROOT . '/public/assets/forum-base.css');
        $application = (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        $theme = (string) file_get_contents(self::ROOT . '/public/assets/forum-theme.css');

        self::assertStringContainsString('@layer forum.components, application;', $base);
        self::assertStringContainsString('@layer forum.components {', $base);
        self::assertStringContainsString('@layer application {', $application);
        self::assertStringNotContainsString('@layer ', $theme);
        self::assertStringContainsString('--font-ui: system-ui,', $theme);
        self::assertStringContainsString('--font-body: var(--font-ui);', $theme);
        self::assertStringContainsString('--measure-prose: 65ch;', $theme);
        self::assertStringContainsString('prefers-color-scheme: dark', $theme);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $theme);
        self::assertStringNotContainsString('@font-face', $base . $theme);
        self::assertStringNotContainsString('@import', $base . $theme);
        self::assertStringNotContainsString('fonts/imladris/', $base . $theme);
    }

    /**
     * ADR 0057: the retained layers keep their selectors, but the Forum layer
     * sets the reading contract. The reset must inherit, so a Forum, package or
     * branding rule that sets capitals or tracking still reaches descendants.
     */
    public function test_forum_layer_carries_the_type_contract_tokens_and_inheriting_reset(): void
    {
        $theme = (string) file_get_contents(self::ROOT . '/public/assets/forum-theme.css');

        self::assertStringContainsString('--font-meta: var(--font-ui);', $theme);
        self::assertStringContainsString('--font-prose: sans-serif;', $theme);
        self::assertStringContainsString('--text-floor: .875rem;', $theme);
        self::assertStringContainsString(':where(body) { text-transform: none; letter-spacing: normal; }', $theme);
        self::assertStringContainsString(
            ':where(body *), :where(body *)::before, :where(body *)::after { text-transform: inherit; letter-spacing: inherit; }',
            $theme,
        );
        self::assertStringNotContainsString(':where(body, body *) { text-transform: none', $theme);
    }

    /**
     * Below the 0.875rem floor only initials and glyphs may be sized: every
     * other retained size is written max(var(--text-floor), <size>).
     */
    public function test_retained_layers_size_text_at_or_above_the_floor(): void
    {
        $small = [];
        foreach (['forum-base.css', 'app.css'] as $file) {
            foreach (self::styleRules((string) file_get_contents(self::ROOT . '/public/assets/' . $file)) as [$selector, $body]) {
                if (preg_match_all('/(?:^|;)\s*(font-size|font)\s*:\s*([^;]+)/', $body, $declarations, PREG_SET_ORDER) === 0) {
                    continue;
                }
                foreach ($declarations as [, $property, $value]) {
                    if (str_contains($value, 'var(--text-floor)')) {
                        continue;
                    }
                    if ($property === 'font') {
                        $value = preg_match('/(\d*\.?\d+(?:rem|px))\s*(?:\/|\s)/', $value, $size) === 1 ? $size[1] : '';
                    }
                    if (!self::belowFloor($value)) {
                        continue;
                    }
                    foreach (array_map('trim', explode(',', $selector)) as $part) {
                        if (preg_match('/monogram|dm-chip-mono|star-marker|avatar-wrap/', $part) !== 1) {
                            $small[] = $file . ': ' . $part . ' { ' . $property . ': ' . trim($value) . ' }';
                        }
                    }
                }
            }
        }

        self::assertSame([], $small, 'Text below the floor must use max(var(--text-floor), <size>).');
    }

    /** Exact identifiers keep the code face; metadata uses the interface face. */
    public function test_exact_identifier_fields_keep_the_code_face(): void
    {
        $application = (string) file_get_contents(self::ROOT . '/public/assets/app.css');

        foreach ([
            '.admin-overview-audit input[name="target_id"]',
            '.admin-console .admin-content .content-board-slug',
            '.admin-console .admin-content .content-tag-update .content-tag-slug',
            '.admin-console .admin-features select[name="mime"].input',
            '.admin-console .member-record-form input[name="confirm_username"]',
            '.features-link-previews .link-preview-hosts',
            '.theme-package-version',
        ] as $selector) {
            self::assertMatchesRegularExpression(
                '/' . preg_quote($selector, '/') . '\s*\{[^}]*font-family:\s*var\(--font-mono\)/',
                $application,
                $selector,
            );
        }
        self::assertMatchesRegularExpression('/\.content-board-count\s*\{[^}]*font-family:\s*var\(--font-meta\)/', $application);
    }

    public function test_every_required_runtime_variable_has_a_definition(): void
    {
        $css = '';
        foreach (['forum-base.css', 'app.css', 'forum-theme.css'] as $file) {
            $css .= (string) file_get_contents(self::ROOT . '/public/assets/' . $file) . "\n";
        }
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
     * A status pair is only correct if every register defines it. A pair built
     * from fixed primitives can pass a contrast ratio in light and dark alike
     * while never flipping; a ratio assertion cannot see that, this can.
     */
    public function test_status_pairs_are_defined_in_every_forum_colour_register(): void
    {
        $registers = self::forumRegisters();

        foreach (['done', 'review', 'pending', 'info', 'staff'] as $status) {
            foreach (['--surface-' . $status, '--on-' . $status] as $token) {
                foreach ($registers as $name => $block) {
                    self::assertMatchesRegularExpression('/' . preg_quote($token, '/') . '\s*:/', $block, $token . ' is missing from the ' . $name . ' register.');
                }
            }
        }
    }

    /**
     * Accounts default to the system theme, so a token overridden for an
     * explicit dark theme must also be overridden for a system dark theme.
     */
    public function test_forum_dark_registers_declare_the_same_tokens(): void
    {
        $registers = self::forumRegisters();

        self::assertSame(
            self::tokenNames($registers['explicit dark']),
            self::tokenNames($registers['system dark']),
            'The [data-theme="dark"] and system dark registers declare different tokens.',
        );
    }

    /**
     * The application owns two dark registers: an explicit `[data-theme="dark"]`
     * block and a `prefers-color-scheme` block for `[data-theme="system"]`, the
     * default. A token overridden in one but not the other silently keeps the
     * light value for that audience; the staff badge once shipped that way.
     */
    public function test_both_application_dark_registers_declare_the_same_tokens(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');

        self::assertSame(1, preg_match('/\[data-theme="dark"\]\s*\{(?<block>[^}]*)\}/s', $css, $explicit), 'The explicit dark token block is missing.');
        self::assertSame(
            1,
            preg_match('/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*\[data-theme="system"\]\s*\{(?<block>[^}]*)\}/s', $css, $system),
            'The system-theme dark token block is missing.',
        );
        self::assertSame(
            self::tokenNames($explicit['block']),
            self::tokenNames($system['block']),
            'The [data-theme="dark"] and [data-theme="system"] dark registers declare different tokens.',
        );
    }

    public function test_staff_badge_uses_the_flipping_semantic_pair_exactly_once(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');

        self::assertSame(1, preg_match_all('/\.badge-staff\b/', $css), 'The staff badge must be declared exactly once in the application stylesheet.');
        self::assertMatchesRegularExpression('/\.badge-staff[^{]*\{[^}]*color:\s*var\(--on-staff\)/s', $css);
        self::assertMatchesRegularExpression('/\.badge-staff[^{]*\{[^}]*background:\s*var\(--surface-staff\)/s', $css);
    }

    public function test_application_quiet_thread_rows_reset_component_hover_motion(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');

        self::assertSame(1, preg_match('/\.thread-row:hover\s*\{(?<declarations>[^}]*)\}/', $css, $matches), 'The application quiet-row hover rule is missing.');
        self::assertMatchesRegularExpression('/\btransform\s*:\s*none\s*;?/', $matches['declarations']);
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

    /**
     * ADR 0024 obligation 3: the console bar and area tier are owned by the
     * component layer. The application layer comes later in the cascade, so a
     * bare re-declaration there silently wins. Qualified selectors (:hover, a
     * state class, a descendant) and responsive @media rules are the sanctioned
     * complement; a bare redeclaration of a property the component sets is not.
     */
    public function test_application_never_overrides_a_component_owned_console_class(): void
    {
        $component = self::bareClassProperties((string) file_get_contents(self::ROOT . '/public/assets/forum-base.css'));
        $application = self::bareClassProperties((string) file_get_contents(self::ROOT . '/public/assets/app.css'));

        $owned = array_filter(array_keys($component), static fn (string $selector): bool => preg_match('/^\.admin-(bar|tier)/', $selector) === 1);
        self::assertNotEmpty($owned, 'forum-base.css should own the console bar and tier classes.');

        $collisions = [];
        foreach ($owned as $selector) {
            foreach (array_keys(array_intersect_key($application[$selector] ?? [], $component[$selector])) as $property) {
                $collisions[] = $selector . ' { ' . $property . ' }';
            }
        }

        self::assertSame([], $collisions, 'app.css re-declares a property the component layer already sets on a console class.');
    }

    /** @return array<string, string> light, explicit dark and system dark token blocks */
    private static function forumRegisters(): array
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/forum-theme.css');
        self::assertSame(1, preg_match('/^:root\s*\{(?<block>[^}]*)\}/m', $css, $light), 'The light register is missing.');
        self::assertSame(1, preg_match('/^\[data-theme="dark"\]\s*\{(?<block>[^}]*)\}/m', $css, $dark), 'The explicit dark register is missing.');
        self::assertSame(
            1,
            preg_match('/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*:root:where\(\[data-theme="system"\], :not\(\[data-theme\]\)\)\s*\{(?<block>[^}]*)\}/s', $css, $system),
            'The system dark register is missing.',
        );

        return ['light' => $light['block'], 'explicit dark' => $dark['block'], 'system dark' => $system['block']];
    }

    /** @return list<string> */
    private static function tokenNames(string $block): array
    {
        preg_match_all('/(--[a-z0-9-]+)\s*:/i', $block, $found);
        $names = array_values(array_unique($found[1]));
        sort($names);

        return $names;
    }

    private static function belowFloor(string $value): bool
    {
        preg_match_all('/(?<![\w.-])(\d*\.?\d+)(rem|px)\b/', $value, $sizes, PREG_SET_ORDER);
        foreach ($sizes as [, $number, $unit]) {
            if (($unit === 'rem' && (float) $number < 0.875) || ($unit === 'px' && (float) $number < 14)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Style rules as [selector, declarations], descending into every
     * conditional and grouping at-rule.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function styleRules(string $css): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $rules = [];
        $length = strlen($css);
        $i = 0;
        while ($i < $length) {
            $open = strpos($css, '{', $i);
            if ($open === false) {
                break;
            }
            $depth = 1;
            $k = $open + 1;
            while ($depth > 0 && $k < $length) {
                $depth += $css[$k] === '{' ? 1 : ($css[$k] === '}' ? -1 : 0);
                $k++;
            }
            $prelude = trim(substr($css, $i, $open - $i));
            $body = substr($css, $open + 1, $k - $open - 2);
            if (str_starts_with($prelude, '@')) {
                if (str_contains($body, '{')) {
                    array_push($rules, ...self::styleRules($body));
                }
            } elseif ($prelude !== '') {
                $rules[] = [(string) preg_replace('/\s+/', ' ', $prelude), $body];
            }
            $i = $k;
        }

        return $rules;
    }

    /** @return array<string, array<string, true>> bare single-class selector => declared properties */
    private static function bareClassProperties(string $css): array
    {
        $map = [];
        foreach (self::styleRules(self::withoutConditionalBlocks($css)) as [$selector, $body]) {
            $properties = [];
            foreach (explode(';', $body) as $declaration) {
                $name = strtolower(trim(explode(':', $declaration, 2)[0]));
                if ($name !== '') {
                    $properties[$name] = true;
                }
            }
            if ($properties === []) {
                continue;
            }
            foreach (explode(',', $selector) as $part) {
                $part = trim($part);
                if (preg_match('/^\.[A-Za-z0-9_-]+$/', $part) === 1) {
                    $map[$part] = ($map[$part] ?? []) + $properties;
                }
            }
        }

        return $map;
    }

    /** Drop @media/@supports/@container blocks; keep @layer contents in place. */
    private static function withoutConditionalBlocks(string $css): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $out = '';
        $length = strlen($css);
        $i = 0;
        while ($i < $length) {
            if ($css[$i] !== '@') {
                $out .= $css[$i];
                $i++;
                continue;
            }
            $cursor = $i;
            while ($cursor < $length && $css[$cursor] !== '{' && $css[$cursor] !== ';') {
                $cursor++;
            }
            if ($cursor >= $length) {
                break;
            }
            if ($css[$cursor] === ';') {
                $i = $cursor + 1;
                continue;
            }
            $depth = 0;
            $end = null;
            for ($k = $cursor; $k < $length; $k++) {
                if ($css[$k] === '{') {
                    $depth++;
                } elseif ($css[$k] === '}' && --$depth === 0) {
                    $end = $k;
                    break;
                }
            }
            if ($end === null) {
                break;
            }
            if (strtolower(substr(trim(substr($css, $i + 1, $cursor - $i - 1)), 0, 5)) === 'layer') {
                $out .= self::withoutConditionalBlocks(substr($css, $cursor + 1, $end - $cursor - 1));
            }
            $i = $end + 1;
        }

        return $out;
    }
}
