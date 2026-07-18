<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Tags');
$errors = $errors ?? [];
$old = $old ?? [];
$errorForm = $error_form ?? null;
$createOld = $errorForm === 'create' ? $old : [];
$createErrors = $errorForm === 'create' ? $errors : [];
?>
<div class="admin">
    <header class="admin-head">
        <h1>Tags</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'tags', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <section class="card">
        <h2>Add a tag</h2>
        <form method="post" action="/admin/tags" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field"><span>Name</span><input class="input" type="text" name="name" maxlength="80" value="<?= $e($createOld['name'] ?? '') ?>"<?= field_attrs($createErrors, 'name') ?> required></label>
            <?= field_error($createErrors, 'name') ?>
            <label class="field"><span>Slug</span><input class="input" type="text" name="slug" maxlength="64" value="<?= $e($createOld['slug'] ?? '') ?>"<?= field_attrs($createErrors, 'slug') ?>></label>
            <?= field_error($createErrors, 'slug') ?>
            <label class="field"><span>Description</span><input class="input" type="text" name="description" maxlength="255" value="<?= $e($createOld['description'] ?? '') ?>"></label>
            <button class="btn btn-small" type="submit">Add tag</button>
        </form>
    </section>

    <section class="card">
        <h2>Catalogue</h2>
        <?php if (empty($tags)): ?>
            <p class="muted empty">No tags yet.</p>
        <?php else: ?>
            <?php // Merge destinations are enabled tags only, so the form gate
                  // counts those — a lone enabled tag among disabled ones must
                  // not render a Merge… form with an empty selector. ?>
            <?php $enabledTagCount = count(array_filter($tags, static fn (array $t): bool => (int) ($t['is_enabled'] ?? 1) === 1)); ?>
            <ul class="admin-board-list">
                <?php foreach ($tags as $tag): ?>
                    <?php
                    $isOldRow = $errorForm === 'update' && (int) ($old['id'] ?? 0) === (int) $tag['id'];
                    $row = $isOldRow ? $old : $tag;
                    $rowVisibility = (string) ($row['visibility'] ?? 'public');
                    $rowEnabled = $isOldRow ? !empty($row['enabled']) : (int) ($tag['is_enabled'] ?? 1) === 1;
                    ?>
                    <li class="admin-board-row">
                        <form method="post" action="/admin/tags/<?= (int) $tag['id'] ?>" class="inline-form">
                            <?= $this->csrfField() ?>
                            <label class="sr-only" for="tag-name-<?= (int) $tag['id'] ?>">Tag name</label>
                            <input id="tag-name-<?= (int) $tag['id'] ?>" class="input" type="text" name="name" maxlength="80" value="<?= $e($row['name'] ?? '') ?>" required>
                            <label class="sr-only" for="tag-slug-<?= (int) $tag['id'] ?>">Tag slug</label>
                            <input id="tag-slug-<?= (int) $tag['id'] ?>" class="input" type="text" name="slug" maxlength="64" value="<?= $e($row['slug'] ?? '') ?>" required>
                            <label class="sr-only" for="tag-description-<?= (int) $tag['id'] ?>">Tag description</label>
                            <input id="tag-description-<?= (int) $tag['id'] ?>" class="input" type="text" name="description" maxlength="255" value="<?= $e($row['description'] ?? '') ?>">
                            <label class="sr-only" for="tag-visibility-<?= (int) $tag['id'] ?>">Tag visibility</label>
                            <select id="tag-visibility-<?= (int) $tag['id'] ?>" class="input input-small" name="visibility">
                                <option value="public"<?= $rowVisibility === 'public' ? ' selected' : '' ?>>Public</option>
                                <option value="hidden"<?= $rowVisibility === 'hidden' ? ' selected' : '' ?>>Hidden</option>
                            </select>
                            <label class="checkline"><input type="checkbox" name="enabled" value="1" <?= $rowEnabled ? 'checked' : '' ?>> Enabled</label>
                            <button class="btn btn-small" type="submit">Save</button>
                        </form>
                        <?php if ($isOldRow && !empty($errors)): ?>
                            <div class="error-list" role="alert">
                                <?php foreach ($errors as $message): ?>
                                    <p class="field-error"><?= $e($message) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ((int) ($tag['is_enabled'] ?? 1) === 1 && $enabledTagCount > 1): ?>
                            <form method="get" action="/admin/tags/<?= (int) $tag['id'] ?>/merge" class="inline-form">
                                <label class="sr-only" for="merge-tag-<?= (int) $tag['id'] ?>">Merge into</label>
                                <select id="merge-tag-<?= (int) $tag['id'] ?>" class="input input-small" name="target_id">
                                    <?php foreach ($tags as $target): ?>
                                        <?php if ((int) $target['id'] !== (int) $tag['id'] && (int) ($target['is_enabled'] ?? 1) === 1): ?>
                                            <option value="<?= (int) $target['id'] ?>"><?= $e($target['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button class="linkbtn danger" type="submit">Merge…</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    </div>
</div>
