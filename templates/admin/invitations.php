<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Invitations');
$this->section('variant', 'admin');
$inviteErrors = $errors ?? [];
$inviteOld = $old ?? [];
$maxUses = (int) ($limits['max_uses'] ?? 100);
$maxDays = (int) ($limits['max_expiry_days'] ?? 365);
$defaultDays = (int) ($limits['default_expiry_days'] ?? 14);
$usesValue = array_key_exists('max_uses', $inviteOld) ? (string) $inviteOld['max_uses'] : '1';
$daysValue = array_key_exists('expires_in_days', $inviteOld) ? (string) $inviteOld['expires_in_days'] : (string) $defaultDays;
?>
<?= $this->partial('admin/_member_tabs', ['active' => 'invitations', 'pane_class' => 'member-invitations']) ?>
        <?php if (!empty($new_invitation)): ?>
            <div class="member-invitations-once" role="status">
                <strong>Copy this invitation link now — it will not be shown again:</strong>
                <code><?= $e($new_invitation['url']) ?></code>
            </div>
        <?php endif; ?>

        <div class="member-invitations-grid">
            <section class="card member-invitations-issue">
                <h2>Issue an invitation</h2>
                <p class="member-invitations-copy">Invitations admit one member per use, expire automatically, and never grant staff or custom roles. Bind to an email address or a domain to restrict who can redeem.</p>
                <form method="post" action="/admin/invitations" class="stacked member-invitations-form">
                    <?= $this->csrfField() ?>
                    <?= field_error($inviteErrors, 'create') ?>

                    <label class="field">
                        <span>Bind to email (optional)</span>
                        <input class="input" type="email" name="email" maxlength="255" value="<?= $e($inviteOld['email'] ?? '') ?>" placeholder="person@example.com"<?= field_attrs($inviteErrors, 'email') ?>>
                    </label>
                    <?= field_error($inviteErrors, 'email') ?>

                    <label class="field">
                        <span>Bind to domain (optional)</span>
                        <input class="input" type="text" name="domain" maxlength="190" value="<?= $e($inviteOld['domain'] ?? '') ?>" placeholder="example.com"<?= field_attrs($inviteErrors, 'domain') ?>>
                    </label>
                    <?= field_error($inviteErrors, 'domain') ?>

                    <div class="member-invitations-number-grid">
                        <label class="field">
                            <span>Max uses (1–<?= $maxUses ?>)</span>
                            <input class="input" type="number" name="max_uses" min="1" max="<?= $maxUses ?>" value="<?= $e($usesValue) ?>"<?= field_attrs($inviteErrors, 'max_uses') ?>>
                        </label>
                        <label class="field">
                            <span>Expires in days (1–<?= $maxDays ?>)</span>
                            <input class="input" type="number" name="expires_in_days" min="1" max="<?= $maxDays ?>" value="<?= $e($daysValue) ?>"<?= field_attrs($inviteErrors, 'expires_in_days') ?>>
                        </label>
                    </div>
                    <?= field_error($inviteErrors, 'max_uses') ?>
                    <?= field_error($inviteErrors, 'expires_in_days') ?>

                    <label class="field">
                        <span>Grant board membership (optional)</span>
                        <select name="onboarding_board_id" class="input"<?= field_attrs($inviteErrors, 'onboarding_board_id') ?>>
                            <option value="">No board grant</option>
                            <?php foreach (($boards ?? []) as $board): ?>
                                <option value="<?= (int) $board['id'] ?>"<?= (string) ($inviteOld['onboarding_board_id'] ?? '') === (string) $board['id'] ? ' selected' : '' ?>><?= $e($board['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?= field_error($inviteErrors, 'onboarding_board_id') ?>

                    <div class="form-actions"><button class="btn btn-small" type="submit">Issue invitation</button></div>
                </form>
            </section>

            <section class="card member-invitations-list">
                <?php if (empty($rows)): ?>
                    <p class="member-invitations-empty">No invitations have been issued yet.</p>
                <?php else: ?>
                    <div class="table-scroll" tabindex="0" role="region" aria-label="Issued invitations">
                        <table class="audit member-invitations-table">
                            <thead>
                                <tr><th scope="col">Created</th><th scope="col">By</th><th scope="col">Binding</th><th scope="col" class="member-invitations-uses">Uses</th><th scope="col">Expires</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $inviteStatus = strtolower((string) $row['status']); ?>
                                <tr>
                                    <td class="member-invitations-created"><?= $e(human_datetime((string) $row['created_at'])) ?></td>
                                    <td class="member-invitations-by"><?= $e($row['creator_username'] ?? 'system') ?></td>
                                    <td>
                                        <?php if ($row['email'] !== null): ?><?= $e($row['email']) ?>
                                        <?php elseif ($row['domain'] !== null): ?>@<?= $e($row['domain']) ?>
                                        <?php else: ?><span class="muted">any email</span><?php endif; ?>
                                    </td>
                                    <td class="member-invitations-uses"><?= (int) $row['used_count'] ?>/<?= (int) $row['max_uses'] ?></td>
                                    <td class="member-invitations-expires"><?= $row['expires_at'] !== null ? $e(human_datetime((string) $row['expires_at'])) : '—' ?></td>
                                    <td><span class="member-invitations-status is-<?= $e($inviteStatus) ?>"><?= $e($inviteStatus) ?></span></td>
                                    <td class="member-invitations-actions">
                                        <?php if ($inviteStatus === 'active'): ?>
                                            <form method="post" action="/admin/invitations/<?= (int) $row['id'] ?>/revoke" class="inline-form">
                                                <?= $this->csrfField() ?>
                                                <button class="linkbtn danger" type="submit">Revoke</button>
                                            </form>
                                        <?php else: ?>
                                            —
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
<?= $this->partial('admin/_console_end') ?>
