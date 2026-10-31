<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Branding');
$this->section('robots', 'noindex, nofollow');
$this->section('variant', 'admin');
$this->section('branding_preview', '1');
$previewLogos = ['base' => $logo_path, 'light' => $logo_light_path, 'dark' => $logo_dark_path];
$previewLogo = ($previewLogos[$theme_default] ?? '') ?: $logo_path;
$sel = static fn (string $v, string $cur): string => $v === $cur ? ' selected' : '';
$flashMessage = trim((string) ($flash ?? ''));
$saveStatus = $flashMessage === 'Saved. Branding appears on the next page load.'
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

        <p class="pane-intro">Set the community name, colours and appearance. Saved branding appears across the community on the next page load. The preview shows the visitor theme you select; signed-in members keep their own light/dark preference.</p>

        <div class="brand-cols">
        <form method="post" action="/admin/branding" class="branding-form-card" enctype="multipart/form-data" data-brand-form data-brand-package-active="<?= !empty($brand_package_active) ? '1' : '0' ?>" data-brand-upload-max-bytes="<?= $e($upload_max_bytes) ?>" data-brand-palettes="<?= $e(json_encode($brand_preview_palettes ?? [], JSON_THROW_ON_ERROR)) ?>">
            <?= $this->csrfField() ?>

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
                        <label for="branding-theme-preset">Theme preset</label>
                        <select id="branding-theme-preset" name="theme_preset" class="input" aria-describedby="branding-preset-help" data-brand-preset>
                            <option value="classic"<?= $sel('classic', $theme_preset) ?>>Classic</option>
                            <option value="retro"<?= $sel('retro', $theme_preset) ?>>Retro</option>
                        </select>
                        <p id="branding-preset-help" class="branding-colour-help">An active package supplies the base theme; Classic or Retro resumes when that package is disabled or in safe mode. Colours entered above override either base.</p>
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
                        <p data-brand-contrast-changed hidden>Colours changed. Save branding again to review the updated contrast warnings.</p>
                    </section>
                <?php endif; ?>

                <?php if (!empty($uploads_need_reselect)): ?>
                    <p class="callout callout-review" role="status">Your selected logo or favicon files were not uploaded. Select those files again before saving.</p>
                <?php endif; ?>

                <h3 class="branding-section-label">Marks</h3>
                <p class="brand-preview-note">Choose a replacement file or mark an existing image for removal. Changes apply when you save branding.</p>
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
                    <button class="btn branding-primary-button" type="submit">Save branding</button>
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

            <aside class="branding-preview-column">
                <section class="brand-preview-panel branding-preview-target" id="branding-preview" tabindex="-1" aria-live="polite">
                    <p class="eyebrow">Live preview</p>
                    <div class="brand-preview brand-preview-shell" data-brand-preview data-brand-theme="<?= $e($theme_default) ?>" data-brand-preset="<?= $e($theme_preset) ?>" data-brand-logos="<?= $e(json_encode($previewLogos, JSON_THROW_ON_ERROR)) ?>">
                        <div class="brand-preview-bar">
                            <img class="brand-preview-logo" data-brand-preview-logo alt=""<?= $previewLogo !== '' ? ' src="' . $e($previewLogo) . '"' : ' hidden' ?>>
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
                                <p class="brand-preview-copy"><span class="unread-dot" aria-label="Unread"></span> A sample discussion</p>
                                <a class="thread-title" href="#branding-preview">Sample link</a>
                                <p>Readable content with a <span class="chip chip-decision_made">Decision</span> status.</p>
                            </div>
                            <button class="btn" type="button">Primary button</button>
                            <span class="brand-preview-accent">Accent marker</span>
                            <label class="brand-preview-field">Sample field<input class="input" type="text" value="Sample text" readonly></label>
                        </div>
                    </div>
                    <p class="brand-preview-note">Light and dark logo variants are used when the resolved theme explicitly matches that variant; system theme falls back to the base logo.</p>
                    <?php if (!empty($brand_package_active)): ?>
                        <p class="brand-preview-note">This sample uses the package theme currently shown in your session. Classic and Retro apply when no package theme is shown. Saving branding changes the live community.</p>
                    <?php endif; ?>
                    <noscript><p class="brand-preview-note">This preview shows the saved colours and logos. Turn on JavaScript to preview edits before saving.</p></noscript>
                    <a class="btn btn-secondary branding-preview-shortcut" href="#branding-palette">Back to appearance controls</a>
                </section>

                <form method="post" action="/admin/branding" class="branding-reset-card" id="branding-reset">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="reset" value="1">
                    <h2>Reset branding</h2>
                    <p>Clears saved colours, logos, favicon, visitor appearance, theme preset, and custom CSS. Keeps the community name and active package theme. This cannot be undone.</p>
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
            </aside>
        </div>
<?= $this->partial('admin/_console_end') ?>
