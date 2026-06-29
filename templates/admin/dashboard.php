<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Admin'); ?>
<div class="admin">
    <header class="admin-head">
        <h1>Admin console</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <nav class="subnav">
        <a class="active" href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <?php if (!empty($features['api_tokens'])): ?><a href="/admin/api-tokens">API tokens</a><?php endif; ?>
        <?php if (!empty($features['webhooks'])): ?><a href="/admin/webhooks">Webhooks</a><?php endif; ?>
    </nav>

    <section class="card">
        <h2>Site name</h2>
        <form method="post" action="/admin/site" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="text" name="site_name" class="input" maxlength="80" value="<?= $e($site_name) ?>" required>
            <button class="btn btn-small" type="submit">Update</button>
        </form>
    </section>

    <section class="card">
        <h2>Trust &amp; safety</h2>
        <form method="post" action="/admin/settings" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Registration</span>
                <select name="registration_mode" class="input">
                    <?php foreach (($registration_modes ?? ['open', 'closed']) as $m): ?>
                        <option value="<?= $e($m) ?>"<?= ($registration_mode ?? 'open') === $m ? ' selected' : '' ?>><?= $e(ucfirst($m)) ?><?= $m === 'closed' ? ' (no new sign-ups)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Anti-abuse enforcement</span>
                <select name="antiabuse_mode" class="input">
                    <?php foreach (($antiabuse_modes ?? ['observe', 'flag', 'hold', 'block']) as $m): ?>
                        <option value="<?= $e($m) ?>"<?= ($antiabuse_mode ?? 'observe') === $m ? ' selected' : '' ?>><?= $e(ucfirst($m)) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="muted">observe = log only · flag · hold = queue for approval · block = reject</span>
            </label>
            <label class="field">
                <span>Blocked words</span>
                <textarea name="antiabuse_blocked_words" class="input" rows="4" placeholder="One word or phrase per line"><?= $e(implode("\n", $antiabuse_blocked_words ?? [])) ?></textarea>
                <span class="muted">Case-insensitive; matched as substrings against new posts.</span>
            </label>
            <div class="form-actions"><button class="btn" type="submit">Save settings</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Recent activity</h2>
        <?php if (empty($audit)): ?>
            <p class="muted">No moderation or admin actions yet.</p>
        <?php else: ?>
            <table class="audit">
                <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Reason</th></tr></thead>
                <tbody>
                <?php foreach ($audit as $row): ?>
                    <tr>
                        <td><?= $e(human_datetime($row['created_at'])) ?></td>
                        <td><?= $e($row['actor_username'] ?? 'system') ?></td>
                        <td><code><?= $e($row['action']) ?></code></td>
                        <td><?= $e($row['target_type']) ?> #<?= (int) $row['target_id'] ?></td>
                        <td><?= $e($row['reason'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
