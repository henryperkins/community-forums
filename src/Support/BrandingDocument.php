<?php

declare(strict_types=1);

namespace App\Support;

use App\Repository\SettingRepository;

/**
 * The versioned branding document (ADR 0054, increment 2): every setting the
 * branding console publishes, in one normalized shape. Revisions store whole
 * documents, so a restore reproduces a configuration exactly; a key missing
 * from an older revision restores its default, because that configuration
 * never set it. Package activation, theme safe mode, feature flags and member
 * preferences are deliberately outside the document.
 */
final class BrandingDocument
{
    /** @var array<string,string> setting key => reset group */
    public const KEYS = [
        'site_name' => 'text',
        'brand_tagline' => 'text',
        'brand_description' => 'text',
        'brand_directory_heading' => 'text',
        'brand_directory_intro' => 'text',
        'brand_logo_path' => 'assets',
        'brand_logo_light_path' => 'assets',
        'brand_logo_dark_path' => 'assets',
        'brand_favicon_path' => 'assets',
        'brand_color_primary' => 'colours',
        'brand_color_accent' => 'colours',
        'brand_color_primary_light' => 'colours',
        'brand_color_accent_light' => 'colours',
        'brand_color_primary_dark' => 'colours',
        'brand_color_accent_dark' => 'colours',
        'brand_theme_default' => 'appearance',
        'brand_theme_preset' => 'appearance',
        'brand_custom_css_enabled' => 'custom_css',
        'brand_custom_css' => 'custom_css',
    ];

    /** @var array<string,string> */
    public const LABELS = [
        'site_name' => 'Community name',
        'brand_tagline' => 'Tagline',
        'brand_description' => 'Community description',
        'brand_directory_heading' => 'Welcome heading',
        'brand_directory_intro' => 'Welcome introduction',
        'brand_logo_path' => 'Logo',
        'brand_logo_light_path' => 'Light theme logo',
        'brand_logo_dark_path' => 'Dark theme logo',
        'brand_favicon_path' => 'Favicon',
        'brand_color_primary' => 'Primary colour',
        'brand_color_accent' => 'Accent colour',
        'brand_color_primary_light' => 'Light mode primary',
        'brand_color_accent_light' => 'Light mode accent',
        'brand_color_primary_dark' => 'Dark mode primary',
        'brand_color_accent_dark' => 'Dark mode accent',
        'brand_theme_default' => 'Visitor mode',
        'brand_theme_preset' => 'Theme preset',
        'brand_custom_css_enabled' => 'Custom CSS enabled',
        'brand_custom_css' => 'Custom CSS',
    ];

    /**
     * Groups an operator can reset on their own. Community text is not one:
     * each field restores its default by being cleared, and the community name
     * is required.
     *
     * @var array<string,array{label:string,keys:list<string>,explain:string}>
     */
    public const RESET_GROUPS = [
        'colours' => [
            'label' => 'colours',
            'keys' => ['brand_color_primary', 'brand_color_accent', 'brand_color_primary_light', 'brand_color_accent_light', 'brand_color_primary_dark', 'brand_color_accent_dark'],
            'explain' => 'Clears the shared and per-mode primary and accent overrides. Every mode returns to the active package or theme preset colours.',
        ],
        'assets' => [
            'label' => 'logos and favicon',
            'keys' => ['brand_logo_path', 'brand_logo_light_path', 'brand_logo_dark_path', 'brand_favicon_path'],
            'explain' => 'Disconnects every logo variant and the favicon. Sign-in screens, the header and the administration bar return to the house mark; the image files are kept for restoring.',
        ],
        'appearance' => [
            'label' => 'appearance defaults',
            'keys' => ['brand_theme_default', 'brand_theme_preset'],
            'explain' => 'Returns signed-out visitors to System mode and the theme preset to Classic. Members keep their own appearance settings.',
        ],
    ];

    /** @return array<string,mixed> the built-in document */
    public static function defaults(string $siteName): array
    {
        $document = array_fill_keys(array_keys(self::KEYS), '');
        $document['site_name'] = $siteName;
        $document['brand_theme_default'] = 'system';
        $document['brand_theme_preset'] = 'classic';
        $document['brand_custom_css_enabled'] = false;
        return $document;
    }

    /**
     * Coerce any stored or historical document into the current shape. Unknown
     * keys are dropped; missing or mistyped keys take their default.
     *
     * @param array<mixed> $document
     * @return array<string,mixed>
     */
    public static function normalize(array $document, string $defaultName): array
    {
        $normalized = self::defaults($defaultName);
        foreach (self::KEYS as $key => $group) {
            if (!array_key_exists($key, $document)) {
                continue;
            }
            $value = $document[$key];
            if ($key === 'brand_custom_css_enabled') {
                $normalized[$key] = $value === true;
            } elseif (is_string($value)) {
                $normalized[$key] = $value;
            }
        }
        if (!in_array($normalized['brand_theme_default'], ['system', 'light', 'dark'], true)) {
            $normalized['brand_theme_default'] = 'system';
        }
        if (!in_array($normalized['brand_theme_preset'], ['classic', 'retro'], true)) {
            $normalized['brand_theme_preset'] = 'classic';
        }
        if (trim((string) $normalized['site_name']) === '') {
            $normalized['site_name'] = $defaultName;
        }
        return $normalized;
    }

    /** @return array<string,mixed> the published document */
    public static function read(SettingRepository $settings, string $defaultName): array
    {
        return self::normalize($settings->getMany(self::defaults($defaultName)), $defaultName);
    }

    /** @param array<string,mixed> $document */
    public static function write(SettingRepository $settings, array $document): void
    {
        foreach (array_keys(self::KEYS) as $key) {
            $settings->set($key, $document[$key]);
        }
    }

    /**
     * @param array<string,mixed> $from
     * @param array<string,mixed> $to
     * @return list<string> keys whose values differ, in document order
     */
    public static function changedKeys(array $from, array $to): array
    {
        $changed = [];
        foreach (array_keys(self::KEYS) as $key) {
            if (($from[$key] ?? null) !== ($to[$key] ?? null)) {
                $changed[] = $key;
            }
        }
        return $changed;
    }

    /**
     * Same-origin media paths a document references, keyed by setting.
     *
     * @param array<string,mixed> $document
     * @return array<string,int> setting key => attachment id
     */
    public static function mediaIds(array $document): array
    {
        $ids = [];
        foreach (self::RESET_GROUPS['assets']['keys'] as $key) {
            if (preg_match('#^/media/([0-9]+)$#D', (string) ($document[$key] ?? ''), $match) === 1) {
                $ids[$key] = (int) $match[1];
            }
        }
        return $ids;
    }

    /**
     * A short human summary of one key's value for history and restore review.
     *
     * @param array<string,mixed> $document
     */
    public static function describe(array $document, string $key): string
    {
        $value = $document[$key] ?? '';
        if ($key === 'brand_custom_css_enabled') {
            return $value === true ? 'On' : 'Off';
        }
        $value = (string) $value;
        if ($value === '') {
            return match (self::KEYS[$key] ?? '') {
                'assets' => 'None',
                'colours' => 'Theme default',
                default => 'Default',
            };
        }
        return match ($key) {
            'brand_theme_default', 'brand_theme_preset' => ucfirst($value),
            'brand_custom_css' => mb_strlen($value) . ' characters',
            default => mb_strimwidth(str_replace("\n", ' ↵ ', $value), 0, 120, '…'),
        };
    }
}
