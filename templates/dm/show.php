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
$receipt_message_id = null;
if (empty($is_group) && (int) $page === (int) $pages) {
    foreach ($messages as $message) {
        if ((int) $message['user_id'] === $current_user->id()) { $receipt_message_id = (int) $message['id']; }
    }
}
$this->layout('layout');
$this->section('title', $title);
?>
<div class="dm-shell reading has-rail" data-dm-conversation="<?= (int) $conversation_id ?>" data-dm-latest="<?= (int) $page === (int) $pages && !empty($can_reply) ? '1' : '0' ?>" data-dm-viewer="<?= $current_user->id() ?>" data-dm-group="<?= !empty($is_group) ? '1' : '0' ?>" data-dm-other="<?= (int) ($other['id'] ?? 0) ?>">
    <?= $this->partial('partials/dm_list', ['conversations' => $conversations ?? [], 'filter' => 'all', 'active_id' => $conversation_id, 'allow_groups' => $allow_groups ?? false, 'show_avatars' => $show_avatars ?? true, 'heading_tag' => 'h2']) ?>

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
                            <?= count(array_filter($participants ?? [], fn ($p) => empty($p['left_at']))) ?> in counsel<span data-dm-group-presence><?php $here = count(array_filter($presence_states ?? [], fn ($s) => $s === 'online')); ?><?= $here > 0 ? ' · ' . $here . ' here now' : '' ?></span><?= !empty($muted) ? ' · muted' : '' ?>
                        <?php elseif ($other !== null): ?>
                            @<?= $e($other['username']) ?><?= $this->partial('partials/dm_presence', ['user_id' => $other['id'], 'state' => $presence_states[(int) $other['id']] ?? 'offline']) ?><?= !empty($muted) ? ' · muted' : '' ?>
                        <?php else: ?>
                            Open letter<?= !empty($muted) ? ' · muted' : '' ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="dm-thread-actions">
                <a href="#dm-rail" class="dm-iconbtn" data-rail-toggle aria-controls="dm-rail" aria-label="<?= $e($railLabel) ?>"><?= $this->partial('partials/icon', ['name' => $railIcon]) ?></a>
                <details class="dm-menu">
                    <summary class="dm-iconbtn" aria-label="More actions"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
                    <div class="dm-menu-pop">
                        <form method="post" action="/messages/<?= (int) $conversation_id ?>/mute">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="muted" value="<?= !empty($muted) ? '0' : '1' ?>">
                            <button class="dm-menu-item" type="submit"><?= $this->partial('partials/icon', ['name' => 'bell-off']) ?><span><?= !empty($muted) ? 'Unmute conversation' : 'Mute conversation' ?></span></button>
                        </form>
                        <a class="dm-menu-item" href="#dm-rail" data-dm-rail-open><?= $this->partial('partials/icon', ['name' => $railIcon]) ?><span><?= $e($railLabel) ?></span></a>
                        <?php if (empty($is_group) && $other !== null): ?>
                            <a class="dm-menu-item" href="/u/<?= $e($other['username']) ?>"><?= $this->partial('partials/icon', ['name' => 'user']) ?><span>View profile</span></a>
                            <div class="dm-menu-sep"></div>
                            <form method="post" action="/u/<?= $e($other['username']) ?>/block">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="return" value="/messages/<?= (int) $conversation_id ?>">
                                <input type="hidden" name="intent" value="<?= !empty($other_is_blocked) ? 'unblock' : 'block' ?>">
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

        <div class="dm-scroll" data-dm-scroll>
            <div class="dm-scroll-inner">
            <?php if ((int) $page > 1): ?><a class="dm-earlier" href="/messages/<?= (int) $conversation_id ?>?page=<?= (int) $page - 1 ?>">Earlier messages</a><?php endif; ?>
            <?php if ((int) $page === 1): ?>
                <div class="dm-day dm-day-begin"><?= $this->partial('partials/icon', ['name' => 'lock']) ?><span><?= !empty($joined_after_message_id) ? 'Your counsel begins here' : 'Beginning of your counsel' ?></span></div>
                <p class="dm-begin-note"><?= !empty($joined_after_message_id) ? 'You can read the messages sent since you joined.' : 'Only those named here can read it.' ?></p>
            <?php endif; ?>
            <div data-dm-messages>
            <?php if (empty($messages)): ?>
                <p class="muted empty">No messages yet.</p>
            <?php else: ?>
                <?= $this->partial('partials/dm_messages', compact('messages', 'participants', 'is_group', 'reasons', 'reference_cards', 'receipt_message_id', 'other_last_read_message_id')) ?>

            <?php endif; ?>

            </div>
            <?php if ($receipt_message_id === null && empty($is_group) && (int) $page === (int) $pages): ?>
                <div class="dm-receipt-row" data-dm-receipt="" hidden></div>
            <?php endif; ?>
            <?php if ((int) $page < (int) $pages): ?><a class="dm-latest" href="/messages/<?= (int) $conversation_id ?>">Latest messages</a><?php endif; ?>

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
            <button type="button" class="dm-newpill" data-dm-newpill hidden><?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?><span>New messages</span></button>
            <span class="sr-only" role="status" data-dm-update-status></span>
        </div>

        <?php if (!empty($can_reply)): ?>
            <?php
            $dmConversationId = (int) $conversation_id;
            $dmConversationRecipient = $other !== null && (string) ($other['username'] ?? '') !== ''
                ? (string) $other['username']
                : 'recipient';
            // A group is addressed as a whole, never by whichever member sorts first.
            $dmComposerPlaceholder = !empty($is_group) ? 'Message the group…' : 'Message @' . $dmConversationRecipient . '…';
            ?>
            <?= $this->partial('partials/composer_shell', [
                'action' => '/messages/' . $dmConversationId,
                'context' => 'dm',
                'target_id' => $dmConversationId,
                'instance_id' => 'dm-conversation-' . $dmConversationId,
                'placeholder' => $dmComposerPlaceholder,
                'maxlength' => 5000,
                'body_value' => (string) ($body ?? ''),
                'submit_label' => 'Send',
                'form_class' => 'dm-composer',
                // The dock rests as one row on phones; a 422 re-render opens it
                // on the preserved draft, as the thread reply dock does.
                'expanded' => !empty($errors) || trim((string) ($body ?? '')) !== '',
                'body_error' => (string) ($errors['body'] ?? ''),
                'identity' => [
                    'display_name' => $current_user->displayName(),
                    'username' => $current_user->username(),
                    'show_avatar' => $show_avatars ?? true,
                ],
                // Present only without scripting: that send lands on its letter
                // (#m{id}); a scripted send is pinned to the end by app.js.
                'wrapper_slot' => static function (): void { ?><noscript hidden><input type="hidden" name="land" value="letter"></noscript><?php },
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
        'presence_states' => $presence_states ?? [],
    ]) ?>
    <a class="dm-rail-scrim" href="#" data-rail-scrim aria-label="Close details"></a>
</div>
