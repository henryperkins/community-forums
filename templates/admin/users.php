<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Users');
$filters = $filters ?? [];
$sort = $filters['sort'] ?? 'created_at';
$dir = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$base = $base_query ?? [];
$sel = fn (string $key, string $val): string => (($filters[$key] ?? '') === $val ? ' selected' : '');
/** Sortable column header that preserves the active filters and toggles direction. */
$sortHeader = function (string $key, string $label) use ($filters, $sort, $dir, $e): string {
    $active = $sort === $key;
    $next = ($active && $dir === 'asc') ? 'desc' : 'asc';
    $qs = array_filter(
        array_merge($filters, ['sort' => $key, 'direction' => $next]),
        static fn ($v): bool => $v !== '' && $v !== null,
    );
    $arrow = $active ? '<span aria-hidden="true">' . ($dir === 'asc' ? ' &#9650;' : ' &#9660;') . '</span>' : '';
    $ariaSort = $active ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    return '<th scope="col" aria-sort="' . $ariaSort . '"><a href="/admin/users?' . $e(http_build_query($qs)) . '">'
        . $e($label) . $arrow . '</a></th>';
};
?>
<div class="admin">
    <header class="admin-head">
        <h1>Users</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'users', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <section class="card">
        <form method="get" action="/admin/users" class="filter-form">
            <div class="filter-grid">
                <label class="field">
                    <span>Search</span>
                    <input type="search" name="q" class="input" maxlength="80" value="<?= $e($filters['q'] ?? '') ?>" placeholder="Username, name, or email">
                </label>
                <label class="field">
                    <span>Role</span>
                    <select name="role" class="input">
                        <option value="">Any role</option>
                        <option value="user"<?= $sel('role', 'user') ?>>User</option>
                        <option value="moderator"<?= $sel('role', 'moderator') ?>>Moderator</option>
                        <option value="admin"<?= $sel('role', 'admin') ?>>Admin</option>
                    </select>
                </label>
                <label class="field">
                    <span>State</span>
                    <select name="status" class="input">
                        <option value="">Any state</option>
                        <option value="active"<?= $sel('status', 'active') ?>>Active</option>
                        <option value="suspended"<?= $sel('status', 'suspended') ?>>Suspended</option>
                        <option value="banned"<?= $sel('status', 'banned') ?>>Banned</option>
                        <option value="deactivated"<?= $sel('status', 'deactivated') ?>>Deactivated</option>
                    </select>
                </label>
                <label class="field">
                    <span>Last seen</span>
                    <select name="last_seen" class="input">
                        <option value="">Any time</option>
                        <option value="1"<?= $sel('last_seen', '1') ?>>Past 24 hours</option>
                        <option value="7"<?= $sel('last_seen', '7') ?>>Past 7 days</option>
                        <option value="30"<?= $sel('last_seen', '30') ?>>Past 30 days</option>
                        <option value="90"<?= $sel('last_seen', '90') ?>>Past 90 days</option>
                        <option value="never"<?= $sel('last_seen', 'never') ?>>Never</option>
                    </select>
                </label>
                <label class="field">
                    <span>Joined from</span>
                    <input type="date" name="joined_from" class="input" value="<?= $e($filters['joined_from'] ?? '') ?>">
                </label>
                <label class="field">
                    <span>Joined to</span>
                    <input type="date" name="joined_to" class="input" value="<?= $e($filters['joined_to'] ?? '') ?>">
                </label>
                <label class="field">
                    <span>Min posts</span>
                    <input type="number" name="min_posts" class="input" min="0" value="<?= $e($filters['min_posts'] ?? '') ?>">
                </label>
                <label class="field">
                    <span>Max posts</span>
                    <input type="number" name="max_posts" class="input" min="0" value="<?= $e($filters['max_posts'] ?? '') ?>">
                </label>
            </div>
            <input type="hidden" name="sort" value="<?= $e($sort) ?>">
            <input type="hidden" name="direction" value="<?= $e($dir) ?>">
            <div class="form-actions">
                <button class="btn btn-small" type="submit">Apply filters</button>
                <a class="btn btn-small btn-ghost" href="/admin/users">Reset</a>
            </div>
        </form>

        <?php if (!empty($bulk_error ?? null)): ?>
            <p class="field-error" role="alert"><?= $e($bulk_error) ?></p>
        <?php endif; ?>

        <form method="post" action="/admin/users/bulk" class="user-directory">
            <?= $this->csrfField() ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="User directory">
            <table class="audit">
                <thead>
                    <tr>
                        <th scope="col" class="col-select"><input type="checkbox" data-bulk-toggle aria-label="Select all users on this page"></th>
                        <?= $sortHeader('username', 'User') ?>
                        <?= $sortHeader('role', 'Role') ?>
                        <?= $sortHeader('status', 'State') ?>
                        <?= $sortHeader('reputation', 'Reputation') ?>
                        <?= $sortHeader('post_count', 'Posts') ?>
                        <?= $sortHeader('last_seen', 'Last seen') ?>
                        <?= $sortHeader('created_at', 'Joined') ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="col-select">
                            <input type="checkbox" name="selected[]" value="<?= (int) $u['id'] ?>" aria-label="Select <?= $e($u['username']) ?>">
                        </td>
                        <td data-label="User">
                            <a href="/admin/users/<?= (int) $u['id'] ?>" class="user-link"><?= $e($u['username']) ?></a>
                            <?php if (($u['display_name'] ?? '') !== ''): ?><span class="muted">(<?= $e($u['display_name']) ?>)</span><?php endif; ?>
                        </td>
                        <td data-label="Role">
                            <span class="role-pill role-<?= $e($u['role']) ?>"><?= $e($u['role']) ?></span>
                            <?php if (($u['role'] ?? '') === 'user' && (int) ($u['moderated_boards'] ?? 0) > 0): ?>
                                <span class="tag" title="Moderates <?= (int) $u['moderated_boards'] ?> board<?= (int) $u['moderated_boards'] === 1 ? '' : 's' ?>">board mod</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="State"><span class="state state-<?= $e($u['status']) ?>"><?= $e($u['status']) ?></span></td>
                        <td data-label="Reputation"><?= (int) $u['reputation'] ?></td>
                        <td data-label="Posts"><?= (int) ($u['post_count'] ?? 0) ?></td>
                        <td data-label="Last seen"><?= !empty($u['last_seen_at']) ? $e(human_date((string) $u['last_seen_at'])) : '<span class="muted">never</span>' ?></td>
                        <td data-label="Joined"><?= $e(human_date($u['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="8" class="muted">No users match these filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>

            <fieldset class="bulk-bar">
                <legend>Bulk actions</legend>
                <label class="field">
                    <span class="sr-only">Bulk action</span>
                    <select name="bulk_action" class="input">
                        <option value="">Choose an action…</option>
                        <option value="warn">Warn selected</option>
                        <option value="suspend">Suspend selected</option>
                    </select>
                </label>
                <button class="btn btn-small" type="submit">Review and apply…</button>
                <p class="muted">You confirm the shared reason on the next screen; every member is actioned and audited individually.</p>
            </fieldset>
        </form>

        <nav class="pager">
            <?php if ($page > 0): ?>
                <a class="btn btn-small" href="/admin/users?<?= $e(http_build_query(array_merge($base, ['page' => $page - 1]))) ?>">Previous</a>
            <?php endif; ?>
            <?php if (!empty($has_next)): ?>
                <a class="btn btn-small" href="/admin/users?<?= $e(http_build_query(array_merge($base, ['page' => $page + 1]))) ?>">Next</a>
            <?php endif; ?>
        </nav>
    </section>
    </div>
</div>
