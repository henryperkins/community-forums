<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Badge rule preview');
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'features', 'tab' => 'badge_rules', 'pane_class' => 'admin-features features-badge-rule-preview']) ?>
    <a class="admin-back" href="/admin/badge-rules">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Back to badge rules
    </a>
    <?php // feature-added: the design's preview is an in-place column swap with no record
          // title. Production is a real route, so the drill-in keeps its own h2 while the
          // Badge rules tab stays lit (FC-12). ?>
    <h2 class="admin-record-title">Badge rule preview</h2>
    <section class="card features-preview-card">
        <h2 class="features-preview-badge"><?= $e($rule['badge_name']) ?></h2>
        <p class="features-preview-meta"><?= $e($rule['rule_type']) ?> &ge; <?= (int) $rule['threshold'] ?><?= !empty($rule['board_name']) ? ' · ' . $e($rule['board_name']) : '' ?></p>
        <?php // feature-removed FC-14: the design leads with "{n} member(s) meet this rule
              // today." preview()['total'] counts a LIMIT-100 page while backfill runs at
              // 1000, so the lead would understate the grant. Ship no count. ?>
        <?php if (empty($users)): ?>
            <p class="features-empty">No members would receive this badge.</p>
        <?php else: ?>
            <ul class="link-list features-preview-list">
                <?php foreach ($users as $user): ?>
                    <li>
                        <?= $this->partial('partials/monogram', ['name' => (string) ($user['display_name'] ?? $user['username']), 'username' => (string) $user['username']]) ?>
                        <?php // feature-added: the design's username is inert text; production links the record. ?>
                        <a href="/admin/users/<?= (int) $user['id'] ?>"><?= $e($user['username']) ?></a>
                        <span class="features-preview-metric">Metric: <?= $e(number_format((int) $user['metric'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
