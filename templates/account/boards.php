<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Board organization'); ?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <section class="card">
        <h2>Organize your boards</h2>
        <p class="muted">Favorite boards rise to the top; muted boards are hidden from your sidebar and unread counts.</p>
        <?php if (empty($groups)): ?>
            <p class="muted">No boards available.</p>
        <?php else: ?>
            <?php foreach ($groups as $g): ?>
                <h3 class="board-cat"><?= $e($g['category']['name']) ?></h3>
                <ul class="board-pref-list">
                    <?php foreach ($g['boards'] as $b): ?>
                        <?php $p = ($prefs[(int) $b['id']] ?? ['is_favorite' => 0, 'is_muted' => 0]); ?>
                        <li class="board-pref-row">
                            <span class="board-pref-name">#<?= $e($b['name']) ?></span>
                            <form class="inline" method="post" action="/settings/boards/toggle">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="board_id" value="<?= (int) $b['id'] ?>">
                                <input type="hidden" name="pref" value="favorite">
                                <button class="linkbtn<?= (int) $p['is_favorite'] === 1 ? ' btn-on' : '' ?>" type="submit"><?= (int) $p['is_favorite'] === 1 ? '★ Favorited' : '☆ Favorite' ?></button>
                            </form>
                            <form class="inline" method="post" action="/settings/boards/toggle">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="board_id" value="<?= (int) $b['id'] ?>">
                                <input type="hidden" name="pref" value="mute">
                                <button class="linkbtn<?= (int) $p['is_muted'] === 1 ? ' btn-on' : '' ?>" type="submit"><?= (int) $p['is_muted'] === 1 ? '🔇 Muted' : 'Mute' ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if (!empty($features['bookmark_folders'])): ?>
        <section class="card">
            <h2>Bookmark folders</h2>
            <p class="muted">Create private folders for threads you have starred.</p>
            <form method="post" action="/settings/bookmark-folders" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="text" name="name" class="input" maxlength="80" placeholder="Read later" required>
                <button class="btn btn-small" type="submit">Create folder</button>
            </form>
            <form method="post" action="/settings/bookmark-folders/add-thread" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="number" name="folder_id" class="input" min="1" placeholder="Folder ID" required>
                <input type="number" name="thread_id" class="input" min="1" placeholder="Starred thread ID" required>
                <button class="btn btn-small" type="submit">Add thread</button>
            </form>
        </section>
    <?php endif; ?>
</div>
