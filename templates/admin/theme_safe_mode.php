<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Theme safe mode');
$this->section('variant', 'plain');
$this->section('robots', 'noindex, nofollow');
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
    <a class="admin-back" href="/admin/themes">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Themes
    </a>
    <p class="pane-intro">Safe mode returns the site to the built-in chrome without uninstalling anything.</p>

    <?php foreach (($errors ?? []) as $field => $err): ?>
        <?php if ($field !== 'current_password'): ?>
            <p class="field-error" role="alert"><?= $e($err) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>

    <section class="card safe-mode-status-card">
        <h2>Status</h2>
        <?php if (!empty($safe_mode)): ?>
            <p class="safe-mode-status is-on">Safe mode is on. Every visitor sees the built-in chrome, whatever is installed.</p>
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
