<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Permission simulator');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Permission simulator</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'roles', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <p class="muted">Runs <code>can(actor, capability, target, time)</code> on the <strong>real resolver</strong>.
    While <code>capabilities</code> is in shadow, answers predict the post-cutover decision; live requests still use legacy authority.</p>

    <section class="card">
        <h2>Simulate</h2>
        <form method="get" action="/admin/roles/simulator" class="stacked">
            <label>Actor (username, id, or <code>guest</code>)
                <input type="text" name="actor" value="<?= $e($q['actor']) ?>" required>
            </label>
            <label>Capability
                <select name="capability" required>
                    <option value="">- pick -</option>
                    <?php foreach ($catalogue as $key => $meta): ?>
                        <option value="<?= $e($key) ?>" <?= $q['capability'] === $key ? 'selected' : '' ?>><?= $e($key) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Board id (optional target)
                <input type="number" name="board_id" min="1" value="<?= $e($q['board_id']) ?>">
            </label>
            <label>At (optional, UTC <code>YYYY-MM-DD HH:MM</code>)
                <input type="text" name="at" value="<?= $e($q['at']) ?>" placeholder="2026-07-15 12:00">
            </label>
            <div class="form-actions"><button class="btn" type="submit">Simulate</button></div>
        </form>
    </section>

    <?php if ($result !== null): ?>
    <section class="card">
        <h2>Result</h2>
        <?php if ($result['error'] !== null): ?>
            <p class="field-error"><?= $e($result['error']) ?></p>
        <?php else: $d = $result['decision']; ?>
            <p>
                <strong><?= $d->allowed ? 'Allowed' : 'Denied' ?></strong>
                - <code><?= $e($d->capability) ?></code> for <?= $e($result['actor_label']) ?>
                <?php if ($result['target_label'] !== null): ?> on <?= $e($result['target_label']) ?><?php endif; ?>
            </p>
            <ul>
                <li>Decisive rule: <code><?= $e($d->source) ?></code></li>
                <li>Reason: <?= $e($d->reason) ?></li>
                <?php if ($d->roleKey !== null): ?>
                    <li>Via role: <code><?= $e($d->roleKey) ?></code><?php if ($d->scopeType !== null): ?> at <?= $e($d->scopeType) ?><?= $d->scopeId !== null ? ' #' . (int) $d->scopeId : '' ?><?php endif; ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    </div>
</div>
