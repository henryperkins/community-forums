<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Messages'); $this->section('robots', 'noindex, nofollow'); ?>
<div class="dm-view">
    <header class="board-header">
        <h1>Messages</h1>
        <a class="btn" href="/messages/new">New message</a>
    </header>

    <?php if (empty($conversations)): ?>
        <p class="muted empty">No conversations yet.</p>
    <?php else: ?>
        <ul class="dm-list">
            <?php foreach ($conversations as $c): ?>
                <?php
                $isGroup = ($c['kind'] ?? 'direct') === 'group';
                $other = $isGroup
                    ? (($c['title'] ?? '') !== '' ? $c['title'] : 'Group conversation')
                    : (($c['other_display_name'] ?? '') !== '' ? $c['other_display_name'] : $c['other_username']);
                $seed = $isGroup ? ('group-' . (int) $c['conversation_id']) : (string) $c['other_username'];
                ?>
                <li class="dm-row<?= !empty($c['is_unread']) ? ' dm-unread' : '' ?>">
                    <a class="dm-link" href="/messages/<?= (int) $c['conversation_id'] ?>">
                        <?= $this->partial('partials/monogram', ['name' => $other, 'username' => $seed]) ?>
                        <span class="dm-row-main">
                            <span class="dm-other"><?php if (!empty($c['is_unread'])): ?><span class="unread-dot"></span><?php endif; ?><?= $e($other) ?></span>
                            <?php if ($isGroup && !empty($c['participant_names'])): ?><span class="muted"><?= $e($c['participant_names']) ?></span><?php endif; ?>
                            <span class="dm-preview"><?= $e(mb_strimwidth((string) ($c['last_body'] ?? ''), 0, 80, '…')) ?></span>
                        </span>
                        <span class="dm-time"><?= $e(human_datetime($c['last_message_at'] ?? null)) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
