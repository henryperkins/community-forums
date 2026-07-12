<?php /** @var \App\Core\View $this */ ?>
<?php $replyExpanded = !empty($reply_errors) || trim((string) ($reply_old['body'] ?? '')) !== ''; ?>
<form method="post" action="/t/<?= (int) $thread['id'] ?>/reply" class="composer reply-composer thread-composer-card<?= $replyExpanded ? ' is-expanded' : '' ?>" id="reply" data-composer-context="reply" data-composer-target-id="<?= (int) $thread['id'] ?>" data-thread-composer>
    <?= $this->csrfField() ?>
    <input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>">
    <div class="thread-composer-identity">
        <?php if ($show_avatars ?? true): ?><?= $this->partial('partials/monogram', ['name' => $current_user->displayName(), 'username' => $current_user->username()]) ?><?php endif; ?>
        <p class="composer-label">Posting as <strong><?= $e($current_user->displayName()) ?></strong></p>
    </div>
    <?php if (!empty($reply_errors['body'])): ?><p class="field-error"><?= $e($reply_errors['body']) ?></p><?php endif; ?>
    <textarea name="body" rows="4" class="composer-input" placeholder="Write a reply… Markdown supported." maxlength="20000" required><?= $e($reply_old['body'] ?? '') ?></textarea>
    <?php if (!empty($thread['board_allow_anonymous'])): ?>
        <label class="checkline">
            <input type="checkbox" name="is_anonymous" value="1" <?= !empty($reply_old['is_anonymous']) ? 'checked' : '' ?>>
            <span class="checkline-copy">Post anonymously <span class="muted">(your name is hidden from other members; moderators can still see it)</span></span>
        </label>
    <?php endif; ?>
    <button class="btn" type="submit">Reply</button>
</form>
