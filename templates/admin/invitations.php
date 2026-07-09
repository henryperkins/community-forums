<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Invitations');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Invitations</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'invitations', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <?php if (!empty($new_invitation)): ?>
        <div class="flash" role="status">
            <strong>Copy this invitation link now — it will not be shown again:</strong>
            <code><?= $e($new_invitation['url']) ?></code>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Issue an invitation</h2>
        <p class="muted">Invitations admit one member per use, expire automatically, and never grant staff or custom roles. Bind to an email address or a domain to restrict who can redeem.</p>
        <form method="post" action="/admin/invitations" class="stacked">
            <?= $this->csrfField() ?>
            <?php if (!empty($errors['create'])): ?><p class="field-error"><?= $e($errors['create']) ?></p><?php endif; ?>

            <label>Bind to email (optional)
                <input type="email" name="email" maxlength="255" value="<?= $e($old['email'] ?? '') ?>" placeholder="person@example.com">
            </label>
            <?php if (!empty($errors['email'])): ?><p class="field-error"><?= $e($errors['email']) ?></p><?php endif; ?>

            <label>Bind to domain (optional)
                <input type="text" name="domain" maxlength="190" value="<?= $e($old['domain'] ?? '') ?>" placeholder="example.com">
            </label>
            <?php if (!empty($errors['domain'])): ?><p class="field-error"><?= $e($errors['domain']) ?></p><?php endif; ?>

            <?php $maxUses = (int) ($limits['max_uses'] ?? 100); $maxDays = (int) ($limits['max_expiry_days'] ?? 365); $defaultDays = (int) ($limits['default_expiry_days'] ?? 14); ?>
            <label>Max uses (1–<?= $maxUses ?>, default 1)
                <input type="number" name="max_uses" min="1" max="<?= $maxUses ?>" value="<?= $e($old['max_uses'] ?? '') ?>">
            </label>
            <?php if (!empty($errors['max_uses'])): ?><p class="field-error"><?= $e($errors['max_uses']) ?></p><?php endif; ?>

            <label>Expires in days (1–<?= $maxDays ?>, default <?= $defaultDays ?>)
                <input type="number" name="expires_in_days" min="1" max="<?= $maxDays ?>" value="<?= $e($old['expires_in_days'] ?? '') ?>">
            </label>
            <?php if (!empty($errors['expires_in_days'])): ?><p class="field-error"><?= $e($errors['expires_in_days']) ?></p><?php endif; ?>

            <label>Grant board membership (optional)
                <select name="onboarding_board_id" class="input">
                    <option value="">No board grant</option>
                    <?php foreach (($boards ?? []) as $board): ?>
                        <option value="<?= (int) $board['id'] ?>"<?= (string) ($old['onboarding_board_id'] ?? '') === (string) $board['id'] ? ' selected' : '' ?>><?= $e($board['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($errors['onboarding_board_id'])): ?><p class="field-error"><?= $e($errors['onboarding_board_id']) ?></p><?php endif; ?>

            <div class="form-actions"><button class="btn" type="submit">Issue invitation</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Issued invitations</h2>
        <?php if (empty($rows)): ?>
            <p class="muted">No invitations have been issued yet.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Created</th><th>By</th><th>Binding</th><th>Uses</th><th>Expires</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= $e(human_datetime((string) $row['created_at'])) ?></td>
                        <td><?= $e($row['creator_username'] ?? 'system') ?></td>
                        <td>
                            <?php if ($row['email'] !== null): ?><?= $e($row['email']) ?>
                            <?php elseif ($row['domain'] !== null): ?>@<?= $e($row['domain']) ?>
                            <?php else: ?><span class="muted">any email</span><?php endif; ?>
                        </td>
                        <td><?= (int) $row['used_count'] ?>/<?= (int) $row['max_uses'] ?></td>
                        <td><?= $row['expires_at'] !== null ? $e(human_datetime((string) $row['expires_at'])) : '—' ?></td>
                        <td><?= $e(ucfirst((string) $row['status'])) ?></td>
                        <td>
                            <?php if ($row['status'] === 'active'): ?>
                                <form method="post" action="/admin/invitations/<?= (int) $row['id'] ?>/revoke">
                                    <?= $this->csrfField() ?>
                                    <button class="btn btn-small" type="submit">Revoke</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    </div>
</div>
