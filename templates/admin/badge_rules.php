<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Badge rules');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Badge rules</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'badge_rules', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <section class="card">
        <h2>Create rule</h2>
        <form method="post" action="/admin/badge-rules" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Badge</span>
                <select class="input" name="badge_id" required>
                    <?php foreach ($badges as $badge): ?>
                        <option value="<?= (int) $badge['id'] ?>"<?= (int) ($old['badge_id'] ?? 0) === (int) $badge['id'] ? ' selected' : '' ?>><?= $e($badge['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($errors['badge_id'])): ?><p class="field-error"><?= $e($errors['badge_id']) ?></p><?php endif; ?>
            <label class="field">
                <span>Rule</span>
                <select class="input" name="rule_type" required>
                    <?php foreach (['post_count' => 'Post count', 'thread_count' => 'Thread count', 'reputation' => 'Reputation', 'solved_count' => 'Solved answers'] as $value => $label): ?>
                        <option value="<?= $e($value) ?>"<?= ($old['rule_type'] ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($errors['rule_type'])): ?><p class="field-error"><?= $e($errors['rule_type']) ?></p><?php endif; ?>
            <label class="field">
                <span>Threshold</span>
                <input class="input" type="number" min="1" max="1000000" name="threshold" value="<?= $e((string) ($old['threshold'] ?? '1')) ?>" required>
            </label>
            <?php if (!empty($errors['threshold'])): ?><p class="field-error"><?= $e($errors['threshold']) ?></p><?php endif; ?>
            <label class="field">
                <span>Board scope</span>
                <select class="input" name="board_id">
                    <option value="">All boards</option>
                    <?php foreach ($boards as $board): ?>
                        <option value="<?= (int) $board['id'] ?>"<?= (int) ($old['board_id'] ?? 0) === (int) $board['id'] ? ' selected' : '' ?>><?= $e($board['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($errors['board_id'])): ?><p class="field-error"><?= $e($errors['board_id']) ?></p><?php endif; ?>
            <button class="btn" type="submit">Create rule</button>
        </form>
    </section>

    <section class="card">
        <h2>Rules</h2>
        <?php if (empty($rules)): ?>
            <p class="muted">No badge rules.</p>
        <?php else: ?>
            <ul class="link-list">
                <?php foreach ($rules as $rule): ?>
                    <?php $ruleEnabled = (int) $rule['is_enabled'] === 1; $ruleName = (string) $rule['badge_name']; ?>
                    <li>
                        <strong><?= $e($ruleName) ?></strong>
                        <span class="muted"><?= $e($rule['rule_type']) ?> &ge; <?= (int) $rule['threshold'] ?><?= !empty($rule['board_name']) ? ' · ' . $e($rule['board_name']) : '' ?></span>
                        <span class="badge<?= $ruleEnabled ? '' : ' badge-muted' ?>"><?= $ruleEnabled ? 'Enabled' : 'Disabled' ?></span>
                        <a class="linkbtn" href="/admin/badge-rules/<?= (int) $rule['id'] ?>/preview">Preview</a>
                        <?php if (!$ruleEnabled): ?>
                            <form class="inline" method="post" action="/admin/badge-rules/<?= (int) $rule['id'] ?>/enable"><?= $this->csrfField() ?><button class="linkbtn" type="submit" aria-label="Enable the <?= $e($ruleName) ?> rule">Enable</button></form>
                        <?php endif; ?>
                        <form class="inline" method="post" action="/admin/badge-rules/<?= (int) $rule['id'] ?>/backfill"><?= $this->csrfField() ?><button class="linkbtn" type="submit" aria-label="Backfill the <?= $e($ruleName) ?> rule">Backfill</button></form>
                        <?php if ($ruleEnabled): ?>
                            <form class="inline" method="post" action="/admin/badge-rules/<?= (int) $rule['id'] ?>/disable"><?= $this->csrfField() ?><button class="linkbtn muted" type="submit" aria-label="Disable the <?= $e($ruleName) ?> rule">Disable</button></form>
                        <?php endif; ?>
                        <form class="inline" method="post" action="/admin/badge-rules/<?= (int) $rule['id'] ?>/revoke"><?= $this->csrfField() ?><button class="linkbtn danger" type="submit" aria-label="Revoke all awards from the <?= $e($ruleName) ?> rule">Revoke awards</button></form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    </div>
</div>
