<?php /** @var \App\Core\View $this */ ?>
<form method="post" action="/t/<?= (int) $thread['id'] ?>/reply" class="composer" id="reply">
    <?= $this->csrfField() ?>
    <input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>">
    <p class="composer-label">Posting as <strong><?= $e($current_user->displayName()) ?></strong></p>
    <?php if (!empty($reply_errors['body'])): ?><p class="field-error"><?= $e($reply_errors['body']) ?></p><?php endif; ?>
    <textarea name="body" rows="4" class="composer-input" placeholder="Write a reply… Markdown supported." maxlength="20000" required><?= $e($reply_old['body'] ?? '') ?></textarea>
    <?php if (!empty($thread['board_allow_anonymous'])): ?>
        <label class="checkline"><input type="checkbox" name="is_anonymous" value="1" <?= !empty($reply_old['is_anonymous']) ? 'checked' : '' ?>> Post anonymously <span class="muted">(your name is hidden from other members; moderators can still see it)</span></label>
    <?php endif; ?>
    <button class="btn" type="submit">Reply</button>
</form>
