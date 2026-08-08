<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Feature flags'); $this->section('variant', 'admin'); ?>
<?= $this->partial('admin/_console', ['area' => 'features', 'tab' => 'features', 'pane_class' => 'admin-features features-flags']) ?>
    <p class="pane-intro features-intro">A read-only view of the declared flags, their configured overrides in <code>settings.features</code>, and the effective runtime state. Readiness distinguishes rows that are not simply shipped. Enablement stays a deliberate <code>settings.features</code> write &mdash; there are intentionally no toggles here.</p>

    <?php if (!empty($features_corrupt)): ?>
        <p class="callout callout-danger features-corrupt-alert" role="alert">The <code>settings.features</code> value is not a JSON object, so all stored feature overrides are being ignored and code defaults are in effect. Rewrite it as a JSON object (see <code>docs/runbooks/operations.md</code> §2) to restore your overrides.</p>
    <?php endif; ?>

    <section class="features-stat-grid" aria-label="Feature flag summary">
        <div class="card features-stat">
            <span class="features-stat-head">Declared</span>
            <strong class="features-stat-count"><?= (int) $stats['declared'] ?></strong>
            <span class="features-stat-detail"><?= (int) $stats['declared'] ?> declared flags</span>
        </div>
        <div class="card features-stat">
            <span class="features-stat-head">Defaults</span>
            <strong class="features-stat-count"><?= (int) $stats['default_on'] ?></strong>
            <span class="features-stat-detail"><?= (int) $stats['default_on'] ?> default-on · <?= (int) $stats['default_off'] ?> default-dark</span>
        </div>
        <div class="card features-stat">
            <span class="features-stat-head">Effective</span>
            <strong class="features-stat-count"><?= (int) $stats['effective_on'] ?></strong>
            <?php // copy: the design reports the corrupt posture in the tile detail rather
                  // than repeating a count the overrides are no longer feeding. ?>
            <span class="features-stat-detail"><?= !empty($features_corrupt)
                ? 'code defaults in effect'
                : ((int) $stats['effective_on'] . ' on · ' . (int) $stats['effective_off'] . ' off') ?></span>
        </div>
        <div class="card features-stat">
            <span class="features-stat-head">Overrides</span>
            <strong class="features-stat-count"><?= !empty($features_corrupt) ? 0 : (int) $stats['overrides'] ?></strong>
            <span class="features-stat-detail"><?= !empty($features_corrupt)
                ? 'all overrides ignored'
                : ((int) $stats['unknown_overrides'] . ' unknown override' . ((int) $stats['unknown_overrides'] === 1 ? '' : 's')) ?></span>
        </div>
    </section>

    <?php foreach ($groups as $group => $rows): ?>
        <section class="card features-group-card">
            <h2 class="features-group-title"><?= $e((string) $group) ?></h2>
            <div class="table-scroll" tabindex="0" role="region" aria-label="<?= $e((string) $group) ?> feature flags">
                <table class="audit audit-flags features-table features-flags-table">
                    <thead>
                        <tr>
                            <th scope="col">Flag</th>
                            <th scope="col">Effective</th>
                            <th scope="col">Default</th>
                            <th scope="col">Override</th>
                            <th scope="col">Rollback / enablement note</th>
                            <th scope="col">Readiness / next step</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="features-flag-cell">
                                    <code><?= $e((string) $row['flag']) ?></code>
                                    <?php if (!empty($row['operations_href'])): ?>
                                        <?php // feature-added: the design has no operations affordance. ?>
                                        <a href="<?= $e($row['operations_href']) ?>">Operations</a>
                                    <?php endif; ?>
                                </td>
                                <?php // constraint C-23: the design shortens these to a bare "on"/"off" pill.
                                      // The fail-dark proof in AppAdminFeaturesTest asserts "Override off" is
                                      // present *and* "Override on" is absent, which a bare "off" cannot
                                      // express. The full strings stay; only the chrome is the design's. ?>
                                <td><span class="features-effective <?= !empty($row['effective']) ? 'is-on' : 'is-off' ?>"><span class="features-dot" aria-hidden="true"></span><?= $e((string) $row['effective_text']) ?></span></td>
                                <td class="features-default"><?= $e((string) $row['default_text']) ?></td>
                                <td><?php if ($row['override_class'] === 'state-pending'): ?><span class="features-override-none"><?= $e((string) $row['override_text']) ?></span><?php else: ?><span class="features-override-pill"><?= $e((string) $row['override_text']) ?></span><?php endif; ?></td>
                                <td class="features-rollback"><?= $e((string) $row['rollback']) ?></td>
                                <td class="features-readiness">
                                    <?php if (!empty($row['readiness_status'])): ?>
                                        <span class="state <?= $e((string) $row['readiness_class']) ?>"><?= $e((string) $row['readiness_status']) ?></span>
                                        <p class="features-readiness-note"><?= $e((string) $row['readiness_note']) ?><?php if (!empty($row['readiness_href'])): ?> <a href="<?= $e((string) $row['readiness_href']) ?>"><?= $e((string) $row['readiness_link']) ?></a><?php endif; ?></p>
                                    <?php else: ?>
                                        <span class="features-readiness-empty">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>

    <?php // feature-added: the design drops the readiness key and both doc pointers.
          // Production keeps them; they move below the tables so the intro reads as the
          // design's three sentences. Must stay outside any <table> (the Reserved-chip
          // count is table-scoped) and inside .admin-pane (the axe scope). ?>
    <p class="features-legend">Flags are declared in <code>src/Core/FeatureFlags.php</code>. The readiness column distinguishes the rows that are not simply shipped &mdash; <strong>Missing user UI</strong>, <strong>Missing admin operations</strong>, <strong>Safety-blocked</strong>, <strong>Operational configuration required</strong> (computed live from posture/config, so it clears once the step is done), and <strong>Reserved (ADR 0018)</strong> &mdash; and links each actionable row to its operations surface. Enablement stays a deliberate <code>settings.features</code> write (<code>docs/runbooks/operations.md</code> §2).</p>

    <section class="card features-unknown-card">
        <h2 class="features-group-title">Unknown overrides</h2>
        <?php if (empty($unknown_overrides)): ?>
            <?php // feature-added: the design renders an empty table; production says why it is empty. ?>
            <p class="features-note">No undeclared keys are present in <code>settings.features</code>.</p>
        <?php else: ?>
            <p class="features-note">These keys are present in <code>settings.features</code> but are not declared in <code>FeatureFlags::DEFAULTS</code>. Remove them unless they are part of an in-progress local patch.</p>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Unknown feature flag overrides">
                <table class="audit features-table features-unknown-table">
                    <thead><tr><th scope="col">Key</th><th scope="col">Cast value</th><th scope="col">Raw value</th></tr></thead>
                    <tbody>
                        <?php foreach ($unknown_overrides as $row): ?>
                            <tr>
                                <td><code><?= $e((string) $row['flag']) ?></code></td>
                                <td><?= $e((string) $row['value_text']) ?></td>
                                <td><code><?= $e((string) $row['raw_value']) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
