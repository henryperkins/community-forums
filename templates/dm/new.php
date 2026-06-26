<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'New message'); ?>
<div class="dm-view">
    <header class="board-header">
        <p class="breadcrumb"><a href="/messages">← Messages</a></p>
        <h1>New message</h1>
    </header>

    <form class="composer" method="post" action="/messages">
        <?= $this->csrfField() ?>
        <label for="dm-to">To (username)</label>
        <input class="input" type="text" id="dm-to" name="to" value="<?= $e($to) ?>" maxlength="32" required>
        <?php if (!empty($errors['to'])): ?><p class="field-error"><?= $e($errors['to']) ?></p><?php endif; ?>

        <label for="dm-body">Message</label>
        <textarea class="composer-input" id="dm-body" name="body" rows="5" maxlength="5000" required><?= $e($body) ?></textarea>
        <?php if (!empty($errors['body'])): ?><p class="field-error"><?= $e($errors['body']) ?></p><?php endif; ?>

        <button class="btn" type="submit">Send message</button>
    </form>
</div>
