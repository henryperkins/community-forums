<?php
/** @var int $package_id @var array<string,mixed> $release */
?>
<form method="post" action="/admin/packages/<?= (int) $package_id ?>/review" class="review-decision-form">
    <?= $this->csrfField() ?>
    <input type="hidden" name="release_id" value="<?= (int) $release['id'] ?>">
    <label>Local review decision
        <select name="decision" required>
            <option value="approved">approved</option>
            <option value="rejected">rejected</option>
            <option value="revoked">revoked</option>
        </select>
    </label>
    <label>Note (optional)
        <textarea name="note" rows="2" maxlength="1000"></textarea>
    </label>
    <label>Confirm with your password
        <input type="password" name="current_password" autocomplete="current-password" required>
    </label>
    <button type="submit">Record decision</button>
</form>
