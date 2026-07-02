<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$package = $detail['package'];
$base = '/admin/packages/' . (int) $package['id'];
$isUpdate = $staged_plan !== null;
$target = $isUpdate ? $staged_plan['target'] : null;
$this->section('title', $isUpdate ? 'Approve update: ' . $package['name'] : 'Consent: ' . $package['name']);
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $isUpdate ? 'Approve update to ' . $e($target['version']) : 'Consent to permissions' ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/packages">Packages</a>
        <a href="<?= $e($base) ?>">Package detail</a>
    </nav>

    <div class="admin-pane">
    <?php foreach (($errors ?? []) as $err): ?>
        <p class="field-error"><?= $e($err) ?></p>
    <?php endforeach; ?>

    <?php if ($isUpdate && $staged_plan['refusal'] !== null): ?>
        <p class="field-error"><?= $e($staged_plan['refusal']['code'] . ': ' . $staged_plan['refusal']['message']) ?></p>
    <?php endif; ?>

    <section class="card">
        <h2><?= $isUpdate ? 'Permission changes' : 'Pending grants' ?></h2>
        <?php if ($isUpdate): ?>
            <h3>New permissions</h3>
            <?php if ($staged_plan['diff']['added'] === []): ?>
                <p class="muted">No new permissions.</p>
            <?php else: ?>
                <div class="table-scroll">
                <table class="audit">
                    <thead><tr><th>Permission</th><th>Risk</th></tr></thead>
                    <tbody>
                    <?php foreach ($staged_plan['diff']['added'] as $permission): ?>
                        <tr>
                            <td><?= $e($permission['label']) ?><br><code><?= $e($permission['kind']) ?>:<?= $e($permission['key']) ?></code></td>
                            <td><?= $e($permission['risk']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

            <h3>Removed</h3>
            <?php if ($staged_plan['diff']['removed'] === []): ?>
                <p class="muted">No removed permissions.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($staged_plan['diff']['removed'] as $permission): ?>
                        <li><?= $e($permission['label']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3>Unchanged</h3>
            <?php if ($staged_plan['diff']['unchanged'] === []): ?>
                <p class="muted">No unchanged permissions.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($staged_plan['diff']['unchanged'] as $permission): ?>
                        <li><?= $e($permission['label']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($pending_permissions === []): ?>
                <p class="muted">No pending grants.</p>
            <?php else: ?>
                <div class="table-scroll">
                <table class="audit">
                    <thead><tr><th>Permission</th><th>Risk</th></tr></thead>
                    <tbody>
                    <?php foreach ($pending_permissions as $permission): ?>
                        <tr>
                            <td><?= $e($permission['label']) ?><br><code><?= $e($permission['kind']) ?>:<?= $e($permission['permission_key']) ?></code></td>
                            <td><?= $e($permission['risk_class']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Grant</h2>
        <form method="post" action="<?= $e($base) ?>/consent" class="stacked">
            <?= $this->csrfField() ?>
            <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
            <button type="submit">Grant and continue</button>
        </form>
    </section>
    </div>
</div>
