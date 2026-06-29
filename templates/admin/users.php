<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Users');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Users</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <a class="active" href="/admin/users">Users</a>
    </nav>

    <section class="card">
        <form method="get" action="/admin/users" class="inline-form">
            <input type="search" name="q" class="input" maxlength="80" value="<?= $e($q) ?>" placeholder="Search username, name, or email">
            <button class="btn btn-small" type="submit">Search</button>
        </form>

        <table class="audit">
            <thead><tr><th>User</th><th>Role</th><th>State</th><th>Reputation</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <a href="/admin/users/<?= (int) $u['id'] ?>"><?= $e($u['username']) ?></a>
                        <?php if (($u['display_name'] ?? '') !== ''): ?><span class="muted">(<?= $e($u['display_name']) ?>)</span><?php endif; ?>
                    </td>
                    <td><?= $e($u['role']) ?></td>
                    <td><?= $e($u['status']) ?></td>
                    <td><?= (int) $u['reputation'] ?></td>
                    <td><?= $e(human_date($u['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="5" class="muted">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <nav class="pager">
            <?php if ($page > 0): ?><a class="btn btn-small" href="/admin/users?<?= $e(http_build_query(['q' => $q, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?>
            <?php if (!empty($has_next)): ?><a class="btn btn-small" href="/admin/users?<?= $e(http_build_query(['q' => $q, 'page' => $page + 1])) ?>">Next</a><?php endif; ?>
        </nav>
    </section>
</div>
