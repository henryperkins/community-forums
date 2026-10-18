<?php /** @var \App\Core\View $this */ ?>
<?php
$availableGroups = [];
$unavailableGroups = [];
foreach ($catalogue as $key => $meta) {
    $group = ucfirst(explode('.', (string) $key)[1] ?? 'other');
    if (\App\Security\EnforcedCapabilities::has($key)) {
        $availableGroups[$group][$key] = $meta;
    } else {
        $unavailableGroups[$group][$key] = $meta;
    }
}
ksort($availableGroups);
ksort($unavailableGroups);
$errorId = $error_prefix . '-capabilities';
$titleId = $error_prefix . '-capabilities-title';
$hasError = !empty($errors['capabilities']);
$focusChoice = $hasError && array_key_first($errors) === 'capabilities';
?>
<h3 class="role-capabilities-heading" id="<?= $e($titleId) ?>">Capabilities</h3>
<div class="role-capability-chooser" role="group" aria-labelledby="<?= $e($titleId) ?>"<?= $hasError ? ' aria-invalid="true" aria-describedby="' . $e($errorId) . '"' : '' ?>>
    <div class="role-capability-grid">
        <?php foreach ($availableGroups as $group => $groupCaps): ?>
        <fieldset>
            <legend><?= $e($group) ?></legend>
            <?php foreach ($groupCaps as $key => $meta): ?>
            <label class="role-capability-option">
                <input type="checkbox" name="capabilities[]" value="<?= $e($key) ?>"<?= in_array($key, $checked, true) ? ' checked' : '' ?><?= $hasError ? ' aria-invalid="true" aria-describedby="' . $e($errorId) . '"' : '' ?><?= $focusChoice ? ' autofocus' : '' ?>>
                <span><span class="role-capability-description"><?= $e($meta['consent'] ?? $meta['description']) ?></span>
                    <?php if ($meta['risk'] === 'high'): ?><span class="role-risk">High risk</span><?php endif; ?>
                    <code class="role-capability-key"><?= $e($key) ?></code>
                </span>
            </label>
            <?php $focusChoice = false; endforeach; ?>
        </fieldset>
        <?php endforeach; ?>
    </div>
    <?= field_error($errors, 'capabilities', $errorId, alert: true) ?>
    <?php if ($unavailableGroups !== []): ?>
    <details class="role-unavailable-capabilities">
        <summary>Unavailable capabilities</summary>
        <p class="role-section-intro">These capabilities cannot be delegated yet. They are shown for reference and cannot be selected.</p>
        <div class="role-capability-grid">
            <?php foreach ($unavailableGroups as $group => $groupCaps): ?>
            <fieldset>
                <legend><?= $e($group) ?></legend>
                <?php foreach ($groupCaps as $key => $meta): ?>
                <label class="role-capability-option">
                    <input type="checkbox" name="capabilities[]" value="<?= $e($key) ?>"<?= in_array($key, $checked, true) ? ' checked' : '' ?> disabled>
                    <span><span class="role-capability-description"><?= $e($meta['consent'] ?? $meta['description']) ?></span>
                        <?php if ($meta['risk'] === 'high'): ?><span class="role-risk">High risk</span><?php endif; ?>
                        <code class="role-capability-key"><?= $e($key) ?></code>
                        <span class="role-capability-locked">Not yet enforceable</span>
                    </span>
                </label>
                <?php endforeach; ?>
            </fieldset>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
</div>
