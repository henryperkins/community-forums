<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('variant', 'admin');
$isBackfill = $action === 'backfill';
$verb = $isBackfill ? 'Backfill' : 'Revoke awards';
$this->section('title', $verb);
?>
<?= $this->partial('admin/_console', ['area' => 'features', 'tab' => 'badge_rules', 'pane_class' => 'admin-features features-badge-rule-preview']) ?>
    <?= $this->partial('partials/back_link', ['href' => '/admin/badge-rules', 'label' => 'Back to badge rules']) ?>
    <h2 class="admin-record-title"><?= $e($verb) ?></h2>
    <section class="card confirm-card">
        <h2 class="features-preview-badge"><?= $e($rule['badge_name']) ?></h2>
        <p class="features-preview-meta"><?= $e($rule['rule_type']) ?> &ge; <?= (int) $rule['threshold'] ?><?= !empty($rule['board_name']) ? ' · ' . $e($rule['board_name']) : '' ?></p>
        <?php if ($isBackfill): ?>
            <p><strong><?= $e(number_format($batch_count)) ?> members in this batch</strong> will receive this badge and its standard notification. Only active members who qualify and do not already hold the badge are included.</p>
            <p>Backfill processes up to 1,000 members per run. Preview shows up to 100 members.<?= $has_more ? ' More members qualify; run Backfill again after this batch to review the next batch.' : '' ?> Awards run only when you confirm Backfill, including for a disabled rule.</p>
        <?php else: ?>
            <dl class="impact-list">
                <dt>Active rule awards to revoke</dt><dd><?= $e(number_format($batch_count)) ?></dd>
                <dt>Visible badges currently held</dt><dd><?= $e(number_format($visible_badge_count)) ?></dd>
            </dl>
            <p>This removes this rule’s award ownership. A visible badge may be preserved when another enabled rule can retain ownership; previous explicit revocations take precedence. Otherwise the visible badge is removed. A badge also granted manually may be removed. Enable/Disable does not revoke existing awards.</p>
        <?php endif; ?>
        <p>Impact is checked again when you submit. If the affected batch changes, review the updated impact and confirm again.</p>
        <?= field_error($errors, 'impact_token') ?>
        <form method="post" action="/admin/badge-rules/<?= (int) $rule['id'] ?>/<?= $e($action) ?>" class="stacked confirm-form">
            <?= $this->csrfField() ?>
            <input type="hidden" name="impact_token" value="<?= $e($impact_token) ?>">
            <label class="field">
                <span>Type <code><?= $e($confirm_target) ?></code> to confirm</span>
                <input class="input" type="text" name="confirm" value="<?= $e((string) ($old['confirm'] ?? '')) ?>" autocomplete="off" autocapitalize="off" spellcheck="false"<?= field_attrs($errors, 'confirm') ?> required>
            </label>
            <?= field_error($errors, 'confirm') ?>
            <div class="form-actions">
                <button class="btn<?= $isBackfill ? '' : ' danger' ?>" type="submit"><?= $e($verb) ?></button>
                <a class="btn btn-secondary" href="/admin/badge-rules">Cancel</a>
            </div>
        </form>
    </section>
<?= $this->partial('admin/_console_end') ?>
