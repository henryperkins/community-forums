<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'New message'); ?>
<div class="dm-shell reading">
    <aside class="dm-listpane dm-return-pane" aria-label="Messages">
        <header class="dm-listpane-head">
            <div class="dm-listpane-top">
                <span>
                    <span class="eyebrow">Private counsel</span>
                    <h1>Messages</h1>
                </span>
            </div>
        </header>
        <div class="dm-empty-inner dm-return-copy">
            <span class="star" aria-hidden="true">✦</span>
            <p><a href="/messages">Back to all messages</a></p>
        </div>
    </aside>

    <section class="dm-threadpane">
        <div class="dm-compose">
            <div class="dm-compose-wrap">
                <p class="breadcrumb"><a href="/messages">← Messages</a></p>
                <span class="eyebrow">Private counsel</span>
                <h1>New message</h1>

                <form class="dm-form" method="post" action="/messages">
                    <?= $this->csrfField() ?>
                    <label class="field" for="dm-to">
                        <span>To</span>
                        <input class="input input-engraved" type="text" id="dm-to" name="to" value="<?= $e($to) ?>" maxlength="255" placeholder="<?= ($allowGroups ?? true) ? 'username, username' : 'username' ?>" required>
                    </label>
                    <?php if ($allowGroups ?? true): ?>
                        <p class="field-hint">Separate multiple usernames with commas to start a group.</p>
                    <?php endif; ?>
                    <?php if (!empty($errors['to'])): ?><p class="field-error"><?= $e($errors['to']) ?></p><?php endif; ?>

                    <?php if ($allowGroups ?? true): ?>
                        <label class="field" for="dm-title">
                            <span>Group title</span>
                            <input class="input input-engraved" type="text" id="dm-title" name="title" value="<?= $e($title ?? '') ?>" maxlength="120" placeholder="Optional">
                        </label>
                        <?php if (!empty($errors['title'])): ?><p class="field-error"><?= $e($errors['title']) ?></p><?php endif; ?>
                    <?php endif; ?>

                    <label class="field" for="dm-body">
                        <span>Message</span>
                        <textarea class="composer-input" id="dm-body" name="body" rows="5" maxlength="5000" required><?= $e($body) ?></textarea>
                    </label>
                    <?php if (!empty($errors['body'])): ?><p class="field-error"><?= $e($errors['body']) ?></p><?php endif; ?>

                    <div class="form-actions"><button class="btn" type="submit">Send message</button></div>
                </form>
            </div>
        </div>
    </section>
</div>
