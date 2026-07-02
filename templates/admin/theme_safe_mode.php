<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Theme safe mode');
$this->section('variant', 'plain');
$this->section('robots', 'noindex, nofollow');
?>
<div class="container">
    <header class="admin-head">
        <h1>Theme safe mode</h1>
        <span class="pill pill-admin">Recovery</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'themes', 'features' => $features ?? []]) ?>

    <?php foreach (($errors ?? []) as $err): ?>
        <p class="field-error"><?= $e($err) ?></p>
    <?php endforeach; ?>

    <section class="card">
        <h2>Status</h2>
        <?php if (!empty($safe_mode)): ?>
            <p class="field-error">Safe mode is on. The built-in system theme is being served.</p>
        <?php else: ?>
            <p class="muted">Safe mode is off.</p>
        <?php endif; ?>
        <?php if (!empty($forced_safe_mode)): ?>
            <p class="field-error">The environment override is forcing safe mode. Remove THEME_SAFE_MODE=1 before exiting here can take effect.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Enter safe mode</h2>
        <form method="post" action="/admin/themes/safe-mode" class="stacked">
            <?= $this->csrfField() ?>
            <button type="submit">Enter safe mode</button>
        </form>
    </section>

    <section class="card">
        <h2>Exit safe mode</h2>
        <?php if (!empty($forced_safe_mode)): ?>
            <p class="muted">Environment-forced safe mode cannot be exited from this page.</p>
        <?php else: ?>
            <form method="post" action="/admin/themes/safe-mode" class="stacked">
                <?= $this->csrfField() ?>
                <input type="hidden" name="exit" value="1">
                <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
                <button type="submit">Exit safe mode</button>
            </form>
        <?php endif; ?>
    </section>
</div>
