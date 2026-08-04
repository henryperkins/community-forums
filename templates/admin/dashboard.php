<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Admin'); $this->section('variant', 'admin'); ?>
<?= $this->partial('admin/_console', ['area' => 'overview', 'tab' => 'dashboard', 'pane_class' => 'admin-overview-dashboard']) ?>
        <p class="pane-intro">Start with the live queues and health signals, then review what has changed across the community.</p>

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
                        <?php $stateLabel = $card['status'] === 'attention' ? 'Needs review' : ucfirst($card['status']); ?>
                        <span class="queue-card-state"><?= $e($stateLabel) ?></span>
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
            <?php if (empty($attention)): ?>
                <p class="attention-empty">No pending operator work right now. The queues are clear.</p>
            <?php endif; ?>
        </section>

        <section class="admin-dashboard-section community-section" aria-labelledby="community-today-heading">
            <span class="eyebrow">Community pulse</span>
            <h2 id="community-today-heading">Community today</h2>
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
                <a href="/admin/audit">View full audit log <span aria-hidden="true">→</span></a>
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
                            <tr class="audit-empty-row"><td colspan="5"><p>No moderation or admin actions yet.</p></td></tr>
                        <?php else: ?>
                            <?php foreach ($audit as $row): ?>
                                <?php
                                $createdAt = (string) $row['created_at'];
                                $createdAtTs = strtotime($createdAt . ' UTC');
                                $createdAtIso = $createdAtTs === false ? '' : gmdate('Y-m-d\TH:i:s\Z', $createdAtTs);
                                ?>
                                <tr>
                                    <td class="audit-time nowrap"><time datetime="<?= $e($createdAtIso) ?>"><?= $e(human_datetime($createdAt)) ?></time></td>
                                    <td><?= $e($row['actor_username'] ?? 'system') ?></td>
                                    <td class="action-cell"><code><?= $e($row['action']) ?></code></td>
                                    <td><span class="audit-target"><?= $e($row['target_type']) ?> #<?= (int) $row['target_id'] ?></span></td>
                                    <td class="audit-reason"><?= $e($row['reason'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
<?= $this->partial('admin/_console_end') ?>
