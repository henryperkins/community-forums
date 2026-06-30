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

    <div class="admin-pane">
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
                            <select class="input input-small" name="visibility">
                                <option value="public"<?= ($tag['visibility'] ?? 'public') === 'public' ? ' selected' : '' ?>>Public</option>
                                <option value="hidden"<?= ($tag['visibility'] ?? 'public') === 'hidden' ? ' selected' : '' ?>>Hidden</option>
                            </select>
                            <label class="checkline"><input type="checkbox" name="enabled" value="1" <?= (int) ($tag['is_enabled'] ?? 1) === 1 ? 'checked' : '' ?>> Enabled</label>
                            <button class="btn btn-small" type="submit">Save</button>
                        </form>
                        <?php if ((int) ($tag['is_enabled'] ?? 1) === 1 && count($tags) > 1): ?>
                            <form method="post" action="/admin/tags/<?= (int) $tag['id'] ?>/merge" class="inline-form">
                                <?= $this->csrfField() ?>
                                <label class="sr-only" for="merge-tag-<?= (int) $tag['id'] ?>">Merge into</label>
                                <select id="merge-tag-<?= (int) $tag['id'] ?>" class="input input-small" name="target_id">
                                    <?php foreach ($tags as $target): ?>
                                        <?php if ((int) $target['id'] !== (int) $tag['id'] && (int) ($target['is_enabled'] ?? 1) === 1): ?>
                                            <option value="<?= (int) $target['id'] ?>"><?= $e($target['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button class="linkbtn muted" type="submit">Merge</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    </div>
</div>
