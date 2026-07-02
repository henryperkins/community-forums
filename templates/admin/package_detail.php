<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Package: ' . $package['name']);
$base = '/admin/packages/' . (int) $package['id'];
$releaseById = [];
foreach ($releases as $release) {
    $releaseById[(int) $release['id']] = $release;
}
$stateLabel = static fn (?string $state): string => match ($state) {
    'enabled' => 'Enabled',
    'disabled' => 'Disabled',
    'quarantined' => 'Quarantined',
    'uninstalled' => 'Uninstalled',
    'installed' => 'Installed',
    default => 'Not installed',
};
$installedState = $installed !== null ? (string) $installed['state'] : null;
$currentRelease = $installed !== null && $installed['release_id'] !== null
    ? ($releaseById[(int) $installed['release_id']] ?? null)
    : null;
$stagedRelease = $installed !== null && $installed['staged_release_id'] !== null
    ? ($releaseById[(int) $installed['staged_release_id']] ?? null)
    : null;
$pendingCount = 0;
foreach ($installed_permissions as $permission) {
    if ((int) $permission['granted'] === 0) {
        $pendingCount++;
    }
}
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($package['name']) ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'packages', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <?php foreach (($errors ?? []) as $err): ?>
        <p class="field-error"><?= $e($err) ?></p>
    <?php endforeach; ?>

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
        <div class="table-scroll" tabindex="0" role="region" aria-label="Package releases">
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
        <h2>Installation</h2>
        <?php if ($installed === null || $installedState === 'uninstalled'): ?>
            <?php if ($installedState === 'uninstalled'): ?>
                <p><span class="pill">Uninstalled</span> Retention ends <?= $e($installed['retain_until'] ?? 'not recorded') ?> UTC.</p>
            <?php endif; ?>
            <p class="muted">Create an install plan before any local state is written. Enabling happens only after install and permission consent.</p>
            <form method="post" action="<?= $e($base) ?>/plan" class="stacked">
                <?= $this->csrfField() ?>
                <label>
                    Release
                    <select name="release_id">
                        <?php foreach ($releases as $release): ?>
                            <option value="<?= (int) $release['id'] ?>" <?= (int) $release['id'] === (int) ($package['latest_release_id'] ?? 0) ? 'selected' : '' ?>>
                                <?= $e($release['version']) ?> (<?= $e(substr((string) $release['digest'], 0, 12)) ?>...)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Install plan</button>
            </form>
        <?php else: ?>
            <table class="audit">
                <tbody>
                    <tr><th>State</th><td><span class="pill"><?= $e($stateLabel($installedState)) ?></span></td></tr>
                    <tr><th>Health</th><td><?= $e($installed['health']) ?><?= $installed['quarantine_reason'] !== null ? ' · ' . $e($installed['quarantine_reason']) : '' ?></td></tr>
                    <tr><th>Version</th><td><?= $currentRelease !== null ? $e($currentRelease['version']) : '<span class="muted">unknown</span>' ?></td></tr>
                    <tr><th>Digest</th><td><code><?= $e(substr((string) $installed['digest'], 0, 24)) ?>...</code></td></tr>
                    <tr><th>Pinned</th><td><?= (int) $installed['pinned'] === 1 ? 'yes' : 'no' ?></td></tr>
                    <tr><th>Update policy</th><td><?= $e($installed['update_policy']) ?></td></tr>
                </tbody>
            </table>

            <?php if ($pendingCount > 0): ?>
                <p class="field-error"><?= (int) $pendingCount ?> permissions await consent. <a href="<?= $e($base) ?>/consent">Review consent</a>.</p>
            <?php endif; ?>
            <?php if ($stagedRelease !== null): ?>
                <p class="field-error">Staged, awaiting re-consent: <?= $e($stagedRelease['version']) ?>. <a href="<?= $e($base) ?>/consent">Review and approve</a>.</p>
                <form method="post" action="<?= $e($base) ?>/update/cancel" class="inline-form">
                    <?= $this->csrfField() ?>
                    <button type="submit">Cancel staged update</button>
                </form>
            <?php endif; ?>

            <div class="form-grid">
                <?php if (in_array($installedState, ['installed', 'disabled'], true)): ?>
                    <form method="post" action="<?= $e($base) ?>/enable" class="stacked">
                        <?= $this->csrfField() ?>
                        <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
                        <button type="submit">Enable</button>
                    </form>
                <?php endif; ?>

                <?php if ($installedState === 'enabled'): ?>
                    <form method="post" action="<?= $e($base) ?>/disable" class="stacked">
                        <?= $this->csrfField() ?>
                        <button type="submit">Disable</button>
                    </form>
                <?php endif; ?>

                <?php if ($installedState === 'quarantined'): ?>
                    <form method="post" action="<?= $e($base) ?>/reverify" class="stacked">
                        <?= $this->csrfField() ?>
                        <button type="submit">Re-verify</button>
                    </form>
                <?php endif; ?>

                <form method="post" action="<?= $e($base) ?>/pin" class="stacked">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="pinned" value="<?= (int) $installed['pinned'] === 1 ? '0' : '1' ?>">
                    <button type="submit"><?= (int) $installed['pinned'] === 1 ? 'Unpin' : 'Pin' ?></button>
                </form>

                <form method="post" action="<?= $e($base) ?>/update-policy" class="stacked">
                    <?= $this->csrfField() ?>
                    <label>
                        Update policy
                        <select name="policy">
                            <option value="manual" <?= $installed['update_policy'] === 'manual' ? 'selected' : '' ?>>manual</option>
                            <option value="notify" <?= $installed['update_policy'] === 'notify' ? 'selected' : '' ?>>notify</option>
                        </select>
                    </label>
                    <button type="submit">Save policy</button>
                </form>

                <?php if ($stagedRelease === null): ?>
                    <form method="post" action="<?= $e($base) ?>/update" class="stacked">
                        <?= $this->csrfField() ?>
                        <?php if ($package['latest_release_id'] !== null && (int) $package['latest_release_id'] !== (int) $installed['release_id']): ?>
                            <p class="muted">Update available.</p>
                        <?php endif; ?>
                        <label>
                            Target release
                            <select name="release_id">
                                <?php foreach ($releases as $release): ?>
                                    <?php if ((int) $release['id'] === (int) $installed['release_id']) { continue; } ?>
                                    <option value="<?= (int) $release['id'] ?>" <?= (int) $release['id'] === (int) ($selected_release_id ?? $package['latest_release_id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= $e($release['version']) ?> (<?= $e(substr((string) $release['digest'], 0, 12)) ?>...)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Current password <input type="password" name="current_password" autocomplete="current-password"></label>
                        <button type="submit">Update</button>
                    </form>
                <?php endif; ?>

                <?php if (($rollback_targets ?? []) !== []): ?>
                    <form method="post" action="<?= $e($base) ?>/rollback" class="stacked">
                        <?= $this->csrfField() ?>
                        <label>
                            Rollback target
                            <select name="release_id">
                                <?php foreach ($rollback_targets as $release): ?>
                                    <option value="<?= (int) $release['id'] ?>" <?= (int) $release['id'] === (int) ($selected_release_id ?? 0) ? 'selected' : '' ?>><?= $e($release['version']) ?> (<?= $e(substr((string) $release['digest'], 0, 12)) ?>...)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Current password <input type="password" name="current_password" autocomplete="current-password"></label>
                        <button type="submit">Rollback</button>
                    </form>
                <?php endif; ?>

                <form method="post" action="<?= $e($base) ?>/export" class="stacked">
                    <?= $this->csrfField() ?>
                    <button type="submit">Export</button>
                </form>

                <form method="post" action="<?= $e($base) ?>/uninstall" class="stacked">
                    <?= $this->csrfField() ?>
                    <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
                    <button type="submit">Uninstall</button>
                </form>
            </div>

            <h3>Permissions</h3>
            <?php if (($permission_labels ?? []) === []): ?>
                <p class="muted">No permissions declared.</p>
            <?php else: ?>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Installed package permissions">
                <table class="audit">
                    <thead><tr><th>Permission</th><th>Risk</th><th>Granted</th></tr></thead>
                    <tbody>
                    <?php foreach ($permission_labels as $permission): ?>
                        <tr>
                            <td><?= $e($permission['label']) ?><br><code><?= $e($permission['kind']) ?>:<?= $e($permission['permission_key']) ?></code></td>
                            <td><?= $e($permission['risk_class']) ?></td>
                            <td><?= (int) $permission['granted'] === 1 ? 'yes' : 'pending' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>History</h2>
        <?php if (($history ?? []) === []): ?>
            <p class="muted">No lifecycle history recorded for this package.</p>
        <?php else: ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Package lifecycle history">
        <table class="audit">
            <thead><tr><th>Event</th><th>Versions</th><th>Digest</th><th>Stage</th><th>Detail</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= $e($h['event']) ?></td>
                    <td><?= $e($h['prior_version'] ?? '') ?><?= $h['new_version'] !== null ? ' -> ' . $e($h['new_version']) : '' ?></td>
                    <td><?= $h['new_digest'] !== null ? '<code>' . $e(substr((string) $h['new_digest'], 0, 16)) . '...</code>' : '<span class="muted">n/a</span>' ?></td>
                    <td><?= $e($h['failure_stage'] ?? '') ?></td>
                    <td><?= $e($h['detail'] ?? '') ?></td>
                    <td><?= $e($h['created_at'] ?? '') ?> UTC</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Advisories</h2>
        <?php if ($advisories === []): ?>
            <p class="muted">No advisories recorded for this package.</p>
        <?php else: ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Package advisories">
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
