<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Webhook: ' . $webhook['name']);
$this->section('variant', 'admin');
$id = (int) $webhook['id'];
$selected = isset($old['events']) ? (array) $old['events'] : (json_decode((string) $webhook['events'], true) ?: []);
$errorContext = $error_context ?? null;
$isActive = ((int) $webhook['is_active']) === 1;
?>
<?= $this->partial('admin/_console', ['area' => 'integrations', 'tab' => 'webhooks', 'pane_class' => 'admin-integrations integrations-webhook-detail']) ?>
    <a class="admin-back" href="/admin/webhooks">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        All endpoints
    </a>
    <h2 class="admin-record-title"><?= $e($webhook['name']) ?></h2>
    <?php if (!empty($new_secret)): ?>
        <div class="flash flash-secret" role="status">
            <strong>Copy this signing secret now — it will not be shown again:</strong>
            <code><?= $e($new_secret) ?></code>
        </div>
    <?php endif; ?>

    <section class="card webhook-detail-card">
        <?php /*
         * The design puts the action row above the fields in one card. Rotate keeps
         * its password confirm (ADR 0021 / WebhookService::rotateSecret) and renders
         * it inline — never behind a JS disclosure, which would vanish with JS off.
         */ ?>
        <div class="webhook-action-row">
            <form method="post" action="/admin/webhooks/<?= $id ?>/toggle" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                <button class="btn btn-secondary btn-small" type="submit"><?= $isActive ? 'Pause' : 'Resume' ?></button>
            </form>
            <form method="post" action="/admin/webhooks/<?= $id ?>/test" class="inline-form">
                <?= $this->csrfField() ?>
                <button class="btn btn-secondary btn-small" type="submit">Send test event</button>
            </form>
            <form method="post" action="/admin/webhooks/<?= $id ?>/rotate" class="webhook-reauth-form">
                <?= $this->csrfField() ?>
                <label class="field field-compact">
                    <span>Confirm your password</span>
                    <input class="input integrations-secret-input" type="password" name="current_password" autocomplete="current-password"<?= $errorContext === 'rotate' ? field_attrs($errors ?? [], 'current_password', 'err-rotate-current_password') : '' ?> required>
                </label>
                <button class="btn btn-ghost btn-small" type="submit">Rotate signing secret</button>
                <?= $errorContext === 'rotate' ? field_error($errors ?? [], 'current_password', 'err-rotate-current_password') : '' ?>
            </form>
        </div>

        <form id="webhook-config" method="post" action="/admin/webhooks/<?= $id ?>" class="stacked webhook-config-form">
            <?= $this->csrfField() ?>
            <div class="integrations-field-grid">
                <label class="field">
                    <span>Name</span>
                    <input class="input" type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? $webhook['name']) ?>"<?= field_attrs($errors ?? [], 'name') ?> required>
                </label>
                <label class="field">
                    <span>URL</span>
                    <input class="input integrations-url-input" type="url" name="url" maxlength="512" value="<?= $e($old['url'] ?? $webhook['url']) ?>"<?= field_attrs($errors ?? [], 'url') ?> required>
                </label>
            </div>
            <?= field_error($errors ?? [], 'name') ?>
            <?= field_error($errors ?? [], 'url') ?>
            <fieldset class="integrations-checkset">
                <legend>Events</legend>
                <?php foreach ($events_catalogue as $event => $desc): ?>
                    <label class="integrations-check">
                        <input type="checkbox" name="events[]" value="<?= $e($event) ?>"<?= in_array($event, $selected, true) ? ' checked' : '' ?>>
                        <span><code><?= $e($event) ?></code> — <?= $e($desc) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?= field_error($errors ?? [], 'events') ?>
        </form>

        <p class="integrations-danger-note">Deleting removes the endpoint and its delivery history and revokes its signing secret. This cannot be undone.</p>
        <?php /*
         * The design pairs Save and Delete in one row. HTML forms cannot nest and a
         * <button formaction> would post /delete with no current_password — a
         * permanent 422 — so Save reaches its form by id and Delete keeps its own
         * form around the reauth field it needs.
         */ ?>
        <div class="webhook-detail-actions">
            <button class="btn btn-small" type="submit" form="webhook-config">Save</button>
            <form method="post" action="/admin/webhooks/<?= $id ?>/delete" class="webhook-reauth-form webhook-delete-form">
                <?= $this->csrfField() ?>
                <label class="field field-compact">
                    <span>Confirm your password</span>
                    <input class="input integrations-secret-input" type="password" name="current_password" autocomplete="current-password"<?= $errorContext === 'delete' ? field_attrs($errors ?? [], 'current_password', 'err-delete-current_password') : '' ?> required>
                </label>
                <button class="btn danger btn-small" type="submit">Delete endpoint</button>
                <?= $errorContext === 'delete' ? field_error($errors ?? [], 'current_password', 'err-delete-current_password') : '' ?>
            </form>
        </div>
    </section>

    <section class="card integrations-table-card">
        <h3>Recent deliveries</h3>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Recent webhook deliveries">
            <table class="audit integrations-table integrations-deliveries-table">
                <thead><tr><th scope="col">Event</th><th scope="col">Status</th><th scope="col" class="integrations-col-numeric">Attempts</th><th scope="col" class="integrations-col-numeric">Response</th><th scope="col">Error</th></tr></thead>
                <tbody>
                <?php foreach ($deliveries as $d): ?>
                    <?php $dStatus = (string) $d['status']; $dError = trim((string) ($d['error'] ?? '')); ?>
                    <tr>
                        <td class="integrations-mono"><?= $e($d['event_type']) ?></td>
                        <td><span class="state state-<?= $e($dStatus) ?>"><?= $e($dStatus) ?></span></td>
                        <td class="integrations-col-numeric"><?= (int) $d['attempt_count'] ?> / <?= (int) $d['max_attempts'] ?></td>
                        <td class="integrations-col-numeric"><?= $e((string) ($d['response_status'] ?? '—')) ?></td>
                        <td class="integrations-error-cell">
                            <?= $dError !== '' ? $e($dError) : '—' ?>
                            <?php if ($dStatus === 'dead'): ?>
                                <form method="post" action="/admin/webhooks/<?= $id ?>/deliveries/<?= (int) $d['id'] ?>/replay" class="inline-form">
                                    <?= $this->csrfField() ?>
                                    <button class="linkbtn integrations-rowbtn" type="submit">Replay</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($deliveries)): ?>
                    <tr><td colspan="5" class="integrations-empty">No deliveries yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?= $this->partial('admin/_console_end') ?>
