<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Badge rule preview');
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'features', 'tab' => 'badge_rules']) ?>
    <p><a href="/admin/badge-rules">Back to badge rules</a></p>
    <h2 class="admin-record-title">Badge rule preview</h2>
    <section class="card">
        <h2><?= $e($rule['badge_name']) ?></h2>
        <p class="muted"><?= $e($rule['rule_type']) ?> &ge; <?= (int) $rule['threshold'] ?><?= !empty($rule['board_name']) ? ' · ' . $e($rule['board_name']) : '' ?></p>
        <?php if (empty($users)): ?>
            <p class="muted">No users would receive this badge.</p>
        <?php else: ?>
            <ul class="link-list">
                <?php foreach ($users as $user): ?>
                    <li>
                        <a href="/admin/users/<?= (int) $user['id'] ?>"><?= $e($user['username']) ?></a>
                        <span class="muted">Metric: <?= (int) $user['metric'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
