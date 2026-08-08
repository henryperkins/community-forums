<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Directory');
$this->section('variant', 'admin');
$filters = $filters ?? [];
$users = $users ?? [];
$sort = $filters['sort'] ?? 'created_at';
$dir = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$base = $base_query ?? [];
$page = (int) ($page ?? 0);
$total = (int) ($total ?? count($users));
$hasNext = !empty($has_next);
$bulkSelected = array_values(array_map('intval', $bulk_selected ?? []));
$selectedCount = count($bulkSelected);
$sel = fn (string $key, string $val): string => (($filters[$key] ?? '') === $val ? ' selected' : '');
$memberCount = count($users);
$resultLabel = $memberCount . ' member' . ($memberCount === 1 ? '' : 's') . ' of ' . $total;
$selectedLabel = $selectedCount === 0
    ? 'None selected'
    : $selectedCount . ' member' . ($selectedCount === 1 ? '' : 's') . ' selected';
$hasAdvancedFilters = false;
foreach (['last_seen', 'joined_from', 'joined_to', 'min_posts', 'max_posts'] as $key) {
    if ((string) ($filters[$key] ?? '') !== '') {
        $hasAdvancedFilters = true;
        break;
    }
}

/** Sortable column header that preserves the active filters and toggles direction. */
$sortHeader = function (string $key, string $label, string $class = '') use ($filters, $sort, $dir, $e): string {
    $active = $sort === $key;
    $next = ($active && $dir === 'asc') ? 'desc' : 'asc';
    $qs = array_filter(
        array_merge($filters, ['sort' => $key, 'direction' => $next]),
        static fn ($v): bool => $v !== '' && $v !== null,
    );
    $arrow = $active ? '<span aria-hidden="true">' . ($dir === 'asc' ? ' &#9650;' : ' &#9660;') . '</span>' : '';
    $ariaSort = $active ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    $classAttr = $class !== '' ? ' class="' . $e($class) . '"' : '';

    return '<th scope="col"' . $classAttr . ' aria-sort="' . $ariaSort . '"><a class="member-directory-sort" href="/admin/users?'
        . $e(http_build_query($qs)) . '">' . $e($label) . $arrow . '</a></th>';
};
?>
<?= $this->partial('admin/_member_tabs', ['active' => 'directory', 'pane_class' => 'member-directory']) ?>
    <section class="card member-directory-filters">
        <form method="get" action="/admin/users" class="filter-form member-directory-filter-form">
            <div class="filter-grid member-directory-filter-grid member-directory-common-filter-grid">
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
            </div>
            <details class="member-directory-advanced-filters"<?= $hasAdvancedFilters ? ' open' : '' ?>>
                <summary>More filters</summary>
                <div class="filter-grid member-directory-filter-grid member-directory-advanced-filter-grid">
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
                        <input type="number" name="min_posts" class="input member-directory-number-input" min="0" value="<?= $e($filters['min_posts'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span>Max posts</span>
                        <input type="number" name="max_posts" class="input member-directory-number-input" min="0" value="<?= $e($filters['max_posts'] ?? '') ?>">
                    </label>
                </div>
            </details>
            <input type="hidden" name="sort" value="<?= $e($sort) ?>">
            <input type="hidden" name="direction" value="<?= $e($dir) ?>">
            <div class="form-actions member-directory-filter-actions">
                <button class="btn btn-small" type="submit">Apply filters</button>
                <a class="btn btn-small btn-ghost" href="/admin/users">Reset</a>
                <span class="member-directory-result-count"><?= $e($resultLabel) ?></span>
            </div>
        </form>
    </section>

    <?php if (!empty($bulk_error ?? null)): ?>
        <p class="member-directory-alert" role="alert"><?= $e($bulk_error) ?></p>
    <?php endif; ?>

    <form method="post" action="/admin/users/bulk" class="member-directory-bulk-form" data-member-bulk-form>
        <?= $this->csrfField() ?>
        <?php foreach ($filters as $key => $value): ?>
            <?php if ($value !== ''): ?>
                <input type="hidden" name="<?= $e((string) $key) ?>" value="<?= $e((string) $value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <input type="hidden" name="page" value="<?= $page ?>">

        <section class="card member-directory-table-card">
            <div class="member-directory-table-shell" data-overflow-cue>
                <p class="table-scroll-cue" data-overflow-cue-label>Scroll for state, activity, and dates <span aria-hidden="true">→</span></p>
                <div class="table-scroll" tabindex="0" role="region" aria-label="User directory" data-overflow-region>
                    <table class="audit member-directory-table">
                    <thead>
                        <tr>
                            <th scope="col" class="col-select"><input type="checkbox" data-bulk-toggle aria-label="Select all members on this page"></th>
                            <?= $sortHeader('username', 'Member', 'member-directory-member-heading') ?>
                            <?= $sortHeader('role', 'Role') ?>
                            <?= $sortHeader('status', 'State') ?>
                            <?= $sortHeader('reputation', 'Reputation', 'member-directory-number') ?>
                            <?= $sortHeader('post_count', 'Posts', 'member-directory-number') ?>
                            <?= $sortHeader('last_seen', 'Last seen') ?>
                            <?= $sortHeader('created_at', 'Joined') ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $display = trim((string) ($u['display_name'] ?? ''));
                        $monogramName = $display !== '' ? $display : (string) $u['username'];
                        $role = (string) ($u['role'] ?? 'user');
                        $state = (string) ($u['status'] ?? 'active');
                        $moderatedBoards = (int) ($u['moderated_boards'] ?? 0);
                        ?>
                        <tr>
                            <td class="col-select">
                                <input type="checkbox" name="selected[]" value="<?= (int) $u['id'] ?>"<?= in_array((int) $u['id'], $bulkSelected, true) ? ' checked' : '' ?> aria-label="Select <?= $e($u['username']) ?>">
                            </td>
                            <td data-label="Member">
                                <span class="member-directory-member">
                                    <span class="member-directory-member-monogram"><?= $this->partial('partials/monogram', ['name' => $monogramName, 'username' => $u['username']]) ?></span>
                                    <a href="/admin/users/<?= (int) $u['id'] ?>" class="user-link member-directory-member-link"><?= $e($u['username']) ?></a>
                                    <?php if ($display !== ''): ?><span class="member-directory-display-name"><?= $e($display) ?></span><?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Role">
                                <span class="role-pill role-<?= $e($role) ?> member-directory-role is-<?= $e($role) ?>"><?= $e($role) ?></span>
                                <?php if ($moderatedBoards > 0): ?>
                                    <span class="tag member-directory-board-mod" title="Moderates <?= $moderatedBoards ?> board<?= $moderatedBoards === 1 ? '' : 's' ?>">board mod</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="State"><span class="state state-<?= $e($state) ?> member-directory-state is-<?= $e($state) ?>"><?= $e($state) ?></span></td>
                            <td class="member-directory-number" data-label="Reputation"><?= $e(number_format((int) $u['reputation'])) ?></td>
                            <td class="member-directory-number" data-label="Posts"><?= $e(number_format((int) ($u['post_count'] ?? 0))) ?></td>
                            <td data-label="Last seen"><?= !empty($u['last_seen_at']) ? $e(human_date((string) $u['last_seen_at'])) : '<span class="muted">never</span>' ?></td>
                            <td data-label="Joined"><?= $e(human_date($u['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            </div>

            <?php if ($users === []): ?>
                <div class="member-directory-empty">
                    <h3>No members match these filters</h3>
                    <p>Reset the filters to see the whole directory.</p>
                </div>
            <?php endif; ?>
        </section>

        <fieldset class="member-directory-bulk-bar">
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
            <span class="member-directory-selected-count" data-bulk-selected-count aria-live="polite"><?= $e($selectedLabel) ?></span>
            <p>You confirm the shared reason on the next screen; every member is actioned and audited individually.</p>
        </fieldset>
    </form>

    <nav class="pager member-directory-pager" aria-label="Pagination">
        <?php if ($page > 0): ?>
            <a class="pager-control member-directory-pager-control" href="/admin/users?<?= $e(http_build_query(array_merge($base, ['page' => $page - 1]))) ?>">Previous</a>
        <?php else: ?>
            <span class="pager-control member-directory-pager-control is-disabled" aria-disabled="true">Previous</span>
        <?php endif; ?>
        <?php if ($hasNext): ?>
            <a class="pager-control member-directory-pager-control" href="/admin/users?<?= $e(http_build_query(array_merge($base, ['page' => $page + 1]))) ?>">Next</a>
        <?php else: ?>
            <span class="pager-control member-directory-pager-control is-disabled" aria-disabled="true">Next</span>
        <?php endif; ?>
    </nav>
<?= $this->partial('admin/_console_end') ?>
