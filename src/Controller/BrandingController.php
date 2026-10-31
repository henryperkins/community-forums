<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\AttachmentRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\WriteGate;
use App\Service\AttachmentService;
use App\Service\Packages\ThemeStateService;
use App\Support\BrandPalette;
use App\Support\UploadLimits;

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
    /** @var array<string,array{setting:string,purpose:string,label:string}> */
    private const ASSETS = [
        'logo' => ['setting' => 'brand_logo_path', 'purpose' => 'brand_logo', 'label' => 'logo'],
        // Variants share the supported attachment purpose; their setting paths
        // select when each image is displayed.
        'logo_light' => ['setting' => 'brand_logo_light_path', 'purpose' => 'brand_logo', 'label' => 'light theme logo'],
        'logo_dark' => ['setting' => 'brand_logo_dark_path', 'purpose' => 'brand_logo', 'label' => 'dark theme logo'],
        'favicon' => ['setting' => 'brand_favicon_path', 'purpose' => 'brand_favicon', 'label' => 'favicon'],
    ];

    /** Public dynamic stylesheet: emitted only for enabled, validated brand overrides. */
    public function css(Request $request): Response
    {
        $previewRequest = $request->query('preview') === '1';
        $cacheControl = $previewRequest ? 'private, no-store' : 'public, max-age=300';
        // When branding is disabled the UI falls back to the built-in chrome, so
        // emit an empty rule set rather than the stored brand colours.
        $flags = $this->container->get(FeatureFlags::class);
        if (!$flags->enabled('branding')) {
            return new Response(':root{}', 200, [
                'Content-Type' => 'text/css; charset=UTF-8',
                'Cache-Control' => $cacheControl,
            ]);
        }
        $settings = $this->container->get(SettingRepository::class);
        $primary = $settings->getString('brand_color_primary', '');
        $accent = $settings->getString('brand_color_accent', '');
        $preset = $settings->getString('brand_theme_preset', 'classic');

        $packageTokens = $this->packageTokens($previewRequest);
        $presets = BrandPalette::presets($packageTokens);
        $modes = $preset === 'retro' && $packageTokens === null
            ? $presets['retro']
            : ['light' => [], 'dark' => []];
        foreach ($modes as &$palette) {
            $palette = BrandPalette::withColours($palette, $primary, $accent);
        }
        unset($palette);
        $css = BrandPalette::css($modes);
        $css .= BrandPalette::previewCss($presets, $primary, $accent);

        if ($flags->enabled('custom_css')
            && $settings->get('brand_custom_css_enabled', false) === true
            && $settings->getString('brand_custom_css', '') !== ''
        ) {
            $css .= "\n" . $settings->getString('brand_custom_css', '');
        }

        return (new Response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => $cacheControl,
        ]));
    }

    public function form(Request $request): Response
    {
        $this->requireAdmin();
        $this->requireBrandingEnabled();
        $settings = $this->container->get(SettingRepository::class);
        return $this->view('admin/branding', $this->formData($settings, [
            'site_name' => $settings->getString('site_name', (string) $this->config()->get('app.name', 'RetroBoards')),
        ]));
    }

    public function update(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(WriteGate::class)->assertCanWrite($admin);
        $this->requireBrandingEnabled();
        $settings = $this->container->get(SettingRepository::class);

        if ($request->str('reset') === '1') {
            // Destructive posture parity with structure deletes: reset clears
            // every stored brand asset and colour, so it requires the typed
            // confirmation the form asks for (enforced server-side).
            if (trim($request->str('reset_confirm')) !== 'RESET') {
                return $this->view('admin/branding', $this->formData($settings, array_replace(
                    $this->submittedFormData($request, $settings),
                    [
                        'errors' => ['reset_confirm' => 'Type RESET to confirm restoring the default branding.'],
                    ],
                )), 422);
            }
            foreach ([
                'brand_color_primary',
                'brand_color_accent',
                'brand_logo_path',
                'brand_logo_light_path',
                'brand_logo_dark_path',
                'brand_favicon_path',
                'brand_theme_default',
                'brand_theme_preset',
                'brand_custom_css_enabled',
                'brand_custom_css',
            ] as $key) {
                $settings->set($key, '');
            }
            $this->bustBrandCache($settings);
            $this->audit($admin->id(), 'reset');
            return $this->redirectWithFlash('/admin/branding', 'Branding reset. The community name and package theme were kept.');
        }

        $errors = [];
        $removals = $this->assetRemovalData($request);
        foreach (self::ASSETS as $field => $asset) {
            $uploadError = $request->fileError($field);
            if ($removals['remove_' . $field] && $uploadError !== null && $uploadError !== UPLOAD_ERR_NO_FILE) {
                $errors['remove_' . $field] = 'Choose either removing the ' . $asset['label'] . ' or uploading a replacement, not both.';
            }
            // file() intentionally returns null for PHP intake failures. Inspect
            // the error first so a rejected selection cannot masquerade as an
            // absent file or partially save another asset/removal in this form.
            $uploadMessage = match ($uploadError) {
                null, UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE => null,
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The ' . $asset['label'] . ' exceeds the server upload limit. Choose a smaller image and select it again.',
                UPLOAD_ERR_PARTIAL => 'The ' . $asset['label'] . ' upload was interrupted. Select the file again and retry.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The ' . $asset['label'] . ' could not be stored. Uploads are temporarily unavailable; retry later or leave this file unselected.',
                default => 'The ' . $asset['label'] . ' upload could not be accepted. Select the file again.',
            };
            if ($uploadMessage !== null) {
                $errors[$field] = $uploadMessage;
            }
        }
        $name = trim($request->str('site_name'));
        if ($name === '' || mb_strlen($name) > 80) {
            $errors['site_name'] = 'Site name must be 1–80 characters.';
        }
        $primary = trim($request->str('color_primary'));
        $accent = trim($request->str('color_accent'));
        if ($primary !== '' && !self::isHex($primary)) {
            $errors['color_primary'] = 'Primary colour must be a hex value, e.g. #2e4a3a.';
        }
        if ($accent !== '' && !self::isHex($accent)) {
            $errors['color_accent'] = 'Accent colour must be a hex value, e.g. #c29a44.';
        }
        $themeDefault = $request->str('theme_default');
        if (!in_array($themeDefault, ['system', 'light', 'dark'], true)) {
            $themeDefault = 'system';
        }
        $themePreset = $request->str('theme_preset', 'classic');
        if (!in_array($themePreset, ['classic', 'retro'], true)) {
            $themePreset = 'classic';
        }
        // Saving changes public branding even when this operator has a session
        // theme preview: measure the globally active theme, not that preview.
        $palettes = BrandPalette::presets($this->packageTokens());
        $contrastWarnings = BrandPalette::warnings($palettes[$themePreset], $primary, $accent);
        // Bind acknowledgement to the exact reviewed candidate and resolved
        // palette. This also rejects stale no-JS edits or an intervening package
        // activation. The hash is a review identity, not an authorization token:
        // requireAdmin + CSRF enforce who may deliberately override a warning.
        $contrastReview = hash('sha256', json_encode([
            strtolower($primary), strtolower($accent), $themePreset,
            $palettes[$themePreset], $contrastWarnings,
        ], JSON_THROW_ON_ERROR));
        $contrastAck = $request->str('contrast_ack') === '1'
            && hash_equals($contrastReview, $request->str('contrast_review'));
        if ($contrastWarnings !== [] && !$contrastAck) {
            $errors['contrast_ack'] = 'Review the contrast warnings and acknowledge them before saving.';
        }

        $flags = $this->container->get(FeatureFlags::class);
        $customCssAvailable = $flags->enabled('custom_css');
        $customCssEnabled = $customCssAvailable && $request->str('custom_css_enabled') === '1';
        $customCssAck = $customCssEnabled && $request->str('custom_css_ack') === '1';
        // A CSS-only disclosure hides the textarea but does not remove it from
        // the request. Match the design's removed-node semantics: while the
        // checkbox is off, ignore that posted value and preserve the saved CSS.
        $customCss = $customCssEnabled
            ? trim((string) $request->post('custom_css', ''))
            : $settings->getString('brand_custom_css', '');
        if ($customCssEnabled) {
            if (!$customCssAck) {
                $errors['custom_css'] = 'Acknowledge that custom CSS applies site-wide before saving it.';
            } elseif (($customError = self::customCssError($customCss)) !== null) {
                $errors['custom_css'] = $customError;
            }
        }

        if ($errors !== []) {
            return $this->view('admin/branding', $this->formData($settings, [
                'site_name' => $name,
                'color_primary' => $primary,
                'color_accent' => $accent,
                'theme_default' => $themeDefault,
                'theme_preset' => $themePreset,
                'custom_css_enabled' => $customCssEnabled,
                'custom_css' => $customCss,
                'custom_css_ack' => $customCssAck,
                'contrast_warnings' => $contrastWarnings,
                'contrast_ack' => $contrastAck,
                'contrast_review' => $contrastReview,
                'uploads_need_reselect' => $this->hasSubmittedAssets($request),
                'errors' => $errors,
            ] + $removals), 422);
        }

        $assetErrors = $this->container->get(Database::class)->transaction(function () use (
            $settings, $name, $primary, $accent, $themeDefault, $themePreset,
            $customCssAvailable, $customCssEnabled, $customCss, $contrastWarnings, $admin, $request, $removals,
        ): array {
            $settings->set('site_name', $name);
            $settings->set('brand_color_primary', self::isHex($primary) ? strtolower($primary) : '');
            $settings->set('brand_color_accent', self::isHex($accent) ? strtolower($accent) : '');
            $settings->set('brand_theme_default', $themeDefault);
            $settings->set('brand_theme_preset', $themePreset);

            if ($customCssAvailable) {
                $settings->set('brand_custom_css_enabled', $customCssEnabled);
                if ($customCssEnabled) {
                    $settings->set('brand_custom_css', $customCss);
                }
            }
            // An override and its audit record commit together. Recalculate the
            // warnings for the posted colours rather than trusting a client list.
            if ($contrastWarnings !== []) {
                $this->audit($admin->id(), 'contrast_override', [
                    'color_primary' => strtolower($primary),
                    'color_accent' => strtolower($accent),
                    'theme_preset' => $themePreset,
                    'warnings' => $contrastWarnings,
                ]);
            }
            // Rejected uploads retain the old asset and produce a visible
            // partial-success notice rather than hiding behind a success flash.
            $assetErrors = [];
            $removedAssets = [];
            foreach (self::ASSETS as $field => $asset) {
                if ($removals['remove_' . $field]) {
                    if ($settings->getString($asset['setting'], '') !== '') {
                        // Match Reset semantics: disconnect the saved mark but
                        // retain its stored media; unrelated branding stays intact.
                        $settings->set($asset['setting'], '');
                        $removedAssets[] = $field;
                    }
                } else {
                    $this->storeAsset($admin->id(), $request->file($field), $asset['purpose'], $asset['setting'], ucfirst($asset['label']), $assetErrors);
                }
            }
            $this->bustBrandCache($settings);
            $this->audit($admin->id(), 'update', ['removed_assets' => $removedAssets]);
            return $assetErrors;
        });
        if ($assetErrors !== []) {
            return $this->redirectWithFlash(
                '/admin/branding',
                'Branding updated, but ' . implode(' ', $assetErrors) . ' The previous asset was kept.',
            );
        }
        return $this->redirectWithFlash('/admin/branding', 'Saved. The chrome is live for everyone.');
    }

    /** @param array<string,mixed> $overrides */
    private function formData(SettingRepository $settings, array $overrides = []): array
    {
        $packageTokens = $this->packageTokens(true);
        $maxBytes = (int) $this->config()->get('uploads.max_bytes', 5242880);
        $phpFileBytes = UploadLimits::phpBytes(ini_get('upload_max_filesize'));
        $phpPostBytes = UploadLimits::phpBytes(ini_get('post_max_size'));
        $maxBytes = min($maxBytes, $phpFileBytes ?? $maxBytes, $phpPostBytes ?? $maxBytes);
        $mimeNames = array_intersect_key([
            'image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/gif' => 'GIF', 'image/webp' => 'WebP',
        ], array_flip((array) $this->config()->get('uploads.allowed_mime', [])));
        $data = [
            'site_name' => $settings->getString('site_name', (string) $this->config()->get('app.name', 'RetroBoards')),
            'color_primary' => $settings->getString('brand_color_primary', ''),
            'color_accent' => $settings->getString('brand_color_accent', ''),
            'logo_path' => $settings->getString('brand_logo_path', ''),
            'logo_light_path' => $settings->getString('brand_logo_light_path', ''),
            'logo_dark_path' => $settings->getString('brand_logo_dark_path', ''),
            'favicon_path' => $settings->getString('brand_favicon_path', ''),
            'remove_logo' => false,
            'remove_logo_light' => false,
            'remove_logo_dark' => false,
            'remove_favicon' => false,
            'theme_default' => $settings->getString('brand_theme_default', 'system'),
            'theme_preset' => $settings->getString('brand_theme_preset', 'classic'),
            'custom_css_available' => $this->container->get(FeatureFlags::class)->enabled('custom_css'),
            'custom_css_enabled' => $settings->get('brand_custom_css_enabled', false) === true,
            'custom_css' => $settings->getString('brand_custom_css', ''),
            'custom_css_ack' => false,
            'brand_preview_palettes' => BrandPalette::presets($packageTokens),
            'brand_package_active' => $packageTokens !== null,
            'contrast_warnings' => [],
            'contrast_ack' => false,
            'contrast_review' => '',
            'reset_confirm' => '',
            'uploads_need_reselect' => false,
            'upload_accept' => implode(',', array_keys($mimeNames)),
            'upload_formats' => implode(', ', $mimeNames),
            'upload_max_bytes' => $maxBytes,
            'upload_max_label' => UploadLimits::label($maxBytes),
            'upload_request_label' => $phpPostBytes !== null ? UploadLimits::label($phpPostBytes) : null,
            'upload_max_width' => (int) $this->config()->get('uploads.max_width', 4096),
            'upload_max_height' => (int) $this->config()->get('uploads.max_height', 4096),
            'errors' => [],
        ];
        return array_replace($data, $overrides);
    }

    /** @return array<string,mixed> */
    private function submittedFormData(Request $request, SettingRepository $settings): array
    {
        $customCssAvailable = $this->container->get(FeatureFlags::class)->enabled('custom_css');
        $customCssEnabled = $customCssAvailable && $request->str('custom_css_enabled') === '1';

        return [
            'site_name' => $request->str(
                'site_name',
                $settings->getString('site_name', (string) $this->config()->get('app.name', 'RetroBoards')),
            ),
            'color_primary' => $request->str('color_primary', $settings->getString('brand_color_primary', '')),
            'color_accent' => $request->str('color_accent', $settings->getString('brand_color_accent', '')),
            'theme_default' => $request->str('theme_default', $settings->getString('brand_theme_default', 'system')),
            'theme_preset' => $request->str('theme_preset', $settings->getString('brand_theme_preset', 'classic')),
            'custom_css_enabled' => $customCssEnabled,
            'custom_css' => $customCssEnabled
                ? trim((string) $request->post('custom_css', ''))
                : $settings->getString('brand_custom_css', ''),
            'custom_css_ack' => $customCssEnabled && $request->str('custom_css_ack') === '1',
            'reset_confirm' => $request->str('reset_confirm'),
            'uploads_need_reselect' => $this->hasSubmittedAssets($request),
        ] + $this->assetRemovalData($request);
    }

    private function hasSubmittedAssets(Request $request): bool
    {
        foreach (array_keys(self::ASSETS) as $field) {
            if (($error = $request->fileError($field)) !== null && $error !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,bool> */
    private function assetRemovalData(Request $request): array
    {
        $removals = [];
        foreach (self::ASSETS as $field => $asset) {
            $removals['remove_' . $field] = $request->str('remove_' . $field) === '1';
        }
        return $removals;
    }

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $file
     * @param array<int,string> $assetErrors collects a per-asset failure sentence
     */
    private function storeAsset(int $adminId, ?array $file, string $purpose, string $settingKey, string $label, array &$assetErrors): void
    {
        if ($file === null) {
            return;
        }
        try {
            $row = $this->container->get(AttachmentService::class)->storeUpload($adminId, $file, $purpose);
        } catch (\App\Core\ValidationException $e) {
            // Keep the existing asset on a bad upload rather than 500 — but
            // report it (a swallowed rejection under a success flash reads
            // as success).
            $assetErrors[] = $label . ' upload was rejected: ' . $e->first();
            return;
        }
        $this->container->get(AttachmentRepository::class)->finalizeBrandAsset((int) $row['id'], $adminId);
        $this->container->get(SettingRepository::class)->set($settingKey, '/media/' . (int) $row['id']);
    }

    private function audit(int $actorId, string $what, ?array $details = null): void
    {
        $this->container->get(ModerationLogRepository::class)->log([
            'actor_id' => $actorId,
            'action' => 'branding_' . $what,
            'target_type' => 'setting',
            'target_id' => 0,
            'after' => $details,
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

    /** @return array<string,mixed>|null */
    private function packageTokens(bool $includePreview = false): ?array
    {
        try {
            if (!$this->container->get(FeatureFlags::class)->enabled('package_themes')) {
                return null;
            }
            $themes = $this->container->get(ThemeStateService::class);
            $build = null;
            if ($includePreview && $this->currentUser()?->isAdmin()) {
                $previewId = $this->session()->get('theme_preview_build');
                $build = $themes->previewBuildFor(is_int($previewId) ? $previewId : null);
            }
            $build ??= $themes->activeBuild();
            return $build === null ? null : json_decode((string) $build['tokens_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function isHex(string $v): bool
    {
        return BrandPalette::isHex($v);
    }

    private static function customCssError(string $css): ?string
    {
        if ($css === '') {
            return null;
        }
        if (strlen($css) > 12000) {
            return 'Custom CSS must be 12 KB or less.';
        }
        if (preg_match('/@import\b/i', $css) === 1) {
            return 'Custom CSS cannot import external stylesheets.';
        }
        if (preg_match('/javascript\s*:/i', $css) === 1 || preg_match('/expression\s*\(/i', $css) === 1) {
            return 'Custom CSS cannot include script-like expressions.';
        }
        if (preg_match('/url\s*\(\s*[\'"]?\s*(?:https?:|\/\/|data:)/i', $css) === 1) {
            return 'Custom CSS cannot load remote or data URLs.';
        }
        if (preg_match('/#[A-Za-z0-9_-]*(delete|destroy|purge|reset)[A-Za-z0-9_-]*/i', $css) === 1) {
            return 'Custom CSS cannot target destructive admin controls by ID.';
        }
        return null;
    }
}
