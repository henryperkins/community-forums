<?php /** @var \App\Core\View $this */ ?>
<?php
$draft = $subscription_old ?? [];
$frequency = (string) ($draft['frequency'] ?? $subscription['frequency'] ?? 'off');
$inApp = $draft !== [] ? ($draft['in_app'] ?? '') === '1' : !empty($subscription['in_app_enabled']);
$email = $draft !== [] ? ($draft['email'] ?? '') === '1' : !empty($subscription['email_enabled']);
?>
<label class="field"><span>Frequency</span><select class="input" name="frequency">
    <?php if (!in_array($frequency, ['instant', 'daily', 'off'], true)): ?><option value="<?= $e($frequency) ?>" selected><?= $e($frequency) ?></option><?php endif; ?>
    <?php foreach (['instant' => 'Instant', 'daily' => 'Daily', 'off' => 'Off'] as $value => $label): ?>
        <option value="<?= $value ?>"<?= $frequency === $value ? ' selected' : '' ?>><?= $label ?></option>
    <?php endforeach; ?>
</select></label>
<label class="switchline"><input class="switch" type="checkbox" role="switch" name="in_app" value="1"<?= $inApp ? ' checked' : '' ?>><span class="switch-text">In-app notifications</span></label>
<label class="switchline"><input class="switch" type="checkbox" role="switch" name="email" value="1"<?= $email ? ' checked' : '' ?>><span class="switch-text">Email notifications</span></label>
