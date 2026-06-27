<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'New topic'); ?>
<div class="card">
    <h1>New topic</h1>
    <form method="post" action="/threads" class="composer stacked">
        <?= $this->csrfField() ?>
        <label class="field">
            <span>Board</span>
            <select name="board_id" class="input">
                <?php foreach ($boards as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === (int) $selected_board ? 'selected' : '' ?>>#<?= $e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (!empty($errors['board_id'])): ?><p class="field-error"><?= $e($errors['board_id']) ?></p><?php endif; ?>

        <label class="field">
            <span>Title</span>
            <input type="text" name="title" class="input" maxlength="160" value="<?= $e($old['title'] ?? '') ?>" required>
        </label>
        <?php if (!empty($errors['title'])): ?><p class="field-error"><?= $e($errors['title']) ?></p><?php endif; ?>

        <label class="field">
            <span>Body</span>
            <textarea name="body" rows="8" class="composer-input" maxlength="20000" required><?= $e($old['body'] ?? '') ?></textarea>
        </label>
        <?php if (!empty($errors['body'])): ?><p class="field-error"><?= $e($errors['body']) ?></p><?php endif; ?>

        <?php if (array_filter($boards, static fn (array $b): bool => !empty($b['allow_anonymous']))): ?>
            <label class="checkline"><input type="checkbox" name="is_anonymous" value="1" <?= !empty($old['is_anonymous']) ? 'checked' : '' ?>> Post anonymously <span class="muted">(only takes effect on boards that allow it; your name stays visible to moderators)</span></label>
        <?php endif; ?>

        <button class="btn" type="submit">Create topic</button>
    </form>
</div>
