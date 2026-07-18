<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$package = $plan['package'];
$release = $plan['release'];
$base = '/admin/packages/' . (int) $package['id'];
$this->section('title', 'Install plan: ' . $package['name']);
?>
<div class="admin">
    <header class="admin-head">
        <h1>Install plan - <?= $e($package['name']) ?> <?= $e($release['version']) ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'packages', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <?php foreach (($errors ?? []) as $err): ?>
        <p class="field-error"><?= $e($err) ?></p>
    <?php endforeach; ?>

    <?php if ($plan['refusal'] !== null): ?>
        <p class="field-error">
            <?= $e($plan['refusal']['code'] . ': ' . $plan['refusal']['message']) ?>
            <?= $plan['refusal']['code'] === 'locally_blocked' ? ' Matched local blocklist.' : '' ?>
        </p>
    <?php endif; ?>

    <?php foreach ($plan['warnings'] as $warning): ?>
        <p class="field-error"><?= $e($warning) ?></p>
    <?php endforeach; ?>

    <section class="card">
        <h2>Install plan</h2>
        <p class="muted">Installing records provenance and permissions; nothing executes until you consent and enable.</p>
        <table class="audit">
            <tbody>
                <tr><th scope="row">Package</th><td><?= $e($package['name']) ?> <code><?= $e($package['package_uid']) ?></code></td></tr>
                <tr><th scope="row">Version</th><td><?= $e($release['version']) ?></td></tr>
                <tr><th scope="row">Digest</th><td><code><?= $e($release['digest']) ?></code></td></tr>
                <tr><th scope="row">Registry</th><td><?= $plan['registry'] !== null ? $e($plan['registry']['source_id']) : 'local' ?></td></tr>
                <tr><th scope="row">Review</th><td><?= $e($release['review_status']) ?></td></tr>
                <tr><th scope="row">Compatibility</th><td><?= $plan['compatible'] === true ? '<span class="pill">compatible</span>' : '<span class="pill">incompatible</span>' ?></td></tr>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Permission preview</h2>
        <?php if ($plan['permissions'] === []): ?>
            <p class="muted">No permissions declared.</p>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Package permission preview">
            <table class="audit">
                <thead><tr><th scope="col">Permission</th><th scope="col">Risk</th></tr></thead>
                <tbody>
                <?php foreach ($plan['permissions'] as $permission): ?>
                    <tr>
                        <td><?= $e($permission['label']) ?><br><code><?= $e($permission['kind']) ?>:<?= $e($permission['key']) ?></code></td>
                        <td><?= $e($permission['risk']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($plan['refusal'] === null && ($plan['installed'] === null || $plan['installed']['state'] === 'uninstalled')): ?>
        <section class="card">
            <h2>Install</h2>
            <form method="post" action="<?= $e($base) ?>/install" class="stacked">
                <?= $this->csrfField() ?>
                <input type="hidden" name="release_id" value="<?= (int) $release['id'] ?>">
                <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
                <div class="form-actions">
                    <button class="btn" type="submit">Record install</button>
                    <a class="linkbtn" href="<?= $e($base) ?>">Cancel</a>
                </div>
                <p class="muted">Nothing runs yet: the next step asks you to review and consent to the permissions, and the package stays disabled until you enable it.</p>
            </form>
        </section>
    <?php endif; ?>
    </div>
</div>
