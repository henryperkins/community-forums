<?php /** @var \App\Core\View $this */ ?>
<?php
$otherName = $other === null ? 'Unknown' : (($other['display_name'] ?? '') !== '' ? $other['display_name'] : $other['username']);
$this->layout('layout');
$this->section('title', 'Conversation with ' . $otherName);
?>
<div class="dm-view dm-thread">
    <header class="board-header">
        <p class="breadcrumb"><a href="/messages">← Messages</a></p>
        <h1>
            <?php if ($other !== null): ?>
                <a href="/u/<?= $e($other['username']) ?>"><?= $e($otherName) ?></a>
            <?php else: ?><?= $e($otherName) ?><?php endif; ?>
        </h1>
    </header>

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

    <form class="composer dm-composer" method="post" action="/messages/<?= (int) $conversation_id ?>">
        <?= $this->csrfField() ?>
        <?php if (!empty($errors['body'])): ?><p class="field-error"><?= $e($errors['body']) ?></p><?php endif; ?>
        <textarea name="body" rows="3" class="composer-input" maxlength="5000" placeholder="Write a message…" required><?= $e($body ?? '') ?></textarea>
        <button class="btn" type="submit">Send</button>
    </form>
</div>
