<?php /** @var \App\Core\View $this */ ?>
<?php if (!empty($status_history)): ?>
<details class="thread-status-history" data-thread-status-history>
    <summary>Status history</summary>
    <ol class="thread-status-history-list">
        <?php foreach ($status_history as $event): ?>
            <li>
                <strong><?= $e($status_labels[$event['new_status']] ?? $event['new_status']) ?></strong>
                <?php if (!empty($event['previous_status'])): ?>
                    <span>← <?= $e($status_labels[$event['previous_status']] ?? $event['previous_status']) ?></span>
                <?php endif; ?>
                <span><?= $e($event['actor_label'] ?? 'system') ?> · <?= $e(human_datetime($event['created_at'])) ?></span>
                <?php if (!empty($event['reason'])): ?><em>“<?= $e($event['reason']) ?>”</em><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</details>
<?php endif; ?>
