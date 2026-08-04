<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Webhook: ' . $webhook['name']);
$this->section('variant', 'admin');
$id = (int) $webhook['id'];
$selected = isset($old['events']) ? (array) $old['events'] : (json_decode((string) $webhook['events'], true) ?: []);
$errorContext = $error_context ?? null;
?>
<?= $this->partial('admin/_console', ['area' => 'integrations', 'tab' => 'webhooks']) ?>
    <a class="admin-back" href="/admin/webhooks">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        All endpoints
    </a>
    <h2 class="admin-record-title">Webhook: <?= $e($webhook['name']) ?></h2>
    <?php if (!empty($new_secret)): ?>
        <div class="flash" role="status">
            <strong>Copy this signing secret now - it will not be shown again:</strong>
            <code><?= $e($new_secret) ?></code>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Configuration</h2>
        <form method="post" action="/admin/webhooks/<?= $id ?>" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? $webhook['name']) ?>"<?= field_attrs($errors ?? [], 'name') ?> required>
            </label>
            <?= field_error($errors ?? [], 'name') ?>
            <label>URL
                <input type="url" name="url" maxlength="512" value="<?= $e($old['url'] ?? $webhook['url']) ?>"<?= field_attrs($errors ?? [], 'url') ?> required>
            </label>
            <?= field_error($errors ?? [], 'url') ?>
            <fieldset>
                <legend>Events</legend>
                <?php foreach ($events_catalogue as $event => $desc): ?>
                    <label><input type="checkbox" name="events[]" value="<?= $e($event) ?>" <?= in_array($event, $selected, true) ? 'checked' : '' ?>> <?= $e($event) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?= field_error($errors ?? [], 'events') ?>
            <div class="form-actions"><button class="btn" type="submit">Save</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Actions</h2>
        <div class="form-actions">
            <form method="post" action="/admin/webhooks/<?= $id ?>/toggle" class="inline">
                <?= $this->csrfField() ?>
                <input type="hidden" name="active" value="<?= ((int) $webhook['is_active']) === 1 ? '0' : '1' ?>">
                <button class="btn" type="submit"><?= ((int) $webhook['is_active']) === 1 ? 'Pause' : 'Resume' ?></button>
            </form>
            <form method="post" action="/admin/webhooks/<?= $id ?>/test" class="inline">
                <?= $this->csrfField() ?>
                <button class="btn" type="submit">Send test event</button>
            </form>
        </div>
        <h3>Rotate signing secret</h3>
        <form method="post" action="/admin/webhooks/<?= $id ?>/rotate" class="stacked">
            <?= $this->csrfField() ?>
            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password"<?= $errorContext === 'rotate' ? field_attrs($errors ?? [], 'current_password', 'err-rotate-current_password') : '' ?> required>
            </label>
            <?= $errorContext === 'rotate' ? field_error($errors ?? [], 'current_password', 'err-rotate-current_password') : '' ?>
            <div class="form-actions"><button class="btn" type="submit">Rotate secret</button></div>
        </form>
        <h3>Delete endpoint</h3>
        <p class="muted">Deleting removes the endpoint and its delivery history and revokes its signing secret. This cannot be undone.</p>
        <form method="post" action="/admin/webhooks/<?= $id ?>/delete" class="stacked">
            <?= $this->csrfField() ?>
            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password"<?= $errorContext === 'delete' ? field_attrs($errors ?? [], 'current_password', 'err-delete-current_password') : '' ?> required>
            </label>
            <?= $errorContext === 'delete' ? field_error($errors ?? [], 'current_password', 'err-delete-current_password') : '' ?>
            <div class="form-actions"><button class="btn danger" type="submit">Delete webhook</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Recent deliveries</h2>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Recent webhook deliveries">
        <table class="audit">
            <thead><tr><th scope="col">Event</th><th scope="col">Status</th><th scope="col">Attempts</th><th scope="col">Last response</th><th scope="col">Error</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($deliveries as $d): ?>
                <tr>
                    <td><?= $e($d['event_type']) ?></td>
                    <td><?= $e($d['status']) ?></td>
                    <td><?= (int) $d['attempt_count'] ?>/<?= (int) $d['max_attempts'] ?></td>
                    <td><?= $e((string) ($d['response_status'] ?? '-')) ?></td>
                    <td><?= $e((string) ($d['error'] ?? '')) ?></td>
                    <td>
                        <?php if ($d['status'] === 'dead'): ?>
                            <form method="post" action="/admin/webhooks/<?= $id ?>/deliveries/<?= (int) $d['id'] ?>/replay" class="inline">
                                <?= $this->csrfField() ?>
                                <button class="linkbtn" type="submit">Replay</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($deliveries)): ?>
                <tr><td colspan="6" class="muted">No deliveries yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
<?= $this->partial('admin/_console_end') ?>
