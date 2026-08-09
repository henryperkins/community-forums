<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Tags');
$this->section('variant', 'admin');
$errors = $errors ?? [];
$old = $old ?? [];
$errorForm = $error_form ?? null;
$createOld = $errorForm === 'create' ? $old : [];
$createErrors = $errorForm === 'create' ? $errors : [];
$mergeTargets = $merge_targets ?? [];
$q = (string) ($q ?? '');
$sort = (string) ($sort ?? 'usage');
$page = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($page_count ?? 1));
$total = max(0, (int) ($total ?? count($tags ?? [])));
$baseQuery = is_array($base_query ?? null) ? $base_query : [];
$queryHref = static function (array $query): string {
    $encoded = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    return '/admin/tags' . ($encoded === '' ? '' : '?' . $encoded);
};
$sortQuery = static fn (string $value): array => array_filter(
    ['q' => $q, 'sort' => $value],
    static fn (string $item): bool => $item !== '',
);
$pageQuery = static fn (int $value): array => array_replace($baseQuery, ['page' => max(1, $value)]);
?>
<?= $this->partial('admin/_console', [
    'area' => 'content',
    'tab' => 'tags',
    'pane_class' => 'admin-content admin-content-tags',
]) ?>
    <section class="card content-form-card content-tag-create">
        <h2>Add a tag</h2>
        <?php if (!empty($createErrors)): ?>
            <?php $createErrorField = (string) array_key_first($createErrors); ?>
            <div class="content-alert content-alert-danger" role="alert">
                <?= field_error($createErrors, $createErrorField, 'err-' . $createErrorField) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="/admin/tags" class="stacked content-tag-create-grid">
            <?= $this->csrfField() ?>
            <input type="hidden" name="q" value="<?= $e($q) ?>">
            <input type="hidden" name="sort" value="<?= $e($sort) ?>">
            <input type="hidden" name="page" value="<?= $page ?>">
            <label class="field"><span>Name</span><input class="input" type="text" name="name" maxlength="80" value="<?= $e($createOld['name'] ?? '') ?>"<?= field_attrs($createErrors, 'name') ?> required></label>
            <label class="field"><span>Slug</span><input class="input content-mono-input" type="text" name="slug" maxlength="64" placeholder="derived from the name" value="<?= $e($createOld['slug'] ?? '') ?>"<?= field_attrs($createErrors, 'slug') ?>></label>
            <label class="field"><span>Description</span><input class="input" type="text" name="description" maxlength="255" value="<?= $e($createOld['description'] ?? '') ?>"></label>
            <button class="btn btn-small" type="submit">Add tag</button>
        </form>
    </section>

    <section class="card content-tag-catalogue">
        <div class="content-tag-catalogue-head">
            <h2>Catalogue</h2>
            <form class="content-tag-search" method="get" action="/admin/tags">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
                <input class="input" type="search" enterkeyhint="search" name="q" value="<?= $e($q) ?>" placeholder="Search the catalogue" aria-label="Search the catalogue">
                <input type="hidden" name="sort" value="<?= $e($sort) ?>">
            </form>
            <nav class="content-tag-sort" aria-label="Sort tags">
                <a class="content-tag-sort-control<?= $sort === 'usage' ? ' is-active' : '' ?>" href="<?= $e($queryHref($sortQuery('usage'))) ?>"<?= $sort === 'usage' ? ' aria-current="page"' : '' ?>>Most used</a>
                <a class="content-tag-sort-control<?= $sort === 'name' ? ' is-active' : '' ?>" href="<?= $e($queryHref($sortQuery('name'))) ?>"<?= $sort === 'name' ? ' aria-current="page"' : '' ?>>A–Z</a>
            </nav>
            <span class="content-tag-result-count"><?= $total ?> tag<?= $total === 1 ? '' : 's' ?></span>
        </div>

        <?php if (empty($tags)): ?>
            <div class="content-tag-empty">
                <?php if ($q !== ''): ?>
                    <h3>Nothing matches “<?= $e($q) ?>”</h3>
                    <p>Try a shorter phrase, or add it as a new tag above.</p>
                <?php else: ?>
                    <h3>No tags yet</h3>
                    <p>Add the first tag above. Fewer, sharper tags beat many vague ones.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php // Merge destinations intentionally come from the unfiltered,
                  // unpaged enabled pool. Search must never hide a valid target. ?>
            <?php $enabledTagCount = count($mergeTargets); ?>
            <ul class="admin-board-list content-tag-list">
                <?php foreach ($tags as $tag): ?>
                    <?php
                    $tagId = (int) $tag['id'];
                    $isOldRow = $errorForm === 'update' && (int) ($old['id'] ?? 0) === $tagId;
                    $row = $isOldRow ? $old : $tag;
                    $rowErrors = $isOldRow ? $errors : [];
                    $rowVisibility = (string) ($row['visibility'] ?? 'public');
                    $rowEnabled = $isOldRow ? !empty($row['enabled']) : (int) ($tag['is_enabled'] ?? 1) === 1;
                    ?>
                    <li class="admin-board-row content-tag-row">
                        <form method="post" action="/admin/tags/<?= $tagId ?>" class="inline-form content-tag-update">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="q" value="<?= $e($q) ?>">
                            <input type="hidden" name="sort" value="<?= $e($sort) ?>">
                            <input type="hidden" name="page" value="<?= $page ?>">
                            <label class="sr-only" for="tag-name-<?= $tagId ?>">Tag name</label>
                            <input id="tag-name-<?= $tagId ?>" class="input content-tag-name" type="text" name="name" maxlength="80" value="<?= $e($row['name'] ?? '') ?>"<?= field_attrs($rowErrors, 'name', 'err-tag-' . $tagId . '-name') ?> required>
                            <label class="sr-only" for="tag-slug-<?= $tagId ?>">Tag slug</label>
                            <input id="tag-slug-<?= $tagId ?>" class="input content-tag-slug" type="text" name="slug" maxlength="64" value="<?= $e($row['slug'] ?? '') ?>"<?= field_attrs($rowErrors, 'slug', 'err-tag-' . $tagId . '-slug') ?> required>
                            <label class="sr-only" for="tag-description-<?= $tagId ?>">Tag description</label>
                            <input id="tag-description-<?= $tagId ?>" class="input content-tag-description" type="text" name="description" maxlength="255" placeholder="Description" value="<?= $e($row['description'] ?? '') ?>">
                            <label class="sr-only" for="tag-visibility-<?= $tagId ?>">Tag visibility</label>
                            <select id="tag-visibility-<?= $tagId ?>" class="input content-tag-visibility" name="visibility">
                                <option value="public"<?= $rowVisibility === 'public' ? ' selected' : '' ?>>Public</option>
                                <option value="hidden"<?= $rowVisibility === 'hidden' ? ' selected' : '' ?>>Hidden</option>
                            </select>
                            <label class="checkline content-tag-enabled"><input type="checkbox" name="enabled" value="1" <?= $rowEnabled ? 'checked' : '' ?>> Enabled</label>
                            <span class="content-tag-uses"><?= (int) ($tag['usage_count'] ?? 0) ?> uses</span>
                            <button class="btn btn-small content-tag-save" type="submit">Save</button>
                            <?php if ($isOldRow && !empty($rowErrors)): ?>
                                <div class="content-tag-row-errors" role="alert">
                                    <?php foreach (array_keys($rowErrors) as $field): ?>
                                        <?= field_error($rowErrors, (string) $field, 'err-tag-' . $tagId . '-' . (string) $field) ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </form>
                        <?php if ((int) ($tag['is_enabled'] ?? 1) === 1 && $enabledTagCount > 1): ?>
                            <form method="get" action="/admin/tags/<?= $tagId ?>/merge" class="inline-form content-tag-merge">
                                <label class="sr-only" for="merge-tag-<?= $tagId ?>">Merge into</label>
                                <select id="merge-tag-<?= $tagId ?>" class="input" name="target_id">
                                    <?php foreach ($mergeTargets as $target): ?>
                                        <?php if ((int) $target['id'] !== $tagId): ?>
                                            <option value="<?= (int) $target['id'] ?>"><?= $e($target['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button class="content-board-action content-board-action-danger" type="submit">Merge…</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($pageCount > 1): ?>
            <nav class="pager content-tag-pager" aria-label="Tag catalogue pages">
                <?php if ($page > 1): ?>
                    <a class="pager-control" href="<?= $e($queryHref($pageQuery($page - 1))) ?>" aria-label="Previous tag page">Previous</a>
                <?php else: ?>
                    <span class="pager-control is-disabled" aria-disabled="true">Previous</span>
                <?php endif; ?>
                <span class="pager-label">Page <?= $page ?> of <?= $pageCount ?></span>
                <?php if ($page < $pageCount): ?>
                    <a class="pager-control" href="<?= $e($queryHref($pageQuery($page + 1))) ?>" aria-label="Next tag page">Next</a>
                <?php else: ?>
                    <span class="pager-control is-disabled" aria-disabled="true">Next</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
