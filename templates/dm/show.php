<?php /** @var \App\Core\View $this */ ?>
<?php
$otherName = $other === null ? 'Unknown' : (($other['display_name'] ?? '') !== '' ? $other['display_name'] : $other['username']);
$title = !empty($is_group)
    ? (($conversation['title'] ?? '') !== '' ? (string) $conversation['title'] : 'Group conversation')
    : 'Conversation with ' . $otherName;
$this->layout('layout');
$this->section('title', $title);
?>
<div class="dm-shell reading">
    <aside class="dm-listpane dm-return-pane" aria-label="Messages">
        <header class="dm-listpane-head">
            <div class="dm-listpane-top">
                <span>
                    <span class="eyebrow">Private counsel</span>
                    <h1>Messages</h1>
                </span>
                <a class="btn btn-small" href="/messages/new">New message</a>
            </div>
        </header>
        <div class="dm-empty-inner dm-return-copy">
            <span class="star" aria-hidden="true">✦</span>
            <p><a href="/messages">Back to all messages</a></p>
        </div>
    </aside>

    <section class="dm-threadpane">
        <header class="dm-thread-head">
            <p class="breadcrumb"><a href="/messages">← Messages</a></p>
            <div class="dm-thread-title-row">
                <div class="dm-thread-id">
                    <?= $this->partial('partials/monogram', ['name' => $title, 'username' => !empty($is_group) ? ('group-' . (int) $conversation_id) : (string) ($other['username'] ?? $otherName), 'gilt' => true]) ?>
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
                                <?= count(array_filter($participants ?? [], fn ($p) => empty($p['left_at']))) ?> active members
                            <?php else: ?>
                                Open letter
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php if (!empty($is_group)): ?>
                    <div class="dm-thread-actions">
                        <form class="inline" method="post" action="/messages/<?= (int) $conversation_id ?>/mute">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="muted" value="1">
                            <button class="dm-head-btn" type="submit">Mute</button>
                        </form>
                        <form class="inline" method="post" action="/messages/<?= (int) $conversation_id ?>/members/remove">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= $current_user !== null ? (int) $current_user->id() : 0 ?>">
                            <button class="dm-head-btn danger" type="submit">Leave</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!empty($is_group)): ?>
            <section class="dm-group-panel">
                <h2>Members</h2>
                <ul class="dm-members">
                    <?php foreach (($participants ?? []) as $p): ?>
                        <li class="dm-member<?= !empty($p['left_at']) ? ' is-left' : '' ?>">
                            <span class="handle">@<?= $e($p['username']) ?></span>
                            <?php if ($p['role'] === 'owner'): ?><span class="role">Owner</span><?php endif; ?>
                            <?php if (!empty($p['left_at'])): ?><span class="role">Left</span><?php endif; ?>
                            <?php if (!empty($is_owner) && empty($p['left_at']) && (int) $p['user_id'] !== (int) ($conversation['owner_user_id'] ?? 0)): ?>
                                <form class="inline" method="post" action="/messages/<?= (int) $conversation_id ?>/members/remove">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>">
                                    <button class="linkbtn danger" type="submit">Remove</button>
                                </form>
                                <form class="inline" method="post" action="/messages/<?= (int) $conversation_id ?>/transfer">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>">
                                    <button class="linkbtn" type="submit">Make owner</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php if (!empty($is_owner)): ?>
                    <div class="dm-owner-tools">
                        <form class="inline-form" method="post" action="/messages/<?= (int) $conversation_id ?>/members">
                            <?= $this->csrfField() ?>
                            <input class="input input-small" type="text" name="username" maxlength="32" placeholder="username" required>
                            <button class="btn btn-small" type="submit">Add member</button>
                        </form>
                        <form class="inline-form" method="post" action="/messages/<?= (int) $conversation_id ?>/rename">
                            <?= $this->csrfField() ?>
                            <input class="input input-small" type="text" name="title" maxlength="120" value="<?= $e($conversation['title'] ?? '') ?>" required>
                            <button class="btn btn-small" type="submit">Rename</button>
                        </form>
                    </div>
            <?php endif; ?>
            </section>
        <?php endif; ?>

        <div class="dm-scroll">
            <?php if (empty($messages)): ?>
                <p class="muted empty">No messages yet.</p>
            <?php else: ?>
                <div class="dm-day">Open letter</div>
                <?php foreach ($messages as $m): ?>
                    <?php $mine = $current_user !== null && (int) $m['user_id'] === $current_user->id(); ?>
                    <div class="dm-message<?= $mine ? ' dm-mine' : '' ?>" id="m<?= (int) $m['id'] ?>">
                        <div class="dm-message-head">
                            <span class="dm-author"><?= $e(($m['author_display_name'] ?? '') !== '' ? $m['author_display_name'] : $m['author_username']) ?></span>
                            <span class="post-time"><?= $e(human_datetime($m['created_at'])) ?></span>
                        </div>
                        <div class="dm-bubble">
                            <?php if (($m['body_html'] ?? '') !== ''): ?>
                                <?= $m['body_html'] /* sanitised at write time */ ?>
                            <?php else: ?>
                                <p><?= $e($m['body']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php $messageReferenceCards = ($reference_cards ?? [])[(int) $m['id']] ?? []; ?>
                        <?php if (!empty($messageReferenceCards)): ?>
                            <div class="reference-cards" aria-label="Referenced content">
                                <?php foreach ($messageReferenceCards as $card): ?>
                                    <a class="reference-card" href="<?= $e($card['url']) ?>">
                                        <span class="badge badge-muted"><?= $e($card['type']) ?></span>
                                        <strong><?= $e($card['title']) ?></strong>
                                        <?php if (($card['meta'] ?? '') !== ''): ?><span class="muted"><?= $e($card['meta']) ?></span><?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$mine): ?>
                            <details class="dm-report">
                                <summary class="linkbtn danger">Report</summary>
                                <form method="post" action="/dm/<?= (int) $m['id'] ?>/report" class="dm-report-form">
                                    <?= $this->csrfField() ?>
                                    <select name="reason_code" class="input input-small">
                                        <?php foreach ($reasons as $rc): ?>
                                            <option value="<?= $e($rc) ?>"><?= $e(ucfirst(str_replace('_', ' ', $rc))) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="reason" class="input" placeholder="Details (optional)" maxlength="255">
                                    <button class="btn btn-small danger" type="submit">Report message</button>
                                </form>
                            </details>
                        <?php endif; ?>
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

        <?php if (!empty($can_reply)): ?>
            <form class="dm-composer" method="post" action="/messages/<?= (int) $conversation_id ?>">
                <?= $this->csrfField() ?>
                <?php if (!empty($errors['body'])): ?><p class="field-error"><?= $e($errors['body']) ?></p><?php endif; ?>
                <textarea name="body" rows="3" class="composer-input" maxlength="5000" placeholder="Write a message…" required><?= $e($body ?? '') ?></textarea>
                <button class="btn" type="submit">Send</button>
            </form>
        <?php else: ?>
            <div class="joinbar">You are no longer an active participant in this conversation.</div>
        <?php endif; ?>
    </section>
</div>
