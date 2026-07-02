<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Package catalogue');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Package catalogue</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'packages', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <p class="muted">Staff browse of signed registry metadata. A signature proves byte provenance under a pinned key; install and enable still require review, consent, and local policy checks.</p>

    <?php foreach ($data['registries'] as $registry): ?>
        <?php if (!$registry['fresh']): ?>
            <p class="field-error">Stale snapshot: <strong><?= $e($registry['source_id']) ?></strong> has no
            verified snapshot inside its freshness window
            (<?= $registry['snapshot_expires_at'] !== null ? 'expired ' . $e($registry['snapshot_expires_at']) . ' UTC' : 'never fetched' ?>).
            Cached metadata below remains viewable. Run <code>php bin/console worker:registry-refresh</code>.</p>
        <?php endif; ?>
    <?php endforeach; ?>

    <section class="card">
        <h2>Packages</h2>
        <?php if ($data['packages'] === []): ?>
            <p class="muted">No packages yet. Pin a trust key, enable the registry, and run the refresh worker.</p>
        <?php else: ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Package catalogue">
        <table class="audit">
            <thead><tr><th>Package</th><th>Type</th><th>Install</th><th>Trust class</th><th>Latest</th><th>Compatibility</th><th>Advisory</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($data['packages'] as $p): ?>
                <?php $state = $data['installed_states'][(int) $p['id']] ?? null; ?>
                <tr>
                    <td>
                        <strong><?= $e($p['name']) ?></strong><br>
                        <code><?= $e($p['package_uid']) ?></code>
                        <span class="muted">via <?= $e($p['registry_source_id'] ?? 'local') ?> · <?= $e($p['publisher_name'] ?? 'unknown publisher') ?></span>
                    </td>
                    <td class="nowrap"><?= $e($p['type']) ?></td>
                    <td class="nowrap">
                        <?php if ($state === null): ?><span class="muted">-</span>
                        <?php elseif ($state === 'enabled'): ?><span class="pill">Enabled</span>
                        <?php elseif ($state === 'installed'): ?><span class="pill">Installed</span>
                        <?php else: ?><span class="pill"><?= $e(ucfirst((string) $state)) ?></span><?php endif; ?>
                    </td>
                    <td class="nowrap"><code><?= $e($p['trust_class']) ?></code></td>
                    <td class="nowrap"><?= $p['latest'] !== null ? $e($p['latest']['version']) : '<span class="muted">none stable</span>' ?></td>
                    <td class="nowrap">
                        <?php if ($p['compatible'] === null): ?><span class="muted">n/a</span>
                        <?php elseif ($p['compatible']): ?><span class="pill">compatible</span>
                        <?php else: ?><span class="pill">incompatible with this core</span><?php endif; ?>
                    </td>
                    <td class="nowrap">
                        <?php if ($p['blocked']): ?><span class="pill">locally blocked</span><?php endif; ?>
                        <?php if ($p['advisory_status'] !== 'none'): ?><span class="pill"><?= $e($p['advisory_status']) ?></span>
                        <?php elseif (!$p['blocked']): ?><span class="muted">none</span><?php endif; ?>
                    </td>
                    <td class="action-cell"><a href="/admin/packages/<?= (int) $p['id'] ?>">Details</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>
    </div>
</div>
