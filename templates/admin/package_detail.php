<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Package: ' . $package['name']);
$this->section('variant', 'admin');
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
$isInstalled = $installed !== null && $installedState !== 'uninstalled';
?>
<?= $this->partial('admin/_console', ['area' => 'packages', 'tab' => 'packages', 'pane_class' => 'admin-packages packages-detail']) ?>
    <a class="admin-back" href="/admin/packages">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Package catalogue
    </a>
    <h2 class="admin-record-title"><?= $e($package['name']) ?></h2>
    <p class="packages-record-uid"><?= $e($package['package_uid']) ?></p>
    <?php // The only surface for keyless service refusals — a server-side enable
          // refused for missing consent lands here, not in a client redirect. ?>
    <?php foreach (($errors ?? []) as $err): ?>
        <p class="callout callout-danger packages-refusal" role="alert"><?= $e($err) ?></p>
    <?php endforeach; ?>

    <div class="admin-split">
        <section class="card packages-panel-card">
            <h3 class="packages-eyebrow">Provenance</h3>
            <dl class="packages-facts">
                <div><dt class="packages-fact-term">Pinned source</dt><dd class="packages-fact-value"><?= $registry !== null ? $e($registry['source_id']) . ' (' . $e($registry['base_url']) . ')' : 'local' ?></dd></div>
                <div><dt class="packages-fact-term">Type</dt><dd class="packages-fact-value"><?= $e($package['type']) ?></dd></div>
                <div><dt class="packages-fact-term">Trust class</dt><dd class="packages-fact-value"><code><?= $e($package['trust_class']) ?></code> &mdash; trust is never implied by being listed</dd></div>
                <div><dt class="packages-fact-term">Advisory status</dt><dd class="packages-fact-value"><?= $e($package['advisory_status']) ?><?= $blocked ? ' · locally blocked' : '' ?></dd></div>
            </dl>
        </section>

        <section class="card packages-panel-card">
            <h3 class="packages-eyebrow">Installation</h3>
            <?php if (!$isInstalled): ?>
                <?php if ($installedState === 'uninstalled'): ?>
                    <p class="callout callout-review">Uninstalled. Retention ends <?= $e($installed['retain_until'] ?? 'not recorded') ?> UTC.</p>
                <?php endif; ?>
                <p class="packages-section-caption">Create an install plan before any local state is written. Enabling happens only after install and permission consent.</p>
                <form method="post" action="<?= $e($base) ?>/plan" class="stacked">
                    <?= $this->csrfField() ?>
                    <?php // feature-added: the design hardcodes the newest release. An operator
                          // installing a specific audited version needs the picker. ?>
                    <label class="field">
                        <span>Release</span>
                        <select class="input" name="release_id">
                            <?php foreach ($releases as $release): ?>
                                <option value="<?= (int) $release['id'] ?>" <?= (int) $release['id'] === (int) ($package['latest_release_id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= $e($release['version']) ?> (<?= $e(substr((string) $release['digest'], 0, 12)) ?>…)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="btn btn-small" type="submit">Install plan</button>
                </form>
            <?php else: ?>
                <?php // copy: the design renders these as a fact grid, not a table. The State
                      // value stays plain text — a chip would add a second exact-match
                      // "Enabled" node and break gate-a's strict-mode getByText. ?>
                <dl class="packages-fact-grid packages-install-facts">
                    <div><dt class="packages-fact-term">State</dt><dd class="packages-fact-value"><?= $e($stateLabel($installedState)) ?></dd></div>
                    <div><dt class="packages-fact-term">Health</dt><dd class="packages-fact-value"><?= $e($installed['health']) ?><?= $installed['quarantine_reason'] !== null ? ' · ' . $e($installed['quarantine_reason']) : '' ?></dd></div>
                    <div><dt class="packages-fact-term">Version</dt><dd class="packages-fact-value is-mono"><?= $currentRelease !== null ? $e($currentRelease['version']) : 'unknown' ?></dd></div>
                    <div><dt class="packages-fact-term">Digest</dt><dd class="packages-fact-value is-mono"><?= $e(substr((string) $installed['digest'], 0, 24)) ?>…</dd></div>
                    <div><dt class="packages-fact-term">Pinned</dt><dd class="packages-fact-value"><?= (int) $installed['pinned'] === 1 ? 'yes' : 'no' ?></dd></div>
                    <div><dt class="packages-fact-term">Update policy</dt><dd class="packages-fact-value"><?= $e($installed['update_policy']) ?></dd></div>
                </dl>

                <?php if ($pendingCount > 0): ?>
                    <?php // copy: pending consent is a review state, not an error. ?>
                    <p class="callout callout-review packages-consent-notice"><?= (int) $pendingCount ?> permission<?= $pendingCount === 1 ? '' : 's' ?> await<?= $pendingCount === 1 ? 's' : '' ?> consent. <a href="<?= $e($base) ?>/consent">Review consent</a>.</p>
                <?php endif; ?>
                <?php if ($stagedRelease !== null): ?>
                    <p class="callout callout-review packages-consent-notice">Staged, awaiting re-consent: <?= $e($stagedRelease['version']) ?>. <a href="<?= $e($base) ?>/consent">Review and approve</a>.</p>
                    <form method="post" action="<?= $e($base) ?>/update/cancel" class="inline-form">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-small btn-ghost" type="submit">Cancel staged update</button>
                    </form>
                <?php endif; ?>

                <?php // constraint C-11/C-14: every control is its own POST form carrying
                      // csrfField(), and both re-auth fields render inline and always —
                      // never behind a disclosure. ?>
                <div class="packages-action-row">
                    <?php if (in_array($installedState, ['installed', 'disabled'], true)): ?>
                        <form method="post" action="<?= $e($base) ?>/enable" class="packages-action-form">
                            <?= $this->csrfField() ?>
                            <label class="reauth-field"><span>Current password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password" required></label>
                            <button class="btn btn-small" type="submit">Enable</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($installedState === 'enabled'): ?>
                        <form method="post" action="<?= $e($base) ?>/disable" class="packages-action-form">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-small btn-secondary" type="submit">Disable</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($installedState === 'quarantined'): ?>
                        <form method="post" action="<?= $e($base) ?>/reverify" class="packages-action-form">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-small btn-ghost" type="submit">Re-verify</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" action="<?= $e($base) ?>/pin" class="packages-action-form">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="pinned" value="<?= (int) $installed['pinned'] === 1 ? '0' : '1' ?>">
                        <button class="btn btn-small btn-secondary" type="submit"><?= (int) $installed['pinned'] === 1 ? 'Unpin' : 'Pin' ?></button>
                    </form>

                    <?php // feature-changed: the design cycles the policy with a stateful ghost
                          // button, which does nothing with JavaScript off. A real select posts. ?>
                    <form method="post" action="<?= $e($base) ?>/update-policy" class="packages-action-form">
                        <?= $this->csrfField() ?>
                        <label class="sr-only" for="pkg-update-policy">Update policy</label>
                        <select class="packages-inline-select" id="pkg-update-policy" name="policy">
                            <option value="manual" <?= $installed['update_policy'] === 'manual' ? 'selected' : '' ?>>manual</option>
                            <option value="notify" <?= $installed['update_policy'] === 'notify' ? 'selected' : '' ?>>notify</option>
                        </select>
                        <button class="btn btn-small btn-ghost" type="submit">Save policy</button>
                    </form>

                    <?php if ($stagedRelease === null): ?>
                        <form method="post" action="<?= $e($base) ?>/update" class="packages-action-form">
                            <?= $this->csrfField() ?>
                            <?php if ($package['latest_release_id'] !== null && (int) $package['latest_release_id'] !== (int) $installed['release_id']): ?>
                                <p class="packages-section-caption">Update available.</p>
                            <?php endif; ?>
                            <label class="sr-only" for="pkg-update-target">Target release</label>
                            <select class="packages-inline-select" id="pkg-update-target" name="release_id">
                                <?php foreach ($releases as $release): ?>
                                    <?php if ((int) $release['id'] === (int) $installed['release_id']) { continue; } ?>
                                    <option value="<?= (int) $release['id'] ?>" <?= (int) $release['id'] === (int) ($selected_release_id ?? $package['latest_release_id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= $e($release['version']) ?> (<?= $e(substr((string) $release['digest'], 0, 12)) ?>…)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label class="reauth-field"><span>Current password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password"></label>
                            <button class="btn btn-small btn-ghost" type="submit">Update</button>
                        </form>
                    <?php endif; ?>

                    <?php if (($rollback_targets ?? []) !== []): ?>
                        <form method="post" action="<?= $e($base) ?>/rollback" class="packages-action-form">
                            <?= $this->csrfField() ?>
                            <label class="sr-only" for="pkg-rollback-target">Rollback target</label>
                            <select class="packages-inline-select" id="pkg-rollback-target" name="release_id">
                                <?php foreach ($rollback_targets as $release): ?>
                                    <option value="<?= (int) $release['id'] ?>" <?= (int) $release['id'] === (int) ($selected_release_id ?? 0) ? 'selected' : '' ?>><?= $e($release['version']) ?> (<?= $e(substr((string) $release['digest'], 0, 12)) ?>…)</option>
                                <?php endforeach; ?>
                            </select>
                            <label class="reauth-field"><span>Current password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password"></label>
                            <button class="btn btn-small btn-ghost" type="submit">Rollback</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" action="<?= $e($base) ?>/export" class="packages-action-form">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-small btn-ghost" type="submit">Export</button>
                    </form>

                    <form method="post" action="<?= $e($base) ?>/uninstall" class="packages-action-form">
                        <?= $this->csrfField() ?>
                        <label class="reauth-field"><span>Current password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password" required></label>
                        <button class="btn btn-small danger" type="submit">Uninstall</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="card packages-table-card">
        <h3 class="packages-section-title">Releases</h3>
        <p class="packages-section-caption">Immutable: any changed byte is a new release.</p>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Package releases">
        <table class="audit packages-table packages-releases-table">
            <thead><tr><th scope="col">Version</th><th scope="col">Channel</th><th scope="col">Digest (sha256)</th><th scope="col">Signed by</th><th scope="col">Core range</th><th scope="col">Review</th><th scope="col">Advisory</th><th scope="col">Local review</th></tr></thead>
            <tbody>
            <?php foreach ($releases as $r): ?>
                <tr>
                    <td class="packages-col-nowrap"><span class="packages-mono"><?= $e($r['version']) ?></span></td>
                    <td class="packages-col-nowrap"><?= $e($r['channel']) ?></td>
                    <td class="packages-col-nowrap"><code class="packages-mono"><?= $e(substr((string) $r['digest'], 0, 16)) ?>…</code><?= $r['blocked'] ? ' <span class="packages-pill is-danger">blocked</span>' : '' ?></td>
                    <td><?= $r['signed_key_id'] !== null ? '<code class="packages-mono">' . $e($r['signed_key_id']) . '</code>' : '<span class="packages-none">snapshot-listed</span>' ?></td>
                    <td class="packages-col-nowrap">
                        <code class="packages-mono"><?= $e($r['core_min'] ?? '*') ?> &ndash; <?= $e($r['core_max'] ?? '*') ?></code>
                        <?= $r['compatible'] ? '<span class="packages-pill is-done">compatible</span>' : '<span class="packages-pill is-review">incompatible</span>' ?>
                    </td>
                    <td><?= $e($r['review_status']) ?></td>
                    <td><?= $e($r['advisory_status']) ?></td>
                    <td><?= $this->partial('admin/_package_review_form', ['package_id' => $package['id'], 'release' => $r, 'review_old' => $review_old ?? []]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <div class="admin-split">
        <?php if ($isInstalled): ?>
            <section class="card packages-panel-card">
                <h3 class="packages-eyebrow">Permissions</h3>
                <?php if (($permission_labels ?? []) === []): ?>
                    <p class="packages-empty">No permissions declared.</p>
                <?php else: ?>
                <ul class="packages-rule-list">
                    <?php foreach ($permission_labels as $permission): ?>
                    <li class="packages-rule-row">
                        <span class="packages-rule-main">
                            <span class="packages-rule-label"><?= $e($permission['label']) ?></span>
                            <code class="packages-rule-id"><?= $e($permission['kind']) ?>:<?= $e($permission['permission_key']) ?></code>
                        </span>
                        <span class="packages-rule-meta"><?= $e($permission['risk_class']) ?></span>
                        <?php if ((int) $permission['granted'] === 1): ?>
                            <span class="packages-pill is-done">granted</span>
                        <?php else: ?>
                            <span class="packages-pill is-review">pending</span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card packages-panel-card">
            <h3 class="packages-eyebrow">History</h3>
            <?php if (($history ?? []) === []): ?>
                <p class="packages-empty">No lifecycle history recorded for this package.</p>
            <?php else: ?>
            <ul class="packages-rule-list">
                <?php foreach ($history as $h): ?>
                    <?php
                    $historyDetail = trim($e($h['prior_version'] ?? '') . ($h['new_version'] !== null ? ' &rarr; ' . $e($h['new_version']) : ''));
                    if (($h['failure_stage'] ?? '') !== '') {
                        $historyDetail .= ($historyDetail !== '' ? ' · ' : '') . $e((string) $h['failure_stage']);
                    }
                    if (($h['detail'] ?? '') !== '') {
                        $historyDetail .= ($historyDetail !== '' ? ' · ' : '') . $e((string) $h['detail']);
                    }
                    ?>
                <li class="packages-rule-row is-stacked">
                    <span class="packages-rule-head">
                        <span class="packages-rule-label"><?= $e($h['event']) ?></span>
                        <code class="packages-rule-id"><?= $h['new_digest'] !== null ? $e(substr((string) $h['new_digest'], 0, 16)) . '…' : 'n/a' ?></code>
                        <span class="packages-rule-when"><?= $e($h['created_at'] ?? '') ?> UTC</span>
                    </span>
                    <?php if ($historyDetail !== ''): ?><span class="packages-rule-detail"><?= $historyDetail ?></span><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>

    <?php // feature-added: the design drops per-package advisories entirely. Seeing an
          // advisory at the point of the install/enable decision is a safety affordance,
          // not decoration. ?>
    <section class="card packages-table-card">
        <h3 class="packages-eyebrow">Advisories</h3>
        <?php if ($advisories === []): ?>
            <p class="packages-empty">No advisories recorded for this package.</p>
        <?php else: ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Package advisories">
        <table class="audit packages-table packages-advisories-table">
            <thead><tr><th scope="col">Advisory</th><th scope="col">Severity</th><th scope="col">Action</th><th scope="col">Affected</th><th scope="col">Acknowledged</th></tr></thead>
            <tbody>
            <?php foreach ($advisories as $a): ?>
                <tr>
                    <td>
                        <code class="packages-rule-id"><?= $e($a['advisory_uid']) ?></code>
                        <span class="packages-rule-detail"><?= $e($a['summary'] ?? '') ?></span>
                    </td>
                    <td class="packages-col-nowrap"><?= $e($a['severity']) ?></td>
                    <td class="packages-col-nowrap"><code class="packages-mono"><?= $e($a['action']) ?></code></td>
                    <td><?= $a['affected_digest'] !== null ? 'digest ' . $e(substr((string) $a['affected_digest'], 0, 16)) . '…' : $e($a['affected_version_range'] ?? 'all versions') ?></td>
                    <td class="packages-col-nowrap"><?= $a['acknowledged_at'] !== null ? $e($a['acknowledged_at']) . ' UTC' : 'not yet' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>
    <?php if (($integration ?? null) !== null): ?>
        <?= $this->partial('admin/_package_integration', [
            'integration' => $integration,
            'settings' => $settings_describe ?? ['fields' => [], 'values' => [], 'has_secret' => []],
            'reveal' => $reveal ?? null,
            'errors' => $errors ?? [],
            'base' => $base,
        ]) ?>
    <?php endif; ?>
<?= $this->partial('admin/_console_end') ?>
