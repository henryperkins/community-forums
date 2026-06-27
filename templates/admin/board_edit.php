<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Edit board'); ?>
<div class="admin">
    <header class="admin-head">
        <h1>Edit board</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
    </nav>

    <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>" class="stacked card">
        <?= $this->csrfField() ?>
        <label class="field"><span>Category</span>
            <select name="category_id" class="input">
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) ($old['category_id'] ?? $board['category_id']) === (int) $category['id'] ? 'selected' : '' ?>>#<?= $e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['category_id'])): ?><span class="field-error"><?= $e($errors['category_id']) ?></span><?php endif; ?>
        </label>

        <label class="field"><span>Name</span>
            <input type="text" name="name" class="input" maxlength="80" value="<?= $e($old['name'] ?? $board['name']) ?>" required>
            <?php if (!empty($errors['name'])): ?><span class="field-error"><?= $e($errors['name']) ?></span><?php endif; ?>
        </label>

        <label class="field"><span>Slug</span>
            <input type="text" name="slug" class="input" maxlength="64" value="<?= $e($old['slug'] ?? $board['slug']) ?>">
            <span class="muted">Changing the slug keeps the old one working via a redirect.</span>
        </label>

        <label class="field"><span>Description</span>
            <input type="text" name="description" class="input" maxlength="255" value="<?= $e($old['description'] ?? $board['description'] ?? '') ?>">
        </label>

        <label class="field"><span>Visibility</span>
            <?php $vis = $old['visibility'] ?? $board['visibility']; ?>
            <select name="visibility" class="input">
                <option value="public" <?= $vis === 'public' ? 'selected' : '' ?>>Public</option>
                <option value="hidden" <?= $vis === 'hidden' ? 'selected' : '' ?>>Hidden (unlisted)</option>
                <option value="private" <?= $vis === 'private' ? 'selected' : '' ?>>Private (admins only)</option>
            </select>
        </label>

        <?php $anon = $old['allow_anonymous'] ?? ($board['allow_anonymous'] ?? 0); ?>
        <label class="checkline"><input type="checkbox" name="allow_anonymous" value="1" <?= !empty($anon) ? 'checked' : '' ?>> Allow anonymous posting <span class="muted">(members may hide their name from other members; moderators can still reveal the author)</span></label>

        <div class="form-actions">
            <button class="btn" type="submit">Save board</button>
            <a class="linkbtn" href="/admin/structure">Cancel</a>
        </div>
    </form>
</div>
