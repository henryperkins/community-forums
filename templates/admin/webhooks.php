<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Webhooks');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Webhooks</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'webhooks', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <?php if (!empty($new_secret)): ?>
        <div class="flash" role="status">
            <strong>Copy this signing secret now - it will not be shown again:</strong>
            <code><?= $e($new_secret) ?></code>
        </div>
    <?php endif; ?>

    <?php if (empty($features['first_party_hooks'])): ?>
        <p class="muted">Domain events are inactive while <code>first_party_hooks</code> is off. The <code>ping</code> test event remains available.</p>
    <?php else: ?>
        <p class="muted">Domain events are active for public-board content. The <code>ping</code> test event remains admin-only.</p>
    <?php endif; ?>

    <section class="card">
        <h2>Register an endpoint</h2>
        <form method="post" action="/admin/webhooks" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? '') ?>"<?= field_attrs($errors ?? [], 'name') ?> required>
            </label>
            <?= field_error($errors ?? [], 'name') ?>

            <label>URL
                <input type="url" name="url" maxlength="512" value="<?= $e($old['url'] ?? '') ?>"<?= field_attrs($errors ?? [], 'url') ?> required>
            </label>
            <?= field_error($errors ?? [], 'url') ?>

            <fieldset>
                <legend>Events</legend>
                <?php $selectedEvents = (array) ($old['events'] ?? []); ?>
                <?php foreach ($events_catalogue as $event => $desc): ?>
                    <label><input type="checkbox" name="events[]" value="<?= $e($event) ?>" <?= in_array($event, $selectedEvents, true) ? 'checked' : '' ?>> <?= $e($event) ?> - <?= $e($desc) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?= field_error($errors ?? [], 'events') ?>

            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password') ?> required>
            </label>
            <?= field_error($errors ?? [], 'current_password') ?>

            <div class="form-actions"><button class="btn" type="submit">Register endpoint</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Endpoints</h2>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Webhook endpoints">
        <table class="audit">
            <thead><tr><th scope="col">Name</th><th scope="col">URL</th><th scope="col">Status</th><th scope="col">Last status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($webhooks as $w): ?>
                <tr>
                    <td><?= $e($w['name']) ?></td>
                    <td><?= $e($w['url']) ?></td>
                    <td><?php $wActive = ((int) $w['is_active']) === 1; ?><span class="state state-<?= $wActive ? 'active' : 'paused' ?>"><?= $wActive ? 'active' : 'paused' ?></span></td>
                    <td><?= $e((string) ($w['last_status'] ?? '-')) ?></td>
                    <td><a href="/admin/webhooks/<?= (int) $w['id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($webhooks)): ?>
                <tr><td colspan="5" class="muted">No endpoints yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
    </div>
</div>
