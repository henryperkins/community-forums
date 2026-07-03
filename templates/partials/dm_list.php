<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The conversation list — the always-present left column of the DM reading room.
 * Shared by dm/index (empty right pane) and dm/show (the open conversation), so
 * the list and the conversation are one shell, not two page shapes.
 *
 * Params: conversations (list rows), filter ('all'|'unread'), active_id (the
 * open conversation id, marked .active) — all optional.
 */
$dmFilter = ($filter ?? 'all') === 'unread' ? 'unread' : 'all';
$dmActiveId = (int) ($active_id ?? 0);
$dmConversations = $conversations ?? [];
?>
<section class="dm-listpane" aria-label="Conversations">
    <header class="dm-listpane-head">
        <div class="dm-listpane-top">
            <span>
                <span class="eyebrow">Private counsel</span>
                <h1>Messages</h1>
            </span>
            <a class="dm-new-btn" href="/messages/new" aria-label="New message" title="New message"><?= $this->partial('partials/icon', ['name' => 'plus']) ?></a>
        </div>
        <nav class="dm-listpane-filters" aria-label="Message filters">
            <a class="pill<?= $dmFilter === 'all' ? ' is-active' : '' ?>" href="/messages"<?= $dmFilter === 'all' ? ' aria-current="page"' : '' ?>>All</a>
            <a class="pill<?= $dmFilter === 'unread' ? ' is-active' : '' ?>" href="/messages?filter=unread"<?= $dmFilter === 'unread' ? ' aria-current="page"' : '' ?>>Unread</a>
        </nav>
    </header>

    <?php if (empty($dmConversations)): ?>
        <p class="dm-list-empty"><?= $dmFilter === 'unread' ? 'No unread conversations.' : 'No conversations yet.' ?></p>
    <?php else: ?>
        <ul class="dm-list">
            <?php foreach ($dmConversations as $c): ?>
                <?php
                $cid = (int) $c['conversation_id'];
                $isGroup = ($c['kind'] ?? 'direct') === 'group';
                $rowName = $isGroup
                    ? (($c['title'] ?? '') !== '' ? $c['title'] : 'Group conversation')
                    : (($c['other_display_name'] ?? '') !== '' ? $c['other_display_name'] : $c['other_username']);
                $seed = $isGroup ? ('group-' . $cid) : (string) $c['other_username'];
                ?>
                <li>
                    <a class="dm-row dm-link<?= $cid === $dmActiveId ? ' active' : '' ?><?= !empty($c['is_unread']) ? ' is-unread' : '' ?>" href="/messages/<?= $cid ?>"<?= $cid === $dmActiveId ? ' aria-current="page"' : '' ?>>
                        <?= $this->partial('partials/monogram', ['name' => $rowName, 'username' => $seed, 'gilt' => $isGroup]) ?>
                        <span class="dm-row-top"><span class="dm-other"><?= $e($rowName) ?></span></span>
                        <span class="dm-time"><?= $e(human_datetime($c['last_message_at'] ?? null)) ?></span>
                        <span class="dm-preview"><?= $e(mb_strimwidth((string) ($c['last_body'] ?? ''), 0, 120, '…')) ?></span>
                        <?php if (!empty($c['is_unread'])): ?><span class="dm-unread-dot" aria-label="Unread"></span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
