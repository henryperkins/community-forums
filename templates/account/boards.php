<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Board organization'); ?>
<?php
$boardChoices = [];
foreach (($groups ?? []) as $group) {
    foreach (($group['boards'] ?? []) as $board) {
        $boardChoices[] = $board;
    }
}
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav') ?>

        <div class="settings-pane">
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
                                <button class="linkbtn<?= (int) $p['is_muted'] === 1 ? ' btn-on' : '' ?>" type="submit"><?= (int) $p['is_muted'] === 1 ? 'Muted' : 'Mute' ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if (!empty($features['board_folders']) || !empty($features['saved_feeds']) || !empty($features['bookmark_folders'])): ?>
        <section class="personal-org-grid" aria-label="Personal organization">
            <?php if (!empty($features['board_folders'])): ?>
                <div class="org-card board-folder-card">
                    <div class="org-card-head">
                        <span class="org-icon" aria-hidden="true">#</span>
                        <div>
                            <h2>Board folders</h2>
                            <p class="muted">Gather boards into private rails for the way you read.</p>
                        </div>
                    </div>
                    <?php if (empty($board_folders)): ?>
                        <p class="org-empty">No board folders yet.</p>
                    <?php else: ?>
                        <ul class="org-folder-list">
                            <?php foreach ($board_folders as $folder): ?>
                                <li class="org-folder">
                                    <span class="org-folder-name"><?= $e($folder['name']) ?></span>
                                    <span class="org-count"><?= count($folder['boards'] ?? []) ?> board<?= count($folder['boards'] ?? []) === 1 ? '' : 's' ?></span>
                                    <?php if (!empty($folder['boards'])): ?>
                                        <span class="org-items">
                                            <?php foreach ($folder['boards'] as $board): ?>
                                                <a href="/c/<?= $e($board['slug']) ?>">#<?= $e($board['name']) ?></a>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" action="/settings/board-folders" class="inline-form org-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input input-small" maxlength="80" placeholder="Vilya · Expose" required>
                        <button class="btn btn-small" type="submit">New folder</button>
                    </form>
                    <?php if (!empty($board_folders) && !empty($boardChoices)): ?>
                        <form method="post" action="/settings/board-folders/0/boards" class="inline-form org-form">
                            <?= $this->csrfField() ?>
                            <label class="sr-only" for="board-folder-id">Folder</label>
                            <select id="board-folder-id" name="folder_id" class="input input-small" required>
                                <?php foreach ($board_folders as $folder): ?>
                                    <option value="<?= (int) $folder['id'] ?>"><?= $e($folder['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="sr-only" for="board-folder-board-id">Board</label>
                            <select id="board-folder-board-id" name="board_id" class="input input-small" required>
                                <?php foreach ($boardChoices as $board): ?>
                                    <option value="<?= (int) $board['id'] ?>">#<?= $e($board['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="linkbtn" type="submit">Add board</button>
                        </form>
                    <?php elseif (!empty($boardChoices)): ?>
                        <p class="org-empty">Create a folder, then add boards to it.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($features['saved_feeds'])): ?>
                <div class="org-card saved-feed-card">
                    <div class="org-card-head">
                        <span class="org-icon" aria-hidden="true">~</span>
                        <div>
                            <h2>Saved feeds</h2>
                            <p class="muted">Keep a filter as a named feed in your rail.</p>
                        </div>
                    </div>
                    <?php if (empty($saved_feeds)): ?>
                        <p class="org-empty">No saved feeds yet.</p>
                    <?php else: ?>
                        <ul class="org-folder-list">
                            <?php foreach ($saved_feeds as $feed): ?>
                                <?php $boardIds = $feed['filter']['board_ids'] ?? []; ?>
                                <li class="org-folder">
                                    <span class="org-folder-name"><?= $e($feed['name']) ?></span>
                                    <span class="org-count"><?= !empty($feed['digest_enabled']) ? 'Digest on' : 'Digest off' ?></span>
                                    <span class="org-items"><span><?= count($boardIds) ?> board filter<?= count($boardIds) === 1 ? '' : 's' ?> · latest first</span></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" action="/settings/saved-feeds" class="inline-form org-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input input-small" maxlength="80" placeholder="Unanswered in evaluations" required>
                        <label class="sr-only" for="saved-feed-board-id">Board filter</label>
                        <select id="saved-feed-board-id" name="board_id" class="input input-small">
                            <option value="">All boards</option>
                            <?php foreach ($boardChoices as $board): ?>
                                <option value="<?= (int) $board['id'] ?>">#<?= $e($board['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="checkline"><input type="checkbox" name="digest_enabled" value="1"> Digest</label>
                        <button class="btn btn-small" type="submit">Save feed</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!empty($features['bookmark_folders'])): ?>
                <div class="org-card bookmark-folder-card">
                    <div class="org-card-head">
                        <span class="org-icon org-star" aria-hidden="true">✦</span>
                        <div>
                            <h2>Bookmark folders</h2>
                            <p class="muted">File starred threads where you will find them.</p>
                        </div>
                    </div>
                    <?php if (empty($bookmark_folders)): ?>
                        <p class="org-empty">No bookmark folders yet.</p>
                    <?php else: ?>
                        <ul class="bookmark-folder-list org-folder-list">
                            <?php foreach ($bookmark_folders as $folder): ?>
                                <li class="org-folder">
                                    <span class="org-folder-name"><?= $e($folder['name']) ?></span>
                                    <span class="org-count"><?= count($folder['threads'] ?? []) ?> thread<?= count($folder['threads'] ?? []) === 1 ? '' : 's' ?></span>
                                    <?php if (!empty($folder['threads'])): ?>
                                        <span class="org-items">
                                            <?php foreach ($folder['threads'] as $thread): ?>
                                                <a href="/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>"><?= $e($thread['title']) ?></a>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" action="/settings/bookmark-folders" class="inline-form org-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input input-small" maxlength="80" placeholder="Read later" required>
                        <button class="btn btn-small" type="submit">New folder</button>
                    </form>
                    <?php if (!empty($bookmark_folders) && !empty($starred_threads)): ?>
                        <form method="post" action="/settings/bookmark-folders/add-thread" class="inline-form org-form">
                            <?= $this->csrfField() ?>
                            <label class="sr-only" for="bookmark-folder-id">Bookmark folder</label>
                            <select id="bookmark-folder-id" name="folder_id" class="input input-small" required>
                                <?php foreach ($bookmark_folders as $folder): ?>
                                    <option value="<?= (int) $folder['id'] ?>"><?= $e($folder['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="sr-only" for="bookmark-thread-id">Starred topic</label>
                            <select id="bookmark-thread-id" name="thread_id" class="input input-small" required>
                                <?php foreach ($starred_threads as $thread): ?>
                                    <option value="<?= (int) $thread['id'] ?>"><?= $e($thread['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="linkbtn" type="submit">Add thread</button>
                        </form>
                    <?php elseif (!empty($bookmark_folders)): ?>
                        <p class="org-empty">Star a topic, then file it in a bookmark folder.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
        </div>
    </div>
</div>
