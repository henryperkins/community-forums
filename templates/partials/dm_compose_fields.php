<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * Recipient and optional group-title fields shared by the dedicated and quick
 * new-message mounts. The shared composer shell owns the canonical body field,
 * body error, actions, CSRF token, and idempotency token.
 *
 * Params: to, title, errors, allow_groups, instance_id.
 */
$cfTo = (string) ($to ?? '');
$cfTitle = (string) ($title ?? '');
$cfErrors = $errors ?? [];
$cfGroups = !empty($allow_groups);
$cfInstance = (string) ($instance_id ?? 'dm-new');
$cfToId = 'dm-to-' . $cfInstance;
$cfTitleId = 'dm-title-' . $cfInstance;
?>
<label class="field" for="<?= $e($cfToId) ?>">
    <span>To</span>
    <input class="input input-engraved" type="text" id="<?= $e($cfToId) ?>" name="to" value="<?= $e($cfTo) ?>" maxlength="255" placeholder="<?= $cfGroups ? 'username, username' : 'username' ?>" required>
</label>
<?php if ($cfGroups): ?>
    <p class="field-hint">Separate multiple usernames with commas to start a group.</p>
<?php endif; ?>
<?php if (!empty($cfErrors['to'])): ?><p class="field-error"><?= $e($cfErrors['to']) ?></p><?php endif; ?>

<?php if ($cfGroups): ?>
    <label class="field" for="<?= $e($cfTitleId) ?>">
        <span>Group title</span>
        <input class="input input-engraved" type="text" id="<?= $e($cfTitleId) ?>" name="title" value="<?= $e($cfTitle) ?>" maxlength="120" placeholder="Optional">
    </label>
    <?php if (!empty($cfErrors['title'])): ?><p class="field-error"><?= $e($cfErrors['title']) ?></p><?php endif; ?>
<?php endif; ?>
