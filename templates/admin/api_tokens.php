<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'API tokens');
?>
<div class="admin">
    <header class="admin-head">
        <h1>API tokens</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a class="active" href="/admin/api-tokens">API tokens</a>
    </nav>

    <div class="admin-pane">
    <?php if (!empty($new_token)): ?>
        <div class="flash" role="status">
            <strong>Copy this token now — it will not be shown again:</strong>
            <code><?= $e($new_token) ?></code>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Create a token</h2>
        <form method="post" action="/admin/api-tokens" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>

            <fieldset>
                <legend>Scopes</legend>
                <?php foreach ($scopes_catalogue as $scope => $desc): ?>
                    <label><input type="checkbox" name="scopes[]" value="<?= $e($scope) ?>"> <?= $e($scope) ?> — <?= $e($desc) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['scopes'])): ?><p class="field-error"><?= $e($errors['scopes']) ?></p><?php endif; ?>

            <label>Expires in days (optional)
                <input type="number" name="expires_in_days" min="1" max="365">
            </label>
            <?php if (!empty($errors['expires_in_days'])): ?><p class="field-error"><?= $e($errors['expires_in_days']) ?></p><?php endif; ?>

            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>

            <div class="form-actions"><button class="btn" type="submit">Create token</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Tokens</h2>
        <table class="audit">
            <thead><tr><th>Name</th><th>Scopes</th><th>Created</th><th>Last used</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tokens as $t): ?>
                <tr>
                    <td><?= $e($t['name']) ?></td>
                    <td><?= $e(implode(', ', json_decode((string) $t['scopes'], true) ?: [])) ?></td>
                    <td><?= $e((string) $t['created_at']) ?></td>
                    <td><?= $e((string) ($t['last_used_at'] ?? '—')) ?></td>
                    <td><?php $tRevoked = (bool) $t['revoked_at']; ?><span class="state state-<?= $tRevoked ? 'revoked' : 'active' ?>"><?= $tRevoked ? 'revoked' : 'active' ?></span></td>
                    <td>
                        <?php if (!$t['revoked_at']): ?>
                        <form method="post" action="/admin/api-tokens/<?= (int) $t['id'] ?>/revoke" class="inline">
                            <?= $this->csrfField() ?>
                            <button class="linkbtn danger" type="submit">Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    </div>
</div>
