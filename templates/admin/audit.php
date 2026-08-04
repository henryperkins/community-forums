<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Audit log');
$this->section('variant', 'admin');
$filters = $filters ?? [];
$base = $base_query ?? [];
$pageNumber = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($per_page ?? 50));
$pageCount = max(1, (int) ($page_count ?? (int) ceil(((int) ($total ?? 0)) / $perPage)));
?>
<?= $this->partial('admin/_console', ['area' => 'overview', 'tab' => 'audit', 'pane_class' => 'admin-overview-audit']) ?>
        <p class="pane-intro">Every moderation and admin action, append-only. Filter it, page through it, and follow a target's whole trail from its own record.</p>

    <section class="card audit-filter-card">
        <form method="get" action="/admin/audit" class="filter-form">
            <div class="filter-grid">
                <label class="field">
                    <span>Actor</span>
                    <input type="search" name="actor" class="input" maxlength="80" value="<?= $e($filters['actor'] ?? '') ?>" placeholder="Username or display name"<?= field_attrs($errors ?? [], 'actor') ?>>
                    <?= field_error($errors ?? [], 'actor') ?>
                </label>
                <label class="field">
                    <span>Action</span>
                    <input type="search" name="action" class="input" maxlength="80" value="<?= $e($filters['action'] ?? '') ?>" placeholder="e.g. suspend, update_board">
                </label>
                <label class="field">
                    <span>Target type</span>
                    <select name="target_type" class="input">
                        <?php $tt = (string) ($filters['target_type'] ?? ''); ?>
                        <option value="">Any target</option>
                        <?php foreach (['user', 'board', 'category', 'thread', 'post', 'setting', 'webhook', 'tag'] as $type): ?>
                            <option value="<?= $e($type) ?>"<?= $tt === $type ? ' selected' : '' ?>><?= $e(ucfirst($type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Target #</span>
                    <input type="number" name="target_id" class="input" min="1" value="<?= $e($filters['target_id'] ?? '') ?>"<?= field_attrs($errors ?? [], 'target_id') ?>>
                    <?= field_error($errors ?? [], 'target_id') ?>
                </label>
                <label class="field">
                    <span>From</span>
                    <input type="date" name="from" class="input" value="<?= $e($filters['from'] ?? '') ?>"<?= field_attrs($errors ?? [], 'from') ?>>
                    <?= field_error($errors ?? [], 'from') ?>
                </label>
                <label class="field">
                    <span>To</span>
                    <input type="date" name="to" class="input" value="<?= $e($filters['to'] ?? '') ?>"<?= field_attrs($errors ?? [], 'to') ?>>
                    <?= field_error($errors ?? [], 'to') ?>
                </label>
            </div>
            <div class="form-actions filter-actions">
                <button class="btn btn-small" type="submit">Apply filters</button>
                <a class="btn btn-small btn-ghost" href="/admin/audit">Reset</a>
                <span class="filter-result-count audit-result-count"><?= (int) $total ?> entr<?= (int) $total === 1 ? 'y' : 'ies' ?></span>
            </div>
        </form>
    </section>

    <section class="card audit-table-card">
        <div class="table-scroll" tabindex="0" role="region" aria-label="Audit log entries">
            <table class="audit">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Action</th>
                        <th scope="col">Target</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Change</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $createdAt = (string) $row['created_at'];
                    $createdAtTs = strtotime($createdAt . ' UTC');
                    $createdAtIso = $createdAtTs === false ? '' : gmdate('Y-m-d\TH:i:s\Z', $createdAtTs);
                    ?>
                    <tr>
                        <td class="audit-time nowrap"><time datetime="<?= $e($createdAtIso) ?>"><?= $e(human_datetime($createdAt)) ?></time></td>
                        <td><?= $e($row['actor_username'] ?? 'system') ?></td>
                        <td class="action-cell"><code><?= $e((string) $row['action']) ?></code></td>
                        <td>
                            <?php $targetType = (string) $row['target_type']; $targetId = (int) $row['target_id']; ?>
                            <?php if ($targetType === 'user' && $targetId > 0): ?>
                                <a class="audit-target-link" href="/admin/users/<?= $targetId ?>">user #<?= $targetId ?></a>
                            <?php else: ?>
                                <span class="audit-target"><?= $e($targetType) ?> #<?= $targetId ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="audit-reason"><?= $e((string) ($row['reason'] ?? '')) ?></td>
                        <td>
                            <?php $before = (string) ($row['before_json'] ?? ''); $after = (string) ($row['after_json'] ?? ''); ?>
                            <?php if ($before !== '' || $after !== ''): ?>
                                <details class="audit-change">
                                    <summary>Details</summary>
                                    <?php if ($before !== ''): ?><p>Before: <code><?= $e($before) ?></code></p><?php endif; ?>
                                    <?php if ($after !== ''): ?><p>After: <code><?= $e($after) ?></code></p><?php endif; ?>
                                </details>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($rows)): ?>
            <?= $this->partial('partials/empty_state', [
                'heading' => 'Nothing matches these filters',
                'message' => 'The record is complete; this slice of it is simply empty.',
                'action_href' => '/admin/audit',
                'action_label' => 'Reset filters',
            ]) ?>
        <?php endif; ?>
    </section>

    <?php if ((int) $total > $perPage): ?>
        <?= $this->partial('partials/pager', [
            'path' => '/admin/audit',
            'query' => $base,
            'page' => $pageNumber,
            'total_pages' => $pageCount,
            'aria_label' => 'Audit log pagination',
        ]) ?>
    <?php endif; ?>
<?= $this->partial('admin/_console_end') ?>
