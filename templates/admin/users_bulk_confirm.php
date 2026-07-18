<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Bulk moderation');
$action = (string) ($action ?? 'warn');
$isSuspend = $action === 'suspend';
$errors = $errors ?? [];
$old = $old ?? [];
$count = count($subjects ?? []);
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $isSuspend ? 'Suspend' : 'Warn' ?> <?= $count ?> member<?= $count === 1 ? '' : 's' ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'users', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
        <section class="card confirm-card">
            <h2>Review before applying</h2>
            <p class="muted">
                <?php if ($isSuspend): ?>
                    Every selected member becomes read-only until the expiry (blank = indefinite). Your own account and other administrators are skipped automatically. Each suspension is audited individually and reversible with Lift.
                <?php else: ?>
                    Every selected member receives the same formal warning; it appears on their record and counts toward their history. Each warning is audited individually.
                <?php endif; ?>
            </p>

            <ul class="link-list">
                <?php foreach ($subjects as $subject): ?>
                    <li>
                        <a href="/admin/users/<?= (int) $subject['id'] ?>">@<?= $e($subject['username']) ?></a>
                        <span class="role-pill role-<?= $e($subject['role']) ?>"><?= $e($subject['role']) ?></span>
                        <span class="state state-<?= $e($subject['status']) ?>"><?= $e($subject['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="post" action="/admin/users/bulk/apply" class="stacked">
                <?= $this->csrfField() ?>
                <input type="hidden" name="bulk_action" value="<?= $e($action) ?>">
                <?php foreach ($subjects as $subject): ?>
                    <input type="hidden" name="selected[]" value="<?= (int) $subject['id'] ?>">
                <?php endforeach; ?>

                <label class="field">
                    <span>Reason (shared; shown to each member)</span>
                    <input type="text" name="reason" class="input" maxlength="255" value="<?= $e((string) ($old['reason'] ?? '')) ?>"<?= field_attrs($errors ?? [], 'reason') ?> required>
                </label>
                <?= field_error($errors ?? [], 'reason') ?>

                <?php if ($isSuspend): ?>
                    <label class="field">
                        <span>Until (UTC, optional — leave blank for indefinite)</span>
                        <input type="text" name="until" class="input" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e((string) ($old['until'] ?? '')) ?>"<?= field_attrs($errors ?? [], 'until') ?>>
                    </label>
                    <?= field_error($errors ?? [], 'until') ?>
                <?php endif; ?>

                <div class="form-actions">
                    <button class="btn<?= $isSuspend ? ' danger' : '' ?>" type="submit"><?= $isSuspend ? 'Suspend' : 'Warn' ?> <?= $count ?> member<?= $count === 1 ? '' : 's' ?></button>
                    <a class="linkbtn" href="/admin/users">Cancel</a>
                </div>
            </form>
        </section>
    </div>
</div>
