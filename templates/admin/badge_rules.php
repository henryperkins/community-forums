<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Badge rules');
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'features', 'tab' => 'badge_rules', 'pane_class' => 'admin-features features-badge-rules']) ?>
    <div class="features-two-up">
        <section class="card features-form-card">
            <h2>Create rule</h2>
            <p class="features-card-intro">Rules award automatically once the metric crosses the threshold. Scope to a board to count only what happened there.</p>
            <form method="post" action="/admin/badge-rules" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Badge</span>
                    <select class="input" name="badge_id"<?= field_attrs($errors ?? [], 'badge_id') ?> required>
                        <?php foreach ($badges as $badge): ?>
                            <option value="<?= (int) $badge['id'] ?>"<?= (int) ($old['badge_id'] ?? 0) === (int) $badge['id'] ? ' selected' : '' ?>><?= $e($badge['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?= field_error($errors ?? [], 'badge_id') ?>
                <label class="field">
                    <span>Rule</span>
                    <select class="input" name="rule_type"<?= field_attrs($errors ?? [], 'rule_type') ?> required>
                        <?php foreach (['post_count' => 'Post count', 'thread_count' => 'Thread count', 'reputation' => 'Reputation', 'solved_count' => 'Solved answers'] as $value => $label): ?>
                            <option value="<?= $e($value) ?>"<?= ($old['rule_type'] ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?= field_error($errors ?? [], 'rule_type') ?>
                <?php // copy: the design pairs Threshold and Board scope in a 1fr/1.4fr sub-grid.
                      // field_error() emits a <p>, which cannot legally nest inside <label>, so each
                      // grid cell wraps the label and its error line instead of moving the error in.
                      // field_attrs()/field_error() still emit the same ids and aria wiring. ?>
                <div class="features-field-pair">
                    <div class="features-field-cell">
                        <label class="field">
                            <span>Threshold</span>
                            <input class="input" type="number" min="1" max="1000000" name="threshold" value="<?= $e((string) ($old['threshold'] ?? '1')) ?>"<?= field_attrs($errors ?? [], 'threshold') ?> required>
                        </label>
                        <?= field_error($errors ?? [], 'threshold') ?>
                    </div>
                    <div class="features-field-cell">
                        <label class="field">
                            <span>Board scope</span>
                            <select class="input" name="board_id"<?= field_attrs($errors ?? [], 'board_id') ?>>
                                <option value="">All boards</option>
                                <?php foreach ($boards as $board): ?>
                                    <option value="<?= (int) $board['id'] ?>"<?= (int) ($old['board_id'] ?? 0) === (int) $board['id'] ? ' selected' : '' ?>><?= $e($board['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?= field_error($errors ?? [], 'board_id') ?>
                    </div>
                </div>
                <button class="btn" type="submit">Create rule</button>
            </form>
        </section>

        <section class="card features-rules-card">
            <h2 class="features-rules-title">Rules</h2>
            <?php if (empty($rules)): ?>
                <?php // feature-added: the design has no empty state. ?>
                <p class="features-empty">No badge rules.</p>
            <?php else: ?>
                <ul class="link-list features-rule-list">
                    <?php foreach ($rules as $rule): ?>
                        <?php $ruleEnabled = (int) $rule['is_enabled'] === 1; $ruleName = (string) $rule['badge_name']; ?>
                        <li>
                            <span class="features-rule-star" aria-hidden="true">&#10022;</span>
                            <strong class="features-rule-name"><?= $e($ruleName) ?></strong>
                            <span class="features-rule-meta"><?= $e($rule['rule_type']) ?> &ge; <?= (int) $rule['threshold'] ?><?= !empty($rule['board_name']) ? ' · ' . $e($rule['board_name']) : '' ?></span>
                            <span class="features-pill <?= $ruleEnabled ? 'is-on' : 'is-off' ?>"><?= $ruleEnabled ? 'Enabled' : 'Disabled' ?></span>
                            <?php // copy: the design's action order is Preview · Backfill · {toggle} · Revoke.
                                  // constraint C-11: Preview is a GET link; every mutation is a POST form
                                  // carrying csrfField() with a row-scoped aria-label. ?>
                            <span class="features-rule-actions">
                                <a class="linkbtn features-rowbtn" href="/admin/badge-rules/<?= (int) $rule['id'] ?>/preview">Preview</a>
                                <form class="inline-form" method="post" action="/admin/badge-rules/<?= (int) $rule['id'] ?>/backfill"><?= $this->csrfField() ?><button class="linkbtn features-rowbtn" type="submit" aria-label="Backfill the <?= $e($ruleName) ?> rule">Backfill</button></form>
                                <?php if (!$ruleEnabled): ?>
                                    <form class="inline-form" method="post" action="/admin/badge-rules/<?= (int) $rule['id'] ?>/enable"><?= $this->csrfField() ?><button class="linkbtn muted features-rowbtn" type="submit" aria-label="Enable the <?= $e($ruleName) ?> rule">Enable</button></form>
                                <?php else: ?>
                                    <form class="inline-form" method="post" action="/admin/badge-rules/<?= (int) $rule['id'] ?>/disable"><?= $this->csrfField() ?><button class="linkbtn muted features-rowbtn" type="submit" aria-label="Disable the <?= $e($ruleName) ?> rule">Disable</button></form>
                                <?php endif; ?>
                                <form class="inline-form" method="post" action="/admin/badge-rules/<?= (int) $rule['id'] ?>/revoke"><?= $this->csrfField() ?><button class="linkbtn danger features-rowbtn" type="submit" aria-label="Revoke all awards from the <?= $e($ruleName) ?> rule">Revoke awards</button></form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
<?= $this->partial('admin/_console_end') ?>
