<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Branding');
$this->section('robots', 'noindex, nofollow');
$sel = static fn (string $v, string $cur): string => $v === $cur ? ' selected' : '';
?>
<div class="card">
    <h1>Branding</h1>
    <p class="muted">Replace the placeholder name, colours, logo, and favicon with your community’s own. Everything falls back to safe defaults if left blank or invalid.</p>

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
            <span>Logo<?php if ($logo_path !== ''): ?> <span class="muted">(current set)</span><?php endif; ?></span>
            <input type="file" name="logo" accept="image/*" class="input">
        </label>
        <label class="field">
            <span>Favicon<?php if ($favicon_path !== ''): ?> <span class="muted">(current set)</span><?php endif; ?></span>
            <input type="file" name="favicon" accept="image/*" class="input">
        </label>

        <button class="btn" type="submit">Save branding</button>
    </form>

    <section class="brand-preview" data-brand-preview aria-live="polite">
        <p class="muted">Live preview</p>
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
        <p class="muted">Separate light/dark logo variants are deferred; uploaded logos should work on both themes for Gate A.</p>
    </section>

    <form method="post" action="/admin/branding" class="stacked card">
        <?= $this->csrfField() ?>
        <input type="hidden" name="reset" value="1">
        <button class="btn btn-secondary" type="submit">Reset to defaults</button>
    </form>
</div>
