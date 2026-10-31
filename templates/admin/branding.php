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
$saveStatus = $flashMessage === 'Saved. The chrome is live for everyone.'
    || str_starts_with($flashMessage, 'Branding updated, but ')
    ? $flashMessage
    : '';
$resetStatus = $flashMessage === 'Reset. The built-in chrome is back.' ? $flashMessage : '';
$unplacedFlash = $saveStatus === '' && $resetStatus === '' ? $flashMessage : '';
$saveErrorMessages = [];
foreach (($errors ?? []) as $field => $message) {
    if ($field !== 'reset_confirm') {
        $saveErrorMessages[] = (string) $message;
    }
}
$saveError = implode(' ', $saveErrorMessages);
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

        <p class="pane-intro">Set the community name, colours and appearance. The preview shows the visitor theme you select; your account appearance stays unchanged.</p>

        <form method="post" action="/admin/branding" class="brand-cols" enctype="multipart/form-data" data-brand-form data-brand-palettes="<?= $e(json_encode($brand_preview_palettes ?? [], JSON_THROW_ON_ERROR)) ?>">
            <?= $this->csrfField() ?>

            <section class="branding-form-card">
                <div class="branding-field-grid">
                    <div class="branding-field branding-field-wide">
                        <label for="branding-site-name">Site name</label>
                        <input id="branding-site-name" type="text" name="site_name" class="input" maxlength="80" value="<?= $e($site_name) ?>"<?= field_attrs($errors ?? [], 'site_name') ?> required data-brand-name>
                        <?= field_error($errors ?? [], 'site_name') ?>
                    </div>

                    <div class="branding-field">
                        <label for="branding-primary">Primary colour (hex)</label>
                        <input id="branding-primary" type="text" name="color_primary" class="input branding-hex-input" maxlength="7" placeholder="#2e4a3a" value="<?= $e($color_primary) ?>"<?= field_attrs($errors ?? [], 'color_primary') ?> data-brand-primary>
                        <?= field_error($errors ?? [], 'color_primary') ?>
                    </div>

                    <div class="branding-field">
                        <label for="branding-accent">Accent colour (hex)</label>
                        <input id="branding-accent" type="text" name="color_accent" class="input branding-hex-input" maxlength="7" placeholder="#c29a44" value="<?= $e($color_accent) ?>"<?= field_attrs($errors ?? [], 'color_accent') ?> data-brand-accent>
                        <?= field_error($errors ?? [], 'color_accent') ?>
                    </div>

                    <div class="branding-field">
                        <label for="branding-theme-default">Default theme for signed-out visitors</label>
                        <select id="branding-theme-default" name="theme_default" class="input" data-brand-theme>
                            <option value="system"<?= $sel('system', $theme_default) ?>>System</option>
                            <option value="light"<?= $sel('light', $theme_default) ?>>Light</option>
                            <option value="dark"<?= $sel('dark', $theme_default) ?>>Dark</option>
                        </select>
                    </div>

                    <div class="branding-field">
                        <label for="branding-theme-preset">Theme preset</label>
                        <select id="branding-theme-preset" name="theme_preset" class="input" data-brand-preset>
                            <option value="classic"<?= $sel('classic', $theme_preset) ?>>Classic</option>
                            <option value="retro"<?= $sel('retro', $theme_preset) ?>>Retro</option>
                        </select>
                    </div>
                </div>

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
                <div class="branding-marks">
                    <?php foreach ([
                        'logo' => ['Logo', $logo_path, !empty($remove_logo)],
                        'favicon' => ['Favicon', $favicon_path, !empty($remove_favicon)],
                        'logo_light' => ['Light theme logo', $logo_light_path, !empty($remove_logo_light)],
                        'logo_dark' => ['Dark theme logo', $logo_dark_path, !empty($remove_logo_dark)],
                    ] as $assetField => [$assetLabel, $assetPath, $removeAsset]): ?>
                        <div>
                            <label class="branding-mark-field">
                                <span><?= $e($assetLabel) ?><?php if ($assetPath !== ''): ?> <span class="branding-current">(current set)</span><?php endif; ?></span>
                                <span class="branding-file-control">
                                    <span class="branding-mark-action" aria-hidden="true"><?= $assetPath !== '' ? 'Replace' : 'Upload' ?></span>
                                    <input type="file" name="<?= $e($assetField) ?>" accept="image/*" aria-label="<?= $e(($assetPath !== '' ? 'Replace ' : 'Upload ') . strtolower($assetLabel)) ?>">
                                </span>
                            </label>
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
                    <p class="callout callout-review branding-css-unavailable">Custom CSS is saved behind the custom_css feature flag and is not available on this install.</p>
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
            </section>

            <aside class="branding-preview-column">
                <section class="brand-preview-panel" aria-live="polite">
                    <p class="eyebrow">Live preview</p>
                    <div class="brand-preview brand-preview-shell" data-brand-preview data-brand-theme="<?= $e($theme_default) ?>" data-brand-preset="<?= $e($theme_preset) ?>" data-brand-logos="<?= $e(json_encode($previewLogos, JSON_THROW_ON_ERROR)) ?>">
                        <div class="brand-preview-bar">
                            <img class="brand-preview-logo" data-brand-preview-logo alt=""<?= $previewLogo !== '' ? ' src="' . $e($previewLogo) . '"' : ' hidden' ?>>
                            <strong data-brand-preview-name><?= $e($site_name) ?></strong>
                            <span data-brand-preview-theme><?= $e(ucfirst($theme_default)) ?></span>
                        </div>
                        <div class="brand-preview-body">
                            <p class="brand-preview-copy">A sample discussion</p>
                            <a href="#">Sample link</a>
                            <button class="btn" type="button">Primary button</button>
                            <span class="brand-preview-accent">Accent marker</span>
                            <label class="brand-preview-field">Sample field<input class="input" type="text" value="Sample text" readonly tabindex="-1"></label>
                        </div>
                    </div>
                    <p class="brand-preview-note">Light and dark logo variants are used when the resolved theme explicitly matches that variant; system theme falls back to the base logo.</p>
                    <?php if (!empty($brand_package_active)): ?>
                        <p class="brand-preview-note">This sample uses the package theme currently shown in your session. Classic and Retro apply when no package theme is shown. Saving branding changes the live community.</p>
                    <?php endif; ?>
                    <noscript><p class="brand-preview-note">This preview shows the saved colours and logos. Turn on JavaScript to preview edits before saving.</p></noscript>
                </section>

                <section class="branding-reset-card">
                    <h2>Reset to defaults</h2>
                    <p>Clears every stored colour, logo, favicon, theme preset, and custom CSS, restoring the built-in chrome. It cannot be undone.</p>
                    <label for="branding-reset-confirm">Type RESET to confirm</label>
                    <input id="branding-reset-confirm" type="text" name="reset_confirm" class="input branding-reset-input" value="<?= $e($reset_confirm ?? '') ?>" autocomplete="off" autocapitalize="off" spellcheck="false"<?= field_attrs($errors ?? [], 'reset_confirm') ?>>
                    <?= field_error($errors ?? [], 'reset_confirm') ?>
                    <button class="btn branding-reset-button" type="submit" name="reset" value="1" formnovalidate>Reset to defaults</button>
                    <?php if ($resetStatus !== ''): ?>
                        <p class="branding-reset-status" role="status"><?= $e($resetStatus) ?></p>
                    <?php endif; ?>
                </section>
            </aside>
        </form>
<?= $this->partial('admin/_console_end') ?>
