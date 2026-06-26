<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Admin'); ?>
<div class="admin">
    <header class="admin-head">
        <h1>Admin console</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <nav class="subnav">
        <a class="active" href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
    </nav>

    <section class="card">
        <h2>Site name</h2>
        <form method="post" action="/admin/site" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="text" name="site_name" class="input" maxlength="80" value="<?= $e($site_name) ?>" required>
            <button class="btn btn-small" type="submit">Update</button>
        </form>
    </section>

    <section class="card">
        <h2>Recent activity</h2>
        <?php if (empty($audit)): ?>
            <p class="muted">No moderation or admin actions yet.</p>
        <?php else: ?>
            <table class="audit">
                <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Reason</th></tr></thead>
                <tbody>
                <?php foreach ($audit as $row): ?>
                    <tr>
                        <td><?= $e(human_datetime($row['created_at'])) ?></td>
                        <td><?= $e($row['actor_username'] ?? 'system') ?></td>
                        <td><code><?= $e($row['action']) ?></code></td>
                        <td><?= $e($row['target_type']) ?> #<?= (int) $row['target_id'] ?></td>
                        <td><?= $e($row['reason'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
