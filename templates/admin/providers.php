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

    <?= field_error($errors ?? [], 'provider', 'err-provider', alert: true) ?>

    <section class="card">
        <h2>Providers</h2>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Sign-in providers">
        <table class="audit">
            <thead><tr><th scope="col">Provider</th><th scope="col">Key</th><th scope="col">Type</th><th scope="col">Issuer</th><th scope="col">Health</th><th scope="col">Sole-method accounts</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
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
                    <?php // data-sole-count is NOT a PE hook (no JS reads it) — it is the
                          // integration-test anchor for the lockout count (round-2 audit
                          // finding 8 reclassified; see AppAdminProvidersTest). ?>
                    <td data-sole-count="<?= (int) $r['sole_method_count'] ?>"><?= (int) $r['sole_method_count'] ?></td>
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
                                        <input type="password" name="current_password" autocomplete="current-password"<?= ($enable_error_id ?? null) === $id ? field_attrs($errors ?? [], 'enable_password', 'err-enable-' . $id) : '' ?> required>
                                    </label>
                                    <?= ($enable_error_id ?? null) === $id ? field_error($errors ?? [], 'enable_password', 'err-enable-' . $id, alert: true) : '' ?>
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
        </div>
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
            <?= field_error($errors ?? [], 'provider_key') ?>

            <label>Display name
                <input type="text" name="display_name" maxlength="190" value="<?= $e($old['display_name'] ?? '') ?>"<?= field_attrs($errors ?? [], 'display_name') ?> required>
            </label>
            <?= field_error($errors ?? [], 'display_name') ?>

            <label>Issuer (pinned)
                <input type="url" name="issuer" maxlength="512" value="<?= $e($old['issuer'] ?? '') ?>"
                       placeholder="https://gitlab.com" required>
            </label>
            <p class="muted">Discovery is resolved from <code>{issuer}/.well-known/openid-configuration</code>; the JWKS URL must be same-origin with this issuer. Enter the issuer exactly as the IdP publishes it — a trailing slash is significant.</p>
            <?= field_error($errors ?? [], 'issuer') ?>

            <label>Client ID
                <input type="text" name="client_id" maxlength="255" value="<?= $e($old['client_id'] ?? '') ?>"<?= field_attrs($errors ?? [], 'client_id') ?> required>
            </label>
            <?= field_error($errors ?? [], 'client_id') ?>

            <label>Client secret
                <input type="password" name="client_secret" autocomplete="off" required>
            </label>
            <p class="muted">Stored write-only in the encrypted service-secret vault (<code>service_secrets</code> must be enabled first); rotate it from the vault, not here.</p>
            <?= field_error($errors ?? [], 'client_secret') ?>

            <label>Claim map (optional JSON)
                <textarea name="claim_map_json" rows="2" placeholder='{"email":"upn"}'><?= $e($old['claim_map_json'] ?? '') ?></textarea>
            </label>
            <p class="muted">Renames the cosmetic claims only (<code>email</code>, <code>email_verified</code>, <code>name</code>, <code>username</code>, <code>picture</code>). The subject claim is always <code>sub</code>.</p>
            <?= field_error($errors ?? [], 'claim_map_json') ?>

            <label>Your password (re-authentication)
                <input type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password') ?> required>
            </label>
            <?= field_error($errors ?? [], 'current_password') ?>

            <button class="btn" type="submit">Add provider</button>
        </form>
    </section>
    </div>
</div>
