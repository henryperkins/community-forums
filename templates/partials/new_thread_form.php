<?php /** @var \App\Core\View $this */ ?>
<form method="post" action="/threads" class="composer stacked" data-composer-context="new_thread" data-composer-target-id="<?= (int) $board['id'] ?>">
    <?= $this->csrfField() ?>
    <input type="hidden" name="board_id" value="<?= (int) $board['id'] ?>">
    <?php // No-JS idempotency: without this, a double-submit (double-click, retry,
          // back-button resend) creates duplicate topics. app.js stamps it too when
          // JS is on; this server-rendered field is the progressive-enhancement base. ?>
    <input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>">
    <?php if (!empty($errors['title'])): ?><p class="field-error"><?= $e($errors['title']) ?></p><?php endif; ?>
    <input type="text" name="title" class="input" placeholder="Title" maxlength="160" value="<?= $e($old['title'] ?? '') ?>" required>
    <?php if (!empty($errors['body'])): ?><p class="field-error"><?= $e($errors['body']) ?></p><?php endif; ?>
    <textarea name="body" rows="6" class="composer-input" placeholder="Write your post… Markdown supported (**bold**, lists, `code`, ## headings)." maxlength="20000" required><?= $e($old['body'] ?? '') ?></textarea>
    <?php if (!empty($board['allow_anonymous'])): ?>
        <label class="checkline"><input type="checkbox" name="is_anonymous" value="1" <?= !empty($old['is_anonymous']) ? 'checked' : '' ?>> Post anonymously <span class="muted">(your name is hidden from other members; moderators can still see it)</span></label>
    <?php endif; ?>
    <div class="composer-actions">
        <button class="btn" type="submit">Create topic</button>
        <button class="btn-secondary composer-cancel" type="button" data-close-composer>Cancel</button>
    </div>
</form>
