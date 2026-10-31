<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Appearance');
$this->section('robots', 'noindex, nofollow');
// /brand.css carries each mode's resolved colours for the thumbnails below.
$this->section('branding_preview', '1');
$theme = (string) ($prefs['theme'] ?? 'system');
// The base theme each mode belongs to: the active package, else the preset.
$themeContext = (string) ($theme_context ?? 'Classic');
$thumb = static fn (string $mode): string => '<span class="theme-thumb-mode" data-thumb-mode="' . $mode . '">'
    . '<span class="theme-thumb-bar"><span class="theme-thumb-mark"></span></span>'
    . '<span class="theme-thumb-body">'
    . '<span class="theme-thumb-rail"><span class="is-selected"></span><span></span><span></span></span>'
    . '<span class="theme-thumb-panel"><span class="theme-thumb-line"></span><span class="theme-thumb-line is-faint"></span>'
    . '<span class="theme-thumb-dot"></span><span class="theme-thumb-action"></span></span>'
    . '</span></span>';
$density = (string) ($prefs['density'] ?? 'comfortable');
$font = (string) ($prefs['font_size'] ?? 'medium');
$motion = !empty($prefs['reduced_motion']);
$sel = static fn (string $v, string $cur): string => $v === $cur ? ' selected' : '';
?>
<div class="settings-screen">
    <header class="settings-head">
        <?= $this->partial('partials/page_heading', ['heading' => 'Account settings']) ?>
        <?= $this->partial('partials/settings_intro') ?>
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
                    <span class="theme-thumb" aria-hidden="true"><?= $thumb('light') ?></span>
                    <span class="choice-card-title">Light</span><span class="choice-card-desc"><?= $e($themeContext) ?> · light backgrounds</span></label>
                <label class="choice-card"><input type="radio" name="theme" value="dark"<?= $theme === 'dark' ? ' checked' : '' ?>>
                    <span class="theme-thumb" aria-hidden="true"><?= $thumb('dark') ?></span>
                    <span class="choice-card-title">Dark</span><span class="choice-card-desc"><?= $e($themeContext) ?> · dark backgrounds</span></label>
                <label class="choice-card"><input type="radio" name="theme" value="system"<?= $theme === 'system' ? ' checked' : '' ?>>
                    <span class="theme-thumb theme-thumb-system" aria-hidden="true"><?= $thumb('light') ?><?= $thumb('dark') ?></span>
                    <span class="choice-card-title">System</span><span class="choice-card-desc">Match your device</span></label>
            </div>
        </div>
        <h3 class="account-subhead">Density</h3>
        <div class="field">
            <div class="choice-cards">
                <label class="choice-card"><input type="radio" name="density" value="comfortable"<?= $density === 'comfortable' ? ' checked' : '' ?>>
                    <span class="density-prev"><span></span><span></span><span></span></span>
                    <span class="choice-card-title">Comfortable</span><span class="choice-card-desc">More space for reading</span></label>
                <label class="choice-card"><input type="radio" name="density" value="compact"<?= $density === 'compact' ? ' checked' : '' ?>>
                    <span class="density-prev is-compact"><span></span><span></span><span></span><span></span></span>
                    <span class="choice-card-title">Compact</span><span class="choice-card-desc">Less space for scanning</span></label>
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
