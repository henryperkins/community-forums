<?php /** @var \App\Core\View $this */ ?>
<?php
$otherName = $other === null ? 'Unknown' : (($other['display_name'] ?? '') !== '' ? $other['display_name'] : $other['username']);
$title = !empty($is_group)
    ? (($conversation['title'] ?? '') !== '' ? (string) $conversation['title'] : 'Group conversation')
    : 'Conversation with ' . $otherName;
$this->layout('layout');
$this->section('title', $title);
?>
<div class="dm-view dm-thread">
    <header class="board-header">
        <p class="breadcrumb"><a href="/messages">← Messages</a></p>
        <h1>
            <?php if (!empty($is_group)): ?>
                <?= $e($title) ?>
            <?php elseif ($other !== null): ?>
                <a href="/u/<?= $e($other['username']) ?>"><?= $e($otherName) ?></a>
            <?php else: ?><?= $e($otherName) ?><?php endif; ?>
        </h1>
        <?php if (!empty($is_group)): ?>
            <p class="muted"><?= count(array_filter($participants ?? [], fn ($p) => empty($p['left_at']))) ?> active members</p>
            <form class="inline" method="post" action="/messages/<?= (int) $conversation_id ?>/mute">
                <?= $this->csrfField() ?>
                <input type="hidden" name="muted" value="1">
                <button class="linkbtn" type="submit">Mute</button>
            </form>
            <form class="inline" method="post" action="/messages/<?= (int) $conversation_id ?>/members/remove">
                <?= $this->csrfField() ?>
                <input type="hidden" name="user_id" value="<?= $current_user !== null ? (int) $current_user->id() : 0 ?>">
                <button class="linkbtn muted" type="submit">Leave</button>
            </form>
        <?php endif; ?>
    </header>

    <?php if (!empty($is_group)): ?>
        <section class="dm-panel">
            <h2>Members</h2>
            <ul class="badge-row">
                <?php foreach (($participants ?? []) as $p): ?>
                    <li class="badge-chip">
                        @<?= $e($p['username']) ?><?= $p['role'] === 'owner' ? ' owner' : '' ?><?= !empty($p['left_at']) ? ' left' : '' ?>
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
                <form class="inline-form" method="post" action="/messages/<?= (int) $conversation_id ?>/members">
                    <?= $this->csrfField() ?>
                    <input class="input" type="text" name="username" maxlength="32" placeholder="username" required>
                    <button class="btn btn-small" type="submit">Add member</button>
                </form>
                <form class="inline-form" method="post" action="/messages/<?= (int) $conversation_id ?>/rename">
                    <?= $this->csrfField() ?>
                    <input class="input" type="text" name="title" maxlength="120" value="<?= $e($conversation['title'] ?? '') ?>" required>
                    <button class="btn btn-small" type="submit">Rename</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (empty($messages)): ?>
        <p class="muted empty">No messages yet.</p>
    <?php else: ?>
        <div class="dm-messages">
            <?php foreach ($messages as $m): ?>
                <?php $mine = $current_user !== null && (int) $m['user_id'] === $current_user->id(); ?>
                <div class="dm-message<?= $mine ? ' dm-mine' : '' ?>" id="m<?= (int) $m['id'] ?>">
                    <div class="dm-message-head">
                        <span class="dm-author"><?= $e(($m['author_display_name'] ?? '') !== '' ? $m['author_display_name'] : $m['author_username']) ?></span>
                        <span class="post-time"><?= $e(human_datetime($m['created_at'])) ?></span>
                    </div>
                    <div class="dm-message-body">
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
                            <form method="post" action="/dm/<?= (int) $m['id'] ?>/report" class="composer">
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
        </div>
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

    <?php if (!empty($can_reply)): ?>
        <form class="composer dm-composer" method="post" action="/messages/<?= (int) $conversation_id ?>">
            <?= $this->csrfField() ?>
            <?php if (!empty($errors['body'])): ?><p class="field-error"><?= $e($errors['body']) ?></p><?php endif; ?>
            <textarea name="body" rows="3" class="composer-input" maxlength="5000" placeholder="Write a message…" required><?= $e($body ?? '') ?></textarea>
            <button class="btn" type="submit">Send</button>
        </form>
    <?php else: ?>
        <div class="joinbar">You are no longer an active participant in this conversation.</div>
    <?php endif; ?>
</div>
