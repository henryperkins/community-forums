<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * A flag row's one write affordance (ADR 0049), rendered inside its Flag cell
 * after the <code> chip so it stays on screen at 390px, where the table
 * scrolls sideways. A native <details> is the inline confirm: opening or
 * cancelling it costs no request and no script, and each form posts one flag
 * with an explicit target state. `name` makes the panels an exclusive
 * accordion where browsers support it. Never render `open` on the server.
 *
 * Keep links and the inventory's cell and readiness vocabulary out of the
 * panel: AppAdminFeaturesTest scans the whole page, closed panels included.
 *
 * @var array<string,mixed> $row
 * @var string $return_path
 */
$flag = (string) $row['flag'];
$toggle = $row['toggle'];
$id = 'flag-' . $flag;
?>
<?php if ($toggle['mode'] === 'confirm'): ?>
    <?php $describedBy = implode(' ', array_filter([
        $toggle['warning'] !== null ? $id . '-warning' : null,
        $id . '-write',
        $toggle['one_way'] !== null ? $id . '-one-way' : null,
    ])); ?>
    <details class="features-flag-toggle" name="features-flag-confirm">
        <summary class="features-toggle-btn">
            <?php // The ellipsis says "a step follows" to the eye; the disclosure's
                  // collapsed/expanded state says it to assistive technology. ?>
            <span class="features-flag-toggle-rest"><?= $e($toggle['verb']) ?><span class="sr-only"> <?= $e($flag) ?></span><span aria-hidden="true">…</span></span>
            <span class="features-flag-toggle-cancel">Cancel<span class="sr-only"> turning <?= $toggle['target'] ? 'on' : 'off' ?> <?= $e($flag) ?></span></span>
        </summary>
        <div class="features-flag-confirm" role="group" aria-labelledby="<?= $e($id) ?>-consequence">
            <p class="features-flag-consequence" id="<?= $e($id) ?>-consequence"><strong><?= $e($toggle['label']) ?>.</strong> <?= $e($toggle['consequence']) ?></p>
            <?php if ($toggle['warning'] !== null): ?>
                <p class="features-flag-warning" id="<?= $e($id) ?>-warning"><?= $e($toggle['warning']) ?></p>
            <?php endif; ?>
            <p class="features-flag-write" id="<?= $e($id) ?>-write"><?php if ($toggle['write'] === 'store'): ?>Saves <code>features.<?= $e($flag) ?>=<?= $toggle['target'] ? 'true' : 'false' ?></code> and records an audit entry.<?php else: ?>Removes the <code>features.<?= $e($flag) ?></code> override so the code default applies, and records an audit entry.<?php endif; ?></p>
            <?php if ($toggle['one_way'] !== null): ?>
                <p class="features-flag-one-way" id="<?= $e($id) ?>-one-way"><?= $e($toggle['one_way']) ?></p>
            <?php endif; ?>
            <form method="post" action="/admin/features/<?= rawurlencode($flag) ?>" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="hidden" name="enabled" value="<?= $toggle['target'] ? '1' : '0' ?>">
                <input type="hidden" name="return" value="<?= $e($return_path) ?>">
                <?php // The commit's name differs from the summary's, so the two are distinct by ear. ?>
                <button class="btn btn-small features-flag-commit<?= $toggle['target'] ? '' : ' danger' ?>" type="submit" aria-describedby="<?= $e($describedBy) ?>"><?= $e($toggle['verb']) ?><span class="sr-only"> <?= $e($flag) ?></span> now</button>
            </form>
        </div>
    </details>
<?php else: ?>
    <?php // Mirrors the console tier's disabled entry (admin/_area_links): a
          // chip that opens nothing, with its reason in words beside it. ?>
    <span class="features-toggle-btn features-flag-locked" aria-disabled="true"><?= $e($toggle['verb']) ?><span class="sr-only"> <?= $e($flag) ?></span></span>
    <p class="features-flag-toggle-note"><?= $e($toggle['note']) ?></p>
<?php endif; ?>
<?php if ($row['outcome'] !== null): ?>
    <p class="features-flag-outcome"><?php if ($row['outcome']['changed']): ?>Turned <?= $e($row['outcome']['state']) ?> just now. <a href="/admin/audit?action=feature_flag.">See the audit log</a><?php else: ?>Already <?= $e($row['outcome']['state']) ?>. Nothing changed.<?php endif; ?></p>
<?php endif; ?>
