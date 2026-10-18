<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Permission simulator');
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'people', 'tab' => 'simulator', 'pane_class' => 'admin-roles admin-role-simulator']) ?>
    <p class="role-simulator-intro">Check a member’s permission for a board and time. The result explains which role or rule decides access.</p>
    <?= $this->partial('admin/_role_live_effect', ['mode' => $mode]) ?>

    <section class="card role-simulator-form-card">
        <h2>Simulate</h2>
        <form method="get" action="/admin/roles/simulator" class="role-simulator-form">
            <label class="role-form-label"><span>Actor (username, id, or guest)</span>
                <input class="input role-mono-input" type="text" name="actor" value="<?= $e($q['actor']) ?>" required>
            </label>
            <label class="role-form-label"><span>Capability</span>
                <select class="input role-time-input" name="capability" required>
                    <option value="">— pick —</option>
                    <?php foreach ($catalogue as $key => $meta): ?>
                        <option value="<?= $e($key) ?>" <?= $q['capability'] === $key ? 'selected' : '' ?>><?= $e($meta['consent'] ?? $meta['description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="role-form-label"><span>Board (optional target)</span>
                <select class="input" name="board_id">
                    <option value="">No board target</option>
                    <?php $boardIds = array_map('strval', array_column($boards ?? [], 'id')); ?>
                    <?php if ($q['board_id'] !== '' && !in_array($q['board_id'], $boardIds, true)): ?>
                        <option value="<?= $e($q['board_id']) ?>" selected>Unavailable board: <?= $e($q['board_id']) ?></option>
                    <?php endif; ?>
                    <?php foreach (($boards ?? []) as $board): ?>
                        <option value="<?= (int) $board['id'] ?>"<?= (string) $board['id'] === $q['board_id'] ? ' selected' : '' ?>><?= $e($board['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="role-form-label"><span>At (optional, UTC)</span>
                <input class="input role-time-input" type="text" name="at" value="<?= $e($q['at']) ?>" placeholder="2026-08-15 12:00">
            </label>
            <div class="role-simulator-actions"><button class="btn" type="submit">Simulate</button></div>
        </form>
    </section>

    <?php if ($result !== null && $result['error'] !== null): ?>
    <section class="card role-simulator-error" role="alert">
        <h2>The simulator could not answer</h2>
        <p><?= $e($result['error']) ?></p>
    </section>
    <?php elseif ($result !== null): $d = $result['decision']; ?>
    <section class="card role-simulator-result">
        <h2>Result</h2>
            <p class="role-simulator-verdict-row">
                <span class="role-simulator-verdict role-simulator-verdict-<?= $d->allowed ? 'allowed' : 'denied' ?>"><?= $d->allowed ? 'Allowed' : 'Denied' ?></span>
                <span><code><?= $e($d->capability) ?></code> for <?= $e($result['actor_label']) ?>
                <?php if ($result['target_label'] !== null): ?> on <?= $e($result['target_label']) ?><?php endif; ?>
                </span>
            </p>
            <ul class="role-simulator-facts">
                <li>Decisive rule: <code><?= $e($d->source) ?></code></li>
                <li>Reason: <?= $e($d->reason) ?></li>
                <?php if ($d->roleKey !== null): ?>
                    <li>Via role: <code><?= $e($d->roleKey) ?></code><?php if ($d->scopeType !== null): ?> at <?= $e($d->scopeType) ?><?= $d->scopeId !== null ? ' #' . (int) $d->scopeId : '' ?><?php endif; ?></li>
                <?php endif; ?>
            </ul>
    </section>
    <?php endif; ?>
<?= $this->partial('admin/_console_end') ?>
