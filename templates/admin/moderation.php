<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Anti-abuse');
$this->section('variant', 'admin');
$review = !empty($settings_review);
$errors = $settings_errors ?? [];
$old = $settings_old ?? [];
$selected = is_string($old['antiabuse_mode'] ?? null) ? $old['antiabuse_mode'] : (string) ($antiabuse_mode ?? 'observe');
$modes = $antiabuse_modes ?? ['observe', 'flag', 'hold', 'block'];
$wordsValue = $old !== []
    ? (is_string($old['antiabuse_blocked_words'] ?? null) ? $old['antiabuse_blocked_words'] : '')
    : implode("\n", $antiabuse_blocked_words ?? []);
?>
<?= $this->partial('admin/_console', ['area' => 'moderation', 'tab' => 'antiabuse']) ?>
        <p class="pane-intro">Set the enforced content-scoring posture and maintain blocked phrases. Moderator exemptions and audit logging remain unchanged.</p>

        <section class="card settings-card" aria-labelledby="anti-abuse-heading">
            <h2 id="anti-abuse-heading"><?= $review ? 'Review Block mode' : 'Enforcement' ?></h2>
            <form method="post" action="/admin/moderation" class="stacked">
                <?= $this->csrfField() ?>
                <?php if ($review): ?>
                    <div class="card confirm-card">
                        <p><strong>Block mode can reject new content from members.</strong> Content that reaches a blocking rule will be refused instead of held for review. Moderator exemptions still apply.</p>
                        <p>The blocked phrases below will replace the saved list. You can return to another mode later; rejected submissions are not restored automatically.</p>
                        <input type="hidden" name="antiabuse_mode" value="block">
                    </div>
                <?php else: ?>
                <label class="field" for="admin-antiabuse-mode">
                    <span>Anti-abuse mode</span>
                    <select id="admin-antiabuse-mode" name="antiabuse_mode" class="input"<?= field_attrs($errors, 'antiabuse_mode', null, 'antiabuse-help') ?>>
                        <?php if (!in_array($selected, $modes, true)): ?>
                            <option value="<?= $e($selected) ?>" selected><?= $e($selected) ?></option>
                        <?php endif; ?>
                        <?php foreach ($modes as $mode): ?>
                            <option value="<?= $e($mode) ?>"<?= $selected === $mode ? ' selected' : '' ?>><?= $e(ucfirst($mode)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="muted" id="antiabuse-help">Observe logs only; flag records a concern; hold queues content; block rejects it.</span>
                </label>
                <?= field_error($errors, 'antiabuse_mode') ?>
                <?php endif; ?>

                <label class="field" for="admin-blocked-words">
                    <span>Blocked words</span>
                    <textarea id="admin-blocked-words" name="antiabuse_blocked_words" class="input" rows="7"<?= $review ? ' readonly' : '' ?> placeholder="One word or phrase per line"<?= field_attrs($errors, 'antiabuse_blocked_words', null, 'antiabuse-blocked-words-help') ?>>
<?= $e($wordsValue) ?></textarea>
                    <span class="muted" id="antiabuse-blocked-words-help">One per line or comma-separated. Matching is case-insensitive; entries shorter than 3 characters are ignored.</span>
                </label>
                <?= field_error($errors, 'antiabuse_blocked_words') ?>

                <div class="form-actions">
                    <?php if ($review): ?>
                        <button class="btn danger" type="submit" name="review_action" value="confirm">Confirm Block mode</button>
                        <button class="btn btn-secondary" type="submit" name="review_action" value="edit">Back to editing</button>
                        <a class="linkbtn" href="/admin/moderation">Cancel</a>
                    <?php else: ?>
                        <button class="btn" type="submit">Save anti-abuse settings</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>
<?= $this->partial('admin/_console_end') ?>
