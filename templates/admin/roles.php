<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Roles');
// Group the flat capability catalogue by its middle namespace token
// (core.board.* → Board, core.user.* → User, …) so the checkbox run scans as
// tiers instead of one undifferentiated list.
$groupedCatalogue = [];
foreach ($catalogue as $capKey => $capMeta) {
    $parts = explode('.', (string) $capKey);
    $groupedCatalogue[ucfirst($parts[1] ?? 'other')][$capKey] = $capMeta;
}
ksort($groupedCatalogue);
?>
<div class="admin">
    <header class="admin-head">
        <h1>Roles &amp; capabilities</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'roles', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <p class="muted">Resolver posture: <strong><?= $e($mode ?? 'shadow') ?></strong>
    (<code>CAPABILITIES_MODE</code>). Under <code>shadow</code> the legacy rules decide and the
    resolver only shadow-compares; under <code>enforce</code> the resolver decides and fails
    closed. Unknown mode values run <code>shadow</code> and emit
    <code>capabilities.mode_invalid</code> telemetry. System roles are protected
    compatibility anchors and cannot be edited; clone one to adapt it
    (cloning copies only currently-enforceable capabilities). Try changes safely in the
    <a href="/admin/roles/simulator">permission simulator</a>.</p>

    <section class="card">
        <h2>Roles</h2>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Role definitions">
        <table class="audit">
            <thead><tr><th scope="col">Name</th><th scope="col">Key</th><th scope="col">Kind</th><th scope="col">Version</th><th scope="col">Capabilities</th><th scope="col">Active assignments</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
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
        </div>
    </section>

    <section class="card">
        <h2>Create a custom role</h2>
        <form method="post" action="/admin/roles" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="190" value="<?= $e($old['name'] ?? '') ?>"<?= field_attrs($errors ?? [], 'name') ?> required>
            </label>
            <?= field_error($errors ?? [], 'name') ?>

            <label>Description (optional)
                <input type="text" name="description" maxlength="255" value="<?= $e($old['description'] ?? '') ?>"<?= field_attrs($errors ?? [], 'description') ?>>
            </label>
            <?= field_error($errors ?? [], 'description') ?>

            <?php $checked = (array) ($old['capabilities'] ?? []); ?>
            <?php foreach ($groupedCatalogue as $groupLabel => $groupCaps): ?>
                <fieldset>
                    <legend><?= $e($groupLabel) ?> capabilities (delegable only; protected authority is never offered)</legend>
                    <?php foreach ($groupCaps as $key => $meta): $enforced = \App\Security\EnforcedCapabilities::has($key); ?>
                        <label>
                            <input type="checkbox" name="capabilities[]" value="<?= $e($key) ?>" <?= in_array($key, $checked, true) ? 'checked' : '' ?><?= $enforced ? '' : ' disabled' ?>>
                            <code><?= $e($key) ?></code> - <?= $e($meta['consent'] ?? $meta['description']) ?>
                            <?php if ($meta['risk'] === 'high'): ?><span class="pill">high risk</span><?php endif; ?>
                            <?php if (!$enforced): ?><span class="muted">(not yet enforceable)</span><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
            <?= field_error($errors ?? [], 'capabilities') ?>

            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password') ?> required>
            </label>
            <?= field_error($errors ?? [], 'current_password') ?>

            <div class="form-actions"><button class="btn" type="submit">Create role</button></div>
        </form>
    </section>
    </div>
</div>
