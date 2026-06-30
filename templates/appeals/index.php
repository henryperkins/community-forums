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

    <?php
    $eligiblePosts = $eligible['posts'] ?? [];
    $eligibleLogs = $eligible['moderation_logs'] ?? [];
    ?>
    <?php if (!empty($eligiblePosts) || !empty($eligibleLogs)): ?>
        <section class="card">
            <h2>Appealable actions</h2>
            <ul class="report-list">
                <?php foreach ($eligiblePosts as $post): ?>
                    <li class="report-row">
                        <div class="report-head">
                            <span class="badge">post removed</span>
                            <span class="muted">
                                <a href="/t/<?= (int) $post['thread_id'] ?>-<?= $e((string) $post['thread_slug']) ?>"><?= $e((string) $post['thread_title']) ?></a>
                                <?php if (!empty($post['deleted_at'])): ?> · <?= $e(human_datetime((string) $post['deleted_at'])) ?><?php endif; ?>
                            </span>
                        </div>
                        <blockquote class="report-excerpt"><?= $e(mb_strimwidth((string) $post['body'], 0, 220, '...')) ?></blockquote>
                        <form method="post" action="/appeals/posts/<?= (int) $post['id'] ?>" class="stacked">
                            <?= $this->csrfField() ?>
                            <label for="appeal-post-<?= (int) $post['id'] ?>">Reason</label>
                            <textarea id="appeal-post-<?= (int) $post['id'] ?>" name="reason" class="input" rows="3" maxlength="2000" required></textarea>
                            <button class="btn btn-small" type="submit">Submit appeal</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($eligibleLogs as $log): ?>
                    <li class="report-row">
                        <div class="report-head">
                            <span class="badge"><?= $e((string) $log['action']) ?></span>
                            <span class="muted"><?= $e(human_datetime((string) $log['created_at'])) ?></span>
                        </div>
                        <?php if (($log['reason'] ?? '') !== ''): ?><blockquote class="report-excerpt"><?= $e((string) $log['reason']) ?></blockquote><?php endif; ?>
                        <form method="post" action="/appeals/modlog/<?= (int) $log['id'] ?>" class="stacked">
                            <?= $this->csrfField() ?>
                            <label for="appeal-log-<?= (int) $log['id'] ?>">Reason</label>
                            <textarea id="appeal-log-<?= (int) $log['id'] ?>" name="reason" class="input" rows="3" maxlength="2000" required></textarea>
                            <button class="btn btn-small" type="submit">Submit appeal</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
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
