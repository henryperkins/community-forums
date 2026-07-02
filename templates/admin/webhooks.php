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
                <input type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>

            <label>URL
                <input type="url" name="url" maxlength="512" value="<?= $e($old['url'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['url'])): ?><p class="field-error"><?= $e($errors['url']) ?></p><?php endif; ?>

            <fieldset>
                <legend>Events</legend>
                <?php $selectedEvents = (array) ($old['events'] ?? []); ?>
                <?php foreach ($events_catalogue as $event => $desc): ?>
                    <label><input type="checkbox" name="events[]" value="<?= $e($event) ?>" <?= in_array($event, $selectedEvents, true) ? 'checked' : '' ?>> <?= $e($event) ?> - <?= $e($desc) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['events'])): ?><p class="field-error"><?= $e($errors['events']) ?></p><?php endif; ?>

            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>

            <div class="form-actions"><button class="btn" type="submit">Register endpoint</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Endpoints</h2>
        <table class="audit">
            <thead><tr><th>Name</th><th>URL</th><th>Status</th><th>Last status</th><th></th></tr></thead>
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
    </section>
    </div>
</div>
