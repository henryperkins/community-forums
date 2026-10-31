<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\AttachmentRepository;
use App\Repository\BrandingDraftRepository;
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
use App\Support\BrandStyle;
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

    /** @var array<string,string> form field => setting key (ADR 0054 increment 4) */
    private const STYLE_FIELDS = [
        'sidebar_style' => 'brand_sidebar_style',
        'type_pairing' => 'brand_type_pairing',
        'corners' => 'brand_corners',
        'density_default' => 'brand_density_default',
    ];

    /** @var array<string,array{0:string,1:string}> form field => [setting key, label] */
    private const MODE_COLOURS = [
        'color_primary_light' => ['brand_color_primary_light', 'Light mode primary'],
        'color_accent_light' => ['brand_color_accent_light', 'Light mode accent'],
        'color_primary_dark' => ['brand_color_primary_dark', 'Dark mode primary'],
        'color_accent_dark' => ['brand_color_accent_dark', 'Dark mode accent'],
    ];

    /** Public dynamic stylesheet: emitted only for enabled, validated brand overrides. */
    public function css(Request $request): Response
    {
        $previewRequest = $request->query('preview') === '1';
        // A draft variant is private whoever asks: only its previewing owner
        // gets the draft, everyone else the published palette, never cached.
        $draftRequest = $request->query('draft') === '1';
        $cacheControl = $previewRequest || $draftRequest ? 'private, no-store' : 'public, max-age=300';
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
        $document = ($draftRequest ? $this->previewedDraft() : null) ?? $this->revisions()->published();
        // Each mode's colours: its own override, else the shared one (ADR 0054).
        $colours = BrandPalette::modeColours($document);
        $preset = (string) $document['brand_theme_preset'];

        $packageTokens = $this->packageTokens($previewRequest);
        $presets = BrandPalette::presets($packageTokens);
        // A non-Classic style preset publishes its whole palette while no
        // package is served; Classic and packages publish only what differs.
        $preset = $packageTokens === null ? BrandStyle::preset($preset) : 'classic';
        $wholePalette = $preset !== 'classic';
        $modes = [];
        $serving = [];
        foreach ($presets[$preset] as $mode => $base) {
            $resolved = BrandPalette::withColours($base, $colours[$mode]['primary'], $colours[$mode]['accent']);
            $serving[$mode] = $resolved;
            // Derive against the serving palette without republishing its
            // surfaces/type over the package stylesheet.
            $modes[$mode] = $wholePalette ? $resolved : array_diff_assoc($resolved, $base);
        }
        // A recovery page asks for the scoped rules only: the sample and the
        // thumbnails, never the global overrides or custom CSS (ADR 0054).
        $sampleOnly = $request->query('scope') === 'sample';
        $css = $sampleOnly ? ':root{}' : BrandPalette::css($modes);
        $css .= BrandPalette::previewCss($presets, $colours);
        // Member appearance thumbnails show what each mode really resolves to.
        $css .= BrandPalette::thumbnailCss($serving);

        if (!$sampleOnly && $flags->enabled('custom_css')
            && $document['brand_custom_css_enabled'] === true
            && (string) $document['brand_custom_css'] !== ''
        ) {
            $css .= "\n" . (string) $document['brand_custom_css'];
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
        return $this->view('admin/branding', $this->formData($this->container->get(SettingRepository::class)));
    }

    public function update(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(WriteGate::class)->assertCanWrite($admin);
        $this->requireBrandingEnabled();
        $settings = $this->container->get(SettingRepository::class);
        // What this form edits: the administrator's private draft when there
        // is one, else the live branding (ADR 0054).
        $existingDraft = $this->drafts()->find($admin->id());
        $basis = $this->basisDocument($existingDraft);

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
                // Every resettable group — colours (shared and per mode), logos
                // and favicon, appearance and style — plus stored custom CSS.
                $keys = ['brand_custom_css_enabled', 'brand_custom_css'];
                foreach (BrandingDocument::RESET_GROUPS as $group) {
                    $keys = array_merge($keys, $group['keys']);
                }
                foreach ($keys as $key) {
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
        [$identity, $identityErrors] = $this->submittedIdentity($request, $basis);
        $errors += $identityErrors;
        $primary = trim($request->str('color_primary'));
        $accent = trim($request->str('color_accent'));
        if ($primary !== '' && !self::isHex($primary)) {
            $errors['color_primary'] = 'Primary colour must be a hex value, e.g. #2e4a3a.';
        }
        if ($accent !== '' && !self::isHex($accent)) {
            $errors['color_accent'] = 'Accent colour must be a hex value, e.g. #c29a44.';
        }
        // Optional per-mode adjustments (ADR 0054). An absent field — a form
        // from before they existed — keeps the basis value; blank inherits.
        $modeColours = [];
        foreach (self::MODE_COLOURS as $field => [$key, $label]) {
            $raw = $request->post($field);
            $value = is_string($raw) ? trim($raw) : (string) $basis[$key];
            if ($value !== '' && !self::isHex($value)) {
                $errors[$field] = $label . ' must be a hex value, e.g. #2e4a3a, or blank to use the shared colour.';
            }
            $modeColours[$field] = $value;
        }
        $themeDefault = $request->str('theme_default');
        if (!in_array($themeDefault, ['system', 'light', 'dark'], true)) {
            $themeDefault = 'system';
        }
        $themePreset = BrandStyle::preset($request->str('theme_preset', 'classic'));
        // Style overrides and the density default (ADR 0054 increment 4). An
        // absent field keeps the basis value; unknown values fall back to the
        // preset (or Comfortable), like the other choice fields.
        $style = [];
        foreach (self::STYLE_FIELDS as $field => $key) {
            $raw = $request->post($field);
            if (!is_string($raw)) {
                $style[$field] = (string) $basis[$key];
            } elseif ($key === 'brand_density_default') {
                $style[$field] = BrandStyle::density(trim($raw));
            } else {
                $style[$field] = BrandStyle::override($key, trim($raw));
            }
        }
        ['warnings' => $contrastWarnings, 'hash' => $contrastReview] = $this->contrastReview([
            'brand_color_primary' => $primary,
            'brand_color_accent' => $accent,
            'brand_theme_preset' => $themePreset,
        ] + array_combine(array_column(self::MODE_COLOURS, 0), array_values($modeColours)));
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
            : (string) $basis['brand_custom_css'];
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
        $intent = $request->str('intent') === 'draft' ? 'draft' : 'publish';
        if ($intent === 'draft') {
            // A private draft is never refused for contrast: the review and its
            // audited acknowledgement belong to publishing it.
            unset($errors['contrast_ack']);
        }
        $view = $this->identityFormValues($identity) + $modeColours + $style + [
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
            return $this->view('admin/branding', $this->formData($settings, $view + ['errors' => $errors]), 422);
        }

        // The candidate document: the basis with this submission's edits.
        // Publishing writes all of it, so a draft publishes exactly as it was
        // previewed, images included.
        $candidate = $basis;
        foreach ($identity as $key => $value) {
            $candidate[$key] = $value;
        }
        $candidate['site_name'] = $name;
        $candidate['brand_color_primary'] = self::isHex($primary) ? strtolower($primary) : '';
        $candidate['brand_color_accent'] = self::isHex($accent) ? strtolower($accent) : '';
        foreach (self::MODE_COLOURS as $field => [$key]) {
            $candidate[$key] = self::isHex($modeColours[$field]) ? strtolower($modeColours[$field]) : '';
        }
        $candidate['brand_theme_default'] = $themeDefault;
        $candidate['brand_theme_preset'] = $themePreset;
        foreach (self::STYLE_FIELDS as $field => $key) {
            $candidate[$key] = $style[$field];
        }
        if ($customCssAvailable) {
            $candidate['brand_custom_css_enabled'] = $customCssEnabled;
            $candidate['brand_custom_css'] = $customCss;
        }
        $removedAssets = [];
        foreach (self::ASSETS as $field => $asset) {
            if ($removals['remove_' . $field] && $candidate[$asset['setting']] !== '') {
                // Disconnect the mark but retain its stored media for history.
                $candidate[$asset['setting']] = '';
                $removedAssets[] = $field;
            }
        }

        if ($intent === 'draft') {
            return $this->saveDraft($admin, $request, $candidate, $existingDraft, $baseRevision, $removals);
        }

        $audit = [];
        try {
            $commit = $this->revisions()->commit($admin, 'publish', function () use (
                $settings, $candidate, $contrastWarnings, $primary, $accent, $themePreset,
                $admin, $request, $removals, $removedAssets, $existingDraft, &$audit,
            ): array {
                $live = $this->revisions()->published();
                $changedText = array_values(array_filter(
                    array_keys(BrandIdentity::FIELDS),
                    static fn (string $key): bool => ($live[$key] ?? '') !== $candidate[$key],
                ));
                // '' is stored rather than deleting a row: it means "use the
                // built-in default", exactly like a cleared colour.
                BrandingDocument::write($settings, $candidate);
                // An override and its audit record commit together. Recalculate the
                // warnings for the posted colours rather than trusting a client list.
                if ($contrastWarnings !== []) {
                    $this->audit($admin->id(), 'contrast_override', [
                        'color_primary' => strtolower($primary),
                        'color_accent' => strtolower($accent),
                        'mode_colours' => BrandPalette::modeColours($candidate),
                        'theme_preset' => $themePreset,
                        'warnings' => $contrastWarnings,
                    ]);
                }
                // Rejected uploads retain the previous asset and produce a visible
                // partial-success notice rather than hiding behind a success flash.
                $assetErrors = [];
                foreach (self::ASSETS as $field => $asset) {
                    if (!$removals['remove_' . $field]) {
                        $this->storeAsset($admin->id(), $request->file($field), $asset['purpose'], $asset['setting'], ucfirst($asset['label']), $assetErrors);
                    }
                }
                // A draft's private images become public with its publication,
                // and the published draft is gone.
                foreach (BrandingDocument::mediaIds($this->revisions()->published()) as $id) {
                    $this->container->get(AttachmentRepository::class)->publishBrandAsset($id, $admin->id());
                }
                if ($existingDraft !== null) {
                    $this->drafts()->delete($admin->id());
                }
                $this->bustBrandCache($settings);
                $audit = ['removed_assets' => $removedAssets, 'changed_text' => $changedText, 'from_draft' => $existingDraft !== null];
                return $assetErrors;
            }, $baseRevision, $overwrite, recorded: function (int $revision, array $changed) use ($admin, &$audit): void {
                $this->audit($admin->id(), 'update', $audit + ['revision' => $revision, 'changed_keys' => $changed]);
            });
        } catch (BrandingConflictException $conflict) {
            // Nothing was written. Return the whole draft and name the newer
            // changes; publishing over them takes an explicit confirmation.
            return $this->view('admin/branding', $this->formData($settings, $view + [
                'conflict' => $this->conflictSummary($conflict),
                'errors' => ['overwrite_revision' => 'Branding changed after you opened this page. Review the newer changes, then confirm replacing them to publish your version.'],
            ]), 409);
        }
        if ($existingDraft !== null) {
            $this->session()->forget('branding_draft_preview');
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
        return $this->redirectWithFlash('/admin/branding', 'Published. Branding appears on the next page load.');
    }

    /** Start or stop this administrator's private whole-site preview of their draft. */
    public function preview(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $this->requireBrandingEnabled();
        if ($request->str('preview') !== '1') {
            $this->session()->forget('branding_draft_preview');
            return $this->redirectWithFlash(
                Controller::localPath($request->str('return'), '/admin/branding'),
                'Draft preview ended. You are seeing the published branding.',
            );
        }
        if ($this->drafts()->find($admin->id()) === null) {
            return $this->redirectWithFlash('/admin/branding', 'Save a draft before previewing it.');
        }
        $this->session()->set('branding_draft_preview', '1');
        return $this->redirectWithFlash(
            Controller::localPath($request->str('return'), '/'),
            'Draft preview started.',
        );
    }

    /** The sign-in screen as visitors would see it, for previewing a draft while signed in. */
    public function previewSignIn(Request $request): Response
    {
        $this->requireAdmin();
        $this->requireBrandingEnabled();
        $response = $this->view('auth/login', ['errors' => [], 'old' => [], 'next' => '/']);
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }

    /** Throw away this administrator's draft and the private images only it used. */
    public function discardDraft(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(WriteGate::class)->assertCanWrite($admin);
        $this->requireBrandingEnabled();
        $draft = $this->drafts()->find($admin->id());
        if ($draft !== null) {
            $this->container->get(Database::class)->transaction(function () use ($admin, $draft): void {
                $this->drafts()->delete($admin->id());
                $this->releaseDraftAssets($admin->id(), $draft['document'], []);
            });
        }
        $this->session()->forget('branding_draft_preview');
        return $this->redirectWithFlash('/admin/branding', $draft === null
            ? 'There was no draft to discard.'
            : 'Draft discarded. The published branding is unchanged.');
    }

    /**
     * Store a private draft (ADR 0054): new uploads become private brand media
     * only this administrator can load, and the draft keeps the revision its
     * form started from, so publishing it later is checked against newer work.
     *
     * @param array<string,mixed> $candidate
     * @param array<string,mixed>|null $existing
     * @param array<string,bool> $removals
     */
    private function saveDraft(\App\Domain\User $admin, Request $request, array $candidate, ?array $existing, int $baseRevision, array $removals): Response
    {
        $assetErrors = [];
        $this->container->get(Database::class)->transaction(function () use ($admin, $request, &$candidate, $existing, $baseRevision, $removals, &$assetErrors): void {
            foreach (self::ASSETS as $field => $asset) {
                if ($removals['remove_' . $field]) {
                    continue;
                }
                $path = $this->storePrivateAsset($admin->id(), $request->file($field), $asset['purpose'], ucfirst($asset['label']), $assetErrors);
                if ($path !== null) {
                    $candidate[$asset['setting']] = $path;
                }
            }
            $this->drafts()->save($admin->id(), $candidate, $baseRevision);
            if ($existing !== null) {
                $this->releaseDraftAssets($admin->id(), $existing['document'], $candidate);
            }
        });
        return $this->redirectWithFlash('/admin/branding', $assetErrors === []
            ? 'Draft saved. Only you can see it; the published branding is unchanged.'
            : 'Draft saved, but ' . implode(' ', $assetErrors) . ' The draft kept its previous image.');
    }

    /**
     * Purge private draft images a draft no longer uses. Published images are
     * public and always retained for history, so they are never touched.
     *
     * @param array<string,mixed> $old
     * @param array<string,mixed> $new
     */
    private function releaseDraftAssets(int $adminId, array $old, array $new): void
    {
        $kept = array_values(BrandingDocument::mediaIds($new));
        $attachments = $this->container->get(AttachmentRepository::class);
        foreach (BrandingDocument::mediaIds($old) as $id) {
            if (in_array($id, $kept, true)) {
                continue;
            }
            $asset = $attachments->find($id);
            if ($asset !== null && (string) $asset['visibility'] === 'private' && (int) $asset['user_id'] === $adminId
                && in_array((string) $asset['purpose'], ['brand_logo', 'brand_favicon'], true)
                && $asset['post_id'] === null && $asset['dm_message_id'] === null) {
                $this->container->get(AttachmentService::class)->purge($asset);
            }
        }
    }

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $file
     * @param array<int,string> $assetErrors
     */
    private function storePrivateAsset(int $adminId, ?array $file, string $purpose, string $label, array &$assetErrors): ?string
    {
        if ($file === null) {
            return null;
        }
        try {
            $row = $this->container->get(AttachmentService::class)->storeUpload($adminId, $file, $purpose);
        } catch (\App\Core\ValidationException $e) {
            $assetErrors[] = $label . ' upload was rejected: ' . $e->first();
            return null;
        }
        $this->container->get(AttachmentRepository::class)->finalizeDraftBrandAsset((int) $row['id'], $adminId);
        return '/media/' . (int) $row['id'];
    }

    /**
     * The document a form edits: this administrator's draft, else the live
     * branding.
     *
     * @param array<string,mixed>|null $draft
     * @return array<string,mixed>
     */
    private function basisDocument(?array $draft): array
    {
        return $draft !== null
            ? BrandingDocument::normalize($draft['document'], $this->defaultName())
            : $this->revisions()->published();
    }

    private function drafts(): BrandingDraftRepository
    {
        return $this->container->get(BrandingDraftRepository::class);
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
        $review = $this->contrastReview($candidate);
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
    /** @param array<string,mixed> $document the candidate's colour and preset settings */
    private function contrastReview(array $document): array
    {
        $preset = BrandStyle::preset($document['brand_theme_preset'] ?? null);
        $colours = BrandPalette::modeColours($document);
        $overridden = [];
        foreach (['primary', 'accent'] as $role) {
            foreach (['light', 'dark'] as $mode) {
                $value = $document['brand_color_' . $role . '_' . $mode] ?? '';
                $overridden[$role . '_' . $mode] = is_string($value) && BrandPalette::isHex($value);
            }
        }
        $palettes = BrandPalette::presets($this->packageTokens());
        $warnings = BrandPalette::modeWarnings($palettes[$preset], $colours, $overridden);
        return ['warnings' => $warnings, 'hash' => hash('sha256', json_encode([
            $colours, $preset, $palettes[$preset], $warnings,
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

    /**
     * The draft this session is previewing, only for its owner.
     *
     * @return array<string,mixed>|null
     */
    private function previewedDraft(): ?array
    {
        $viewer = $this->currentUser();
        if ($viewer === null || !$viewer->isAdmin() || $this->session()->get('branding_draft_preview') !== '1') {
            return null;
        }
        $draft = $this->drafts()->find($viewer->id());
        return $draft !== null ? BrandingDocument::normalize($draft['document'], $this->defaultName()) : null;
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
        // The form edits this administrator's private draft when there is one
        // (ADR 0054), else the live branding; a draft keeps the revision it was
        // started from, so publishing it is checked against newer work.
        $admin = $this->currentUser();
        $draft = $admin !== null ? $this->drafts()->find($admin->id()) : null;
        $document = $this->basisDocument($draft);
        $currentRevision = $this->revisions()->currentRevision();
        $data = [
            'site_name' => (string) $document['site_name'],
        ] + $this->identityFormValues(BrandIdentity::storedValues($document)) + [
            'identity_limits' => array_column(BrandIdentity::FIELDS, 'max', 'field'),
            'color_primary' => (string) $document['brand_color_primary'],
            'color_accent' => (string) $document['brand_color_accent'],
            'color_primary_light' => (string) $document['brand_color_primary_light'],
            'color_accent_light' => (string) $document['brand_color_accent_light'],
            'color_primary_dark' => (string) $document['brand_color_primary_dark'],
            'color_accent_dark' => (string) $document['brand_color_accent_dark'],
            'logo_path' => (string) $document['brand_logo_path'],
            'logo_light_path' => (string) $document['brand_logo_light_path'],
            'logo_dark_path' => (string) $document['brand_logo_dark_path'],
            'favicon_path' => (string) $document['brand_favicon_path'],
            'remove_logo' => false,
            'remove_logo_light' => false,
            'remove_logo_dark' => false,
            'remove_favicon' => false,
            'theme_default' => (string) $document['brand_theme_default'],
            'theme_preset' => (string) $document['brand_theme_preset'],
            'sidebar_style' => (string) $document['brand_sidebar_style'],
            'type_pairing' => (string) $document['brand_type_pairing'],
            'corners' => (string) $document['brand_corners'],
            'density_default' => (string) $document['brand_density_default'],
            'custom_css_available' => $this->container->get(FeatureFlags::class)->enabled('custom_css'),
            'custom_css_enabled' => $document['brand_custom_css_enabled'] === true,
            'custom_css' => (string) $document['brand_custom_css'],
            'custom_css_ack' => false,
            'brand_preview_palettes' => BrandPalette::presets($packageTokens),
            'brand_package_active' => $packageTokens !== null,
            'branding_draft' => $draft === null ? null : [
                'updated_at' => $draft['updated_at'],
                'base_revision' => $draft['base_revision_id'],
                'stale' => $draft['base_revision_id'] !== $currentRevision,
                'previewing' => $this->session()->get('branding_draft_preview') === '1',
            ],
            // Versioning (ADR 0054): the revision this form was opened at, the
            // recovery groups, and any stale-editor conflict to explain.
            'base_revision' => $draft !== null ? $draft['base_revision_id'] : $currentRevision,
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
        $data['theme_preset'] = BrandStyle::preset($data['theme_preset']);
        foreach (self::STYLE_FIELDS as $field => $key) {
            $data[$field] = $key === 'brand_density_default'
                ? BrandStyle::density($data[$field] ?? null)
                : BrandStyle::override($key, $data[$field] ?? null);
        }
        $data['colour_suggestions'] = $this->colourSuggestions($data);
        // The sample's style: each override, else the preset's choice; with a
        // package in this session an axis without an override follows it.
        $data['style_presets'] = BrandStyle::PRESETS;
        $data['style_rails'] = BrandStyle::RAILS;
        $data['style_types'] = BrandStyle::TYPES;
        $data['style_corners'] = BrandStyle::CORNERS;
        $data['sample_style'] = BrandStyle::effective([
            'brand_theme_preset' => $data['theme_preset'],
            'brand_sidebar_style' => $data['sidebar_style'],
            'brand_type_pairing' => $data['type_pairing'],
            'brand_corners' => $data['corners'],
        ], !empty($data['brand_package_active']));
        return $data;
    }

    /**
     * Automatic suggestions per mode (ADR 0054): when the colour a mode would
     * use draws a warning there, the nearest tone of it that passes. They are
     * computed against the palette publishing reviews — the globally active
     * package, not a private package preview — and are offered, never applied.
     *
     * @param array<string,mixed> $data
     * @return array<string,string> form field => suggested hex
     */
    private function colourSuggestions(array $data): array
    {
        $colours = BrandPalette::modeColours([
            'brand_color_primary' => $data['color_primary'] ?? '',
            'brand_color_accent' => $data['color_accent'] ?? '',
            'brand_color_primary_light' => $data['color_primary_light'] ?? '',
            'brand_color_accent_light' => $data['color_accent_light'] ?? '',
            'brand_color_primary_dark' => $data['color_primary_dark'] ?? '',
            'brand_color_accent_dark' => $data['color_accent_dark'] ?? '',
        ]);
        $palettes = BrandPalette::presets($this->packageTokens())[$data['theme_preset']] ?? [];
        $suggestions = [];
        foreach (['light', 'dark'] as $mode) {
            foreach (['primary', 'accent'] as $role) {
                $colour = $colours[$mode][$role];
                if ($colour === '' || !isset($palettes[$mode])) {
                    continue;
                }
                $suggested = BrandPalette::suggestion($colour, $palettes[$mode], $role);
                if ($suggested !== '' && $suggested !== $colour) {
                    $suggestions['color_' . $role . '_' . $mode] = $suggested;
                }
            }
        }
        return $suggestions;
    }

    /** @return array<string,mixed> */
    private function submittedFormData(Request $request, SettingRepository $settings): array
    {
        $customCssAvailable = $this->container->get(FeatureFlags::class)->enabled('custom_css');
        $customCssEnabled = $customCssAvailable && $request->str('custom_css_enabled') === '1';

        $admin = $this->currentUser();
        $basis = $this->basisDocument($admin !== null ? $this->drafts()->find($admin->id()) : null);
        return $this->identityFormValues($this->submittedIdentity($request, $basis)[0]) + [
            'site_name' => $request->str('site_name', (string) $basis['site_name']),
            'color_primary' => $request->str('color_primary', (string) $basis['brand_color_primary']),
            'color_accent' => $request->str('color_accent', (string) $basis['brand_color_accent']),
            'theme_default' => $request->str('theme_default', (string) $basis['brand_theme_default']),
            'theme_preset' => $request->str('theme_preset', (string) $basis['brand_theme_preset']),
            'sidebar_style' => $request->str('sidebar_style', (string) $basis['brand_sidebar_style']),
            'type_pairing' => $request->str('type_pairing', (string) $basis['brand_type_pairing']),
            'corners' => $request->str('corners', (string) $basis['brand_corners']),
            'density_default' => $request->str('density_default', (string) $basis['brand_density_default']),
            'custom_css_enabled' => $customCssEnabled,
            'custom_css' => $customCssEnabled
                ? trim((string) $request->post('custom_css', ''))
                : (string) $basis['brand_custom_css'],
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
    private function submittedIdentity(Request $request, array $basis): array
    {
        $values = [];
        $errors = [];
        $saved = BrandIdentity::storedValues($basis);
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
