<?php /** @var \App\Core\View $this */ ?>
<?php
$otherName = $other === null ? 'Unknown' : (($other['display_name'] ?? '') !== '' ? $other['display_name'] : $other['username']);
$title = !empty($is_group)
    ? (($conversation['title'] ?? '') !== '' ? (string) $conversation['title'] : 'Group conversation')
    : 'Conversation with ' . $otherName;
// The rail's icon + label are typed once here so the toggle button's
// aria-label and the menu item's visible text can never drift apart.
$railIcon = !empty($is_group) ? 'users' : 'panel-right';
$railLabel = !empty($is_group) ? 'Members & details' : 'Details';
$this->layout('layout');
$this->section('title', $title);
?>
<div class="dm-shell reading has-rail">
    <?= $this->partial('partials/dm_list', ['conversations' => $conversations ?? [], 'filter' => 'all', 'active_id' => $conversation_id, 'allow_groups' => $allow_groups ?? false, 'show_avatars' => $show_avatars ?? true]) ?>

    <section class="dm-threadpane">
        <header class="dm-thread-head">
            <a class="dm-back" href="/messages" aria-label="Back to messages"><?= $this->partial('partials/icon', ['name' => 'chevron-left']) ?></a>
            <div class="dm-thread-id">
                <?= $this->partial('partials/monogram', ['name' => !empty($is_group) ? $title : $otherName, 'username' => !empty($is_group) ? ('group-' . (int) $conversation_id) : (string) ($other['username'] ?? $otherName), 'gilt' => true]) ?>
                <div>
                    <span class="dm-thread-eyebrow"><?= $this->partial('partials/icon', ['name' => 'lock']) ?><?= !empty($is_group) ? 'Private group' : 'Private counsel' ?></span>
                    <h1 class="dm-thread-title">
                        <?php if (!empty($is_group)): ?>
                            <?= $e($title) ?>
                        <?php elseif ($other !== null): ?>
                            <a href="/u/<?= $e($other['username']) ?>"><?= $e($otherName) ?></a>
                        <?php else: ?><?= $e($otherName) ?><?php endif; ?>
                    </h1>
                    <p class="dm-thread-sub">
                        <?php if (!empty($is_group)): ?>
                            <?= count(array_filter($participants ?? [], fn ($p) => empty($p['left_at']))) ?> in counsel<?= !empty($muted) ? ' · muted' : '' ?>
                        <?php elseif ($other !== null): ?>
                            @<?= $e($other['username']) ?><?= !empty($muted) ? ' · muted' : '' ?>
                        <?php else: ?>
                            Open letter<?= !empty($muted) ? ' · muted' : '' ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="dm-thread-actions">
                <button type="button" class="dm-iconbtn is-active" data-rail-toggle aria-controls="dm-rail" aria-expanded="true" aria-label="<?= $e($railLabel) ?>"><?= $this->partial('partials/icon', ['name' => $railIcon]) ?></button>
                <details class="dm-menu">
                    <summary class="dm-iconbtn" aria-label="More actions"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
                    <div class="dm-menu-pop" role="menu">
                        <form method="post" action="/messages/<?= (int) $conversation_id ?>/mute">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="muted" value="<?= !empty($muted) ? '0' : '1' ?>">
                            <button class="dm-menu-item" type="submit"><?= $this->partial('partials/icon', ['name' => 'bell-off']) ?><span><?= !empty($muted) ? 'Unmute conversation' : 'Mute conversation' ?></span></button>
                        </form>
                        <a class="dm-menu-item" href="#dm-rail" data-rail-open><?= $this->partial('partials/icon', ['name' => $railIcon]) ?><span><?= $e($railLabel) ?></span></a>
                        <?php if (empty($is_group) && $other !== null): ?>
                            <a class="dm-menu-item" href="/u/<?= $e($other['username']) ?>"><?= $this->partial('partials/icon', ['name' => 'user']) ?><span>View profile</span></a>
                            <div class="dm-menu-sep"></div>
                            <form method="post" action="/u/<?= $e($other['username']) ?>/block">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="return" value="/messages/<?= (int) $conversation_id ?>">
                                <button class="dm-menu-item danger" type="submit"><?= $this->partial('partials/icon', ['name' => 'ban']) ?><span><?= !empty($other_is_blocked) ? 'Unblock' : 'Block' ?> <?= $e($otherName) ?></span></button>
                            </form>
                        <?php elseif (!empty($is_group)): ?>
                            <div class="dm-menu-sep"></div>
                            <form method="post" action="/messages/<?= (int) $conversation_id ?>/members/remove">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $current_user !== null ? (int) $current_user->id() : 0 ?>">
                                <button class="dm-menu-item danger" type="submit"><?= $this->partial('partials/icon', ['name' => 'log-out']) ?><span>Leave group</span></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </header>

        <div class="dm-scroll">
            <div class="dm-scroll-inner">
            <?php if (empty($messages)): ?>
                <p class="muted empty">No messages yet.</p>
            <?php else: ?>
                <div class="dm-day dm-day-private"><?= $this->partial('partials/icon', ['name' => 'lock']) ?>Private — only those named here can read</div>
                <?php
                // Group consecutive messages by author into de-boxed "letters":
                // one author line per run, then the run's messages.
                $dmGroups = [];
                foreach ($messages as $m) {
                    $lastIdx = count($dmGroups) - 1;
                    if ($lastIdx >= 0 && (int) $dmGroups[$lastIdx]['user_id'] === (int) $m['user_id']) {
                        $dmGroups[$lastIdx]['items'][] = $m;
                    } else {
                        $dmGroups[] = ['user_id' => (int) $m['user_id'], 'items' => [$m]];
                    }
                }
                // Conversation role (owner/member) per user, for the group rank pill.
                $dmRoles = [];
                foreach (($participants ?? []) as $pp) {
                    $dmRoles[(int) $pp['user_id']] = (string) ($pp['role'] ?? '');
                }
                ?>
                <?php foreach ($dmGroups as $g): ?>
                    <?php
                    $first = $g['items'][0];
                    $mine = $current_user !== null && (int) $first['user_id'] === $current_user->id();
                    $authorName = ($first['author_display_name'] ?? '') !== '' ? $first['author_display_name'] : $first['author_username'];
                    ?>
                    <div class="dm-group<?= $mine ? ' mine' : '' ?>">
                        <?php if (!$mine): ?>
                            <span class="dm-mono-col"><?= $this->partial('partials/monogram', ['name' => $authorName, 'username' => $first['author_username']]) ?></span>
                        <?php endif; ?>
                        <div class="dm-msgs">
                            <div class="dm-ghead">
                                <span class="dm-name"><?= $mine ? 'You' : $e($authorName) ?></span>
                                <?php if (!$mine && !empty($is_group) && ($dmRoles[(int) $first['user_id']] ?? '') === 'owner'): ?>
                                    <span class="dm-rank">Owner</span>
                                <?php endif; ?>
                                <span class="dm-gtime"><?= $e(human_datetime($first['created_at'])) ?></span>
                            </div>
                            <?php foreach ($g['items'] as $m): ?>
                                <div class="dm-line" id="m<?= (int) $m['id'] ?>">
                                    <div class="dm-body formatted-content">
                                        <?= $m['body_html'] /* sanitised at write time or rendered read fallback */ ?>
                                    </div>
                                    <?php if (!$mine): ?>
                                        <span class="dm-line-menu">
                                            <details class="dm-report">
                                                <summary class="dm-dotbtn" aria-label="Message actions"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
                                                <form method="post" action="/dm/<?= (int) $m['id'] ?>/report" class="dm-report-form">
                                                    <?= $this->csrfField() ?>
                                                    <button type="button" class="linkbtn dm-copy" data-copy-message hidden><?= $this->partial('partials/icon', ['name' => 'copy']) ?><span>Copy text</span></button>
                                                    <select name="reason_code" class="input input-small">
                                                        <?php foreach ($reasons as $rc): ?><option value="<?= $e($rc) ?>"><?= $e(ucfirst(str_replace('_', ' ', $rc))) ?></option><?php endforeach; ?>
                                                    </select>
                                                    <input type="text" name="reason" class="input input-small" placeholder="Details (optional)" maxlength="255">
                                                    <button class="btn btn-small danger" type="submit">Report message</button>
                                                </form>
                                            </details>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php $messageReferenceCards = ($reference_cards ?? [])[(int) $m['id']] ?? []; ?>
                                <?php if (!empty($messageReferenceCards)): ?>
                                    <div class="reference-cards" aria-label="Referenced content">
                                        <?php foreach ($messageReferenceCards as $card): ?>
                                            <a class="reference-card" href="<?= $e($card['url']) ?>">
                                                <span class="ref-type"><?= $e($card['type']) ?></span>
                                                <strong><?= $e($card['title']) ?></strong>
                                                <?php if (($card['meta'] ?? '') !== ''): ?><span class="ref-meta"><?= $e($card['meta']) ?></span><?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php
                // Read receipt — a quiet line under my last letter. Direct only,
                // and only on the newest page (that's where the last letter is).
                $lastMessage = $messages[count($messages) - 1];
                $receipt = null;
                if (empty($is_group) && $other !== null && $current_user !== null
                    && (int) $lastMessage['user_id'] === $current_user->id()
                    && (int) $page === (int) $pages) {
                    $receipt = ($other_last_read_message_id ?? null) !== null
                        && (int) $other_last_read_message_id >= (int) $lastMessage['id'] ? 'Read' : 'Delivered';
                }
                ?>
                <?php if ($receipt !== null): ?>
                    <div class="dm-receipt-row"><span class="dm-receipt"><?php if ($receipt === 'Read'): ?><?= $this->partial('partials/icon', ['name' => 'check']) ?><?php endif; ?><?= $receipt ?></span></div>
                <?php endif; ?>
            <?php endif; ?>

            <?= $this->partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'base_url' => '/messages/' . (int) $conversation_id . '?']) ?>

            <?php if (!empty($events)): ?>
                <details class="dm-events">
                    <summary class="linkbtn">Group history</summary>
                    <ul>
                        <?php foreach ($events as $event): ?>
                            <li class="muted"><?= $e(str_replace('_', ' ', (string) $event['event_type'])) ?><?= !empty($event['subject_username']) ? ' @' . $e($event['subject_username']) : '' ?> · <?= $e(human_datetime($event['created_at'])) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($can_reply)): ?>
            <?php
            $dmConversationId = (int) $conversation_id;
            $dmConversationRecipient = $other !== null && (string) ($other['username'] ?? '') !== ''
                ? (string) $other['username']
                : 'recipient';
            ?>
            <?= $this->partial('partials/composer_shell', [
                'action' => '/messages/' . $dmConversationId,
                'context' => 'dm',
                'target_id' => $dmConversationId,
                'instance_id' => 'dm-conversation-' . $dmConversationId,
                'placeholder' => 'Message @' . $dmConversationRecipient . '…',
                'maxlength' => 5000,
                'body_value' => (string) ($body ?? ''),
                'submit_label' => 'Send',
                'form_class' => 'dm-composer',
                'body_error' => (string) ($errors['body'] ?? ''),
                'identity' => [
                    'display_name' => $current_user->displayName(),
                    'username' => $current_user->username(),
                    'show_avatar' => $show_avatars ?? true,
                ],
            ]) ?>
        <?php else: ?>
            <div class="joinbar">You are no longer an active participant in this conversation.</div>
        <?php endif; ?>
    </section>

    <?= $this->partial('partials/dm_rail', [
        'conversation' => $conversation,
        'conversation_id' => $conversation_id,
        'is_group' => $is_group,
        'is_owner' => $is_owner ?? false,
        'other' => $other,
        'participants' => $participants ?? [],
        'muted' => $muted ?? false,
        'other_is_blocked' => $other_is_blocked ?? false,
        'rail_label' => $railLabel,
    ]) ?>
    <a class="dm-rail-scrim" href="#" data-rail-scrim aria-label="Close details"></a>
</div>
