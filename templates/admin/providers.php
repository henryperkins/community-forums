<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Sign-in providers');
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'integrations', 'tab' => 'providers', 'pane_class' => 'admin-integrations integrations-providers']) ?>
    <?php // The builtin set belongs to ProviderRegistry — the design's shorter clause does not hard-code it. ?>
    <p class="integrations-intro">Generic OIDC providers are configuration, not code: a pinned HTTPS issuer,
    a client id, and a client secret stored only in the encrypted vault. New providers land
    <strong>disabled</strong> — run “Test connection”, then enable. Builtin providers
    are configured through environment variables and shown here for visibility.
    Disabling never deletes linked identities.</p>

    <?= field_error($errors ?? [], 'provider', 'err-provider', alert: true) ?>

    <section class="card integrations-table-card">
        <div class="table-scroll" tabindex="0" role="region" aria-label="Sign-in providers">
            <table class="audit integrations-table integrations-providers-table">
                <thead><tr><th scope="col">Provider</th><th scope="col">Type</th><th scope="col">Issuer</th><th scope="col">Health</th><th scope="col" class="integrations-col-numeric">Sole-method</th><th scope="col">Status</th><th scope="col" class="integrations-col-actions">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $builtin = ((string) $r['type']) !== 'generic_oidc';
                    $id = (int) $r['id'];
                    $isOn = $builtin ? !empty($r['env_configured']) : !empty($r['is_enabled']);
                    $statusText = $builtin
                        ? ($isOn ? 'Configured' : 'Not configured')
                        : ($isOn ? 'Enabled' : 'Disabled');
                    ?>
                    <tr>
                        <td><span class="integrations-strong"><?= $e($r['display_name']) ?></span> <code class="provider-key"><?= $e($r['provider_key']) ?></code></td>
                        <td class="integrations-quiet"><?= $builtin ? 'Builtin (env config)' : 'Generic OIDC' ?></td>
                        <td class="integrations-mono"><?= $e($r['issuer'] ?? '—') ?></td>
                        <?php // health_status is the DB enum (unknown|ok|degraded|down) — production never
                              // emits the design's "reachable"/"never checked" prose. Only the composition
                              // into one middot-joined string is adopted. ?>
                        <td class="provider-health"><?= $e($r['health_status']) ?><?php if (!empty($r['health_checked_at'])): ?><span class="provider-health-when"> · <?= $e(human_datetime((string) $r['health_checked_at'])) ?></span><?php endif; ?></td>
                        <?php // data-sole-count is NOT a PE hook (no JS reads it) — it is the
                              // integration-test anchor for the lockout count (round-2 audit
                              // finding 8 reclassified; see AppAdminProvidersTest). ?>
                        <td class="integrations-col-numeric" data-sole-count="<?= (int) $r['sole_method_count'] ?>"><?= (int) $r['sole_method_count'] ?></td>
                        <td><span class="provider-status<?= $isOn ? ' is-on' : '' ?>"><?= $e($statusText) ?></span></td>
                        <td class="integrations-col-actions">
                            <?php if (!$builtin): ?>
                                <div class="provider-actions">
                                    <form method="post" action="/admin/providers/<?= $id ?>/test" class="inline-form">
                                        <?= $this->csrfField() ?>
                                        <button class="linkbtn integrations-rowbtn" type="submit">Test connection</button>
                                    </form>
                                    <?php if (empty($r['is_enabled'])): ?>
                                        <?php // Enabling a sign-in provider is reauthed; the confirm renders inline,
                                              // always, so it survives with JS off (never behind a disclosure). ?>
                                        <form method="post" action="/admin/providers/<?= $id ?>/enable" class="provider-enable-form">
                                            <?= $this->csrfField() ?>
                                            <label class="field field-compact">
                                                <span>Your password</span>
                                                <input class="input integrations-secret-input" type="password" name="current_password" autocomplete="current-password"<?= ($enable_error_id ?? null) === $id ? field_attrs($errors ?? [], 'enable_password', 'err-enable-' . $id) : '' ?> required>
                                            </label>
                                            <button class="linkbtn integrations-rowbtn" type="submit">Enable</button>
                                            <?= ($enable_error_id ?? null) === $id ? field_error($errors ?? [], 'enable_password', 'err-enable-' . $id, alert: true) : '' ?>
                                        </form>
                                    <?php else: ?>
                                        <a class="linkbtn danger integrations-rowbtn" href="/admin/providers/<?= $id ?>/disable">Disable…</a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="integrations-env-hint">Set <code>OAUTH_<?= $e(strtoupper((string) $r['provider_key'])) ?>_*</code> env vars</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card integrations-add-card">
        <h2>Add an OIDC provider</h2>
        <form method="post" action="/admin/providers" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Provider key</span>
                <input class="input integrations-mono-input" type="text" name="provider_key" maxlength="32" value="<?= $e($old['provider_key'] ?? '') ?>"
                       pattern="[a-z0-9][a-z0-9_-]{1,31}" placeholder="gitlab" required
                       <?= field_attrs($errors ?? [], 'provider_key', describedBy: 'provider-key-help') ?>>
            </label>
            <p class="integrations-hint" id="provider-key-help">Stable slug used in <code>/auth/{key}/…</code> URLs and identity rows — it cannot be changed later. Lowercase letters, digits, hyphens, underscores.</p>
            <?= field_error($errors ?? [], 'provider_key') ?>

            <label class="field">
                <span>Display name</span>
                <input class="input" type="text" name="display_name" maxlength="190" value="<?= $e($old['display_name'] ?? '') ?>" placeholder="GitLab"<?= field_attrs($errors ?? [], 'display_name') ?> required>
            </label>
            <?= field_error($errors ?? [], 'display_name') ?>

            <label class="field">
                <span>Issuer (pinned)</span>
                <input class="input integrations-url-input" type="url" name="issuer" maxlength="512" value="<?= $e($old['issuer'] ?? '') ?>"
                       placeholder="https://gitlab.com"<?= field_attrs($errors ?? [], 'issuer') ?> required>
            </label>
            <p class="integrations-hint">Discovery is resolved from <code>{issuer}/.well-known/openid-configuration</code>; the JWKS URL must be same-origin with this issuer. Enter the issuer exactly as the IdP publishes it — a trailing slash is significant.</p>
            <?= field_error($errors ?? [], 'issuer') ?>

            <div class="integrations-field-grid">
                <label class="field">
                    <span>Client ID</span>
                    <input class="input integrations-mono-input" type="text" name="client_id" maxlength="255" value="<?= $e($old['client_id'] ?? '') ?>"<?= field_attrs($errors ?? [], 'client_id') ?> required>
                </label>
                <label class="field">
                    <span>Client secret</span>
                    <input class="input integrations-secret-input" type="password" name="client_secret" autocomplete="off"<?= field_attrs($errors ?? [], 'client_secret') ?> required>
                </label>
            </div>
            <p class="integrations-hint">Stored write-only in the encrypted service-secret vault (<code>service_secrets</code> must be enabled first); rotate it from the vault, not here.</p>
            <?= field_error($errors ?? [], 'client_id') ?>
            <?= field_error($errors ?? [], 'client_secret') ?>

            <label class="field">
                <span>Claim map (optional JSON)</span>
                <textarea class="input integrations-mono-input" name="claim_map_json" rows="2" placeholder='{"email":"upn"}'<?= field_attrs($errors ?? [], 'claim_map_json') ?>><?= $e($old['claim_map_json'] ?? '') ?></textarea>
            </label>
            <p class="integrations-hint">Renames the cosmetic claims only (<code>email</code>, <code>email_verified</code>, <code>name</code>, <code>username</code>, <code>picture</code>). The subject claim is always <code>sub</code>.</p>
            <?= field_error($errors ?? [], 'claim_map_json') ?>

            <label class="field">
                <span>Your password (re-authentication)</span>
                <input class="input integrations-secret-input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password') ?> required>
            </label>
            <?= field_error($errors ?? [], 'current_password') ?>

            <div class="form-actions"><button class="btn btn-small" type="submit">Add provider</button></div>
        </form>
    </section>
<?= $this->partial('admin/_console_end') ?>
