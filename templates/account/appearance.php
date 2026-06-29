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
        <div class="field">
            <span>Theme</span>
            <div class="choice-cards">
                <label class="choice-card"><input type="radio" name="theme" value="light"<?= $theme === 'light' ? ' checked' : '' ?>>
                    <span class="theme-swatch swatch-parchment"><span class="sw-bg"></span><span class="sw-card"></span><span class="sw-accent"></span></span>
                    <span class="choice-card-title">Parchment</span><span class="choice-card-desc">Warm paper — daylight register.</span></label>
                <label class="choice-card"><input type="radio" name="theme" value="dark"<?= $theme === 'dark' ? ' checked' : '' ?>>
                    <span class="theme-swatch swatch-twilight"><span class="sw-bg"></span><span class="sw-card"></span><span class="sw-accent"></span></span>
                    <span class="choice-card-title">Twilight</span><span class="choice-card-desc">Evergreen night register.</span></label>
                <label class="choice-card"><input type="radio" name="theme" value="system"<?= $theme === 'system' ? ' checked' : '' ?>>
                    <span class="theme-swatch swatch-system"><span class="sw-bg"></span><span class="sw-card"></span><span class="sw-accent"></span></span>
                    <span class="choice-card-title">System</span><span class="choice-card-desc">Match your device.</span></label>
            </div>
        </div>
        <div class="field">
            <span>Density</span>
            <div class="choice-cards">
                <label class="choice-card"><input type="radio" name="density" value="comfortable"<?= $density === 'comfortable' ? ' checked' : '' ?>>
                    <span class="density-prev"><span></span><span></span><span></span></span>
                    <span class="choice-card-title">Comfortable</span><span class="choice-card-desc">A card per topic — for reading.</span></label>
                <label class="choice-card"><input type="radio" name="density" value="compact"<?= $density === 'compact' ? ' checked' : '' ?>>
                    <span class="density-prev is-compact"><span></span><span></span><span></span><span></span></span>
                    <span class="choice-card-title">Compact</span><span class="choice-card-desc">One line per topic — for triage.</span></label>
            </div>
        </div>
        <label class="field">
            <span>Font size</span>
            <select name="font_size" class="input">
                <option value="small"<?= $sel('small', $font) ?>>Small</option>
                <option value="medium"<?= $sel('medium', $font) ?>>Medium</option>
                <option value="large"<?= $sel('large', $font) ?>>Large</option>
            </select>
        </label>
        <label class="switchline"><input class="switch" type="checkbox" name="reduced_motion" value="1"<?= $motion ? ' checked' : '' ?>><span class="switch-text">Reduce motion and animations</span></label>
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
