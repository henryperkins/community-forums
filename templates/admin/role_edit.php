<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Role: ' . ($row['role']['name'] ?? ''));
$role = $row['role'];
$isSystem = ((string) $role['kind']) === 'system';
$checked = (array) ($old['capabilities'] ?? $current_keys);
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($role['name']) ?> <small>v<?= (int) $role['version'] ?></small></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/roles">Roles</a>
    </nav>

    <div class="admin-pane">
    <p class="muted">
        <code><?= $e($role['role_key']) ?></code> -
        <?= $isSystem ? 'Protected system anchor (decision #18), read-only.' : 'Custom role.' ?>
        Active assignments affected by changes: <strong><?= (int) $row['impact'] ?></strong>.
    </p>

    <?php if (!$isSystem): ?>
    <section class="card">
        <h2>Edit definition</h2>
        <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="190" value="<?= $e($old['name'] ?? $role['name']) ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>

            <label>Description (optional)
                <input type="text" name="description" maxlength="255" value="<?= $e($old['description'] ?? ($role['description'] ?? '')) ?>">
            </label>
            <?php if (!empty($errors['description'])): ?><p class="field-error"><?= $e($errors['description']) ?></p><?php endif; ?>

            <fieldset>
                <legend>Capabilities</legend>
                <?php foreach ($catalogue as $key => $meta): ?>
                    <label>
                        <input type="checkbox" name="capabilities[]" value="<?= $e($key) ?>" <?= in_array($key, $checked, true) ? 'checked' : '' ?>>
                        <code><?= $e($key) ?></code> - <?= $e($meta['consent'] ?? $meta['description']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['capabilities'])): ?><p class="field-error"><?= $e($errors['capabilities']) ?></p><?php endif; ?>

            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>

            <div class="form-actions"><button class="btn" type="submit">Save (bumps version)</button></div>
        </form>
    </section>
    <?php else: ?>
    <section class="card">
        <h2>Capabilities held</h2>
        <ul>
            <?php foreach ($current_keys as $key): ?><li><code><?= $e($key) ?></code></li><?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="card">
        <h2>Clone into a new custom role</h2>
        <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>/clone" class="stacked">
            <?= $this->csrfField() ?>
            <label>New role name
                <input type="text" name="name" maxlength="190" required>
            </label>
            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <div class="form-actions"><button class="btn" type="submit">Clone</button></div>
        </form>
    </section>
    </div>
</div>
