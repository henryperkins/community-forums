<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The new-message fields (To / optional group title / Message), shared by the
 * full /messages/new page and the list pane's compose dialog so the two forms
 * can never drift. Both post to the existing POST /messages endpoint; a failed
 * validation re-renders /messages/new with these same fields carrying the
 * typed values (anti-draft-loss). Group affordances render only while the
 * group_dms feature is enabled.
 *
 * Params: to, title, body, errors, allow_groups.
 */
$cfTo = (string) ($to ?? '');
$cfTitle = (string) ($title ?? '');
$cfBody = (string) ($body ?? '');
$cfErrors = $errors ?? [];
$cfGroups = !empty($allow_groups);
?>
<label class="field" for="dm-to">
    <span>To</span>
    <input class="input input-engraved" type="text" id="dm-to" name="to" value="<?= $e($cfTo) ?>" maxlength="255" placeholder="<?= $cfGroups ? 'username, username' : 'username' ?>" required>
</label>
<?php if ($cfGroups): ?>
    <p class="field-hint">Separate multiple usernames with commas to start a group.</p>
<?php endif; ?>
<?php if (!empty($cfErrors['to'])): ?><p class="field-error"><?= $e($cfErrors['to']) ?></p><?php endif; ?>

<?php if ($cfGroups): ?>
    <label class="field" for="dm-title">
        <span>Group title</span>
        <input class="input input-engraved" type="text" id="dm-title" name="title" value="<?= $e($cfTitle) ?>" maxlength="120" placeholder="Optional">
    </label>
    <?php if (!empty($cfErrors['title'])): ?><p class="field-error"><?= $e($cfErrors['title']) ?></p><?php endif; ?>
<?php endif; ?>

<label class="field" for="dm-body">
    <span>Message</span>
    <textarea class="composer-input" id="dm-body" name="body" rows="5" maxlength="5000" required><?= $e($cfBody) ?></textarea>
</label>
<?php if (!empty($cfErrors['body'])): ?><p class="field-error"><?= $e($cfErrors['body']) ?></p><?php endif; ?>
