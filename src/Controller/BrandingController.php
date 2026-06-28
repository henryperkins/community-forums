<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\AttachmentRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Service\AttachmentService;

/**
 * Operator branding (P3-07): site name, light/dark logo, favicon, and primary/
 * accent colors with a live-served stylesheet, reset, and audit. Brand colors
 * are delivered as an external /brand.css (the strict CSP forbids inline styles):
 * the primary colour maps onto --accent (the whole UI re-themes safely) and the
 * accent colour onto --accent-2 (highlight/indicator surfaces).
 * Uploaded brand assets go through the same sniff/re-encode pipeline as post
 * media; everything falls back to the safe built-in chrome when unset or invalid.
 */
final class BrandingController extends Controller
{
    /** Public dynamic stylesheet: only emitted when colors are customized. */
    public function css(Request $request): Response
    {
        // When branding is disabled the UI falls back to the built-in chrome, so
        // emit an empty rule set rather than the stored brand colours.
        if (!$this->container->get(FeatureFlags::class)->enabled('branding')) {
            return new Response(':root{}', 200, [
                'Content-Type' => 'text/css; charset=UTF-8',
                'Cache-Control' => 'public, max-age=300',
            ]);
        }
        $settings = $this->container->get(SettingRepository::class);
        $primary = $settings->getString('brand_color_primary', '');
        $accent = $settings->getString('brand_color_accent', '');

        $css = ':root{';
        if (self::isHex($primary)) {
            $contrast = self::contrastToken($primary) ?? '#ffffff';
            $css .= '--accent:' . $primary . ';--brand-primary:' . $primary . ';--accent-contrast:' . $contrast . ';';
        }
        if (self::isHex($accent)) {
            $contrast = self::contrastToken($accent) ?? '#ffffff';
            $css .= '--accent-2:' . $accent . ';--brand-accent:' . $accent . ';--brand-accent-contrast:' . $contrast . ';';
        }
        $css .= '}';

        return (new Response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]));
    }

    public function form(Request $request): Response
    {
        $this->requireAdmin();
        $this->requireBrandingEnabled();
        $settings = $this->container->get(SettingRepository::class);
        return $this->view('admin/branding', [
            'site_name' => $settings->getString('site_name', (string) $this->config()->get('app.name', 'RetroBoards')),
            'color_primary' => $settings->getString('brand_color_primary', ''),
            'color_accent' => $settings->getString('brand_color_accent', ''),
            'logo_path' => $settings->getString('brand_logo_path', ''),
            'favicon_path' => $settings->getString('brand_favicon_path', ''),
            'theme_default' => $settings->getString('brand_theme_default', 'system'),
            'errors' => [],
        ]);
    }

    public function update(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $this->requireBrandingEnabled();
        $settings = $this->container->get(SettingRepository::class);

        if ($request->str('reset') === '1') {
            foreach (['brand_color_primary', 'brand_color_accent', 'brand_logo_path', 'brand_favicon_path', 'brand_theme_default'] as $key) {
                $settings->set($key, '');
            }
            $this->bustBrandCache($settings);
            $this->audit($admin->id(), 'reset');
            return $this->redirectWithFlash('/admin/branding', 'Branding was reset to the safe defaults.');
        }

        $errors = [];
        $name = trim($request->str('site_name'));
        if ($name === '' || mb_strlen($name) > 80) {
            $errors['site_name'] = 'Enter a site name (max 80 characters).';
        }
        $primary = trim($request->str('color_primary'));
        $accent = trim($request->str('color_accent'));
        if ($primary !== '' && !self::isHex($primary)) {
            $errors['color_primary'] = 'Use a 6-digit hex colour like #2f6fed.';
        }
        if ($accent !== '' && !self::isHex($accent)) {
            $errors['color_accent'] = 'Use a 6-digit hex colour like #7c3aed.';
        }
        if ($primary !== '' && self::isHex($primary) && self::contrastToken($primary) === null) {
            $errors['color_primary'] = 'Choose a primary colour that supports readable button text.';
        }
        if ($accent !== '' && self::isHex($accent) && self::contrastToken($accent) === null) {
            $errors['color_accent'] = 'Choose an accent colour with enough contrast for UI indicators.';
        }
        $themeDefault = $request->str('theme_default');
        if (!in_array($themeDefault, ['system', 'light', 'dark'], true)) {
            $themeDefault = 'system';
        }

        if ($errors !== []) {
            return $this->view('admin/branding', [
                'site_name' => $name,
                'color_primary' => $primary,
                'color_accent' => $accent,
                'logo_path' => $settings->getString('brand_logo_path', ''),
                'favicon_path' => $settings->getString('brand_favicon_path', ''),
                'theme_default' => $themeDefault,
                'errors' => $errors,
            ], 422);
        }

        $settings->set('site_name', $name);
        $settings->set('brand_color_primary', self::isHex($primary) ? strtolower($primary) : '');
        $settings->set('brand_color_accent', self::isHex($accent) ? strtolower($accent) : '');
        $settings->set('brand_theme_default', $themeDefault);

        $this->storeAsset($admin->id(), $request->file('logo'), 'brand_logo', 'brand_logo_path');
        $this->storeAsset($admin->id(), $request->file('favicon'), 'brand_favicon', 'brand_favicon_path');
        $this->bustBrandCache($settings);

        $this->audit($admin->id(), 'update');
        return $this->redirectWithFlash('/admin/branding', 'Branding updated.');
    }

    /** @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $file */
    private function storeAsset(int $adminId, ?array $file, string $purpose, string $settingKey): void
    {
        if ($file === null) {
            return;
        }
        try {
            $row = $this->container->get(AttachmentService::class)->storeUpload($adminId, $file, $purpose);
        } catch (\App\Core\ValidationException) {
            return; // keep the existing asset on a bad upload rather than 500
        }
        $this->container->get(AttachmentRepository::class)->finalizeBrandAsset((int) $row['id'], $adminId);
        $this->container->get(SettingRepository::class)->set($settingKey, '/media/' . (int) $row['id']);
    }

    private function audit(int $actorId, string $what): void
    {
        $this->container->get(ModerationLogRepository::class)->log([
            'actor_id' => $actorId,
            'action' => 'branding_' . $what,
            'target_type' => 'setting',
            'target_id' => 0,
        ]);
    }

    private function bustBrandCache(SettingRepository $settings): void
    {
        $settings->set('brand_version', bin2hex(random_bytes(8)));
    }

    private function requireBrandingEnabled(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('branding')) {
            throw new NotFoundException();
        }
    }

    private static function isHex(string $v): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1;
    }

    private static function contrastToken(string $hex): ?string
    {
        $white = self::contrastRatio($hex, '#ffffff');
        $dark = self::contrastRatio($hex, '#0f1218');
        if ($white >= 4.5 || $dark >= 4.5) {
            return $white >= $dark ? '#ffffff' : '#0f1218';
        }
        return null;
    }

    private static function contrastRatio(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $parts = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
        $linear = array_map(static function (float $v): float {
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $parts);
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
