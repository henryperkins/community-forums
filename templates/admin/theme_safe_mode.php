<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Theme safe mode');
$this->section('variant', 'plain');
$this->section('robots', 'noindex, nofollow');
$brandingNote = !empty($features['branding'])
    ? 'Saved branding still applies.'
    : 'Branding is disabled; its saved settings are kept.';
$brandingStatusNote = !empty($features['branding'])
    ? 'saved branding still applies.'
    : 'branding is disabled and its saved settings are kept.';
?>
<div class="safe-mode-recovery">
    <?php /*
       Recovery pages stay chrome-less on purpose (variant=plain, ADR 0024 C-21):
       this is the page you reach when a theme package has broken the site, so it
       must not depend on the console chrome it might be used to repair. A single
       link back to Themes replaces the area tier.
    */ ?>
    <header class="safe-mode-head">
        <h1>Theme safe mode</h1>
        <span class="pill pill-admin">Recovery</span>
    </header>
    <?= $this->partial('partials/back_link', ['href' => '/admin/themes', 'label' => 'Themes']) ?>
    <p class="pane-intro">Safe mode pauses package themes without uninstalling them. <?= $e($brandingNote) ?></p>
    <?php if (!empty($features['branding'])): ?>
        <p>If colours, logos or the preset need restoring, <a href="/admin/branding#branding-reset">review the branding reset</a>.</p>
    <?php endif; ?>

    <?php foreach (($errors ?? []) as $field => $err): ?>
        <?php if ($field !== 'current_password'): ?>
            <p class="field-error" role="alert"><?= $e($err) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>

    <section class="card safe-mode-status-card">
        <h2>Status</h2>
        <?php if (!empty($safe_mode)): ?>
            <p class="safe-mode-status is-on">Safe mode is on. Package themes are paused; <?= $e($brandingStatusNote) ?></p>
        <?php else: ?>
            <p class="safe-mode-status">Safe mode is off.</p>
        <?php endif; ?>
        <?php if (!empty($forced_safe_mode)): ?>
            <p class="safe-mode-forced">The environment override is forcing safe mode. Remove THEME_SAFE_MODE=1 before an exit here can take effect.</p>
        <?php endif; ?>
    </section>

    <?php if (empty($safe_mode)): ?>
        <section class="card safe-mode-action-card">
            <h2>Enter safe mode</h2>
            <form method="post" action="/admin/themes/safe-mode">
                <?= $this->csrfField() ?>
                <button class="btn btn-secondary" type="submit">Enter safe mode</button>
            </form>
        </section>
    <?php else: ?>
        <section class="card safe-mode-action-card">
            <h2>Exit safe mode</h2>
            <?php if (!empty($forced_safe_mode)): ?>
                <p class="muted">Environment-forced safe mode cannot be exited from this page.</p>
            <?php else: ?>
                <form method="post" action="/admin/themes/safe-mode">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="exit" value="1">
                    <label class="reauth-field">
                        <span>Current password</span>
                        <input class="input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password', 'err-theme-safe-mode-password') ?> required>
                    </label>
                    <?= field_error($errors ?? [], 'current_password', 'err-theme-safe-mode-password') ?>
                    <button class="btn btn-secondary" type="submit">Exit safe mode</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
