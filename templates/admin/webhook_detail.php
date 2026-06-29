<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Webhook: ' . $webhook['name']);
$id = (int) $webhook['id'];
$selected = isset($old['events']) ? (array) $old['events'] : (json_decode((string) $webhook['events'], true) ?: []);
?>
<div class="admin">
    <header class="admin-head">
        <h1>Webhook: <?= $e($webhook['name']) ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/webhooks">Webhooks</a>
    </nav>

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
                <input type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? $webhook['name']) ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>
            <label>URL
                <input type="url" name="url" maxlength="512" value="<?= $e($old['url'] ?? $webhook['url']) ?>" required>
            </label>
            <?php if (!empty($errors['url'])): ?><p class="field-error"><?= $e($errors['url']) ?></p><?php endif; ?>
            <fieldset>
                <legend>Events</legend>
                <?php foreach ($events_catalogue as $event => $desc): ?>
                    <label><input type="checkbox" name="events[]" value="<?= $e($event) ?>" <?= in_array($event, $selected, true) ? 'checked' : '' ?>> <?= $e($event) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['events'])): ?><p class="field-error"><?= $e($errors['events']) ?></p><?php endif; ?>
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
            <form method="post" action="/admin/webhooks/<?= $id ?>/delete" class="inline">
                <?= $this->csrfField() ?>
                <button class="linkbtn danger" type="submit">Delete</button>
            </form>
        </div>
        <h3>Rotate signing secret</h3>
        <form method="post" action="/admin/webhooks/<?= $id ?>/rotate" class="stacked">
            <?= $this->csrfField() ?>
            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
            <div class="form-actions"><button class="btn" type="submit">Rotate secret</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Recent deliveries</h2>
        <table class="audit">
            <thead><tr><th>Event</th><th>Status</th><th>Attempts</th><th>Last response</th><th>Error</th><th></th></tr></thead>
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
    </section>
</div>
