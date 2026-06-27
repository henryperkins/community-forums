<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Appearance');
$this->section('robots', 'noindex, nofollow');
$theme = (string) ($prefs['theme'] ?? 'system');
$density = (string) ($prefs['density'] ?? 'comfortable');
$font = (string) ($prefs['font_size'] ?? 'medium');
$motion = !empty($prefs['reduced_motion']);
$sel = static fn (string $v, string $cur): string => $v === $cur ? ' selected' : '';
?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <form method="post" action="/settings/appearance" class="stacked card">
        <?= $this->csrfField() ?>
        <label class="field">
            <span>Theme</span>
            <select name="theme" class="input">
                <option value="system"<?= $sel('system', $theme) ?>>System (match device)</option>
                <option value="light"<?= $sel('light', $theme) ?>>Light</option>
                <option value="dark"<?= $sel('dark', $theme) ?>>Dark</option>
            </select>
        </label>
        <label class="field">
            <span>Density</span>
            <select name="density" class="input">
                <option value="comfortable"<?= $sel('comfortable', $density) ?>>Comfortable</option>
                <option value="compact"<?= $sel('compact', $density) ?>>Compact</option>
            </select>
        </label>
        <label class="field">
            <span>Font size</span>
            <select name="font_size" class="input">
                <option value="small"<?= $sel('small', $font) ?>>Small</option>
                <option value="medium"<?= $sel('medium', $font) ?>>Medium</option>
                <option value="large"<?= $sel('large', $font) ?>>Large</option>
            </select>
        </label>
        <label class="checkline"><input type="checkbox" name="reduced_motion" value="1"<?= $motion ? ' checked' : '' ?>> Reduce motion and animations</label>
        <button class="btn" type="submit">Save appearance</button>
    </form>

    <div class="stacked card">
        <p class="muted">Download a copy of your appearance, reading, and composing preferences as a JSON file.</p>
        <a class="btn btn-secondary" href="/settings/preferences/export" download>Export preferences</a>
    </div>

    <form method="post" action="/settings/preferences/reset" class="stacked card">
        <?= $this->csrfField() ?>
        <p class="muted">Reset appearance, reading, and composing preferences to their defaults.</p>
        <button class="btn btn-secondary" type="submit">Reset to defaults</button>
    </form>
</div>
