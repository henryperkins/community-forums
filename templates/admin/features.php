<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Feature flags'); ?>
<div class="admin">
    <header class="admin-head">
        <span>
            <span class="eyebrow">Runtime controls</span>
            <h1>Feature flags</h1>
        </span>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <?= $this->partial('admin/_nav', ['active' => 'features', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
        <p class="pane-intro">Read-only view of the declared feature flags from <code>src/Core/FeatureFlags.php</code>, their configured overrides in <code>settings.features</code>, and the effective runtime state. The readiness column distinguishes the rows that are not simply shipped — <strong>Ready for acceptance</strong>, <strong>Missing user UI</strong>, <strong>Missing admin operations</strong>, <strong>Safety-blocked</strong>, <strong>Operational configuration required</strong> (computed live from posture/config, so it clears once the step is done), and <strong>Reserved (ADR 0018)</strong> — and links each actionable row to its operations surface. Enablement stays a deliberate <code>settings.features</code> write (<code>docs/runbooks/operations.md</code> §2); there are intentionally no toggles here.</p>

        <?php if (!empty($features_corrupt)): ?>
            <p class="field-error">The <code>settings.features</code> value is not a JSON object, so all stored feature overrides are being ignored and code defaults are in effect. Rewrite it as a JSON object (see <code>docs/runbooks/operations.md</code> §2) to restore your overrides.</p>
        <?php endif; ?>

        <section class="admin-dashboard-grid" aria-label="Feature flag summary">
            <div class="card queue-card is-static">
                <span class="queue-card-head">Declared</span>
                <strong class="queue-card-count"><?= (int) $stats['declared'] ?></strong>
                <span class="queue-card-detail"><?= (int) $stats['declared'] ?> declared flags</span>
            </div>
            <div class="card queue-card is-static">
                <span class="queue-card-head">Defaults</span>
                <strong class="queue-card-count"><?= (int) $stats['default_on'] ?></strong>
                <span class="queue-card-detail"><?= (int) $stats['default_on'] ?> default-on · <?= (int) $stats['default_off'] ?> default-dark</span>
            </div>
            <div class="card queue-card is-static">
                <span class="queue-card-head">Effective</span>
                <strong class="queue-card-count"><?= (int) $stats['effective_on'] ?></strong>
                <span class="queue-card-detail"><?= (int) $stats['effective_on'] ?> on · <?= (int) $stats['effective_off'] ?> off</span>
            </div>
            <div class="card queue-card is-static">
                <span class="queue-card-head">Overrides</span>
                <strong class="queue-card-count"><?= (int) $stats['overrides'] ?></strong>
                <span class="queue-card-detail"><?= (int) $stats['unknown_overrides'] ?> unknown override<?= (int) $stats['unknown_overrides'] === 1 ? '' : 's' ?></span>
            </div>
        </section>

        <?php foreach ($groups as $group => $rows): ?>
            <section class="card">
                <h2><?= $e((string) $group) ?></h2>
                <div class="table-scroll" tabindex="0" role="region" aria-label="<?= $e((string) $group) ?> feature flags">
                    <table class="audit audit-flags">
                        <thead>
                            <tr>
                                <th>Flag</th>
                                <th>Effective</th>
                                <th>Default</th>
                                <th>Override</th>
                                <th>Rollback / enablement note</th>
                                <th>Readiness / next step</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <code><?= $e((string) $row['flag']) ?></code>
                                        <?php if (!empty($row['operations_href'])): ?>
                                            <a href="<?= $e($row['operations_href']) ?>">Operations</a>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="state <?= !empty($row['effective']) ? 'state-active' : 'state-paused' ?>"><?= $e((string) $row['effective_text']) ?></span></td>
                                    <td><span class="state <?= !empty($row['default']) ? 'state-active' : 'state-paused' ?>"><?= $e((string) $row['default_text']) ?></span></td>
                                    <td><span class="state <?= $e((string) $row['override_class']) ?>"><?= $e((string) $row['override_text']) ?></span></td>
                                    <td><?= $e((string) $row['rollback']) ?></td>
                                    <td>
                                        <?php if (!empty($row['readiness_status'])): ?>
                                            <span class="state <?= $e((string) $row['readiness_class']) ?>"><?= $e((string) $row['readiness_status']) ?></span>
                                            <p class="muted"><?= $e((string) $row['readiness_note']) ?><?php if (!empty($row['readiness_href'])): ?> <a href="<?= $e((string) $row['readiness_href']) ?>"><?= $e((string) $row['readiness_link']) ?></a><?php endif; ?></p>
                                        <?php else: ?>
                                            <span class="muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="card">
            <h2>Unknown overrides</h2>
            <?php if (empty($unknown_overrides)): ?>
                <p class="muted">No undeclared keys are present in <code>settings.features</code>.</p>
            <?php else: ?>
                <p class="muted">These keys are present in <code>settings.features</code> but are not declared in <code>FeatureFlags::DEFAULTS</code>. Remove them unless they are part of an in-progress local patch.</p>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Unknown feature flag overrides">
                    <table class="audit">
                        <thead><tr><th>Key</th><th>Cast value</th><th>Raw value</th></tr></thead>
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
    </div>
</div>
