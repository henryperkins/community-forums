<?php
/** @var int $package_id @var array<string,mixed> $release */
$rid = (int) $release['id'];
$reviewOld = $review_old ?? [];
// Only the row that was actually submitted replays typed input; every other row
// shows its stored decision.
$isErrored = ((int) ($reviewOld['release_id'] ?? 0)) === $rid;
$decision = $isErrored ? (string) ($reviewOld['decision'] ?? '') : (string) ($release['review_status'] ?? 'approved');
$note = $isErrored ? (string) ($reviewOld['note'] ?? '') : '';
?>
<?php // constraint C-14: the decision select stays visible on first render — never
      // behind a disclosure — and the per-row re-auth is deliberate. ?>
<form method="post" action="/admin/packages/<?= (int) $package_id ?>/review" class="review-decision-form packages-review-form">
    <?= $this->csrfField() ?>
    <input type="hidden" name="release_id" value="<?= $rid ?>">
    <label class="field packages-field-compact">
        <span>Local review decision</span>
        <select class="input" name="decision" required>
            <?php foreach (['approved', 'rejected', 'revoked'] as $opt): ?>
                <option value="<?= $opt ?>"<?= $decision === $opt ? ' selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="field packages-field-compact">
        <span>Note (optional)</span>
        <textarea class="input" name="note" rows="2" maxlength="1000"><?= $e($note) ?></textarea>
    </label>
    <label class="reauth-field">
        <span>Confirm with your password</span>
        <input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-small" type="submit">Record decision</button>
</form>
