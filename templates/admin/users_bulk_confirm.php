<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('variant', 'admin');
$action = (string) ($action ?? 'warn');
$isSuspend = $action === 'suspend';
$errors = $errors ?? [];
$old = $old ?? [];
$subjects = $subjects ?? [];
$count = count($subjects);
$actorId = (int) ($actor_id ?? 0);
$subjectRows = [];
$actionableCount = 0;
foreach ($subjects as $subject) {
    $skipped = $isSuspend
        && ((string) ($subject['role'] ?? '') === 'admin' || (int) ($subject['id'] ?? 0) === $actorId);
    if (!$skipped) {
        $actionableCount++;
    }
    $subjectRows[] = ['subject' => $subject, 'skipped' => $skipped];
}
$verb = $isSuspend ? 'Suspend' : 'Warn';
// Drill-ins name the action, not the area (cf. structure_confirm, tag_merge_confirm).
$this->section('title', $verb . ' members');
?>
<?= $this->partial('admin/_member_tabs', ['active' => 'directory', 'pane_class' => 'member-bulk-confirm']) ?>
    <a class="admin-back member-bulk-back" href="/admin/users">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        All members
    </a>

    <section class="card member-bulk-card">
        <h2><?= $verb ?> <?= $count ?> member<?= $count === 1 ? '' : 's' ?></h2>
        <p class="member-bulk-intro">
            <?php if ($isSuspend): ?>
                Every selected member becomes read-only until the expiry (blank is indefinite). Your own account and other administrators are skipped automatically. Each suspension is audited individually and reversible with Lift.
            <?php else: ?>
                Every selected member receives the same formal warning; it appears on their record and counts toward their history. Each warning is audited individually.
            <?php endif; ?>
        </p>

        <ul class="member-bulk-subjects">
            <?php foreach ($subjectRows as $row): ?>
                <?php
                $subject = $row['subject'];
                $display = trim((string) ($subject['display_name'] ?? ''));
                $monogramName = $display !== '' ? $display : (string) $subject['username'];
                ?>
                <li class="member-bulk-subject">
                    <span class="member-bulk-subject-monogram"><?= $this->partial('partials/monogram', ['name' => $monogramName, 'username' => $subject['username']]) ?></span>
                    <a class="member-bulk-subject-link" href="/admin/users/<?= (int) $subject['id'] ?>">@<?= $e($subject['username']) ?></a>
                    <span class="role-pill role-<?= $e($subject['role']) ?> member-bulk-role"><?= $e($subject['role']) ?></span>
                    <span class="member-bulk-state"><?= $e($subject['status']) ?></span>
                    <?php if ($row['skipped']): ?><span class="member-bulk-skipped">skipped — administrator</span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="post" action="/admin/users/bulk/apply" class="stacked member-bulk-form">
            <?= $this->csrfField() ?>
            <input type="hidden" name="bulk_action" value="<?= $e($action) ?>">
            <?php foreach ($subjects as $subject): ?>
                <input type="hidden" name="selected[]" value="<?= (int) $subject['id'] ?>">
            <?php endforeach; ?>

            <label class="field">
                <span>Reason (shared; shown to each member)</span>
                <input type="text" name="reason" class="input" maxlength="255" value="<?= $e((string) ($old['reason'] ?? '')) ?>"<?= field_attrs($errors, 'reason') ?> required>
            </label>
            <?= field_error($errors, 'reason') ?>

            <?php if ($isSuspend): ?>
                <label class="field">
                    <span>Until (UTC, optional — blank is indefinite)</span>
                    <input type="text" name="until" class="input member-bulk-until" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e((string) ($old['until'] ?? '')) ?>"<?= field_attrs($errors, 'until') ?>>
                </label>
                <?= field_error($errors, 'until') ?>
            <?php endif; ?>

            <div class="form-actions member-bulk-actions">
                <button class="btn<?= $isSuspend ? ' danger' : '' ?>" type="submit"><?= $verb ?> <?= $actionableCount ?> member<?= $actionableCount === 1 ? '' : 's' ?></button>
                <a class="linkbtn" href="/admin/users">Cancel</a>
            </div>
        </form>
    </section>
<?= $this->partial('admin/_console_end') ?>
