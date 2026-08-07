<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'API tokens');
$this->section('variant', 'admin');
$selectedScopes = array_values(array_filter((array) ($old['scopes'] ?? []), 'is_string'));
?>
<?= $this->partial('admin/_console', ['area' => 'integrations', 'tab' => 'api_tokens', 'pane_class' => 'admin-integrations integrations-tokens']) ?>
    <?php if (!empty($conflict)): ?>
        <div class="flash flash-error" role="alert">
            That token request was already processed. No new token was minted — the original was shown once. Start again if you still need one.
        </div>
    <?php endif; ?>
    <?php if (!empty($new_token)): ?>
        <?php // A minted credential is not a success message: the design gives it its own gold plate. ?>
        <div class="flash flash-secret" role="status">
            <strong>Copy this token now — it will not be shown again:</strong>
            <code><?= $e($new_token) ?></code>
        </div>
    <?php endif; ?>

    <div class="integrations-two-up">
        <section class="card integrations-form-card">
            <h2>Create a token</h2>
            <p class="integrations-card-intro">A token carries only the scopes you tick. The secret is shown once, on creation, and never again.</p>
            <form method="post" action="/admin/api-tokens" class="stacked">
                <?= $this->csrfField() ?>
                <?php $mintKey = (string) ($old['idempotency_key'] ?? '') !== '' ? (string) $old['idempotency_key'] : bin2hex(random_bytes(16)); ?>
                <input type="hidden" name="idempotency_key" value="<?= $e($mintKey) ?>">
                <label class="field">
                    <span>Name</span>
                    <input class="input" type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? '') ?>" placeholder="Read-only mirror"<?= field_attrs($errors ?? [], 'name') ?> required>
                </label>
                <?= field_error($errors ?? [], 'name') ?>

                <fieldset class="integrations-checkset">
                    <legend>Scopes</legend>
                    <?php foreach ($scopes_catalogue as $scope => $desc): ?>
                        <label class="integrations-check">
                            <input type="checkbox" name="scopes[]" value="<?= $e($scope) ?>"<?= in_array((string) $scope, $selectedScopes, true) ? ' checked' : '' ?>>
                            <span><code><?= $e($scope) ?></code> — <?= $e($desc) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?= field_error($errors ?? [], 'scopes') ?>

                <label class="field">
                    <span>Expires in days (optional)</span>
                    <input class="input integrations-number" type="number" name="expires_in_days" min="1" max="365" value="<?= $e($old['expires_in_days'] ?? '') ?>"<?= field_attrs($errors ?? [], 'expires_in_days') ?>>
                </label>
                <?= field_error($errors ?? [], 'expires_in_days') ?>

                <label class="field">
                    <span>Confirm your password</span>
                    <input class="input integrations-secret-input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password') ?> required>
                </label>
                <?= field_error($errors ?? [], 'current_password') ?>

                <div class="form-actions"><button class="btn btn-small" type="submit">Create token</button></div>
            </form>
        </section>

        <section class="card integrations-table-card">
            <div class="table-scroll" tabindex="0" role="region" aria-label="API tokens">
                <table class="audit integrations-table integrations-tokens-table">
                    <thead><tr><th scope="col">Name</th><th scope="col">Scopes</th><th scope="col">Created</th><th scope="col">Last used</th><th scope="col">Status</th><th scope="col" class="integrations-col-actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($tokens as $t): ?>
                        <tr>
                            <td class="integrations-strong"><?= $e($t['name']) ?></td>
                            <td class="integrations-mono"><?= $e(implode(', ', json_decode((string) $t['scopes'], true) ?: [])) ?></td>
                            <td class="integrations-when"><?= $e(human_datetime((string) $t['created_at'])) ?></td>
                            <td class="integrations-when"><?= ($t['last_used_at'] ?? null) !== null ? $e(human_datetime((string) $t['last_used_at'])) : '—' ?></td>
                            <td><?php $tRevoked = (bool) $t['revoked_at']; ?><span class="state state-<?= $tRevoked ? 'revoked' : 'active' ?>"><?= $tRevoked ? 'revoked' : 'active' ?></span></td>
                            <td class="integrations-col-actions">
                                <?php if (!$t['revoked_at']): ?>
                                <form method="post" action="/admin/api-tokens/<?= (int) $t['id'] ?>/revoke" class="inline-form">
                                    <?= $this->csrfField() ?>
                                    <?php // The accessible name disambiguates the row (ADR 0023 §5) — the design's bare "Revoke" repeats on every row. ?>
                                    <button class="linkbtn danger integrations-rowbtn" type="submit" aria-label="Revoke the <?= $e($t['name']) ?> token">Revoke</button>
                                </form>
                                <?php else: ?>
                                    <span class="integrations-placeholder">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tokens)): ?>
                        <tr><td colspan="6" class="integrations-empty">No tokens yet. Tokens are shown once at creation and stored only as hashes.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?= $this->partial('admin/_console_end') ?>
