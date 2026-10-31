<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\AttachmentRepository;
use App\Repository\BrandingRevisionRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\WriteGate;
use App\Service\AttachmentService;
use App\Service\BrandingConflictException;
use App\Service\BrandingRevisionService;
use App\Service\Packages\ThemeStateService;
use App\Support\BrandIdentity;
use App\Support\BrandingDocument;
use App\Support\BrandPalette;
use App\Support\UploadLimits;

/**
 * Operator branding (P3-07): site name, light/dark logo, favicon, and primary/
 * accent colors with a live-served stylesheet, reset, and audit. Brand colors
 * are delivered as an external /brand.css (the strict CSP forbids inline styles):
 * primary colours resolve action/selection roles; accent colours resolve
 * highlights/indicators. Status and presence families retain their meanings.
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
            // Member appearance thumbnails still picture the serving base: an
            // active package, else the built-in Classic palette.
            return new Response(':root{}' . BrandPalette::thumbnailCss(
                BrandPalette::presets($this->packageTokens($previewRequest))['classic'],
            ), 200, [
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
        $retro = $preset === 'retro' && $packageTokens === null;
        $modes = [];
        $serving = [];
        foreach ($presets[$retro ? 'retro' : 'classic'] as $mode => $base) {
            $resolved = BrandPalette::withColours($base, $primary, $accent);
            $serving[$mode] = $resolved;
            // Derive against the serving palette without republishing its
            // surfaces/type over the package stylesheet.
            $modes[$mode] = $retro ? $resolved : array_diff_assoc($resolved, $base);
        }
        // A recovery page asks for the scoped rules only: the sample and the
        // thumbnails, never the global overrides or custom CSS (ADR 0054).
        $sampleOnly = $request->query('scope') === 'sample';
        $css = $sampleOnly ? ':root{}' : BrandPalette::css($modes);
        $css .= BrandPalette::previewCss($presets, $primary, $accent);
        // Member appearance thumbnails show what each mode really resolves to.
        $css .= BrandPalette::thumbnailCss($serving);

        if (!$sampleOnly && $flags->enabled('custom_css')
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
            // Recorded as a revision like any other change, so a reset can be
            // undone from branding history (ADR 0054).
            $this->revisions()->commit($admin, 'reset', function () use ($settings): void {
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
            }, recorded: function (int $revision, array $changed) use ($admin): void {
                $this->audit($admin->id(), 'reset', ['revision' => $revision, 'changed_keys' => $changed]);
            });
            return $this->redirectWithFlash('/admin/branding', 'Branding reset. The community name, community text and package theme were kept.');
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
        [$identity, $identityErrors] = $this->submittedIdentity($request, $settings);
        $errors += $identityErrors;
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
        ['warnings' => $contrastWarnings, 'hash' => $contrastReview] = $this->contrastReview($primary, $accent, $themePreset);
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

        // The revision this form was opened at. An absent value is a form from
        // before versioning, which can only be as old as revision 0.
        $baseRevision = self::postedRevision($request, 'base_revision') ?? 0;
        $overwrite = self::postedRevision($request, 'overwrite_revision');
        $draft = $this->identityFormValues($identity) + [
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
            'base_revision' => $baseRevision,
        ] + $removals;
        if ($errors !== []) {
            return $this->view('admin/branding', $this->formData($settings, $draft + ['errors' => $errors]), 422);
        }

        $audit = [];
        try {
            $commit = $this->revisions()->commit($admin, 'publish', function () use (
                $settings, $name, $primary, $accent, $themeDefault, $themePreset, $identity,
                $customCssAvailable, $customCssEnabled, $customCss, $contrastWarnings, $admin, $request, $removals, &$audit,
            ): array {
                $changedText = [];
                foreach ($identity as $key => $value) {
                    if ($settings->getString($key, '') !== $value) {
                        $changedText[] = $key;
                    }
                    // '' is stored rather than deleting the row: it means "use the
                    // built-in default", exactly like a cleared colour.
                    $settings->set($key, $value);
                }
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
                $audit = ['removed_assets' => $removedAssets, 'changed_text' => $changedText];
                return $assetErrors;
            }, $baseRevision, $overwrite, recorded: function (int $revision, array $changed) use ($admin, &$audit): void {
                $this->audit($admin->id(), 'update', $audit + ['revision' => $revision, 'changed_keys' => $changed]);
            });
        } catch (BrandingConflictException $conflict) {
            // Nothing was written. Return the whole draft and name the newer
            // changes; publishing over them takes an explicit confirmation.
            return $this->view('admin/branding', $this->formData($settings, $draft + [
                'uploads_need_reselect' => $this->hasSubmittedAssets($request),
                'conflict' => $this->conflictSummary($conflict),
                'errors' => ['overwrite_revision' => 'Branding changed after you opened this page. Review the newer changes, then confirm replacing them to publish your version.'],
            ]), 409);
        }
        $assetErrors = (array) $commit['result'];
        if ($commit['changed'] === []) {
            // An unchanged save still writes an audit entry, as before.
            $this->audit($admin->id(), 'update', $audit + ['revision' => $commit['revision'], 'changed_keys' => []]);
        }
        if ($assetErrors !== []) {
            return $this->redirectWithFlash(
                '/admin/branding',
                'Branding updated, but ' . implode(' ', $assetErrors) . ' The previous asset was kept.',
            );
        }
        return $this->redirectWithFlash('/admin/branding', 'Saved. Branding appears on the next page load.');
    }

    /** Recovery (ADR 0054): return one group to its defaults as a recorded, restorable revision. */
    public function resetGroup(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(WriteGate::class)->assertCanWrite($admin);
        $this->requireBrandingEnabled();
        $group = (string) ($params['group'] ?? '');
        $definition = BrandingDocument::RESET_GROUPS[$group] ?? null;
        if ($definition === null) {
            throw new NotFoundException();
        }
        $settings = $this->container->get(SettingRepository::class);
        $defaults = BrandingDocument::defaults($this->defaultName());
        $commit = $this->revisions()->commit($admin, 'reset', function () use ($settings, $definition, $defaults): void {
            foreach ($definition['keys'] as $key) {
                $settings->set($key, $defaults[$key]);
            }
        }, recorded: function (int $revision, array $changed) use ($admin, $group): void {
            $this->audit($admin->id(), 'reset_group', ['group' => $group, 'revision' => $revision, 'changed_keys' => $changed]);
        });
        return $this->redirectWithFlash('/admin/branding', $commit['changed'] === []
            ? 'The ' . $definition['label'] . ' already use their defaults; nothing changed.'
            : 'Reset the ' . $definition['label'] . '. Everything else was kept. Branding history can restore the previous ' . $definition['label'] . '.');
    }

    /** Published branding revisions, newest first (ADR 0054). */
    public function history(Request $request): Response
    {
        $this->requireAdmin();
        $this->requireBrandingEnabled();
        $repository = $this->container->get(BrandingRevisionRepository::class);
        $perPage = 25;
        $total = $repository->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        return $this->view('admin/branding_history', [
            'revisions' => $repository->page($perPage, ($page - 1) * $perPage),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'current_revision' => $this->revisions()->currentRevision(),
        ]);
    }

    /** One revision compared with the published branding, ready to restore. */
    public function revision(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->requireBrandingEnabled();
        return $this->view('admin/branding_revision', $this->revisionData((int) ($params['id'] ?? 0)));
    }

    /**
     * Publish a past revision again (ADR 0054). Scope: every setting in the
     * branding document — community name and text, logos and favicon,
     * colours, appearance defaults and stored custom CSS. Package activation,
     * theme safe mode, feature flags and member preferences are untouched. The
     * restore is itself a new revision, so it can be undone the same way.
     */
    public function restore(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(WriteGate::class)->assertCanWrite($admin);
        $this->requireBrandingEnabled();
        $id = (int) ($params['id'] ?? 0);
        $data = $this->revisionData($id);
        $candidate = $data['candidate'];
        $warnings = $data['contrast_warnings'];
        $acknowledged = $request->str('contrast_ack') === '1'
            && hash_equals((string) $data['contrast_review'], $request->str('contrast_review'));
        if ($warnings !== [] && !$acknowledged) {
            return $this->view('admin/branding_revision', array_replace($data, [
                'errors' => ['contrast_ack' => 'Review the contrast warnings and acknowledge them before restoring.'],
            ]), 422);
        }
        $settings = $this->container->get(SettingRepository::class);
        $baseRevision = self::postedRevision($request, 'base_revision') ?? 0;
        $overwrite = self::postedRevision($request, 'overwrite_revision');
        try {
            $commit = $this->revisions()->commit($admin, 'restore', function () use ($settings, $candidate, $warnings, $admin): void {
                BrandingDocument::write($settings, $candidate);
                if ($warnings !== []) {
                    $this->audit($admin->id(), 'contrast_override', [
                        'color_primary' => (string) $candidate['brand_color_primary'],
                        'color_accent' => (string) $candidate['brand_color_accent'],
                        'theme_preset' => (string) $candidate['brand_theme_preset'],
                        'warnings' => $warnings,
                    ]);
                }
                $this->bustBrandCache($settings);
            }, $baseRevision, $overwrite, $id, function (int $revision, array $changed) use ($admin, $id, $data): void {
                $this->audit($admin->id(), 'restore', [
                    'revision' => $revision,
                    'restored_from' => $id,
                    'changed_keys' => $changed,
                    'missing_assets' => $data['missing'],
                ]);
            });
        } catch (BrandingConflictException $conflict) {
            // The comparison is rebuilt against the newer live branding, with a
            // fresh base revision: reviewing it again is the confirmation.
            return $this->view('admin/branding_revision', array_replace($this->revisionData($id), [
                'conflict' => $this->conflictSummary($conflict),
            ]), 409);
        }
        return $this->redirectWithFlash('/admin/branding', $commit['changed'] === []
            ? 'Revision ' . $id . ' already matches the published branding; nothing changed.'
            : 'Restored revision ' . $id . ' as revision ' . $commit['revision'] . '. The configuration it replaced remains in branding history.');
    }

    /** @return array<string,mixed> */
    private function revisionData(int $id): array
    {
        $repository = $this->container->get(BrandingRevisionRepository::class);
        $revision = $repository->find($id);
        $restorable = $this->revisions()->restorable($id);
        if ($revision === null || $restorable === null) {
            throw new NotFoundException();
        }
        $candidate = $restorable['document'];
        $published = $this->revisions()->published();
        $review = $this->contrastReview(
            (string) $candidate['brand_color_primary'],
            (string) $candidate['brand_color_accent'],
            (string) $candidate['brand_theme_preset'],
        );
        return [
            'revision' => $revision,
            'candidate' => $candidate,
            'published' => $published,
            'differences' => BrandingDocument::changedKeys($published, $candidate),
            'missing' => $restorable['missing'],
            'current_revision' => $this->revisions()->currentRevision(),
            'contrast_warnings' => $review['warnings'],
            'contrast_review' => $review['hash'],
            'conflict' => null,
            'errors' => [],
        ];
    }

    /**
     * Contrast review of a candidate against the globally active theme, never
     * an admin's private package preview: publishing changes public branding.
     * The hash binds an acknowledgement to the exact candidate and resolved
     * palette, so a changed candidate or intervening package activation needs
     * another review. It is a review identity, not an authorization token.
     *
     * @return array{warnings:list<string>,hash:string}
     */
    private function contrastReview(string $primary, string $accent, string $preset): array
    {
        $preset = in_array($preset, ['classic', 'retro'], true) ? $preset : 'classic';
        $palettes = BrandPalette::presets($this->packageTokens());
        $warnings = BrandPalette::warnings($palettes[$preset], $primary, $accent);
        return ['warnings' => $warnings, 'hash' => hash('sha256', json_encode([
            strtolower($primary), strtolower($accent), $preset, $palettes[$preset], $warnings,
        ], JSON_THROW_ON_ERROR))];
    }

    /** @return array{current:int,newer:list<array{id:int,when:string,actor:string,action:string,changed:list<string>}>} */
    private function conflictSummary(BrandingConflictException $conflict): array
    {
        $newer = [];
        foreach ($conflict->newer as $revision) {
            // A baseline only records what the first change replaced; it is not
            // a change anyone published.
            if ((string) $revision['action'] === 'baseline') {
                continue;
            }
            $newer[] = [
                'id' => (int) $revision['id'],
                'when' => (string) $revision['created_at'],
                'actor' => (string) ($revision['actor_username'] ?? ''),
                'action' => (string) $revision['action'],
                'changed' => array_values(array_map(
                    static fn (string $key): string => BrandingDocument::LABELS[$key] ?? $key,
                    $revision['changed_keys'],
                )),
            ];
        }
        return ['current' => $conflict->currentRevision, 'newer' => $newer];
    }

    private function revisions(): BrandingRevisionService
    {
        return $this->container->get(BrandingRevisionService::class);
    }

    private function defaultName(): string
    {
        return (string) $this->config()->get('app.name', 'RetroBoards');
    }

    /** A posted revision number, or null when absent or malformed. */
    private static function postedRevision(Request $request, string $field): ?int
    {
        $value = $request->post($field);
        return is_string($value) && preg_match('/^(0|[1-9][0-9]{0,18})$/D', $value) === 1 ? (int) $value : null;
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
        ] + $this->identityFormValues(BrandIdentity::storedValues(
            $settings->getMany(array_fill_keys(array_keys(BrandIdentity::FIELDS), '')),
        )) + [
            'identity_limits' => array_column(BrandIdentity::FIELDS, 'max', 'field'),
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
            // Versioning (ADR 0054): the revision this form was opened at, the
            // recovery groups, and any stale-editor conflict to explain.
            'base_revision' => $this->revisions()->currentRevision(),
            'reset_groups' => BrandingDocument::RESET_GROUPS,
            'conflict' => null,
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
        $data = array_replace($data, $overrides);
        // Reset stores empty strings. Resolve them before rendering so the
        // saved no-JS sample follows visitor defaults, not the admin's palette.
        if (!in_array($data['theme_default'], ['system', 'light', 'dark'], true)) {
            $data['theme_default'] = 'system';
        }
        if (!in_array($data['theme_preset'], ['classic', 'retro'], true)) {
            $data['theme_preset'] = 'classic';
        }
        return $data;
    }

    /** @return array<string,mixed> */
    private function submittedFormData(Request $request, SettingRepository $settings): array
    {
        $customCssAvailable = $this->container->get(FeatureFlags::class)->enabled('custom_css');
        $customCssEnabled = $customCssAvailable && $request->str('custom_css_enabled') === '1';

        return $this->identityFormValues($this->submittedIdentity($request, $settings)[0]) + [
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

    /**
     * Normalized community text from the form (BrandIdentity, ADR 0054). A
     * submitted empty field restores its default. An absent field keeps the
     * saved value, so a branding page opened before these fields existed (or
     * any other partial form) cannot erase community text by omission.
     *
     * @return array{0:array<string,string>,1:array<string,string>} values by setting key, errors by form field
     */
    private function submittedIdentity(Request $request, SettingRepository $settings): array
    {
        $values = [];
        $errors = [];
        $saved = BrandIdentity::storedValues($settings->getMany(array_fill_keys(array_keys(BrandIdentity::FIELDS), '')));
        foreach (BrandIdentity::FIELDS as $key => $field) {
            $raw = $request->post($field['field']);
            if ($raw === null) {
                $values[$key] = $saved[$key];
                continue;
            }
            $value = is_string($raw) ? BrandIdentity::normalize($raw, $field['multiline']) : '';
            if (!is_string($raw)) {
                $errors[$field['field']] = $field['label'] . ' must be text.';
            } elseif (($error = BrandIdentity::error($value, $key)) !== null) {
                $errors[$field['field']] = $error;
            }
            $values[$key] = $value;
        }
        return [$values, $errors];
    }

    /**
     * @param array<string,string> $identity values by setting key
     * @return array<string,string> values by form field
     */
    private function identityFormValues(array $identity): array
    {
        $values = [];
        foreach (BrandIdentity::FIELDS as $key => $field) {
            $values[$field['field']] = (string) ($identity[$key] ?? '');
        }
        return $values;
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
