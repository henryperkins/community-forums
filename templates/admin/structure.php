<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Boards & categories'); ?>
<?php
    $boardOld = $create_board_old ?? [];
    $boardErr = $create_board_errors ?? [];
    $boardChecked = static fn (string $key, bool $default): bool => $boardOld === [] ? $default : !empty($boardOld[$key]);
?>
<div class="admin">
    <header class="admin-head">
        <h1>Boards &amp; categories</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'structure', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <?php if (!empty($reorder_error ?? null)): ?>
        <div class="flash flash-error"><?= $e($reorder_error) ?></div>
    <?php endif; ?>

    <div class="admin-structure" data-reorder-categories>
        <?php foreach ($categories as $category): ?>
            <?php $catFailed = (($update_category_id ?? null) === (int) $category['id']); ?>
            <section class="card admin-cat" data-category-id="<?= (int) $category['id'] ?>">
                <div class="admin-cat-head">
                    <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>" class="inline-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input" value="<?= $e($catFailed ? ($update_category_old['name'] ?? $category['name']) : $category['name']) ?>" maxlength="64" required>
                        <button class="btn btn-small" type="submit">Save</button>
                    </form>
                    <span class="admin-cat-actions">
                        <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/move" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="dir" value="up">
                            <button class="linkbtn" type="submit" aria-label="Move category <?= $e($category['name']) ?> up">↑</button>
                        </form>
                        <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/move" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="dir" value="down">
                            <button class="linkbtn" type="submit" aria-label="Move category <?= $e($category['name']) ?> down">↓</button>
                        </form>
                        <a class="linkbtn danger" href="/admin/categories/<?= (int) $category['id'] ?>/delete">Delete category</a>
                    </span>
                </div>
                <?php if ($catFailed && !empty($update_category_error ?? null)): ?>
                    <p class="field-error"><?= $e($update_category_error) ?></p>
                <?php endif; ?>

                <ul class="admin-board-list" data-reorder-boards data-category-id="<?= (int) $category['id'] ?>">
                    <?php foreach (($boards_by_category[(int) $category['id']] ?? []) as $board): ?>
                        <li class="admin-board-row" data-board-id="<?= (int) $board['id'] ?>">
                            <span><span class="hash">#</span><?= $e($board['name']) ?>
                                <span class="muted">/c/<?= $e($board['slug']) ?></span>
                                <?php if ($board['visibility'] !== 'public'): ?><span class="tag"><?= $e($board['visibility']) ?></span><?php endif; ?>
                                <?php if ((int) ($board['is_archived'] ?? 0) === 1): ?><span class="tag tag-archived">Archived</span><?php endif; ?>
                                <span class="muted">· <?= (int) $board['thread_count'] ?> threads</span>
                            </span>
                            <span class="admin-board-actions">
                                <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/move" class="inline">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="dir" value="up">
                                    <button class="linkbtn" type="submit" aria-label="Move <?= $e($board['name']) ?> up">↑</button>
                                </form>
                                <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/move" class="inline">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="dir" value="down">
                                    <button class="linkbtn" type="submit" aria-label="Move <?= $e($board['name']) ?> down">↓</button>
                                </form>
                                <a class="linkbtn" href="/admin/boards/<?= (int) $board['id'] ?>/edit">Edit</a>
                                <?php if ((int) ($board['is_archived'] ?? 0) === 1): ?>
                                    <a class="linkbtn" href="/admin/boards/<?= (int) $board['id'] ?>/unarchive">Unarchive</a>
                                <?php else: ?>
                                    <a class="linkbtn" href="/admin/boards/<?= (int) $board['id'] ?>/archive">Archive</a>
                                <?php endif; ?>
                                <a class="linkbtn danger" href="/admin/boards/<?= (int) $board['id'] ?>/delete">Delete</a>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="card">
        <h2>Add a category</h2>
        <?php if (!empty($create_category_error ?? null)): ?>
            <div class="flash flash-error"><?= $e($create_category_error) ?></div>
        <?php endif; ?>
        <form method="post" action="/admin/categories" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="text" name="name" class="input" placeholder="Category name" maxlength="64" value="<?= $e($create_category_old['name'] ?? '') ?>" required>
            <button class="btn btn-small" type="submit">Add category</button>
        </form>
    </section>

    <?php if (!empty($categories)): ?>
        <section class="card">
            <h2>Add a board</h2>
            <?php if (!empty($boardErr)): ?>
                <div class="flash flash-error">Please fix the highlighted fields.</div>
            <?php endif; ?>
            <form method="post" action="/admin/boards" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field"><span>Category</span>
                    <select name="category_id" class="input">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) ($boardOld['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>#<?= $e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($boardErr['category_id'])): ?><span class="field-error"><?= $e($boardErr['category_id']) ?></span><?php endif; ?>
                </label>
                <label class="field"><span>Name</span><input type="text" name="name" class="input" maxlength="80" value="<?= $e($boardOld['name'] ?? '') ?>" required>
                    <?php if (!empty($boardErr['name'])): ?><span class="field-error"><?= $e($boardErr['name']) ?></span><?php endif; ?>
                </label>
                <label class="field"><span>Slug <span class="muted">(optional — derived from name)</span></span><input type="text" name="slug" class="input" maxlength="64" value="<?= $e($boardOld['slug'] ?? '') ?>"></label>
                <label class="field"><span>Description</span><input type="text" name="description" class="input" maxlength="255" value="<?= $e($boardOld['description'] ?? '') ?>">
                    <?php if (!empty($boardErr['description'])): ?><span class="field-error"><?= $e($boardErr['description']) ?></span><?php endif; ?>
                </label>
                <label class="field"><span>Visibility</span>
                    <?php $bvis = $boardOld['visibility'] ?? 'public'; ?>
                    <select name="visibility" class="input">
                        <option value="public" <?= $bvis === 'public' ? 'selected' : '' ?>>Public</option>
                        <option value="hidden" <?= $bvis === 'hidden' ? 'selected' : '' ?>>Hidden (unlisted)</option>
                        <option value="private" <?= $bvis === 'private' ? 'selected' : '' ?>>Private (admins only)</option>
                    </select>
                    <?php if (!empty($boardErr['visibility'])): ?><span class="field-error"><?= $e($boardErr['visibility']) ?></span><?php endif; ?>
                </label>
                <label class="field"><span>Assignment mode</span>
                    <?php $bmode = $boardOld['assignment_mode'] ?? 'off'; ?>
                    <select name="assignment_mode" class="input">
                        <option value="off" <?= $bmode === 'off' ? 'selected' : '' ?>>Off</option>
                        <option value="self" <?= $bmode === 'self' ? 'selected' : '' ?>>Members can assign themselves</option>
                        <option value="staff" <?= $bmode === 'staff' ? 'selected' : '' ?>>Staff can assign members</option>
                    </select>
                </label>
                <label class="checkline"><input type="checkbox" name="allow_anonymous" value="1" <?= $boardChecked('allow_anonymous', false) ? 'checked' : '' ?>> Allow anonymous posting</label>
                <label class="checkline"><input type="checkbox" name="tags_enabled" value="1" <?= $boardChecked('tags_enabled', true) ? 'checked' : '' ?>> Allow approved tags</label>
                <label class="checkline"><input type="checkbox" name="wiki_enabled" value="1" <?= $boardChecked('wiki_enabled', false) ? 'checked' : '' ?>> Allow wiki-style post editing</label>
                <button class="btn btn-small" type="submit">Add board</button>
            </form>
        </section>
    <?php endif; ?>
    </div>
</div>
