<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The conversation list — the always-present left column of the DM reading room.
 * Shared by dm/index (empty right pane) and dm/show (the open conversation), so
 * the list and the conversation are one shell, not two page shapes.
 *
 * Params: conversations (list rows), filter ('all'|'unread'), active_id (the
 * open conversation id, marked .active), q (applied search term), allow_groups
 * (group_dms flag, gates the compose dialog's group fields) — all optional.
 */
$dmFilter = ($filter ?? 'all') === 'unread' ? 'unread' : 'all';
$dmActiveId = (int) ($active_id ?? 0);
$dmConversations = $conversations ?? [];
$dmQ = trim((string) ($q ?? ''));
$dmAllowGroups = !empty($allow_groups);
// The pills keep an applied search; with no search they stay byte-identical
// to the long-pinned hrefs.
$dmAllHref = '/messages' . ($dmQ !== '' ? '?q=' . urlencode($dmQ) : '');
$dmUnreadHref = '/messages?filter=unread' . ($dmQ !== '' ? '&q=' . urlencode($dmQ) : '');
?>
<section class="dm-listpane" aria-label="Conversations">
    <header class="dm-listpane-head">
        <div class="dm-listpane-top">
            <span>
                <span class="eyebrow dm-lock-eyebrow"><?= $this->partial('partials/icon', ['name' => 'lock']) ?>Private counsel</span>
                <h1>Messages</h1>
            </span>
            <details class="dm-compose-details">
                <summary class="dm-new-btn" aria-label="New message" title="New message"><?= $this->partial('partials/icon', ['name' => 'plus']) ?></summary>
                <div class="dm-dialog" aria-labelledby="dm-compose-title">
                    <div class="dm-dialog-head">
                        <div><span class="eyebrow">Private counsel</span><h2 id="dm-compose-title">New message</h2></div>
                        <button type="button" class="dm-dialog-close" data-close-compose aria-label="Close"><?= $this->partial('partials/icon', ['name' => 'x']) ?></button>
                    </div>
                    <?php
                    $dmDialogInstance = 'dm-new-dialog';
                    $dmDialogWrapper = function () use ($dmAllowGroups, $dmDialogInstance): void {
                        ?><div class="dm-dialog-body"><?php
                        echo $this->partial('partials/dm_compose_fields', [
                            'to' => '',
                            'title' => '',
                            'errors' => [],
                            'allow_groups' => $dmAllowGroups,
                            'instance_id' => $dmDialogInstance,
                        ]);
                        ?></div><?php
                    };
                    $dmDialogBeforeSubmit = function (): void {
                        ?><button class="btn btn-ghost" type="button" data-close-compose>Cancel</button><?php
                    };
                    ?>
                    <?= $this->partial('partials/composer_shell', [
                        'action' => '/messages',
                        'context' => 'dm',
                        'target_id' => 0,
                        'instance_id' => $dmDialogInstance,
                        'placeholder' => 'Message @recipient…',
                        'maxlength' => 5000,
                        'body_value' => '',
                        'submit_label' => 'Send',
                        'form_class' => 'dm-form',
                        'identity' => [
                            'display_name' => $current_user->displayName(),
                            'username' => $current_user->username(),
                            'show_avatar' => $show_avatars ?? true,
                        ],
                        'no_wysiwyg' => true,
                        'wrapper_slot' => $dmDialogWrapper,
                        'before_submit_slot' => $dmDialogBeforeSubmit,
                    ]) ?>
                </div>
            </details>
        </div>
        <form class="dm-search" method="get" action="/messages" role="search">
            <?= $this->partial('partials/icon', ['name' => 'search']) ?>
            <input type="search" name="q" value="<?= $e($dmQ) ?>" placeholder="Search messages…" aria-label="Search messages" maxlength="120">
            <?php if ($dmFilter === 'unread'): ?><input type="hidden" name="filter" value="unread"><?php endif; ?>
        </form>
        <nav class="dm-listpane-filters" aria-label="Message filters">
            <a class="pill<?= $dmFilter === 'all' ? ' is-active' : '' ?>" href="<?= $e($dmAllHref) ?>"<?= $dmFilter === 'all' ? ' aria-current="page"' : '' ?>>All</a>
            <a class="pill<?= $dmFilter === 'unread' ? ' is-active' : '' ?>" href="<?= $e($dmUnreadHref) ?>"<?= $dmFilter === 'unread' ? ' aria-current="page"' : '' ?>>Unread</a>
        </nav>
    </header>

    <?php if (empty($dmConversations)): ?>
        <p class="dm-list-empty"><?= $dmQ !== '' ? 'No letters match your search.' : ($dmFilter === 'unread' ? 'No unread conversations.' : 'No conversations yet.') ?></p>
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
        <p class="dm-list-empty" data-search-empty role="status" hidden>No letters match your search.</p>
    <?php endif; ?>
</section>
