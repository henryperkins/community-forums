<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'API tokens');
$selectedScopes = array_values(array_filter((array) ($old['scopes'] ?? []), 'is_string'));
?>
<div class="admin">
    <header class="admin-head">
        <h1>API tokens</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'api_tokens', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <?php if (!empty($conflict)): ?>
        <div class="flash flash-error" role="alert">
            That token request was already processed. No new token was minted — the original was shown once. Start again if you still need one.
        </div>
    <?php endif; ?>
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
            <?php $mintKey = (string) ($old['idempotency_key'] ?? '') !== '' ? (string) $old['idempotency_key'] : bin2hex(random_bytes(16)); ?>
            <input type="hidden" name="idempotency_key" value="<?= $e($mintKey) ?>">
            <label>Name
                <input type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>

            <fieldset>
                <legend>Scopes</legend>
                <?php foreach ($scopes_catalogue as $scope => $desc): ?>
                    <label><input type="checkbox" name="scopes[]" value="<?= $e($scope) ?>"<?= in_array((string) $scope, $selectedScopes, true) ? ' checked' : '' ?>> <?= $e($scope) ?> — <?= $e($desc) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['scopes'])): ?><p class="field-error"><?= $e($errors['scopes']) ?></p><?php endif; ?>

            <label>Expires in days (optional)
                <input type="number" name="expires_in_days" min="1" max="365" value="<?= $e($old['expires_in_days'] ?? '') ?>">
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
        <div class="table-scroll" tabindex="0" role="region" aria-label="API tokens">
        <table class="audit">
            <thead><tr><th scope="col">Name</th><th scope="col">Scopes</th><th scope="col">Created</th><th scope="col">Last used</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
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
                            <button class="linkbtn danger" type="submit" aria-label="Revoke the <?= $e($t['name']) ?> token">Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tokens)): ?>
                <tr><td colspan="6" class="muted">No tokens yet. Tokens are shown once at creation and stored only as hashes.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
    </div>
</div>
