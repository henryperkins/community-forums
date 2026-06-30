<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Badge rule preview');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Badge rule preview</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <div class="admin-pane">
    <p><a href="/admin/badge-rules">Back to badge rules</a></p>
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
    </div>
</div>
