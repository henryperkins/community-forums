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
            <?= field_error($errors ?? [], 'create') ?>

            <label>Bind to email (optional)
                <input type="email" name="email" maxlength="255" value="<?= $e($old['email'] ?? '') ?>" placeholder="person@example.com"<?= field_attrs($errors ?? [], 'email') ?>>
            </label>
            <?= field_error($errors ?? [], 'email') ?>

            <label>Bind to domain (optional)
                <input type="text" name="domain" maxlength="190" value="<?= $e($old['domain'] ?? '') ?>" placeholder="example.com"<?= field_attrs($errors ?? [], 'domain') ?>>
            </label>
            <?= field_error($errors ?? [], 'domain') ?>

            <?php $maxUses = (int) ($limits['max_uses'] ?? 100); $maxDays = (int) ($limits['max_expiry_days'] ?? 365); $defaultDays = (int) ($limits['default_expiry_days'] ?? 14); ?>
            <label>Max uses (1–<?= $maxUses ?>, default 1)
                <input type="number" name="max_uses" min="1" max="<?= $maxUses ?>" value="<?= $e($old['max_uses'] ?? '') ?>"<?= field_attrs($errors ?? [], 'max_uses') ?>>
            </label>
            <?= field_error($errors ?? [], 'max_uses') ?>

            <label>Expires in days (1–<?= $maxDays ?>, default <?= $defaultDays ?>)
                <input type="number" name="expires_in_days" min="1" max="<?= $maxDays ?>" value="<?= $e($old['expires_in_days'] ?? '') ?>"<?= field_attrs($errors ?? [], 'expires_in_days') ?>>
            </label>
            <?= field_error($errors ?? [], 'expires_in_days') ?>

            <label>Grant board membership (optional)
                <select name="onboarding_board_id" class="input">
                    <option value="">No board grant</option>
                    <?php foreach (($boards ?? []) as $board): ?>
                        <option value="<?= (int) $board['id'] ?>"<?= (string) ($old['onboarding_board_id'] ?? '') === (string) $board['id'] ? ' selected' : '' ?>><?= $e($board['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?= field_error($errors ?? [], 'onboarding_board_id') ?>

            <div class="form-actions"><button class="btn" type="submit">Issue invitation</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Issued invitations</h2>
        <?php if (empty($rows)): ?>
            <p class="muted">No invitations have been issued yet.</p>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Issued invitations">
            <table class="audit">
                <thead>
                    <tr><th scope="col">Created</th><th scope="col">By</th><th scope="col">Binding</th><th scope="col">Uses</th><th scope="col">Expires</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr>
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
                                    <button class="btn btn-small danger" type="submit">Revoke</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>
    </div>
</div>
