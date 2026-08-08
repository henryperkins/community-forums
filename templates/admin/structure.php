<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Boards & categories'); $this->section('variant', 'admin'); ?>
<?php
    $boardOld = $create_board_old ?? [];
    $boardErr = $create_board_errors ?? [];
    $boardChecked = static fn (string $key, bool $default): bool => $boardOld === [] ? $default : !empty($boardOld[$key]);
?>
<?= $this->partial('admin/_console', [
    'area' => 'content',
    'tab' => 'structure',
    'pane_class' => 'admin-content admin-content-structure',
]) ?>
    <p class="pane-intro content-structure-intro">Categories group boards; boards are where topics live. Renaming a board is safe — its old link keeps working. Archiving makes a board read-only — its topics stay readable and searchable.</p>

    <?php if (!empty($reorder_error ?? null)): ?>
        <div class="content-alert content-alert-danger content-reorder-alert" role="alert"><?= $e($reorder_error) ?></div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <section class="content-empty-state">
            <h2>No categories yet</h2>
            <p>A forum needs at least one board. Add a category below, then put a board inside it.</p>
        </section>
    <?php endif; ?>

    <div class="admin-structure">
        <?php foreach ($categories as $category): ?>
            <?php $catFailed = (($update_category_id ?? null) === (int) $category['id']); ?>
            <section class="card admin-cat content-category-card">
                <div class="admin-cat-head">
                    <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>" class="inline-form content-category-rename">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input content-category-name" value="<?= $e($catFailed ? ($update_category_old['name'] ?? $category['name']) : $category['name']) ?>" maxlength="64" aria-label="Rename category <?= $e($category['name']) ?>" required>
                        <button class="btn btn-small" type="submit">Save</button>
                    </form>
                    <span class="admin-cat-actions">
                        <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/move" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="dir" value="up">
                            <button class="content-icon-button content-icon-button-category" type="submit" aria-label="Move category <?= $e($category['name']) ?> up">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                            </button>
                        </form>
                        <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/move" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="dir" value="down">
                            <button class="content-icon-button content-icon-button-category" type="submit" aria-label="Move category <?= $e($category['name']) ?> down">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                            </button>
                        </form>
                        <a class="content-category-delete" href="/admin/categories/<?= (int) $category['id'] ?>/delete">Delete category</a>
                    </span>
                </div>
                <?php if ($catFailed && !empty($update_category_error ?? null)): ?>
                    <p class="field-error content-category-error" role="alert"><?= $e($update_category_error) ?></p>
                <?php endif; ?>

                <ul class="admin-board-list content-board-list">
                    <?php foreach (($boards_by_category[(int) $category['id']] ?? []) as $board): ?>
                        <li class="admin-board-row content-board-row">
                            <div class="content-board-copy">
                                <span class="content-board-meta">
                                    <a class="content-board-link" href="/c/<?= $e($board['slug']) ?>"><span class="hash">#</span><?= $e($board['name']) ?></a>
                                    <span class="content-board-slug">/c/<?= $e($board['slug']) ?></span>
                                    <?php if ($board['visibility'] === 'hidden'): ?><span class="content-board-chip content-board-chip-hidden">Hidden</span><?php endif; ?>
                                    <?php if ($board['visibility'] === 'private'): ?><span class="content-board-chip content-board-chip-private">Private</span><?php endif; ?>
                                    <?php if ((int) ($board['is_archived'] ?? 0) === 1): ?><span class="content-board-chip content-board-chip-archived">Archived</span><?php endif; ?>
                                    <span class="content-board-count">· <?= (int) $board['thread_count'] ?> thread<?= (int) $board['thread_count'] === 1 ? '' : 's' ?></span>
                                </span>
                                <p class="content-board-description"><?= $e($board['description'] ?? '') ?></p>
                            </div>
                            <span class="admin-board-actions content-board-actions">
                                <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/move" class="inline">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="dir" value="up">
                                    <button class="content-icon-button content-icon-button-board" type="submit" aria-label="Move <?= $e($board['name']) ?> up">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                                    </button>
                                </form>
                                <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/move" class="inline">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="dir" value="down">
                                    <button class="content-icon-button content-icon-button-board" type="submit" aria-label="Move <?= $e($board['name']) ?> down">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    </button>
                                </form>
                                <a class="content-board-action" href="/admin/boards/<?= (int) $board['id'] ?>/edit">Edit</a>
                                <?php if ((int) ($board['is_archived'] ?? 0) === 1): ?>
                                    <a class="content-board-action" href="/admin/boards/<?= (int) $board['id'] ?>/unarchive">Unarchive</a>
                                <?php else: ?>
                                    <a class="content-board-action" href="/admin/boards/<?= (int) $board['id'] ?>/archive">Archive</a>
                                <?php endif; ?>
                                <a class="content-board-action content-board-action-danger" href="/admin/boards/<?= (int) $board['id'] ?>/delete">Delete</a>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="card content-form-card content-add-category-card">
        <h2>Add a category</h2>
        <?php if (!empty($create_category_error ?? null)): ?>
            <div class="content-alert content-alert-danger" role="alert"><?= $e($create_category_error) ?></div>
        <?php endif; ?>
        <form method="post" action="/admin/categories" class="inline-form content-add-category-form">
            <?= $this->csrfField() ?>
            <label class="sr-only" for="new-category-name">Category name</label>
            <input type="text" id="new-category-name" name="name" class="input" placeholder="Category name" maxlength="64" value="<?= $e($create_category_old['name'] ?? '') ?>" required>
            <button class="btn btn-small" type="submit">Add category</button>
        </form>
    </section>

    <?php if (!empty($categories)): ?>
        <section class="card content-form-card content-add-board-card">
            <h2>Add a board</h2>
            <?php if (!empty($boardErr)): ?>
                <div class="content-alert content-alert-danger" role="alert">Please fix the highlighted fields.</div>
            <?php endif; ?>
            <form method="post" action="/admin/boards" class="stacked content-board-grid">
                <?= $this->csrfField() ?>
                <label class="field"><span>Category</span>
                    <select name="category_id" class="input">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) ($boardOld['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>#<?= $e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($boardErr, 'category_id', 'err-board-category_id') ?>
                </label>
                <label class="field"><span>Name</span><input type="text" name="name" class="input" maxlength="80" value="<?= $e($boardOld['name'] ?? '') ?>"<?= field_attrs($boardErr, 'name', 'err-board-name') ?> required>
                    <?= field_error($boardErr, 'name', 'err-board-name') ?>
                </label>
                <label class="field"><span>Slug <span class="content-label-note">(optional — derived from name)</span></span><input type="text" name="slug" class="input content-mono-input" maxlength="64" placeholder="derived from the name" value="<?= $e($boardOld['slug'] ?? '') ?>"<?= field_attrs($boardErr, 'slug', 'err-board-slug') ?>>
                    <?= field_error($boardErr, 'slug', 'err-board-slug') ?>
                </label>
                <label class="field"><span>Description</span><input type="text" name="description" class="input" maxlength="255" value="<?= $e($boardOld['description'] ?? '') ?>"<?= field_attrs($boardErr, 'description', 'err-board-description') ?>>
                    <?= field_error($boardErr, 'description', 'err-board-description') ?>
                </label>
                <label class="field"><span>Visibility</span>
                    <?php $bvis = $boardOld['visibility'] ?? 'public'; ?>
                    <select name="visibility" class="input">
                        <option value="public" <?= $bvis === 'public' ? 'selected' : '' ?>>Public</option>
                        <option value="hidden" <?= $bvis === 'hidden' ? 'selected' : '' ?>>Hidden (unlisted)</option>
                        <option value="private" <?= $bvis === 'private' ? 'selected' : '' ?>>Private (members only)</option>
                    </select>
                    <?= field_error($boardErr, 'visibility', 'err-board-visibility') ?>
                </label>
                <label class="field"><span>Who can post</span>
                    <?php $bMinRole = $boardOld['post_min_role'] ?? 'user'; ?>
                    <select name="post_min_role" class="input">
                        <option value="user" <?= $bMinRole === 'user' ? 'selected' : '' ?>>All members</option>
                        <option value="moderator" <?= $bMinRole === 'moderator' ? 'selected' : '' ?>>Moderators and admins</option>
                        <option value="admin" <?= $bMinRole === 'admin' ? 'selected' : '' ?>>Admins only (announcements)</option>
                    </select>
                    <?= field_error($boardErr, 'post_min_role', 'err-board-post_min_role') ?>
                </label>
                <label class="field"><span>Edit window (minutes, 0 = no limit)</span>
                    <input type="number" name="edit_window_minutes" class="input content-mono-input content-edit-window-input" min="0" max="10080" value="<?= $e((string) ($boardOld['edit_window_minutes'] ?? '0')) ?>"<?= field_attrs($boardErr, 'edit_window_minutes', 'err-board-edit_window_minutes') ?>>
                    <?= field_error($boardErr, 'edit_window_minutes', 'err-board-edit_window_minutes') ?>
                </label>
                <label class="field"><span>Assignment mode</span>
                    <?php $bmode = $boardOld['assignment_mode'] ?? 'off'; ?>
                    <select name="assignment_mode" class="input">
                        <option value="off" <?= $bmode === 'off' ? 'selected' : '' ?>>Off</option>
                        <option value="self" <?= $bmode === 'self' ? 'selected' : '' ?>>Members can assign themselves</option>
                        <option value="staff" <?= $bmode === 'staff' ? 'selected' : '' ?>>Staff can assign members</option>
                    </select>
                </label>
                <div class="content-check-grid">
                    <label class="checkline"><input type="hidden" name="allow_anonymous" value="0"><input type="checkbox" name="allow_anonymous" value="1" <?= $boardChecked('allow_anonymous', false) ? 'checked' : '' ?>> Allow anonymous posting</label>
                    <label class="checkline"><input type="hidden" name="require_approval" value="0"><input type="checkbox" name="require_approval" value="1" <?= $boardChecked('require_approval', false) ? 'checked' : '' ?>> Require approval before posts appear</label>
                    <label class="checkline"><input type="hidden" name="tags_enabled" value="0"><input type="checkbox" name="tags_enabled" value="1" <?= $boardChecked('tags_enabled', true) ? 'checked' : '' ?>> Allow approved tags</label>
                    <label class="checkline"><input type="hidden" name="wiki_enabled" value="0"><input type="checkbox" name="wiki_enabled" value="1" <?= $boardChecked('wiki_enabled', false) ? 'checked' : '' ?>> Allow wiki-style post editing</label>
                </div>
                <div class="content-grid-actions">
                    <button class="btn btn-small" type="submit">Add board</button>
                </div>
            </form>
        </section>
    <?php endif; ?>
<?= $this->partial('admin/_console_end') ?>
