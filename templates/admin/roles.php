<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Roles');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Roles &amp; capabilities</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a class="active" href="/admin/roles">Roles</a>
    </nav>

    <div class="admin-pane">
    <p class="muted">Definitions are recorded but <strong>inert</strong>: nothing enforces them until the
    capability resolver passes parity and is enabled. System roles are protected
    compatibility anchors and cannot be edited; clone one to adapt it.</p>

    <section class="card">
        <h2>Roles</h2>
        <table class="audit">
            <thead><tr><th>Name</th><th>Key</th><th>Kind</th><th>Version</th><th>Capabilities</th><th>Active assignments</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $role = $r['role']; ?>
                <tr>
                    <td><?= $e($role['name']) ?></td>
                    <td><code><?= $e($role['role_key']) ?></code></td>
                    <td><?= ((string) $role['kind']) === 'system' ? 'Protected anchor' : 'Custom' ?></td>
                    <td>v<?= (int) $role['version'] ?></td>
                    <td><?= (int) $r['capability_count'] ?></td>
                    <td><?= (int) $r['impact'] ?></td>
                    <td><a href="/admin/roles/<?= (int) $role['id'] ?>"><?= ((string) $role['kind']) === 'system' ? 'View / clone' : 'Edit' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Create a custom role</h2>
        <form method="post" action="/admin/roles" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="190" value="<?= $e($old['name'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>

            <label>Description (optional)
                <input type="text" name="description" maxlength="255" value="<?= $e($old['description'] ?? '') ?>">
            </label>
            <?php if (!empty($errors['description'])): ?><p class="field-error"><?= $e($errors['description']) ?></p><?php endif; ?>

            <fieldset>
                <legend>Capabilities (delegable only; protected authority is never offered)</legend>
                <?php $checked = (array) ($old['capabilities'] ?? []); ?>
                <?php foreach ($catalogue as $key => $meta): ?>
                    <label>
                        <input type="checkbox" name="capabilities[]" value="<?= $e($key) ?>" <?= in_array($key, $checked, true) ? 'checked' : '' ?>>
                        <code><?= $e($key) ?></code> - <?= $e($meta['consent'] ?? $meta['description']) ?>
                        <?php if ($meta['risk'] === 'high'): ?><span class="pill">high risk</span><?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['capabilities'])): ?><p class="field-error"><?= $e($errors['capabilities']) ?></p><?php endif; ?>

            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>

            <div class="form-actions"><button class="btn" type="submit">Create role</button></div>
        </form>
    </section>
    </div>
</div>
