<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Appeals'); ?>
<div class="settings">
    <h1>Appeals</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <?php if (!empty($errors)): ?>
        <div class="card error-list" role="alert">
            <?php foreach ($errors as $message): ?>
                <p><?= $e($message) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Your appeals</h2>
        <?php if (empty($appeals)): ?>
            <p class="muted">No appeals yet. Appeal forms appear next to eligible moderation actions.</p>
        <?php else: ?>
            <ul class="report-list">
                <?php foreach ($appeals as $appeal): ?>
                    <li class="report-row">
                        <div class="report-head">
                            <span class="badge"><?= $e((string) $appeal['status']) ?></span>
                            <span class="muted"><?= $e((string) $appeal['target_type']) ?> #<?= (int) $appeal['target_id'] ?> · <?= $e(human_datetime($appeal['created_at'])) ?></span>
                        </div>
                        <?php if (($appeal['target_summary'] ?? '') !== ''): ?><p><?= $e((string) $appeal['target_summary']) ?></p><?php endif; ?>
                        <blockquote class="report-excerpt"><?= $e((string) $appeal['reason']) ?></blockquote>
                        <?php if (($appeal['resolution_note'] ?? '') !== ''): ?><p class="muted">Resolution: <?= $e((string) $appeal['resolution_note']) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
