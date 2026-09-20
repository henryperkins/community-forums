<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Board organization'); ?>
<?php
$oldFor = static fn (string $key): array => ($org_form ?? '') === $key ? ($org_old ?? []) : [];
$invalidFor = static fn (string $key, string $field): bool => ($org_form ?? '') === $key && isset($org_errors[$field]);
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
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'boards']) ?>

        <div class="settings-pane">
    <section class="scribe-panel">
        <h2 class="scribe-panel-head">Organize your boards</h2>
        <p class="muted">Favorited boards rise to the top of your rail. Muted boards are hidden from it, and their threads stop counting towards your unread.</p>
        <?php if (empty($groups)): ?>
            <p class="account-note">No boards available.</p>
        <?php else: ?>
            <?php foreach ($groups as $g): ?>
                <h3 class="board-cat"><?= $e($g['category']['name']) ?></h3>
                <ul class="board-pref-list">
                    <?php foreach ($g['boards'] as $b): ?>
                        <?php
                        $p = ($prefs[(int) $b['id']] ?? ['is_favorite' => 0, 'is_muted' => 0]);
                        $isFav = (int) $p['is_favorite'] === 1;
                        $isMuted = (int) $p['is_muted'] === 1;
                        ?>
                        <li class="board-pref-row">
                            <span class="board-pref-name"><span class="board-hash" aria-hidden="true">#</span><?= $e($b['name']) ?></span>
                            <form class="inline" method="post" action="/settings/boards/toggle">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="board_id" value="<?= (int) $b['id'] ?>">
                                <input type="hidden" name="pref" value="favorite">
                                <button class="linkbtn<?= $isFav ? ' btn-on' : '' ?>" type="submit"><span class="board-pref-star" aria-hidden="true"><?= $isFav ? '★' : '☆' ?></span> <?= $isFav ? 'Favorited' : 'Favorite' ?></button>
                            </form>
                            <form class="inline" method="post" action="/settings/boards/toggle">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="board_id" value="<?= (int) $b['id'] ?>">
                                <input type="hidden" name="pref" value="mute">
                                <button class="board-mute-toggle<?= $isMuted ? ' is-on' : '' ?>" type="submit"><?= $isMuted ? 'Muted' : 'Mute' ?></button>
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
                                    <?php $folderOld = $oldFor('folder-' . $folder['id']); ?>
                                    <?= $this->partial('partials/organization_error', ['org_form' => $org_form ?? '', 'org_errors' => $org_errors ?? [], 'form_key' => 'folder-' . $folder['id']]) ?>
                                    <form method="post" action="/settings/board-folders/<?= (int) $folder['id'] ?>/rename" class="org-form">
                                        <?= $this->csrfField() ?>
                                        <label for="folder-name-<?= (int) $folder['id'] ?>">Folder name</label>
                                        <input class="input input-small" id="folder-name-<?= (int) $folder['id'] ?>" name="name"<?php if ($invalidFor('folder-' . $folder['id'], 'name')): ?> aria-invalid="true" aria-describedby="folder-<?= (int) $folder['id'] ?>-name-error"<?php endif; ?> value="<?= $e($folderOld['name'] ?? $folder['name']) ?>" maxlength="80" required>
                                        <button class="btn btn-small" type="submit">Rename folder</button>
                                    </form>
                                    <form method="post" action="/settings/board-folders/<?= (int) $folder['id'] ?>/delete" class="org-form">
                                        <?= $this->csrfField() ?><button class="linkbtn" type="submit">Delete folder</button>
                                    </form>
                                    <?php if (!empty($folder['boards'])): ?>
                                        <span class="org-items">
                                            <?php foreach ($folder['boards'] as $board): ?>
                                                <a href="/c/<?= $e($board['slug']) ?>">#<?= $e($board['name']) ?></a>
                                                <form method="post" action="/settings/board-folders/<?= (int) $folder['id'] ?>/boards/<?= (int) $board['id'] ?>/remove">
                                                    <?= $this->csrfField() ?><button class="linkbtn" type="submit" aria-label="Remove <?= $e($board['name']) ?> from <?= $e($folder['name']) ?>">Remove board</button>
                                                </form>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php $createOld = $oldFor('folder-create'); ?>
                    <?= $this->partial('partials/organization_error', ['org_form' => $org_form ?? '', 'org_errors' => $org_errors ?? [], 'form_key' => 'folder-create']) ?>
                    <form method="post" action="/settings/board-folders" class="inline-form org-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input input-small" maxlength="80" placeholder="Morning read" aria-label="Name"<?php if ($invalidFor('folder-create', 'name')): ?> aria-invalid="true" aria-describedby="folder-create-name-error"<?php endif; ?> value="<?= $e($createOld['name'] ?? '') ?>" required>
                        <button class="btn btn-small" type="submit">New folder</button>
                    </form>
                    <?php if (!empty($board_folders) && !empty($boardChoices)): ?>
                        <?php $addOld = $oldFor('folder-add'); ?>
                        <?= $this->partial('partials/organization_error', ['org_form' => $org_form ?? '', 'org_errors' => $org_errors ?? [], 'form_key' => 'folder-add']) ?>
                        <form method="post" action="/settings/board-folders/0/boards" class="inline-form org-form">
                            <?= $this->csrfField() ?>
                            <label class="sr-only" for="board-folder-id">Folder</label>
                            <select id="board-folder-id" name="folder_id" class="input input-small" required>
                                <?php foreach ($board_folders as $folder): ?>
                                    <option value="<?= (int) $folder['id'] ?>"<?= (int) ($addOld['folder_id'] ?? 0) === (int) $folder['id'] ? ' selected' : '' ?>><?= $e($folder['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="sr-only" for="board-folder-board-id">Board</label>
                            <select id="board-folder-board-id"<?php if ($invalidFor('folder-add', 'board_id')): ?> aria-invalid="true" aria-describedby="folder-add-board_id-error"<?php endif; ?> name="board_id" class="input input-small" required>
                                <?php foreach ($boardChoices as $board): ?>
                                    <option value="<?= (int) $board['id'] ?>"<?= (int) ($addOld['board_id'] ?? 0) === (int) $board['id'] ? ' selected' : '' ?>>#<?= $e($board['name']) ?></option>
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
                                <?php
                                $boardIds = $feed['filter']['board_ids'] ?? [];
                                $feedOld = $oldFor('feed-' . $feed['id']);
                                $selectedIds = isset($feedOld['board_ids']) && is_array($feedOld['board_ids']) ? array_map('intval', $feedOld['board_ids'])
                                    : (array_key_exists('board_id', $feedOld) ? ((string) $feedOld['board_id'] === '' ? [] : [(int) $feedOld['board_id']]) : $boardIds);
                                $visibleIds = array_map(static fn (array $board): int => (int) $board['id'], $boardChoices);
                                $multi = count($boardIds) > 1;
                                $feedDigest = $feedOld !== [] ? !empty($feedOld['digest_enabled']) : !empty($feed['digest_enabled']);
                                ?>
                                <li class="org-folder">
                                    <a class="org-folder-name" href="/feeds/saved/<?= (int) $feed['id'] ?>"><?= $e($feed['name']) ?></a>
                                    <span class="org-count"><?= !empty($feed['digest_enabled']) ? (!empty($digest_active) ? 'Digest on' : 'Daily digest is off') : 'Digest off' ?></span>
                                    <?php if (!empty($feed['digest_enabled']) && empty($digest_active)): ?><a href="/settings/notifications">Set daily digest schedule</a><?php endif; ?>
                                    <span class="org-items"><span><?= (int) $feed['readable_board_count'] ?> board filter<?= (int) $feed['readable_board_count'] === 1 ? '' : 's' ?> · latest first</span></span>
                                    <?= $this->partial('partials/organization_error', ['org_form' => $org_form ?? '', 'org_errors' => $org_errors ?? [], 'form_key' => 'feed-' . $feed['id']]) ?>
                                    <form method="post" action="/settings/saved-feeds/<?= (int) $feed['id'] ?>" class="org-form">
                                        <?= $this->csrfField() ?>
                                        <label for="feed-name-<?= (int) $feed['id'] ?>">Feed name</label>
                                        <input class="input input-small" id="feed-name-<?= (int) $feed['id'] ?>" name="name"<?php if ($invalidFor('feed-' . $feed['id'], 'name')): ?> aria-invalid="true" aria-describedby="feed-<?= (int) $feed['id'] ?>-name-error"<?php endif; ?> value="<?= $e($feedOld['name'] ?? $feed['name']) ?>" maxlength="80" required>
                                        <label for="feed-board-<?= (int) $feed['id'] ?>">Board filter</label>
                                        <select class="input input-small" id="feed-board-<?= (int) $feed['id'] ?>"<?php if ($invalidFor('feed-' . $feed['id'], 'board_id')): ?> aria-invalid="true" aria-describedby="feed-<?= (int) $feed['id'] ?>-board_id-error"<?php endif; ?> name="<?= $multi ? 'board_ids[]' : 'board_id' ?>"<?= $multi ? ' multiple' : '' ?>>
                                            <?php if (!$multi): ?><option value=""<?= $selectedIds === [] ? ' selected' : '' ?>>All boards</option><?php endif; ?>
                                            <?php foreach (array_diff($selectedIds, $visibleIds) as $selectedId): ?>
                                                <option value="<?= (int) $selectedId ?>" selected>Unavailable board</option>
                                            <?php endforeach; ?>
                                            <?php foreach ($boardChoices as $board): ?>
                                                <option value="<?= (int) $board['id'] ?>"<?= in_array((int) $board['id'], $selectedIds, true) ? ' selected' : '' ?>>#<?= $e($board['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="checkline"><input type="checkbox" name="digest_enabled" value="1"<?= $feedDigest ? ' checked' : '' ?>> Digest</label>
                                        <button class="btn btn-small" type="submit">Update feed</button>
                                    </form>
                                    <?php if (!empty($feed['digest_enabled'])): ?>
                                        <form method="post" action="/settings/saved-feeds/<?= (int) $feed['id'] ?>" class="org-form">
                                            <?= $this->csrfField() ?><input type="hidden" name="digest_enabled" value="0"><button class="linkbtn" type="submit">Turn off digest</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="/settings/saved-feeds/<?= (int) $feed['id'] ?>/delete" class="org-form">
                                        <?= $this->csrfField() ?><button class="linkbtn" type="submit">Delete feed</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php $createOld = $oldFor('feed-create'); ?>
                    <?= $this->partial('partials/organization_error', ['org_form' => $org_form ?? '', 'org_errors' => $org_errors ?? [], 'form_key' => 'feed-create']) ?>
                    <form method="post" action="/settings/saved-feeds" class="inline-form org-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input input-small" maxlength="80" placeholder="Unanswered in evaluations" aria-label="Name"<?php if ($invalidFor('feed-create', 'name')): ?> aria-invalid="true" aria-describedby="feed-create-name-error"<?php endif; ?> value="<?= $e($createOld['name'] ?? '') ?>" required>
                        <label class="sr-only" for="saved-feed-board-id">Board filter</label>
                        <select id="saved-feed-board-id"<?php if ($invalidFor('feed-create', 'board_id')): ?> aria-invalid="true" aria-describedby="feed-create-board_id-error"<?php endif; ?> name="board_id" class="input input-small">
                            <option value="">All boards</option>
                            <?php foreach ($boardChoices as $board): ?>
                                <option value="<?= (int) $board['id'] ?>"<?= (int) ($createOld['board_id'] ?? 0) === (int) $board['id'] ? ' selected' : '' ?>>#<?= $e($board['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="checkline"><input type="checkbox" name="digest_enabled" value="1"<?php if ($invalidFor('feed-create', 'digest_enabled')): ?> aria-invalid="true" aria-describedby="feed-create-digest_enabled-error"<?php endif; ?><?= !empty($createOld['digest_enabled']) ? ' checked' : '' ?>> Digest</label>
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
                    <?php $createOld = $oldFor('bookmark-create'); ?>
                    <?= $this->partial('partials/organization_error', ['org_form' => $org_form ?? '', 'org_errors' => $org_errors ?? [], 'form_key' => 'bookmark-create']) ?>
                    <form method="post" action="/settings/bookmark-folders" class="inline-form org-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input input-small" maxlength="80" placeholder="Read later" aria-label="Name"<?php if ($invalidFor('bookmark-create', 'name')): ?> aria-invalid="true" aria-describedby="bookmark-create-name-error"<?php endif; ?> value="<?= $e($createOld['name'] ?? '') ?>" required>
                        <button class="btn btn-small" type="submit">New folder</button>
                    </form>
                    <?php if (!empty($bookmark_folders) && (!empty($starred_threads) || ($org_form ?? '') === 'bookmark-add')): ?>
                        <?php $addOld = $oldFor('bookmark-add'); ?>
                        <?= $this->partial('partials/organization_error', ['org_form' => $org_form ?? '', 'org_errors' => $org_errors ?? [], 'form_key' => 'bookmark-add']) ?>
                        <form method="post" action="/settings/bookmark-folders/add-thread" class="inline-form org-form">
                            <?= $this->csrfField() ?>
                            <label class="sr-only" for="bookmark-folder-id">Bookmark folder</label>
                            <select id="bookmark-folder-id" name="folder_id" class="input input-small" required>
                                <?php foreach ($bookmark_folders as $folder): ?>
                                    <option value="<?= (int) $folder['id'] ?>"<?= (int) ($addOld['folder_id'] ?? 0) === (int) $folder['id'] ? ' selected' : '' ?>><?= $e($folder['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="sr-only" for="bookmark-thread-id">Starred topic</label>
                            <select id="bookmark-thread-id"<?php if ($invalidFor('bookmark-add', 'thread_id')): ?> aria-invalid="true" aria-describedby="bookmark-add-thread_id-error"<?php endif; ?> name="thread_id" class="input input-small" required>
                                <?php if (!empty($addOld['thread_id']) && !in_array((int) $addOld['thread_id'], array_map(static fn (array $thread): int => (int) $thread['id'], $starred_threads ?? []), true)): ?>
                                    <option value="<?= (int) $addOld['thread_id'] ?>" selected>Previously selected topic unavailable</option>
                                <?php endif; ?>
                                <?php foreach ($starred_threads as $thread): ?>
                                    <option value="<?= (int) $thread['id'] ?>"<?= (int) ($addOld['thread_id'] ?? 0) === (int) $thread['id'] ? ' selected' : '' ?>><?= $e($thread['title']) ?></option>
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
