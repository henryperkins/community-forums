<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Package catalogue');
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'packages', 'tab' => 'packages', 'pane_class' => 'admin-packages packages-catalogue']) ?>
    <div class="packages-intro-row">
        <p class="pane-intro packages-intro">A staff browse of signed registry metadata. A signature proves byte provenance under a pinned key; install and enable still require review, consent, and local policy checks.</p>
        <?php // constraint C-11: this is one of only two inbound links to the security
              // console and AppAdminNavIaTest pins the href. It stays an <a>, never the
              // design's <button onClick>. ?>
        <a class="packages-intro-action" href="/admin/packages/security">Package security response &rarr;</a>
    </div>

    <?php foreach ($data['registries'] as $registry): ?>
        <?php // feature-changed FC-23: gate the alert on the registry being enabled.
              // A registry added here "starts disabled", and painting a red
              // "never fetched" banner for a source the operator deliberately left
              // off reports a fault that does not exist. The freshness fact itself is
              // still computed for every registry by RegistryCatalogService. ?>
        <?php if (!$registry['fresh'] && ((int) $registry['is_enabled']) === 1): ?>
            <p class="callout callout-danger packages-stale-alert" role="alert">Stale snapshot: <strong><?= $e($registry['source_id']) ?></strong> has no verified snapshot inside its freshness window (<?= $registry['snapshot_expires_at'] !== null ? 'expired ' . $e($registry['snapshot_expires_at']) . ' UTC' : 'never fetched' ?>). Cached metadata below remains viewable. Run <code>php bin/console worker:registry-refresh</code>.</p>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($data['packages'] === []): ?>
        <?php // feature-added FA-22: the design renders an empty table; production says why. ?>
        <p class="packages-empty">No packages yet. Pin a trust key, enable the registry, and run the refresh worker.</p>
    <?php else: ?>
    <?php // copy: the design's catalogue card carries no heading — the area h1 and the
          // lit tab already name the surface. The labelled scroll region is the
          // accessible name. Mirrors the People slice's removal of <h2>Roles</h2>. ?>
    <section class="card packages-table-card">
        <div class="table-scroll" tabindex="0" role="region" aria-label="Package catalogue">
        <table class="audit packages-table packages-catalogue-table">
            <thead><tr><th scope="col">Package</th><th scope="col">Type</th><th scope="col">Install</th><th scope="col">Trust class</th><th scope="col">Latest</th><th scope="col">Compatibility</th><th scope="col">Advisory</th><th scope="col" class="packages-col-actions"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($data['packages'] as $p): ?>
                <?php $state = $data['installed_states'][(int) $p['id']] ?? null; ?>
                <tr>
                    <td>
                        <strong class="packages-name"><?= $e($p['name']) ?></strong>
                        <code class="packages-uid"><?= $e($p['package_uid']) ?></code>
                        <span class="packages-via">via <?= $e($p['registry_source_id'] ?? 'local') ?> · <?= $e($p['publisher_name'] ?? 'unknown publisher') ?></span>
                    </td>
                    <td class="packages-col-nowrap"><?= $e($p['type']) ?></td>
                    <td class="packages-col-nowrap">
                        <?php if ($state === null): ?><span class="packages-none">&mdash;</span>
                        <?php elseif ($state === 'enabled'): ?><span class="packages-pill is-install">Enabled</span>
                        <?php elseif ($state === 'installed'): ?><span class="packages-pill is-install">Installed</span>
                        <?php else: ?><span class="packages-pill is-install"><?= $e(ucfirst((string) $state)) ?></span><?php endif; ?>
                    </td>
                    <td class="packages-col-nowrap"><code class="packages-mono"><?= $e($p['trust_class']) ?></code></td>
                    <td class="packages-col-nowrap"><?= $p['latest'] !== null ? '<span class="packages-mono">' . $e($p['latest']['version']) . '</span>' : '<span class="packages-none">none stable</span>' ?></td>
                    <td class="packages-col-nowrap">
                        <?php // feature-added: the design has no third state. Production
                              // distinguishes "no declared range" from "incompatible". ?>
                        <?php if ($p['compatible'] === null): ?><span class="packages-none">n/a</span>
                        <?php elseif ($p['compatible']): ?><span class="packages-pill is-done">compatible</span>
                        <?php else: ?><span class="packages-pill is-review">incompatible</span><?php endif; ?>
                    </td>
                    <td class="packages-col-nowrap">
                        <?php // feature-changed FC-22: the design collapses these to one signal.
                              // A local block and an upstream advisory are independent facts and
                              // both stay. ?>
                        <?php if ($p['blocked']): ?><span class="packages-pill is-danger">locally blocked</span><?php endif; ?>
                        <?php if ($p['advisory_status'] !== 'none'): ?><span class="packages-pill is-danger"><?= $e($p['advisory_status']) ?></span>
                        <?php elseif (!$p['blocked']): ?><span class="packages-none">none</span><?php endif; ?>
                    </td>
                    <td class="packages-col-actions"><a class="packages-rowbtn" href="/admin/packages/<?= (int) $p['id'] ?>">Details</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php endif; ?>
<?= $this->partial('admin/_console_end') ?>
