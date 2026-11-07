<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Branding');
$this->section('robots', 'noindex, nofollow');
$this->section('variant', 'admin');
$this->section('branding_preview', '1');
// The branding pages keep the built-in palette for their own chrome, so saved
// colours can never make the recovery controls unreadable (ADR 0054); the
// sample and thumbnails still show the saved or edited branding.
$this->section('recovery_surface', '1');
$previewLogos = ['base' => $logo_path, 'light' => $logo_light_path, 'dark' => $logo_dark_path];
// The saved sample resolves logos exactly as the public chrome does for the
// selected visitor mode; System lets the device preference choose (ADR 0054).
$previewLogoPlan = \App\Support\BrandLogo::plan($logo_path, $logo_light_path, $logo_dark_path, $theme_default);
$identityLimit = static fn (string $field): int => (int) ($identity_limits[$field] ?? 0);
$sel = static fn (string $v, string $cur): string => $v === $cur ? ' selected' : '';
$flashMessage = trim((string) ($flash ?? ''));
$saveStatus = $flashMessage === 'Published. Branding appears on the next page load.'
    || str_starts_with($flashMessage, 'Draft saved')
    || str_starts_with($flashMessage, 'Branding updated, but ')
    ? $flashMessage
    : '';
$resetStatus = str_starts_with($flashMessage, 'Branding reset.') ? $flashMessage : '';
$unplacedFlash = $saveStatus === '' && $resetStatus === '' ? $flashMessage : '';
$saveErrorMessages = [];
foreach (($errors ?? []) as $field => $message) {
    if ($field !== 'reset_confirm') {
        $saveErrorMessages[] = (string) $message;
    }
}
$saveError = implode(' ', $saveErrorMessages);
$colourDefaultText = static function (string $token, string $value) use ($brand_preview_palettes, $theme_preset, $theme_default): string {
    $modes = $theme_default === 'system' ? ['light', 'dark'] : [$theme_default];
    $defaults = [];
    foreach ($modes as $mode) {
        $defaults[] = ucfirst($mode) . ' ' . $brand_preview_palettes[$theme_preset][$mode][$token];
    }
    return ($value === '' ? 'Using theme default: ' : 'Theme default: ')
        . implode(' · ', $defaults) . '.'
        . ($value === '' ? '' : ' Clear this field to use it.');
};
?>
<?= $this->partial('admin/_console', [
    'area' => 'appearance',
    'tab' => 'branding',
    'pane_class' => 'admin-appearance admin-appearance-branding',
    'defer_flash' => true,
]) ?>
        <?php if ($unplacedFlash !== ''): ?>
            <?= $this->partial('partials/flash', ['flash' => $unplacedFlash]) ?>
        <?php endif; ?>

        <p class="pane-intro">Set the community name, colours and appearance. Published branding appears across the community on the next page load; a saved draft stays private until you publish it, and you can preview it across the community first. The preview shows the visitor theme you select; signed-in members keep their own light/dark preference. These branding pages always use the built-in colours, so their controls stay readable whatever is saved.</p>

        <div class="brand-cols">
        <div class="branding-main-column">
        <?php if (is_array($branding_draft ?? null)): ?>
            <?php /* The draft's own actions are separate forms, so this panel sits outside the branding form. */ ?>
            <section class="callout branding-draft-panel" aria-labelledby="branding-draft-title">
                <h2 class="branding-section-label" id="branding-draft-title">Unpublished draft</h2>
                <p>The form below shows the draft you saved <?= $e(human_datetime((string) $branding_draft['updated_at'])) ?>. Only you can see it and its images; visitors still see the published branding until you publish.</p>
                <?php if (!empty($branding_draft['stale'])): ?>
                    <p class="branding-draft-stale">Branding was published after this draft was started. Publishing it will ask you to review those changes first.</p>
                <?php endif; ?>
                <?php if (!empty($branding_draft['previewing'])): ?>
                    <p>This session shows the draft across the community. Check the <a href="/">board directory</a>, the <a href="/admin/branding/preview/sign-in">sign-in page</a> and <a href="/settings/appearance">appearance settings</a>.</p>
                <?php endif; ?>
                <div class="branding-draft-actions">
                    <form method="post" action="/admin/branding/preview">
                        <?= $this->csrfField() ?>
                        <?php if (!empty($branding_draft['previewing'])): ?>
                            <input type="hidden" name="preview" value="0">
                            <input type="hidden" name="return" value="/admin/branding">
                            <button class="btn btn-secondary btn-small" type="submit">Exit preview</button>
                        <?php else: ?>
                            <input type="hidden" name="preview" value="1">
                            <input type="hidden" name="return" value="/">
                            <button class="btn btn-secondary btn-small" type="submit">Preview the community with this draft</button>
                        <?php endif; ?>
                    </form>
                    <?= $this->partial('partials/dm_confirm', [
                        'summary_class' => 'btn btn-ghost btn-small branding-draft-discard',
                        'label' => 'Discard draft…',
                        'note' => 'Deletes this unpublished draft and any images only it used. The published branding and its history are unchanged.',
                        'note_id' => 'branding-draft-discard-note',
                        'action' => '/admin/branding/draft/discard',
                        'fields' => [],
                        'confirm' => 'Discard draft',
                        'danger' => true,
                    ]) ?>
                </div>
            </section>
        <?php endif; ?>
        <form method="post" action="/admin/branding" class="branding-form-card" enctype="multipart/form-data" data-brand-form data-brand-package-active="<?= !empty($brand_package_active) ? '1' : '0' ?>" data-brand-upload-max-bytes="<?= $e($upload_max_bytes) ?>" data-brand-palettes="<?= $e(json_encode($brand_preview_palettes ?? [], JSON_THROW_ON_ERROR)) ?>">
            <?= $this->csrfField() ?>
            <?php /* The revision this form was opened at: publishing over a newer one needs explicit confirmation (ADR 0054). */ ?>
            <input type="hidden" name="base_revision" value="<?= (int) ($base_revision ?? 0) ?>">
            <?php if (is_array($conflict ?? null)): ?>
                <section class="callout callout-review branding-conflict" aria-labelledby="branding-conflict-title">
                    <h3 id="branding-conflict-title">Branding changed while you were editing</h3>
                    <p>These changes were published after you opened this page. Your edits below are unchanged and nothing was saved.</p>
                    <ul>
                        <?php foreach ($conflict['newer'] as $newer): ?>
                            <li><a href="/admin/branding/history/<?= (int) $newer['id'] ?>">Revision <?= (int) $newer['id'] ?></a>, <?= $e(human_datetime((string) $newer['when'])) ?><?= $newer['actor'] !== '' ? ' by @' . $e($newer['actor']) : '' ?>: <?= $e($newer['changed'] !== [] ? implode(', ', $newer['changed']) : ucfirst((string) $newer['action'])) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <label class="branding-checkline" for="branding-overwrite">
                        <input id="branding-overwrite" type="checkbox" name="overwrite_revision" value="<?= (int) $conflict['current'] ?>"<?= field_attrs($errors ?? [], 'overwrite_revision') ?>>
                        <span>Publish this form’s branding and replace those changes. They stay in branding history.</span>
                    </label>
                    <?= field_error($errors ?? [], 'overwrite_revision') ?>
                </section>
            <?php endif; ?>

                <div class="branding-field-grid branding-preview-target" id="branding-palette" tabindex="-1">
                    <div class="branding-field branding-field-wide">
                        <label for="branding-site-name">Site name</label>
                        <input id="branding-site-name" type="text" name="site_name" class="input" maxlength="80" value="<?= $e($site_name) ?>"<?= field_attrs($errors ?? [], 'site_name') ?> required data-brand-name>
                        <?= field_error($errors ?? [], 'site_name') ?>
                    </div>

                    <div class="branding-field">
                        <label for="branding-primary">Primary colour (hex)</label>
                        <p id="branding-primary-scope" class="branding-colour-help">Actions, links, focus and selected navigation. Lighter backgrounds and readable text are derived from this colour.</p>
                        <input id="branding-primary" type="text" name="color_primary" class="input branding-hex-input" maxlength="7" placeholder="Use theme default" value="<?= $e($color_primary) ?>"<?= field_attrs($errors ?? [], 'color_primary', describedBy: 'branding-primary-help branding-primary-scope') ?> data-brand-primary>
                        <p id="branding-primary-help" class="branding-colour-help" data-brand-colour-help="primary"><span class="branding-colour-swatch" data-brand-colour-swatch aria-hidden="true" hidden></span><span data-brand-colour-description><?= $e($colourDefaultText('--accent', $color_primary)) ?></span></p>
                        <?= field_error($errors ?? [], 'color_primary') ?>
                    </div>

                    <div class="branding-field">
                        <label for="branding-accent">Accent colour (hex)</label>
                        <p id="branding-accent-scope" class="branding-colour-help">Highlights, unread indicators and counts, and selection rules. Warning, success, moderation and presence colours keep their meanings.</p>
                        <input id="branding-accent" type="text" name="color_accent" class="input branding-hex-input" maxlength="7" placeholder="Use theme default" value="<?= $e($color_accent) ?>"<?= field_attrs($errors ?? [], 'color_accent', describedBy: 'branding-accent-help branding-accent-scope') ?> data-brand-accent>
                        <p id="branding-accent-help" class="branding-colour-help" data-brand-colour-help="accent"><span class="branding-colour-swatch" data-brand-colour-swatch aria-hidden="true" hidden></span><span data-brand-colour-description><?= $e($colourDefaultText('--accent-2', $color_accent)) ?></span></p>
                        <?= field_error($errors ?? [], 'color_accent') ?>
                    </div>

                    <?php
                    // Optional per-mode adjustments (ADR 0054): blank inherits the
                    // shared colour above, then the package or preset role.
                    $modeFields = [
                        'light' => ['color_primary_light' => ['Primary', '--accent', $color_primary], 'color_accent_light' => ['Accent', '--accent-2', $color_accent]],
                        'dark' => ['color_primary_dark' => ['Primary', '--accent', $color_primary], 'color_accent_dark' => ['Accent', '--accent-2', $color_accent]],
                    ];
                    $modeOpen = false;
                    foreach ($modeFields as $fields) {
                        foreach (array_keys($fields) as $field) {
                            $modeOpen = $modeOpen || (string) ($$field ?? '') !== '' || !empty($errors[$field]) || isset($colour_suggestions[$field]);
                        }
                    }
                    ?>
                    <details class="branding-field branding-field-wide branding-mode-colours"<?= $modeOpen ? ' open' : '' ?>>
                        <summary>Adjust colours for light and dark modes</summary>
                        <p class="branding-colour-help">Optional. Blank uses the shared colour above, or the theme default when that is blank too. Each mode is reviewed for contrast on its own surfaces; a suggestion appears when a colour is hard to read in a mode.</p>
                        <div class="branding-mode-grid">
                            <?php foreach ($modeFields as $mode => $fields): ?>
                                <fieldset class="branding-mode-set">
                                    <legend><?= $mode === 'light' ? 'Light mode' : 'Dark mode' ?></legend>
                                    <?php foreach ($fields as $field => [$roleLabel, $token, $shared]): ?>
                                        <?php
                                        $value = (string) ($$field ?? '');
                                        $default = (string) ($brand_preview_palettes[$theme_preset][$mode][$token] ?? '');
                                        $inherited = $shared !== '' ? 'Blank uses the shared colour ' . strtolower($shared) . '.' : 'Blank uses the theme default ' . $default . '.';
                                        $inputId = 'branding-' . str_replace('_', '-', $field);
                                        ?>
                                        <div class="branding-mode-field">
                                            <label for="<?= $e($inputId) ?>"><?= $e($roleLabel) ?></label>
                                            <input id="<?= $e($inputId) ?>" type="text" name="<?= $e($field) ?>" class="input branding-hex-input" maxlength="7" placeholder="Use shared colour" value="<?= $e($value) ?>"<?= field_attrs($errors ?? [], $field, describedBy: $inputId . '-help') ?> data-brand-mode-colour="<?= $e($field) ?>">
                                            <p id="<?= $e($inputId) ?>-help" class="branding-colour-help"><?= $e($inherited) ?></p>
                                            <?php if (isset($colour_suggestions[$field])): ?>
                                                <p class="branding-colour-suggestion">Suggested for readable <?= $roleLabel === 'Primary' ? 'links' : 'indicators' ?> in <?= $mode ?> mode: <code><?= $e($colour_suggestions[$field]) ?></code>
                                                    <button class="btn btn-secondary btn-small" type="button" data-brand-suggest="<?= $e($field) ?>" data-brand-suggest-value="<?= $e($colour_suggestions[$field]) ?>" hidden>Use suggestion</button></p>
                                            <?php endif; ?>
                                            <?= field_error($errors ?? [], $field) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <div class="branding-field">
                        <label for="branding-theme-default">Default theme for signed-out visitors</label>
                        <select id="branding-theme-default" name="theme_default" class="input" aria-describedby="branding-mode-help" data-brand-theme>
                            <option value="system"<?= $sel('system', $theme_default) ?>>System</option>
                            <option value="light"<?= $sel('light', $theme_default) ?>>Light</option>
                            <option value="dark"<?= $sel('dark', $theme_default) ?>>Dark</option>
                        </select>
                        <p id="branding-mode-help" class="branding-colour-help">System follows the visitor’s device. Members keep their personal light/dark preference.</p>
                    </div>

                    <div class="branding-field">
                        <label for="branding-theme-preset">Style preset</label>
                        <select id="branding-theme-preset" name="theme_preset" class="input" aria-describedby="branding-preset-help" data-brand-preset>
                            <?php foreach ($style_presets as $presetKey => $presetInfo): ?>
                                <option value="<?= $e($presetKey) ?>"<?= $sel($presetKey, $theme_preset) ?>><?= $e($presetInfo['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p id="branding-preset-help" class="branding-colour-help">Each preset sets the page and panel colours, the sidebar, the heading type and the corners together. An active package supplies the base theme instead; the preset resumes when that package is disabled or in safe mode. Colours entered above override any base.</p>
                    </div>
                </div>

                <?php
                // Curated style (ADR 0054 increment 4): the preset's own choices,
                // and optional overrides for the sidebar, type and corners.
                $presetStyle = $style_presets[$theme_preset] ?? $style_presets['classic'];
                $styleFields = [
                    'sidebar_style' => ['Sidebar', 'rail', $style_rails, $sidebar_style],
                    'type_pairing' => ['Headings and text', 'type', $style_types, $type_pairing],
                    'corners' => ['Corners', 'corners', $style_corners, $corners],
                ];
                ?>
                <h3 class="branding-section-label" id="branding-style-title">Style</h3>
                <div class="branding-style" role="group" aria-labelledby="branding-style-title" data-brand-style-presets="<?= $e(json_encode(array_map(static fn (array $p): array => ['rail' => $p['rail'], 'type' => $p['type'], 'corners' => $p['corners']], $style_presets), JSON_THROW_ON_ERROR)) ?>"<?= !empty($brand_package_active) ? ' data-brand-package-active' : '' ?>>
                    <ul class="branding-style-presets" aria-label="Style presets">
                        <?php foreach ($style_presets as $presetKey => $presetInfo): ?>
                            <li><strong><?= $e($presetInfo['label']) ?></strong> — <?= $e($presetInfo['summary']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="branding-colour-help" id="branding-style-help">Override one part of the preset below, or leave it on “Preset”. Body text uses your system’s sans-serif font. With an active package, a part you override applies over the package and the rest follows the package.</p>
                    <div class="branding-field-grid branding-style-grid">
                        <?php foreach ($styleFields as $field => [$styleLabel, $axis, $choices, $current]): ?>
                            <?php $inputId = 'branding-' . str_replace('_', '-', $field); ?>
                            <div class="branding-field">
                                <label for="<?= $e($inputId) ?>"><?= $e($styleLabel) ?></label>
                                <select id="<?= $e($inputId) ?>" name="<?= $e($field) ?>" class="input" aria-describedby="branding-style-help" data-brand-style="<?= $e($axis) ?>" data-brand-style-labels="<?= $e(json_encode($choices, JSON_THROW_ON_ERROR)) ?>">
                                    <option value=""<?= $sel('', $current) ?> data-brand-style-preset-option>Preset — <?= $e($choices[$presetStyle[$axis]] ?? '') ?></option>
                                    <?php foreach ($choices as $choice => $choiceLabel): ?>
                                        <option value="<?= $e($choice) ?>"<?= $sel($choice, $current) ?>><?= $e($choiceLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                        <div class="branding-field">
                            <label for="branding-density-default">Default density</label>
                            <select id="branding-density-default" name="density_default" class="input" aria-describedby="branding-density-help">
                                <option value="comfortable"<?= $sel('comfortable', $density_default) ?>>Comfortable</option>
                                <option value="compact"<?= $sel('compact', $density_default) ?>>Compact</option>
                            </select>
                            <p id="branding-density-help" class="branding-colour-help">For signed-out visitors and members who have not chosen a density. A member’s own choice always wins.</p>
                        </div>
                    </div>
                </div>

                <a class="btn btn-secondary branding-preview-shortcut" href="#branding-preview">View appearance preview</a>

                <?php if (!empty($contrast_warnings)): ?>
                    <section class="callout callout-review branding-contrast-warning" aria-labelledby="branding-contrast-title">
                        <input type="hidden" name="contrast_review" value="<?= $e($contrast_review ?? '') ?>">
                        <h3 id="branding-contrast-title">Review colour contrast</h3>
                        <p>These colours can make parts of the community difficult to read. Choose different colours, or acknowledge the warnings to save them.</p>
                        <ul>
                            <?php foreach ($contrast_warnings as $warning): ?>
                                <li><?= $e($warning) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <label class="branding-checkline" for="branding-contrast-ack">
                            <input id="branding-contrast-ack" type="checkbox" name="contrast_ack" value="1"<?= !empty($contrast_ack) ? ' checked' : '' ?><?= field_attrs($errors ?? [], 'contrast_ack') ?>>
                            <span>I understand these readability risks and want to save these colours. This acknowledgement will be recorded in the audit log.</span>
                        </label>
                        <?= field_error($errors ?? [], 'contrast_ack') ?>
                        <p data-brand-contrast-changed hidden>Colours changed. Publish again to review the updated contrast warnings.</p>
                    </section>
                <?php endif; ?>

                <?php if (!empty($uploads_need_reselect)): ?>
                    <p class="callout callout-review" role="status">Your selected logo or favicon files were not uploaded. Select those files again before saving.</p>
                <?php endif; ?>

                <h3 class="branding-section-label">Marks</h3>
                <p class="brand-preview-note">Choose a replacement file or mark an existing image for removal. Changes apply when you save branding. The logo appears in the header, the administration bar and on every sign-in screen; light and dark variants replace it in their mode, and System follows each visitor’s device.</p>
                <p class="brand-preview-note" id="branding-upload-help"><?= $e($upload_formats) ?> images, up to <?= $e($upload_max_label) ?> per file and <?= $e($upload_max_width) ?> × <?= $e($upload_max_height) ?> pixels.<?php if ($upload_request_label !== null): ?> Keep all files in one save below <?= $e($upload_request_label) ?> combined.<?php endif; ?></p>
                <div class="branding-marks">
                    <?php foreach ([
                        'logo' => ['Logo', $logo_path, !empty($remove_logo)],
                        'favicon' => ['Favicon', $favicon_path, !empty($remove_favicon)],
                        'logo_light' => ['Light theme logo', $logo_light_path, !empty($remove_logo_light)],
                        'logo_dark' => ['Dark theme logo', $logo_dark_path, !empty($remove_logo_dark)],
                    ] as $assetField => [$assetLabel, $assetPath, $removeAsset]): ?>
                        <div class="branding-mark-field" data-brand-asset="<?= $e($assetField) ?>" data-brand-saved-asset="<?= $e($assetPath) ?>">
                            <label for="branding-file-<?= $e($assetField) ?>"><?= $e($assetLabel) ?></label>
                            <div class="branding-asset-preview">
                                <img data-brand-asset-image alt=""<?= $assetPath !== '' ? ' src="' . $e($assetPath) . '"' : ' hidden' ?>>
                                <span data-brand-asset-status role="status"><?= $removeAsset ? 'Will be removed on save.' : ($assetPath !== '' ? 'Saved image' : 'No image saved.') ?></span>
                            </div>
                            <input id="branding-file-<?= $e($assetField) ?>" class="branding-file-input" type="file" name="<?= $e($assetField) ?>" accept="<?= $e($upload_accept) ?>"<?= field_attrs($errors ?? [], $assetField, describedBy: 'branding-upload-help') ?>>
                            <?= field_error($errors ?? [], $assetField) ?>
                            <button class="btn btn-secondary branding-file-clear" type="button" data-brand-file-clear hidden>Clear selected <?= $e(strtolower($assetLabel)) ?></button>
                            <?php if ($assetPath !== '' || $removeAsset): ?>
                                <label class="branding-checkline" for="branding-remove-<?= $e($assetField) ?>">
                                    <input id="branding-remove-<?= $e($assetField) ?>" type="checkbox" name="remove_<?= $e($assetField) ?>" value="1"<?= $removeAsset ? ' checked' : '' ?><?= field_attrs($errors ?? [], 'remove_' . $assetField) ?>>
                                    <span>Remove <?= $e(strtolower($assetLabel)) ?> on save</span>
                                </label>
                                <?= field_error($errors ?? [], 'remove_' . $assetField) ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="branding-section-label" id="branding-text">Community text</h3>
                <p class="brand-preview-note">Plain text. Leave a field blank to use its default. Saved text appears on the next page load.</p>
                <div class="branding-field-grid branding-text-grid">
                    <div class="branding-field branding-field-wide">
                        <label for="branding-tagline">Tagline (optional)</label>
                        <input id="branding-tagline" type="text" name="site_tagline" class="input" maxlength="<?= $identityLimit('site_tagline') ?>" value="<?= $e($site_tagline) ?>"<?= field_attrs($errors ?? [], 'site_tagline', describedBy: 'branding-tagline-help') ?>>
                        <p id="branding-tagline-help" class="branding-colour-help">Shown beneath the community’s name or logo on sign-in screens and above the board directory heading. Up to <?= $identityLimit('site_tagline') ?> characters; blank shows nothing.</p>
                        <?= field_error($errors ?? [], 'site_tagline') ?>
                    </div>
                    <div class="branding-field branding-field-wide">
                        <label for="branding-description">Community description</label>
                        <textarea id="branding-description" name="site_description" class="input branding-textarea" rows="3" maxlength="<?= $identityLimit('site_description') ?>"<?= field_attrs($errors ?? [], 'site_description', describedBy: 'branding-description-help') ?>><?= $e($site_description) ?></textarea>
                        <p id="branding-description-help" class="branding-colour-help">Used by search engines and link previews for pages without a description of their own. Up to <?= $identityLimit('site_description') ?> characters; search results usually show the first 155. Blank uses “<?= $e(\App\Support\BrandIdentity::defaultDescription($site_name)) ?>”</p>
                        <?= field_error($errors ?? [], 'site_description') ?>
                    </div>
                    <div class="branding-field branding-field-wide">
                        <label for="branding-directory-heading">Welcome heading</label>
                        <input id="branding-directory-heading" type="text" name="directory_heading" class="input" maxlength="<?= $identityLimit('directory_heading') ?>" placeholder="<?= $e(\App\Support\BrandIdentity::DEFAULT_DIRECTORY_HEADING) ?>" value="<?= $e($directory_heading) ?>"<?= field_attrs($errors ?? [], 'directory_heading', describedBy: 'branding-directory-heading-help') ?>>
                        <p id="branding-directory-heading-help" class="branding-colour-help">The board directory’s main heading. Up to <?= $identityLimit('directory_heading') ?> characters. Blank uses “<?= $e(\App\Support\BrandIdentity::DEFAULT_DIRECTORY_HEADING) ?>”.</p>
                        <?= field_error($errors ?? [], 'directory_heading') ?>
                    </div>
                    <div class="branding-field branding-field-wide">
                        <label for="branding-directory-intro">Welcome introduction</label>
                        <textarea id="branding-directory-intro" name="directory_intro" class="input branding-textarea" rows="4" maxlength="<?= $identityLimit('directory_intro') ?>" placeholder="<?= $e(\App\Support\BrandIdentity::DEFAULT_DIRECTORY_INTRO) ?>"<?= field_attrs($errors ?? [], 'directory_intro', describedBy: 'branding-directory-intro-help') ?>><?= $e($directory_intro) ?></textarea>
                        <p id="branding-directory-intro-help" class="branding-colour-help">Shown beneath the welcome heading. Each line becomes a paragraph, and a link to the member’s inbox always follows. Up to <?= $identityLimit('directory_intro') ?> characters. Blank uses the built-in introduction.</p>
                        <?= field_error($errors ?? [], 'directory_intro') ?>
                    </div>
                </div>

                <h3 class="branding-section-label">Custom CSS</h3>
                <?php if (!empty($custom_css_available)): ?>
                    <div class="branding-custom-css">
                        <label class="branding-checkline">
                            <input type="checkbox" name="custom_css_enabled" value="1"<?= !empty($custom_css_enabled) ? ' checked' : '' ?>>
                            <span>Enable custom CSS</span>
                        </label>
                        <div class="branding-custom-css-fields">
                            <label class="branding-css-label" for="branding-custom-css">Custom CSS</label>
                            <textarea id="branding-custom-css" name="custom_css" class="input code-area" rows="7" maxlength="12000" spellcheck="false"<?= field_attrs($errors ?? [], 'custom_css') ?>><?= $e($custom_css) ?></textarea>
                            <label class="branding-checkline branding-css-ack">
                                <input type="checkbox" name="custom_css_ack" value="1"<?= !empty($custom_css_ack) ? ' checked' : '' ?>>
                                <span>I understand this CSS applies site-wide and can affect usability.</span>
                            </label>
                            <?= field_error($errors ?? [], 'custom_css') ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="callout callout-review branding-css-unavailable">Custom CSS is unavailable until safe recovery is supported. You can use the colours and theme presets above to customize the community.</p>
                <?php endif; ?>

                <div class="branding-save-row">
                    <button class="btn branding-primary-button" type="submit" name="intent" value="publish">Publish branding</button>
                    <button class="btn btn-secondary branding-draft-button" type="submit" name="intent" value="draft">Save draft</button>
                    <?php if ($saveStatus !== ''): ?>
                        <?php if (str_starts_with($saveStatus, 'Branding updated, but ')): ?>
                            <p class="branding-status callout callout-review" role="status"><?= $e($saveStatus) ?></p>
                        <?php else: ?>
                            <span class="branding-status" role="status"><?= $e($saveStatus) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($saveError !== ''): ?>
                        <span class="branding-error" role="alert"><?= $e($saveError) ?></span>
                    <?php endif; ?>
                </div>
        </form>

            <section class="branding-recovery" id="branding-recovery" aria-labelledby="branding-recovery-title">
                <h2 class="branding-section-label" id="branding-recovery-title">Undo and reset</h2>
                <p class="brand-preview-note">Every published change is kept in <a href="/admin/branding/history">branding history</a> with its logos. Restoring publishes that configuration again, and the one it replaces stays in history. Each reset below changes only its own group.</p>
                <div class="branding-reset-groups">
                    <?php foreach (($reset_groups ?? []) as $group => $definition): ?>
                        <?= $this->partial('partials/dm_confirm', [
                            'summary_class' => 'btn btn-secondary btn-small branding-reset-group',
                            'label' => 'Reset ' . $definition['label'] . '…',
                            'note' => $definition['explain'] . ' The previous ' . $definition['label'] . ' can be restored from branding history.',
                            'note_id' => 'branding-reset-' . $group . '-note',
                            'action' => '/admin/branding/reset/' . $group,
                            'fields' => [],
                            'confirm' => 'Reset ' . $definition['label'],
                            'group' => 'branding-reset-groups',
                        ]) ?>
                    <?php endforeach; ?>
                </div>
            <form method="post" action="/admin/branding" class="branding-reset-card" id="branding-reset">
                <?= $this->csrfField() ?>
                <input type="hidden" name="reset" value="1">
                <h3>Reset branding</h3>
                <p>Clears saved colours, logos, favicon, visitor appearance, theme preset, and custom CSS. Keeps the community name, community text and active package theme. Branding history keeps the configuration it replaces, logos included, so a reset can be restored.</p>
                <?php if (!empty($features['package_themes'])): ?>
                    <p>To pause package themes instead, use <a href="/admin/themes/safe-mode">theme safe mode</a>.</p>
                <?php endif; ?>
                <label for="branding-reset-confirm">Type RESET to confirm</label>
                <input id="branding-reset-confirm" type="text" name="reset_confirm" class="input branding-reset-input" value="<?= $e($reset_confirm ?? '') ?>" autocomplete="off" autocapitalize="off" spellcheck="false" required pattern="RESET"<?= field_attrs($errors ?? [], 'reset_confirm') ?>>
                <?= field_error($errors ?? [], 'reset_confirm') ?>
                <button class="btn branding-reset-button" type="submit">Reset branding</button>
                <?php if ($resetStatus !== ''): ?>
                    <p class="branding-reset-status" role="status"><?= $e($resetStatus) ?></p>
                <?php endif; ?>
            </form>
            </section>
        </div>

            <aside class="branding-preview-column">
                <section class="brand-preview-panel branding-preview-target" id="branding-preview" tabindex="-1" aria-live="polite">
                    <p class="eyebrow">Live preview</p>
                    <div class="brand-preview brand-preview-shell" data-brand-preview data-brand-theme="<?= $e($theme_default) ?>" data-brand-preset="<?= $e($theme_preset) ?>"<?php foreach (\App\Support\BrandStyle::attributes($sample_style, true) as $styleName => $styleValue): ?> <?= $e($styleName) ?>="<?= $e($styleValue) ?>"<?php endforeach; ?> data-brand-logos="<?= $e(json_encode($previewLogos, JSON_THROW_ON_ERROR)) ?>">
                        <div class="brand-preview-bar">
                            <span class="brand-preview-logo-slot" data-brand-preview-logo-slot><?= $this->partial('partials/brand_logo', ['plan' => $previewLogoPlan, 'class' => 'brand-preview-logo', 'alt' => '', 'fallback' => '', 'attrs' => ' data-brand-preview-logo']) ?></span>
                            <strong data-brand-preview-name><?= $e($site_name) ?></strong>
                            <span data-brand-preview-theme><?= !empty($brand_package_active) ? 'Package · ' : '' ?><?= $e(ucfirst($theme_default)) ?></span>
                        </div>
                        <div class="brand-preview-body">
                            <nav class="brand-preview-nav" aria-label="Sample community navigation">
                                <a class="forum-bar-surface is-active" href="#branding-preview" aria-current="page">Boards</a>
                                <a class="forum-bar-surface" href="#branding-preview">Inbox <span class="forum-bar-count">3</span></a>
                            </nav>
                            <nav class="brand-preview-rail" aria-label="Sample board navigation">
                                <a class="board-rail-item is-active" href="#branding-preview" aria-current="page"><span class="board-rail-name">General discussion</span><span class="board-rail-unread" aria-label="3 unread topics">3</span></a>
                            </nav>
                            <section class="feed-view-menu brand-preview-menu" aria-label="Sample feed menu">
                                <p class="brand-preview-copy">Feed view</p>
                                <div class="inbox-menu-panel">
                                    <nav class="inbox-tabs feed-tabs" aria-label="Sample feed views">
                                        <a class="inbox-tab is-active" href="#branding-preview" aria-current="page">Following</a>
                                        <a class="inbox-tab" href="#branding-preview">Latest</a>
                                    </nav>
                                </div>
                            </section>
                            <div class="brand-preview-content thread-row-inbox">
                                <p class="brand-preview-copy"><span class="unread-dot" role="img" aria-label="Unread"></span> A sample discussion</p>
                                <a class="thread-title" href="#branding-preview">Sample link</a>
                                <p>Readable content with a <span class="chip chip-decision_made">Decision</span> status.</p>
                            </div>
                            <button class="btn" type="button">Primary button</button>
                            <span class="brand-preview-accent">Accent marker</span>
                            <label class="brand-preview-field">Sample field<input class="input" type="text" value="Sample text" readonly></label>
                        </div>
                    </div>
                    <?php /* Both palettes and their interaction states together (ADR 0054), painted from each mode's resolved roles. */ ?>
                    <div class="brand-mode-states" role="group" aria-label="Light and dark states">
                        <?php foreach (['light' => 'Light', 'dark' => 'Dark'] as $mode => $modeLabel): ?>
                            <div class="brand-preview brand-states" data-brand-states data-brand-theme="<?= $e($mode) ?>" data-brand-preset="<?= $e($theme_preset) ?>">
                                <p class="brand-states-title"><?= $e($modeLabel) ?></p>
                                <div class="brand-states-row">
                                    <span class="brand-state brand-state-action">Action</span>
                                    <span class="brand-state brand-state-hover">Hover</span>
                                    <span class="brand-state brand-state-press">Pressed</span>
                                    <span class="brand-state brand-state-focus">Focus</span>
                                </div>
                                <div class="brand-states-row">
                                    <span class="brand-state brand-state-selected">Selected</span>
                                    <span class="brand-state brand-state-unread">3 unread</span>
                                    <span class="brand-state brand-state-link">Link</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="brand-preview-note">Each mode uses its own logo variant, falling back to the base logo. System follows the visitor’s device. Sign-in screens show the same logo on a raised plate.</p>
                    <?php if (!empty($brand_package_active)): ?>
                        <p class="brand-preview-note">This sample uses the package theme currently shown in your session. Built-in presets apply when no package theme is shown. Saving branding changes the live community.</p>
                    <?php endif; ?>
                    <noscript><p class="brand-preview-note">This preview shows the saved colours and logos. Turn on JavaScript to preview edits before saving.</p></noscript>
                    <a class="btn btn-secondary branding-preview-shortcut" href="#branding-palette">Back to appearance controls</a>
                </section>

            </aside>
        </div>
<?= $this->partial('admin/_console_end') ?>
