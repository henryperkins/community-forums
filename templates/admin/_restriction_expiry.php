<?php /** @var \App\Core\View $this */ ?>
<?php
$expiryErrors = $expiry_errors ?? [];
$expiryValue = (string) ($expiry_value ?? '');
$duration = (string) ($duration ?? 'custom');
$context = (string) ($expiry_context ?? '');
$prefix = $context !== '' ? 'err-' . $context . '-' : 'err-';
$helpId = ($context !== '' ? $context : 'bulk') . '-expiry-help';
// A native input discards malformed or legacy text on render. Keep that exact
// draft in a text input on a 422, with its error and UTC help still associated.
$native = $expiryValue === '' || (str_contains($expiryValue, 'T') && \App\Support\UtcDateTimeInput::parse($expiryValue) !== null);
$options = ['custom' => 'Custom date/time (blank is indefinite)', 'day' => '24 hours', 'week' => '7 days', 'month' => '30 days'];
?>
<label class="field">
    <span>Suspension duration</span>
    <select name="duration" class="input"<?= field_attrs($expiryErrors, 'duration', $prefix . 'duration', $helpId) ?>>
        <?php if (!array_key_exists($duration, $options)): ?><option value="<?= $e($duration) ?>" selected><?= $e($duration) ?></option><?php endif; ?>
        <?php foreach ($options as $value => $label): ?>
            <option value="<?= $e($value) ?>"<?= $duration === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
        <?php endforeach; ?>
    </select>
</label>
<?= field_error($expiryErrors, 'duration', $prefix . 'duration') ?>
<label class="field">
    <span><?= $e($expiry_label ?? 'Until (UTC, optional)') ?></span>
    <input type="<?= $native ? 'datetime-local' : 'text' ?>" name="until" class="input"<?= $native ? ' step="1" min="1000-01-01T00:00" max="9999-12-31T23:59:59"' : '' ?> value="<?= $e($expiryValue) ?>"<?= field_attrs($expiryErrors, 'until', $prefix . 'until', $helpId) ?>>
    <span class="muted" id="<?= $e($helpId) ?>">Enter UTC, not your local time. This date applies only to Custom date/time; blank means indefinite. Presets run from the time you submit.</span>
</label>
<?= field_error($expiryErrors, 'until', $prefix . 'until') ?>
