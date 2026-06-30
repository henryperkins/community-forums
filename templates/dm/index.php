<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Messages'); $this->section('robots', 'noindex, nofollow'); ?>
<div class="dm-shell">
    <section class="dm-listpane" aria-label="Conversations">
        <header class="dm-listpane-head">
            <div class="dm-listpane-top">
                <span>
                    <span class="eyebrow">Private counsel</span>
                    <h1>Messages</h1>
                </span>
                <a class="btn btn-small" href="/messages/new">New message</a>
            </div>
            <?php $filter = ($filter ?? 'all') === 'unread' ? 'unread' : 'all'; ?>
            <nav class="dm-listpane-filters" aria-label="Message filters">
                <a class="pill<?= $filter === 'all' ? ' is-active' : '' ?>" href="/messages"<?= $filter === 'all' ? ' aria-current="page"' : '' ?>>All</a>
                <a class="pill<?= $filter === 'unread' ? ' is-active' : '' ?>" href="/messages?filter=unread"<?= $filter === 'unread' ? ' aria-current="page"' : '' ?>>Unread</a>
            </nav>
        </header>

        <?php if (empty($conversations)): ?>
            <p class="dm-list-empty"><?= $filter === 'unread' ? 'No unread conversations.' : 'No conversations yet.' ?></p>
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
                    <li>
                        <a class="dm-row dm-link<?= !empty($c['is_unread']) ? ' is-unread' : '' ?>" href="/messages/<?= (int) $c['conversation_id'] ?>">
                        <?= $this->partial('partials/monogram', ['name' => $other, 'username' => $seed]) ?>
                            <span class="dm-row-top">
                                <?php if (!empty($c['is_unread'])): ?><span class="unread-dot" aria-hidden="true"></span><?php endif; ?>
                                <span class="dm-other"><?= $e($other) ?></span>
                            </span>
                        <span class="dm-time"><?= $e(human_datetime($c['last_message_at'] ?? null)) ?></span>
                            <span class="dm-preview"><?= $e(mb_strimwidth((string) ($c['last_body'] ?? ''), 0, 120, '…')) ?></span>
                            <?php if ($isGroup && !empty($c['participant_names'])): ?><span class="dm-group-meta"><?= $e($c['participant_names']) ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="dm-threadpane">
        <div class="dm-empty">
            <div class="dm-empty-inner">
                <span class="star" aria-hidden="true">✦</span>
                <h2>Choose a thread of counsel</h2>
                <p>Select a conversation from the left, or begin a new private message.</p>
            </div>
        </div>
    </section>
</div>
