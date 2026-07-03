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
    <?= $this->partial('partials/dm_list', ['conversations' => $conversations ?? [], 'filter' => 'all', 'active_id' => $conversation_id, 'allow_groups' => $allow_groups ?? false]) ?>

    <section class="dm-threadpane">
        <header class="dm-thread-head">
            <a class="dm-back" href="/messages" aria-label="Back to messages"><?= $this->partial('partials/icon', ['name' => 'chevron-left']) ?></a>
            <div class="dm-thread-id">
                <?= $this->partial('partials/monogram', ['name' => !empty($is_group) ? $title : $otherName, 'username' => !empty($is_group) ? ('group-' . (int) $conversation_id) : (string) ($other['username'] ?? $otherName), 'gilt' => true]) ?>
                <div>
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
                <div class="dm-day">Beginning of your counsel</div>
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
                                <span class="dm-gtime"><?= $e(human_datetime($first['created_at'])) ?></span>
                            </div>
                            <?php foreach ($g['items'] as $m): ?>
                                <div class="dm-line" id="m<?= (int) $m['id'] ?>">
                                    <div class="dm-body">
                                        <?php if (($m['body_html'] ?? '') !== ''): ?><?= $m['body_html'] /* sanitised at write time */ ?><?php else: ?><p><?= $e($m['body']) ?></p><?php endif; ?>
                                    </div>
                                    <?php if (!$mine): ?>
                                        <span class="dm-line-menu">
                                            <details class="dm-report">
                                                <summary class="dm-dotbtn" aria-label="Message actions"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
                                                <form method="post" action="/dm/<?= (int) $m['id'] ?>/report" class="dm-report-form">
                                                    <?= $this->csrfField() ?>
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
            <form class="dm-composer composer" method="post" action="/messages/<?= (int) $conversation_id ?>" data-composer-context="dm" data-composer-target-id="<?= (int) $conversation_id ?>">
                <?= $this->csrfField() ?>
                <?php if (!empty($errors['body'])): ?><p class="field-error"><?= $e($errors['body']) ?></p><?php endif; ?>
                <textarea name="body" rows="3" class="composer-input" maxlength="5000" placeholder="Write a message…" required><?= $e($body ?? '') ?></textarea>
                <button class="btn" type="submit">Send</button>
            </form>
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
