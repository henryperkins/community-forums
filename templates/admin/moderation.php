<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Anti-abuse');
$errors = $settings_errors ?? [];
$old = $settings_old ?? [];
$selected = (string) ($old['antiabuse_mode'] ?? $antiabuse_mode ?? 'observe');
$modes = $antiabuse_modes ?? ['observe', 'flag', 'hold', 'block'];
$wordsValue = isset($old['antiabuse_blocked_words']) && is_string($old['antiabuse_blocked_words'])
    ? $old['antiabuse_blocked_words']
    : implode("\n", $antiabuse_blocked_words ?? []);
?>
<div class="admin">
    <header class="admin-head">
        <span>
            <span class="eyebrow">Moderation</span>
            <h1>Anti-abuse</h1>
        </span>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <?= $this->partial('admin/_nav', ['active' => 'moderation', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
        <p class="pane-intro">Set the enforced content-scoring posture and maintain blocked phrases. Moderator exemptions and audit logging remain unchanged.</p>

        <section class="card settings-card" aria-labelledby="anti-abuse-heading">
            <h2 id="anti-abuse-heading">Enforcement</h2>
            <form method="post" action="/admin/moderation" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field" for="admin-antiabuse-mode">
                    <span>Anti-abuse mode</span>
                    <select id="admin-antiabuse-mode" name="antiabuse_mode" class="input" aria-describedby="antiabuse-help<?= !empty($errors['antiabuse_mode']) ? ' antiabuse-error' : '' ?>">
                        <?php if (!in_array($selected, $modes, true)): ?>
                            <option value="<?= $e($selected) ?>" selected><?= $e($selected) ?></option>
                        <?php endif; ?>
                        <?php foreach ($modes as $mode): ?>
                            <option value="<?= $e($mode) ?>"<?= $selected === $mode ? ' selected' : '' ?>><?= $e(ucfirst($mode)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="muted" id="antiabuse-help">Observe logs only; flag records a concern; hold queues content; block rejects it.</span>
                    <?php if (!empty($errors['antiabuse_mode'])): ?>
                        <span class="field-error" id="antiabuse-error"><?= $e($errors['antiabuse_mode']) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field" for="admin-blocked-words">
                    <span>Blocked words</span>
                    <textarea id="admin-blocked-words" name="antiabuse_blocked_words" class="input" rows="7" placeholder="One word or phrase per line"><?= $e($wordsValue) ?></textarea>
                    <span class="muted">One per line or comma-separated. Matching is case-insensitive; entries shorter than 3 characters are ignored.</span>
                    <?php if (!empty($errors['antiabuse_blocked_words'])): ?>
                        <span class="field-error"><?= $e($errors['antiabuse_blocked_words']) ?></span>
                    <?php endif; ?>
                </label>

                <div class="form-actions"><button class="btn" type="submit">Save anti-abuse settings</button></div>
            </form>
        </section>
    </div>
</div>
