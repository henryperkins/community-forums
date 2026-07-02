<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Announcements');
$ann = $announcement ?? [];
$active = is_array($ann) && !empty($ann['active']);
?>
<div class="admin">
    <header class="admin-head">
        <h1>Announcements</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'announcements', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <section class="card">
        <h2>Current banner</h2>
        <?php if ($active): ?>
            <p class="site-announcement-current"><?= $e((string) ($ann['message'] ?? '')) ?></p>
            <p class="muted">
                <?= !empty($ann['dismissible']) ? 'Dismissible' : 'Not dismissible' ?>
                &middot; version <?= (int) ($ann['version'] ?? 0) ?>
            </p>
            <form method="post" action="/admin/announcements" class="inline">
                <?= $this->csrfField() ?>
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-small danger" type="submit">Clear banner</button>
            </form>
        <?php else: ?>
            <p class="muted">No banner is currently shown.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Publish a banner</h2>
        <form method="post" action="/admin/announcements" class="stacked">
            <?= $this->csrfField() ?>
            <label>Message
                <textarea name="message" rows="3" maxlength="500" required><?= $e((string) ($old['message'] ?? '')) ?></textarea>
            </label>
            <?php if (!empty($errors['message'])): ?><p class="field-error"><?= $e($errors['message']) ?></p><?php endif; ?>

            <label><input type="checkbox" name="dismissible" value="1" <?= !empty($old['dismissible']) ? 'checked' : '' ?>> Members can dismiss this banner</label>
            <label><input type="checkbox" name="broadcast" value="1" <?= !empty($old['broadcast']) ? 'checked' : '' ?>> Also send an in-app broadcast notification to all members</label>
            <label><input type="checkbox" name="broadcast_email" value="1" <?= !empty($old['broadcast_email']) ? 'checked' : '' ?>> Also queue an email broadcast to active members</label>

            <div class="form-actions"><button class="btn" type="submit">Publish banner</button></div>
        </form>
    </section>
    </div>
</div>
