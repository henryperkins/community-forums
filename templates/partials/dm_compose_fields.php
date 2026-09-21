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
// Error ids are instance-scoped: the dedicated and quick mounts render the same
// field names, so an unscoped err-to would duplicate the id across the document.
$cfToErrId = 'dm-to-error-' . $cfInstance;
$cfTitleErrId = 'dm-title-error-' . $cfInstance;
?>
<div data-dm-picker data-dm-allow-groups="<?= $cfGroups ? '1' : '0' ?>" data-dm-avatars="<?= ($show_avatars ?? true) ? '1' : '0' ?>">
<div class="field">
    <label for="<?= $e($cfToId) ?>">To</label>
    <input class="input input-engraved" type="text" id="<?= $e($cfToId) ?>" name="to" value="<?= $e($cfTo) ?>" maxlength="255" placeholder="<?= $cfGroups ? 'username, username' : 'username' ?>"<?= field_attrs($cfErrors, 'to', $cfToErrId) ?> required>
</div>
<?php if ($cfGroups): ?>
    <p class="field-hint">Separate multiple usernames with commas to start a group.</p>
<?php endif; ?>
<?= field_error($cfErrors, 'to', $cfToErrId) ?>

<template data-dm-chip-template><span class="dm-chip"><?php if ($show_avatars ?? true): ?><span class="monogram dm-chip-mono" aria-hidden="true"></span><?php endif; ?><span data-chip-name></span><button type="button" class="dm-chip-x"><?= $this->partial('partials/icon', ['name' => 'x']) ?></button></span></template>
</div>

<?php if ($cfGroups): ?>
    <label data-dm-group-title class="field" for="<?= $e($cfTitleId) ?>">
        <span>Group title</span>
        <input class="input input-engraved" type="text" id="<?= $e($cfTitleId) ?>" name="title" value="<?= $e($cfTitle) ?>" maxlength="120" placeholder="Optional"<?= field_attrs($cfErrors, 'title', $cfTitleErrId) ?>>
    </label>
    <?= field_error($cfErrors, 'title', $cfTitleErrId) ?>
<?php endif; ?>
