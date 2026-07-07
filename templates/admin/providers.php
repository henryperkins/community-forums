<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Sign-in providers');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Sign-in providers</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'providers', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <p class="muted">Generic OIDC providers are configuration, not code: a pinned HTTPS issuer,
    a client id, and a client secret stored only in the encrypted vault. New providers land
    <strong>disabled</strong> — run “Test connection”, then enable. Builtin providers
    (Google, Apple, GitHub) are configured through environment variables and only shown
    here for visibility. Disabling never deletes linked identities.</p>

    <?php if (!empty($errors['provider'])): ?><p class="field-error" role="alert"><?= $e($errors['provider']) ?></p><?php endif; ?>

    <section class="card">
        <h2>Providers</h2>
        <table class="audit">
            <thead><tr><th>Provider</th><th>Key</th><th>Type</th><th>Issuer</th><th>Health</th><th>Sole-method accounts</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $builtin = ((string) $r['type']) !== 'generic_oidc'; $id = (int) $r['id']; ?>
                <tr>
                    <td><?= $e($r['display_name']) ?></td>
                    <td><code><?= $e($r['provider_key']) ?></code></td>
                    <td><?= $builtin ? 'Builtin (env config)' : 'Generic OIDC' ?></td>
                    <td><?= $e($r['issuer'] ?? '—') ?></td>
                    <td>
                        <?= $e($r['health_status']) ?>
                        <?php if (!empty($r['health_checked_at'])): ?>
                            <span class="muted"><?= $e(human_datetime((string) $r['health_checked_at'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $r['sole_method_count'] ?></td>
                    <td>
                        <?php if ($builtin): ?>
                            <?= !empty($r['env_configured']) ? 'Configured' : 'Not configured' ?>
                        <?php else: ?>
                            <?= !empty($r['is_enabled']) ? 'Enabled' : 'Disabled' ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$builtin): ?>
                            <form method="post" action="/admin/providers/<?= $id ?>/test" class="inline-form">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-small" type="submit">Test connection</button>
                            </form>
                            <?php if (empty($r['is_enabled'])): ?>
                                <form method="post" action="/admin/providers/<?= $id ?>/enable" class="inline-form">
                                    <?= $this->csrfField() ?>
                                    <label>Your password
                                        <input type="password" name="current_password" autocomplete="current-password" required>
                                    </label>
                                    <button class="btn btn-small" type="submit">Enable</button>
                                </form>
                            <?php else: ?>
                                <a href="/admin/providers/<?= $id ?>/disable">Disable…</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted">Set <code>OAUTH_<?= $e(strtoupper((string) $r['provider_key'])) ?>_*</code> env vars</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Add an OIDC provider</h2>
        <form method="post" action="/admin/providers" class="stacked">
            <?= $this->csrfField() ?>
            <label>Provider key
                <input type="text" name="provider_key" maxlength="32" value="<?= $e($old['provider_key'] ?? '') ?>"
                       pattern="[a-z0-9][a-z0-9_-]{1,31}" required
                       aria-describedby="provider-key-help">
            </label>
            <p class="muted" id="provider-key-help">Stable slug used in <code>/auth/{key}/…</code> URLs and identity rows — it cannot be changed later. Lowercase letters, digits, hyphens, underscores.</p>
            <?php if (!empty($errors['provider_key'])): ?><p class="field-error"><?= $e($errors['provider_key']) ?></p><?php endif; ?>

            <label>Display name
                <input type="text" name="display_name" maxlength="190" value="<?= $e($old['display_name'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['display_name'])): ?><p class="field-error"><?= $e($errors['display_name']) ?></p><?php endif; ?>

            <label>Issuer (pinned)
                <input type="url" name="issuer" maxlength="512" value="<?= $e($old['issuer'] ?? '') ?>"
                       placeholder="https://gitlab.com" required>
            </label>
            <p class="muted">Discovery is resolved from <code>{issuer}/.well-known/openid-configuration</code>; the JWKS URL must be same-origin with this issuer.</p>
            <?php if (!empty($errors['issuer'])): ?><p class="field-error"><?= $e($errors['issuer']) ?></p><?php endif; ?>

            <label>Client ID
                <input type="text" name="client_id" maxlength="255" value="<?= $e($old['client_id'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['client_id'])): ?><p class="field-error"><?= $e($errors['client_id']) ?></p><?php endif; ?>

            <label>Client secret
                <input type="password" name="client_secret" autocomplete="off" required>
            </label>
            <p class="muted">Stored write-only in the encrypted service-secret vault (<code>service_secrets</code> must be enabled first); rotate it from the vault, not here.</p>
            <?php if (!empty($errors['client_secret'])): ?><p class="field-error"><?= $e($errors['client_secret']) ?></p><?php endif; ?>

            <label>Claim map (optional JSON)
                <textarea name="claim_map_json" rows="2" placeholder='{"email":"upn"}'><?= $e($old['claim_map_json'] ?? '') ?></textarea>
            </label>
            <p class="muted">Renames the cosmetic claims only (<code>email</code>, <code>email_verified</code>, <code>name</code>, <code>username</code>, <code>picture</code>). The subject claim is always <code>sub</code>.</p>
            <?php if (!empty($errors['claim_map_json'])): ?><p class="field-error"><?= $e($errors['claim_map_json']) ?></p><?php endif; ?>

            <label>Your password (re-authentication)
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>

            <button class="btn" type="submit">Add provider</button>
        </form>
    </section>
    </div>
</div>
