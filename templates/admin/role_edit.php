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
    <?= $this->partial('admin/_nav', ['active' => 'roles', 'features' => $features ?? []]) ?>

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
                <?php foreach ($catalogue as $key => $meta): $enforced = \App\Security\EnforcedCapabilities::has($key); ?>
                    <label>
                        <input type="checkbox" name="capabilities[]" value="<?= $e($key) ?>" <?= in_array($key, $checked, true) ? 'checked' : '' ?><?= $enforced ? '' : ' disabled' ?>>
                        <code><?= $e($key) ?></code> - <?= $e($meta['consent'] ?? $meta['description']) ?>
                        <?php if (!$enforced): ?><span class="muted">(not yet enforceable)</span><?php endif; ?>
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

    <?php if (!$isSystem): ?>
    <?php
    $boardNames = [];
    foreach (($boards ?? []) as $b) {
        $boardNames[(int) $b['id']] = (string) $b['name'];
    }
    $renewAssignmentId = (int) ($old['renew_assignment_id'] ?? 0);
    $renewErrorContext = isset($old['renew_assignment_id']);
    ?>
    <section class="card">
        <h2>Assignments</h2>
        <?php if (empty($assignments)): ?>
        <p class="muted">No one has been assigned this role yet.</p>
        <?php else: ?>
        <table class="audit">
            <thead><tr><th>Member</th><th>Scope</th><th>Window</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><a href="/u/<?= $e($a['username']) ?>">@<?= $e($a['username']) ?></a></td>
                    <td><?= $e((string) $a['scope_type']) ?><?php if ($a['scope_id'] !== null): ?> &mdash; <?= $e($boardNames[(int) $a['scope_id']] ?? ('#' . (int) $a['scope_id'])) ?><?php endif; ?></td>
                    <td><?= $e((string) ($a['starts_at'] ?? 'now')) ?> &rarr; <?= $e((string) ($a['ends_at'] ?? 'no expiry')) ?></td>
                    <td><span class="state state-<?= $e((string) $a['status']) ?>"><?= $e((string) $a['status']) ?></span></td>
                    <td>
                        <?php if ($a['status'] !== 'revoked'): ?>
                        <form method="post" action="/admin/role-assignments/<?= (int) $a['id'] ?>/revoke" class="inline">
                            <?= $this->csrfField() ?>
                            <button class="linkbtn danger" type="submit">Revoke</button>
                        </form>
                        <form method="post" action="/admin/role-assignments/<?= (int) $a['id'] ?>/renew" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input type="text" class="input" name="ends_at" placeholder="YYYY-MM-DD HH:MM" aria-label="New expiry (UTC) for @<?= $e($a['username']) ?>" value="<?= $renewAssignmentId === (int) $a['id'] ? $e((string) ($old['renew']['ends_at'] ?? '')) : '' ?>" required>
                            <input type="password" class="input" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                            <button class="btn btn-small" type="submit">Renew</button>
                        </form>
                        <?php if ($renewAssignmentId === (int) $a['id']): ?>
                            <?php if (!empty($errors['ends_at'])): ?><p class="field-error"><?= $e($errors['ends_at']) ?></p><?php endif; ?>
                            <?php if (!empty($errors['assignment'])): ?><p class="field-error"><?= $e($errors['assignment']) ?></p><?php endif; ?>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Assign this role</h2>
        <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>/assignments" class="stacked">
            <?= $this->csrfField() ?>
            <label>Member username
                <input type="text" name="username" maxlength="32" value="<?= $e((string) ($old['assignment']['username'] ?? '')) ?>" required>
            </label>
            <?php if (!empty($errors['username'])): ?><p class="field-error"><?= $e($errors['username']) ?></p><?php endif; ?>

            <label>Scope
                <select name="scope_type">
                    <?php foreach (['site' => 'Site-wide', 'board' => 'A single board', 'category' => 'A single category'] as $value => $label): ?>
                        <option value="<?= $e($value) ?>"<?= ($old['assignment']['scope_type'] ?? 'site') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($errors['scope_type'])): ?><p class="field-error"><?= $e($errors['scope_type']) ?></p><?php endif; ?>

            <label>Board/category id <span class="muted">(leave blank for site-wide)</span>
                <input type="text" name="scope_id" list="assignment-board-options" value="<?= $e((string) ($old['assignment']['scope_id'] ?? '')) ?>">
                <datalist id="assignment-board-options">
                    <?php foreach (($boards ?? []) as $b): ?><option value="<?= (int) $b['id'] ?>" label="<?= $e((string) $b['name']) ?>"><?php endforeach; ?>
                </datalist>
            </label>
            <?php if (!empty($errors['scope_id'])): ?><p class="field-error"><?= $e($errors['scope_id']) ?></p><?php endif; ?>

            <label>Starts (UTC) <span class="muted">(optional — blank starts now)</span>
                <input type="text" name="starts_at" placeholder="YYYY-MM-DD HH:MM" value="<?= $e((string) ($old['assignment']['starts_at'] ?? '')) ?>">
            </label>
            <?php if (!$renewErrorContext && !empty($errors['starts_at'])): ?><p class="field-error"><?= $e($errors['starts_at']) ?></p><?php endif; ?>

            <label>Ends (UTC) <span class="muted">(optional — blank never expires)</span>
                <input type="text" name="ends_at" placeholder="YYYY-MM-DD HH:MM" value="<?= $e((string) ($old['assignment']['ends_at'] ?? '')) ?>">
            </label>
            <?php if (!$renewErrorContext && !empty($errors['ends_at'])): ?><p class="field-error"><?= $e($errors['ends_at']) ?></p><?php endif; ?>

            <label>Reason (optional)
                <input type="text" name="reason" maxlength="255" value="<?= $e((string) ($old['assignment']['reason'] ?? '')) ?>">
            </label>

            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>

            <div class="form-actions"><button class="btn" type="submit">Assign role</button></div>
        </form>
    </section>
    <?php endif; ?>
    </div>
</div>
