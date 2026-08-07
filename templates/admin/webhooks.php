<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Webhooks');
$this->section('variant', 'admin');
$selectedEvents = (array) ($old['events'] ?? []);
?>
<?= $this->partial('admin/_console', ['area' => 'integrations', 'tab' => 'webhooks', 'pane_class' => 'admin-integrations integrations-webhooks']) ?>
    <?php if (!empty($new_secret)): ?>
        <div class="flash flash-secret" role="status">
            <strong>Copy this signing secret now — it will not be shown again:</strong>
            <code><?= $e($new_secret) ?></code>
        </div>
    <?php endif; ?>

    <div class="integrations-two-up">
        <section class="card integrations-form-card">
            <h2>Register an endpoint</h2>
            <?php // Six, not the design's three: webhooks.max_attempts is 6 (config/config.php). ?>
            <p class="integrations-card-intro">Deliveries are signed with a per-endpoint secret and retried up to six times before they go dead.</p>
            <?php if (empty($features['first_party_hooks'])): ?>
                <p class="integrations-card-intro">Domain events are inactive while <code>first_party_hooks</code> is off. The <code>ping</code> test event remains available.</p>
            <?php else: ?>
                <p class="integrations-card-intro">Domain events are active for public-board content. The <code>ping</code> test event remains admin-only.</p>
            <?php endif; ?>
            <form method="post" action="/admin/webhooks" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Name</span>
                    <input class="input" type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? '') ?>" placeholder="Ops bridge"<?= field_attrs($errors ?? [], 'name') ?> required>
                </label>
                <?= field_error($errors ?? [], 'name') ?>

                <label class="field">
                    <span>URL</span>
                    <input class="input integrations-url-input" type="url" name="url" maxlength="512" value="<?= $e($old['url'] ?? '') ?>" placeholder="https://"<?= field_attrs($errors ?? [], 'url') ?> required>
                </label>
                <?= field_error($errors ?? [], 'url') ?>

                <fieldset class="integrations-checkset">
                    <legend>Events</legend>
                    <?php foreach ($events_catalogue as $event => $desc): ?>
                        <label class="integrations-check">
                            <input type="checkbox" name="events[]" value="<?= $e($event) ?>"<?= in_array($event, $selectedEvents, true) ? ' checked' : '' ?>>
                            <span><code><?= $e($event) ?></code> — <?= $e($desc) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?= field_error($errors ?? [], 'events') ?>

                <label class="field">
                    <span>Confirm your password</span>
                    <input class="input integrations-secret-input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password') ?> required>
                </label>
                <?= field_error($errors ?? [], 'current_password') ?>

                <div class="form-actions"><button class="btn btn-small" type="submit">Register endpoint</button></div>
            </form>
        </section>

        <section class="card integrations-table-card">
            <div class="table-scroll" tabindex="0" role="region" aria-label="Webhook endpoints">
                <table class="audit integrations-table integrations-hooks-table">
                    <thead><tr><th scope="col">Name</th><th scope="col">URL</th><th scope="col">Status</th><th scope="col" class="integrations-col-numeric">Last status</th><th scope="col" class="integrations-col-actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($webhooks as $w): ?>
                        <tr>
                            <td class="integrations-strong"><?= $e($w['name']) ?></td>
                            <td class="integrations-mono"><?= $e($w['url']) ?></td>
                            <td><?php $wActive = ((int) $w['is_active']) === 1; ?><span class="state state-<?= $wActive ? 'active' : 'paused' ?>"><?= $wActive ? 'active' : 'paused' ?></span></td>
                            <td class="integrations-col-numeric"><?= $e((string) ($w['last_status'] ?? '—')) ?></td>
                            <?php // Stays an <a>: the design's Manage is a button, but navigation must be a link (PE). ?>
                            <td class="integrations-col-actions"><a class="btn btn-secondary btn-small" href="/admin/webhooks/<?= (int) $w['id'] ?>">Manage</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($webhooks)): ?>
                        <tr><td colspan="5" class="integrations-empty">No endpoints yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?= $this->partial('admin/_console_end') ?>
