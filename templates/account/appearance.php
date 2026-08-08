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
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'appearance']) ?>

        <div class="settings-pane">
    <form method="post" action="/settings/appearance" class="stacked scribe-panel">
        <h2 class="scribe-panel-head">Theme</h2>
        <?= $this->csrfField() ?>
        <div class="field">
            <div class="choice-cards">
                <label class="choice-card"><input type="radio" name="theme" value="light"<?= $theme === 'light' ? ' checked' : '' ?>>
                    <span class="theme-swatch swatch-parchment"><span class="sw-bg"></span><span class="sw-card"></span><span class="sw-accent"></span></span>
                    <span class="choice-card-title">Parchment</span><span class="choice-card-desc">Warm paper — daylight</span></label>
                <label class="choice-card"><input type="radio" name="theme" value="dark"<?= $theme === 'dark' ? ' checked' : '' ?>>
                    <span class="theme-swatch swatch-twilight"><span class="sw-bg"></span><span class="sw-card"></span><span class="sw-accent"></span></span>
                    <span class="choice-card-title">Twilight</span><span class="choice-card-desc">Evergreen night</span></label>
                <label class="choice-card"><input type="radio" name="theme" value="system"<?= $theme === 'system' ? ' checked' : '' ?>>
                    <span class="theme-swatch swatch-system"><span class="sw-bg"></span><span class="sw-card"></span><span class="sw-accent"></span></span>
                    <span class="choice-card-title">System</span><span class="choice-card-desc">Match your device</span></label>
            </div>
        </div>
        <h3 class="account-subhead">Density</h3>
        <div class="field">
            <div class="choice-cards">
                <label class="choice-card"><input type="radio" name="density" value="comfortable"<?= $density === 'comfortable' ? ' checked' : '' ?>>
                    <span class="density-prev"><span></span><span></span><span></span></span>
                    <span class="choice-card-title">Comfortable</span><span class="choice-card-desc">A card per topic — for reading</span></label>
                <label class="choice-card"><input type="radio" name="density" value="compact"<?= $density === 'compact' ? ' checked' : '' ?>>
                    <span class="density-prev is-compact"><span></span><span></span><span></span><span></span></span>
                    <span class="choice-card-title">Compact</span><span class="choice-card-desc">One line per topic — for triage</span></label>
            </div>
        </div>
        <label class="field field-narrow">
            <span>Font size</span>
            <select name="font_size" class="input">
                <option value="small"<?= $sel('small', $font) ?>>Small</option>
                <option value="medium"<?= $sel('medium', $font) ?>>Medium</option>
                <option value="large"<?= $sel('large', $font) ?>>Large</option>
            </select>
        </label>
        <label class="switchline"><input class="switch" type="checkbox" role="switch" name="reduced_motion" value="1"<?= $motion ? ' checked' : '' ?>><span class="switch-text">Reduce motion and animations</span></label>
        <button class="btn" type="submit">Save appearance</button>
    </form>

    <section class="scribe-panel">
        <div class="account-actions">
            <p>Download a copy of your appearance, reading, and composing preferences, or reset them to defaults.</p>
            <div class="account-actions-group">
                <a class="btn btn-secondary btn-small" href="/settings/preferences/export" download>Export preferences</a>
                <form method="post" action="/settings/preferences/reset">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-ghost btn-small" type="submit">Reset to defaults</button>
                </form>
            </div>
        </div>
    </section>
        </div>
    </div>
</div>
