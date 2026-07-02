<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Package: ' . $package['name']);
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($package['name']) ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/packages">Packages</a>
        <a href="/admin/registries">Registry trust</a>
    </nav>

    <div class="admin-pane">
    <section class="card">
        <h2>Provenance</h2>
        <table class="audit">
            <tbody>
                <tr><th>Package identity</th><td><code><?= $e($package['package_uid']) ?></code></td></tr>
                <tr><th>Pinned source</th><td><?= $registry !== null ? $e($registry['source_id']) . ' (' . $e($registry['base_url']) . ')' : 'local' ?></td></tr>
                <tr><th>Type</th><td><?= $e($package['type']) ?></td></tr>
                <tr><th>Trust class</th><td><code><?= $e($package['trust_class']) ?></code>; trust is never implied by being listed</td></tr>
                <tr><th>Advisory status</th><td><?= $e($package['advisory_status']) ?><?= $blocked ? ' · locally blocked' : '' ?></td></tr>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Releases (immutable: any changed byte is a new release)</h2>
        <div class="table-scroll">
        <table class="audit">
            <thead><tr><th>Version</th><th>Channel</th><th>Digest (sha256)</th><th>Signed by</th><th>Review</th><th>Core range</th><th>Advisory</th></tr></thead>
            <tbody>
            <?php foreach ($releases as $r): ?>
                <tr>
                    <td><?= $e($r['version']) ?></td>
                    <td><?= $e($r['channel']) ?></td>
                    <td><code><?= $e(substr((string) $r['digest'], 0, 16)) ?>...</code><?= $r['blocked'] ? ' <span class="pill">blocked</span>' : '' ?></td>
                    <td><?= $r['signed_key_id'] !== null ? '<code>' . $e($r['signed_key_id']) . '</code>' : '<span class="muted">snapshot-listed</span>' ?></td>
                    <td><?= $e($r['review_status']) ?></td>
                    <td>
                        <code><?= $e($r['core_min'] ?? '*') ?> - <?= $e($r['core_max'] ?? '*') ?></code>
                        <?= $r['compatible'] ? '<span class="pill">compatible</span>' : '<span class="pill">incompatible</span>' ?>
                    </td>
                    <td><?= $e($r['advisory_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="card">
        <h2>Advisories</h2>
        <?php if ($advisories === []): ?>
            <p class="muted">No advisories recorded for this package.</p>
        <?php else: ?>
        <div class="table-scroll">
        <table class="audit">
            <thead><tr><th>Advisory</th><th>Severity</th><th>Action</th><th>Affected</th><th>Acknowledged</th></tr></thead>
            <tbody>
            <?php foreach ($advisories as $a): ?>
                <tr>
                    <td><code><?= $e($a['advisory_uid']) ?></code><br><span class="muted"><?= $e($a['summary'] ?? '') ?></span></td>
                    <td><?= $e($a['severity']) ?></td>
                    <td><code><?= $e($a['action']) ?></code></td>
                    <td><?= $a['affected_digest'] !== null ? 'digest ' . $e(substr((string) $a['affected_digest'], 0, 16)) . '...' : $e($a['affected_version_range'] ?? 'all versions') ?></td>
                    <td><?= $a['acknowledged_at'] !== null ? $e($a['acknowledged_at']) . ' UTC' : 'not yet' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>
    </div>
</div>
