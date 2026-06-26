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

    <?php foreach ($categories as $category): ?>
        <section class="card admin-cat">
            <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="text" name="name" class="input" value="<?= $e($category['name']) ?>" maxlength="64" required>
                <input type="number" name="position" class="input input-narrow" value="<?= (int) $category['position'] ?>" aria-label="Position">
                <button class="btn btn-small" type="submit">Save</button>
            </form>
            <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete" class="inline">
                <?= $this->csrfField() ?>
                <button class="linkbtn danger" type="submit">Delete category</button>
            </form>

            <ul class="admin-board-list">
                <?php foreach (($boards_by_category[(int) $category['id']] ?? []) as $board): ?>
                    <li class="admin-board-row">
                        <span><span class="hash">#</span><?= $e($board['name']) ?>
                            <span class="muted">/c/<?= $e($board['slug']) ?></span>
                            <?php if ($board['visibility'] !== 'public'): ?><span class="tag"><?= $e($board['visibility']) ?></span><?php endif; ?>
                            <span class="muted">· <?= (int) $board['thread_count'] ?> threads</span>
                        </span>
                        <span class="admin-board-actions">
                            <a class="linkbtn" href="/admin/boards/<?= (int) $board['id'] ?>/edit">Edit</a>
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
                <button class="btn btn-small" type="submit">Add board</button>
            </form>
        </section>
    <?php endif; ?>
</div>
