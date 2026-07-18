<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Admin'); ?>
<div class="admin">
    <header class="admin-head">
        <span>
            <span class="eyebrow">Operator desk</span>
            <h1>Admin console</h1>
        </span>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <?= $this->partial('admin/_nav', ['active' => 'dashboard', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
        <p class="pane-intro">Start with live queues and health signals, then review what has changed across the community.</p>

        <section class="admin-dashboard-section" aria-labelledby="queue-health-heading">
            <div class="section-heading-row">
                <div>
                    <span class="eyebrow">Live operations</span>
                    <h2 id="queue-health-heading">Queue health</h2>
                </div>
                <span class="status-legend"><i aria-hidden="true"></i> Live</span>
            </div>
            <div class="admin-dashboard-grid" aria-label="Queue health summary">
                <?php foreach ($queue_cards as $card): ?>
                    <?php $tag = !empty($card['href']) ? 'a' : 'div'; ?>
                    <<?= $tag ?> class="card queue-card queue-status-<?= $e($card['status']) ?><?= empty($card['href']) ? ' is-static' : '' ?>" data-queue-status="<?= $e($card['status']) ?>"<?= !empty($card['href']) ? ' href="' . $e($card['href']) . '"' : '' ?>>
                        <span class="queue-card-head"><?= $e($card['title']) ?></span>
                        <strong class="queue-card-count"><?= (int) $card['count'] ?></strong>
                        <span class="queue-card-detail"><?= $e($card['detail']) ?></span>
                        <span class="queue-card-state"><?= $e(ucfirst($card['status'])) ?></span>
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card attention-panel" aria-labelledby="attention-heading">
            <div class="section-heading-row">
                <div>
                    <span class="eyebrow">Triage</span>
                    <h2 id="attention-heading">Needs attention</h2>
                </div>
                <span class="attention-total"><?= count($attention) ?></span>
            </div>
            <?php if (empty($attention)): ?>
                <p class="muted">No pending operator work right now.</p>
            <?php else: ?>
                <ul class="link-list attention-list">
                    <?php foreach ($attention as $item): ?>
                        <li>
                            <?php if (!empty($item['href'])): ?>
                                <a href="<?= $e($item['href']) ?>"><?= $e($item['label']) ?></a>
                            <?php else: ?>
                                <span><?= $e($item['label']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="admin-dashboard-section" aria-labelledby="community-today-heading">
            <div class="section-heading-row">
                <div>
                    <span class="eyebrow">Community pulse</span>
                    <h2 id="community-today-heading">Community today</h2>
                </div>
            </div>
            <div class="activity-card-grid">
                <?php foreach ($activity_cards as $card): ?>
                    <a class="card activity-card" href="<?= $e($card['href']) ?>">
                        <span class="activity-card-copy">
                            <span class="activity-card-title"><?= $e($card['title']) ?></span>
                            <span class="queue-card-detail"><?= $e($card['detail']) ?></span>
                        </span>
                        <strong class="activity-card-count"><?= (int) $card['count'] ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card recent-activity-card" id="recent-activity" aria-labelledby="recent-activity-heading">
            <div class="section-heading-row recent-activity-heading">
                <div>
                    <span class="eyebrow">Audit trail</span>
                    <h2 id="recent-activity-heading">Recent activity</h2>
                </div>
                <a href="/admin/audit">View full audit log</a>
            </div>
            <div class="activity-table-shell" data-overflow-cue>
                <p class="table-scroll-cue" data-overflow-cue-label>Scroll for Target and Reason <span aria-hidden="true">→</span></p>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Recent activity, horizontally scrollable" data-overflow-region>
                    <table class="audit audit-recent">
                        <thead>
                            <tr><th scope="col">When</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Target</th><th scope="col">Reason</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($audit)): ?>
                            <tr><td colspan="5" class="muted">No moderation or admin actions yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($audit as $row): ?>
                                <tr>
                                    <td class="nowrap"><?= $e(human_datetime($row['created_at'])) ?></td>
                                    <td><?= $e($row['actor_username'] ?? 'system') ?></td>
                                    <td class="action-cell"><code><?= $e($row['action']) ?></code></td>
                                    <td><?= $e($row['target_type']) ?> #<?= (int) $row['target_id'] ?></td>
                                    <td><?= $e($row['reason'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
