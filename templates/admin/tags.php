<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Tags'); ?>
<div class="admin">
    <header class="admin-head">
        <h1>Tags</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <a class="active" href="/admin/tags">Tags</a>
    </nav>

    <section class="card">
        <h2>Add a tag</h2>
        <form method="post" action="/admin/tags" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field"><span>Name</span><input class="input" type="text" name="name" maxlength="80" required></label>
            <label class="field"><span>Slug</span><input class="input" type="text" name="slug" maxlength="64"></label>
            <label class="field"><span>Description</span><input class="input" type="text" name="description" maxlength="255"></label>
            <button class="btn btn-small" type="submit">Add tag</button>
        </form>
    </section>

    <section class="card">
        <h2>Catalogue</h2>
        <?php if (empty($tags)): ?>
            <p class="muted empty">No tags yet.</p>
        <?php else: ?>
            <ul class="admin-board-list">
                <?php foreach ($tags as $tag): ?>
                    <li class="admin-board-row">
                        <form method="post" action="/admin/tags/<?= (int) $tag['id'] ?>" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input class="input" type="text" name="name" maxlength="80" value="<?= $e($tag['name']) ?>" required>
                            <input class="input" type="text" name="slug" maxlength="64" value="<?= $e($tag['slug']) ?>" required>
                            <input class="input" type="text" name="description" maxlength="255" value="<?= $e($tag['description'] ?? '') ?>">
                            <input type="hidden" name="enabled" value="1">
                            <button class="btn btn-small" type="submit">Save</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
