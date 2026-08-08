<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Branding');
$this->section('robots', 'noindex, nofollow');
$this->section('variant', 'admin');
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

        <p class="pane-intro">The chrome this community wears. Colours are stored as hex and resolved into the theme; the preview beside the form updates as you type.</p>

        <form method="post" action="/admin/branding" class="brand-cols" enctype="multipart/form-data" data-brand-form>
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
                        <select id="branding-theme-preset" name="theme_preset" class="input">
                            <option value="classic"<?= $sel('classic', $theme_preset) ?>>Classic</option>
                            <option value="retro"<?= $sel('retro', $theme_preset) ?>>Retro</option>
                        </select>
                    </div>
                </div>

                <h3 class="branding-section-label">Marks</h3>
                <div class="branding-marks">
                    <label class="branding-mark-field">
                        <span>Logo<?php if ($logo_path !== ''): ?> <span class="branding-current">(current set)</span><?php endif; ?></span>
                        <span class="branding-file-control">
                            <span class="branding-mark-action" aria-hidden="true"><?= $logo_path !== '' ? 'Replace' : 'Upload' ?></span>
                            <input type="file" name="logo" accept="image/*" aria-label="<?= $logo_path !== '' ? 'Replace logo' : 'Upload logo' ?>">
                        </span>
                    </label>
                    <label class="branding-mark-field">
                        <span>Favicon<?php if ($favicon_path !== ''): ?> <span class="branding-current">(current set)</span><?php endif; ?></span>
                        <span class="branding-file-control">
                            <span class="branding-mark-action" aria-hidden="true"><?= $favicon_path !== '' ? 'Replace' : 'Upload' ?></span>
                            <input type="file" name="favicon" accept="image/*" aria-label="<?= $favicon_path !== '' ? 'Replace favicon' : 'Upload favicon' ?>">
                        </span>
                    </label>
                    <label class="branding-mark-field">
                        <span>Light theme logo<?php if ($logo_light_path !== ''): ?> <span class="branding-current">(current set)</span><?php endif; ?></span>
                        <span class="branding-file-control">
                            <span class="branding-mark-action" aria-hidden="true"><?= $logo_light_path !== '' ? 'Replace' : 'Upload' ?></span>
                            <input type="file" name="logo_light" accept="image/*" aria-label="<?= $logo_light_path !== '' ? 'Replace light theme logo' : 'Upload light theme logo' ?>">
                        </span>
                    </label>
                    <label class="branding-mark-field">
                        <span>Dark theme logo<?php if ($logo_dark_path !== ''): ?> <span class="branding-current">(current set)</span><?php endif; ?></span>
                        <span class="branding-file-control">
                            <span class="branding-mark-action" aria-hidden="true"><?= $logo_dark_path !== '' ? 'Replace' : 'Upload' ?></span>
                            <input type="file" name="logo_dark" accept="image/*" aria-label="<?= $logo_dark_path !== '' ? 'Replace dark theme logo' : 'Upload dark theme logo' ?>">
                        </span>
                    </label>
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

            <aside class="brand-preview" data-brand-preview>
                <section class="brand-preview-panel" aria-live="polite">
                    <p class="eyebrow">Live preview</p>
                    <div class="brand-preview-shell">
                        <div class="brand-preview-bar">
                            <strong data-brand-preview-name><?= $e($site_name) ?></strong>
                            <span data-brand-preview-theme><?= $e(ucfirst($theme_default)) ?></span>
                        </div>
                        <div class="brand-preview-body">
                            <a href="#">Sample link</a>
                            <button class="btn" type="button">Primary button</button>
                            <span class="brand-preview-accent">Accent marker</span>
                        </div>
                    </div>
                    <p class="brand-preview-note">Light and dark logo variants are used when the resolved theme explicitly matches that variant; system theme falls back to the base logo.</p>
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
