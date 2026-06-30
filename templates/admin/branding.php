<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Branding');
$this->section('robots', 'noindex, nofollow');
$sel = static fn (string $v, string $cur): string => $v === $cur ? ' selected' : '';
?>
<div class="admin">
    <header class="admin-head">
        <span>
            <span class="eyebrow">Operator desk</span>
            <h1>Branding</h1>
        </span>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <nav class="subnav admin-subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <a href="/admin/users">Users</a>
        <a href="/admin/email">Email</a>
        <a href="/admin/tags">Tags</a>
        <a class="active" href="/admin/branding">Branding</a>
        <?php if (!empty($features['appeals'])): ?><a href="/mod/appeals">Appeals</a><?php endif; ?>
    </nav>

    <div class="admin-pane">
        <p class="pane-intro">Tune the public name, colour accents, assets, and preview before the council sees the updated hall.</p>

        <section class="card brand-cols">
            <form method="post" action="/admin/branding" class="stacked" enctype="multipart/form-data" data-brand-form>
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Site name</span>
                    <input type="text" name="site_name" class="input" maxlength="80" value="<?= $e($site_name) ?>" required data-brand-name>
                </label>
                <?php if (!empty($errors['site_name'])): ?><p class="field-error"><?= $e($errors['site_name']) ?></p><?php endif; ?>

                <label class="field">
                    <span>Primary colour (hex, e.g. #2f6fed)</span>
                    <input type="text" name="color_primary" class="input" maxlength="7" placeholder="#2f6fed" value="<?= $e($color_primary) ?>" data-brand-primary>
                </label>
                <?php if (!empty($errors['color_primary'])): ?><p class="field-error"><?= $e($errors['color_primary']) ?></p><?php endif; ?>

                <label class="field">
                    <span>Accent colour (hex)</span>
                    <input type="text" name="color_accent" class="input" maxlength="7" placeholder="#7c3aed" value="<?= $e($color_accent) ?>" data-brand-accent>
                </label>
                <?php if (!empty($errors['color_accent'])): ?><p class="field-error"><?= $e($errors['color_accent']) ?></p><?php endif; ?>

                <label class="field">
                    <span>Default theme for signed-out visitors</span>
                    <select name="theme_default" class="input" data-brand-theme>
                        <option value="system"<?= $sel('system', $theme_default) ?>>System</option>
                        <option value="light"<?= $sel('light', $theme_default) ?>>Light</option>
                        <option value="dark"<?= $sel('dark', $theme_default) ?>>Dark</option>
                    </select>
                </label>

                <label class="field">
                    <span>Theme preset</span>
                    <select name="theme_preset" class="input">
                        <option value="classic"<?= $sel('classic', $theme_preset) ?>>Classic</option>
                        <option value="retro"<?= $sel('retro', $theme_preset) ?>>Retro</option>
                    </select>
                </label>

                <label class="field">
                    <span>Logo<?php if ($logo_path !== ''): ?> <span class="muted">(current set)</span><?php endif; ?></span>
                    <input type="file" name="logo" accept="image/*" class="input">
                </label>
                <label class="field">
                    <span>Light theme logo<?php if ($logo_light_path !== ''): ?> <span class="muted">(current set)</span><?php endif; ?></span>
                    <input type="file" name="logo_light" accept="image/*" class="input">
                </label>
                <label class="field">
                    <span>Dark theme logo<?php if ($logo_dark_path !== ''): ?> <span class="muted">(current set)</span><?php endif; ?></span>
                    <input type="file" name="logo_dark" accept="image/*" class="input">
                </label>
                <label class="field">
                    <span>Favicon<?php if ($favicon_path !== ''): ?> <span class="muted">(current set)</span><?php endif; ?></span>
                    <input type="file" name="favicon" accept="image/*" class="input">
                </label>

                <?php if (!empty($custom_css_available)): ?>
                    <label class="checkline">
                        <input type="checkbox" name="custom_css_enabled" value="1"<?= !empty($custom_css_enabled) ? ' checked' : '' ?>>
                        <span>Enable custom CSS</span>
                    </label>
                    <label class="field">
                        <span>Custom CSS</span>
                        <textarea name="custom_css" class="input code-area" rows="8" maxlength="12000" spellcheck="false"><?= $e($custom_css) ?></textarea>
                    </label>
                    <label class="checkline">
                        <input type="checkbox" name="custom_css_ack" value="1">
                        <span>I understand this CSS applies site-wide and can affect usability.</span>
                    </label>
                    <?php if (!empty($errors['custom_css'])): ?><p class="field-error"><?= $e($errors['custom_css']) ?></p><?php endif; ?>
                <?php else: ?>
                    <p class="muted">Custom CSS is saved behind the custom_css feature flag and is not available on this install.</p>
                <?php endif; ?>

                <button class="btn" type="submit">Save branding</button>
            </form>

            <section class="brand-preview" data-brand-preview aria-live="polite">
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
                <p class="muted">Light and dark logo variants are used when the resolved theme explicitly matches that variant; system theme falls back to the base logo.</p>
            </section>
        </section>

        <form method="post" action="/admin/branding" class="stacked card">
            <?= $this->csrfField() ?>
            <input type="hidden" name="reset" value="1">
            <button class="btn btn-secondary" type="submit">Reset to defaults</button>
        </form>
    </div>
</div>
