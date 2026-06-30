<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Boards & categories'); ?>
<div class="admin">
    <header class="admin-head">
        <h1>Boards &amp; categories</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a class="active" href="/admin/structure">Boards &amp; categories</a>
    </nav>

    <div class="admin-pane">
    <?php if (!empty($reorder_error ?? null)): ?>
        <div class="flash flash-error"><?= $e($reorder_error) ?></div>
    <?php endif; ?>

    <div class="admin-structure" data-reorder-categories>
        <?php foreach ($categories as $category): ?>
            <section class="card admin-cat" data-category-id="<?= (int) $category['id'] ?>">
                <div class="admin-cat-head">
                    <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>" class="inline-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input" value="<?= $e($category['name']) ?>" maxlength="64" required>
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
                        <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete" class="inline">
                            <?= $this->csrfField() ?>
                            <button class="linkbtn danger" type="submit">Delete category</button>
                        </form>
                    </span>
                </div>

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
                                    <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/unarchive" class="inline">
                                        <?= $this->csrfField() ?>
                                        <button class="linkbtn" type="submit">Unarchive</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/archive" class="inline">
                                        <?= $this->csrfField() ?>
                                        <button class="linkbtn" type="submit">Archive</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/delete" class="inline">
                                    <?= $this->csrfField() ?>
                                    <button class="linkbtn danger" type="submit">Delete</button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="card">
        <h2>Add a category</h2>
        <form method="post" action="/admin/categories" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="text" name="name" class="input" placeholder="Category name" maxlength="64" required>
            <button class="btn btn-small" type="submit">Add category</button>
        </form>
    </section>

    <?php if (!empty($categories)): ?>
        <section class="card">
            <h2>Add a board</h2>
            <form method="post" action="/admin/boards" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field"><span>Category</span>
                    <select name="category_id" class="input">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>">#<?= $e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span>Name</span><input type="text" name="name" class="input" maxlength="80" required></label>
                <label class="field"><span>Slug <span class="muted">(optional — derived from name)</span></span><input type="text" name="slug" class="input" maxlength="64"></label>
                <label class="field"><span>Description</span><input type="text" name="description" class="input" maxlength="255"></label>
                <label class="field"><span>Visibility</span>
                    <select name="visibility" class="input">
                        <option value="public">Public</option>
                        <option value="hidden">Hidden (unlisted)</option>
                        <option value="private">Private (admins only)</option>
                    </select>
                </label>
                <label class="field"><span>Assignment mode</span>
                    <select name="assignment_mode" class="input">
                        <option value="off">Off</option>
                        <option value="self">Members can assign themselves</option>
                        <option value="staff">Staff can assign members</option>
                    </select>
                </label>
                <label class="checkline"><input type="checkbox" name="allow_anonymous" value="1"> Allow anonymous posting</label>
                <label class="checkline"><input type="checkbox" name="tags_enabled" value="1" checked> Allow approved tags</label>
                <label class="checkline"><input type="checkbox" name="wiki_enabled" value="1"> Allow wiki-style post editing</label>
                <button class="btn btn-small" type="submit">Add board</button>
            </form>
        </section>
    <?php endif; ?>
    </div>
</div>
