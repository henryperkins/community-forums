<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Audit log');
$filters = $filters ?? [];
$base = $base_query ?? [];
$page = (int) ($page ?? 0);
?>
<div class="admin">
    <header class="admin-head">
        <span>
            <span class="eyebrow">Accountability</span>
            <h1>Audit log</h1>
        </span>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'audit', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <section class="card">
        <p class="pane-intro">Every moderation and admin action, append-only (ADMIN §3.6). Filter, page, and follow a target's trail from its own record screen.</p>
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
            <div class="form-actions">
                <button class="btn btn-small" type="submit">Apply filters</button>
                <a class="btn btn-small btn-ghost" href="/admin/audit">Reset</a>
            </div>
        </form>

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
                    <tr>
                        <td class="nowrap"><?= $e(human_datetime((string) $row['created_at'])) ?></td>
                        <td><?= $e($row['actor_username'] ?? 'system') ?></td>
                        <td class="action-cell"><code><?= $e((string) $row['action']) ?></code></td>
                        <td>
                            <?php $targetType = (string) $row['target_type']; $targetId = (int) $row['target_id']; ?>
                            <?php if ($targetType === 'user' && $targetId > 0): ?>
                                <a href="/admin/users/<?= $targetId ?>">user #<?= $targetId ?></a>
                            <?php else: ?>
                                <?= $e($targetType) ?> #<?= $targetId ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $e((string) ($row['reason'] ?? '')) ?></td>
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
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="muted">No audit entries match these filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="muted"><?= (int) $total ?> matching entr<?= (int) $total === 1 ? 'y' : 'ies' ?>.</p>
        <nav class="pager" aria-label="Pagination">
            <?php if ($page > 0): ?>
                <a class="btn btn-small" href="/admin/audit?<?= $e(http_build_query($base + ['page' => $page - 1])) ?>">Previous</a>
            <?php endif; ?>
            <?php if (!empty($has_next)): ?>
                <a class="btn btn-small" href="/admin/audit?<?= $e(http_build_query($base + ['page' => $page + 1])) ?>">Next</a>
            <?php endif; ?>
        </nav>
    </section>
    </div>
</div>
