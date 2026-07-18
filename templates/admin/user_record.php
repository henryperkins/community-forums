<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'User · ' . ($subject['username'] ?? ''));
$display = ($subject['display_name'] ?? '') !== '' ? $subject['display_name'] : ($subject['username'] ?? '');
$uid = (int) $subject['id'];
$status = (string) ($subject['status'] ?? 'active');
$history = $history ?? ['warnings' => [], 'notes' => [], 'bans' => [], 'log' => []];
$ctx = $error_context ?? null;
$errs = $errors ?? [];
$old = $old ?? [];
$pii = $pii ?? null;
/** Field error scoped to the originating form (so a warn error is not echoed under ban). */
$ferr = function (string $context, string $field) use ($ctx, $errs): string {
    return $ctx === $context ? field_error($errs, $field, 'err-' . $context . '-' . $field) : '';
};
/** Input attributes (aria-invalid / aria-describedby / autofocus) scoped the same way. */
$fattr = function (string $context, string $field) use ($ctx, $errs): string {
    return $ctx === $context ? field_attrs($errs, $field, 'err-' . $context . '-' . $field) : '';
};
/** Old value scoped to the originating form. */
$oldv = function (string $context, string $field) use ($ctx, $old): string {
    return $ctx === $context ? (string) ($old[$field] ?? '') : '';
};
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($display) ?> <span class="muted">@<?= $e($subject['username']) ?></span></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'users', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <section class="card">
        <h2>Status</h2>
        <dl class="profile-stats">
            <div><dt>Role</dt><dd><span class="role-pill role-<?= $e($subject['role']) ?>"><?= $e($subject['role']) ?></span></dd></div>
            <div><dt>State</dt><dd><span class="state state-<?= $e($status) ?>"><?= $e($status) ?></span></dd></div>
            <?php if ($status === 'suspended'): ?>
                <div><dt>Suspended until</dt><dd><?= $subject['suspended_until'] ? $e(human_datetime((string) $subject['suspended_until'])) : 'Indefinite' ?></dd></div>
            <?php endif; ?>
            <div><dt>Joined</dt><dd><?= $e(human_date((string) $subject['created_at'])) ?></dd></div>
            <div><dt>Last seen</dt><dd><?= !empty($subject['last_seen_at']) ? $e(human_date((string) $subject['last_seen_at'])) : 'never' ?></dd></div>
            <div><dt>Reputation</dt><dd><?= (int) $subject['reputation'] ?></dd></div>
            <div><dt>Posts</dt><dd><?= (int) ($subject['post_count'] ?? 0) ?></dd></div>
            <div><dt>Profile</dt><dd><a href="/u/<?= $e($subject['username']) ?>">View public profile</a></dd></div>
        </dl>
    </section>

    <section class="card">
        <h2>Contact &amp; signals <span class="muted">(PII — access is audited)</span></h2>
        <?php if ($pii !== null): ?>
            <dl class="profile-stats">
                <div><dt>Email</dt><dd><code><?= $e($pii['email']) ?></code></dd></div>
                <div><dt>Recent session IPs</dt><dd><?= empty($pii['session_ips']) ? '<span class="muted">none recorded</span>' : $e(implode(', ', $pii['session_ips'])) ?></dd></div>
                <div><dt>Recent post IPs</dt><dd><?= empty($pii['post_ips']) ? '<span class="muted">none recorded</span>' : $e(implode(', ', $pii['post_ips'])) ?></dd></div>
            </dl>
            <p class="muted">Shown for this view only; this access was written to the audit log. IPs are anonymised on the retention schedule.</p>
        <?php else: ?>
            <p class="muted">Email and recently observed IPs are hidden by default (ADMIN §5.5). Revealing them writes a <code>view_pii</code> audit entry naming you.</p>
            <form method="post" action="/admin/users/<?= $uid ?>/pii" class="inline-form">
                <?= $this->csrfField() ?>
                <button class="btn btn-small" type="submit">Reveal email &amp; IPs (audited)</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Account restrictions</h2>
        <?php if (!empty($errs['user']) && in_array($ctx, ['suspend', 'ban'], true)): ?>
            <p class="field-error"><?= $e($errs['user']) ?></p>
        <?php endif; ?>
        <?php if (!empty($is_self)): ?>
            <p class="muted">You cannot suspend or ban your own account.</p>
        <?php elseif (empty($can_govern)): ?>
            <p class="muted">Administrators cannot be suspended or banned here.</p>
        <?php else: ?>
            <?php if ($status !== 'active'): ?>
                <form method="post" action="/admin/users/<?= $uid ?>/lift" class="inline-form">
                    <?= $this->csrfField() ?>
                    <button class="btn" type="submit">Lift restriction</button>
                    <span class="muted">Restore this account to active.</span>
                </form>
            <?php endif; ?>

            <h3>Suspend</h3>
            <p class="muted">Temporary read-only state; reversible with Lift and auto-expires when an Until is set.</p>
            <form method="post" action="/admin/users/<?= $uid ?>/suspend" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Reason</span>
                    <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($oldv('suspend', 'reason')) ?>"<?= $fattr('suspend', 'reason') ?> required>
                </label>
                <?= $ferr('suspend', 'reason') ?>
                <label class="field">
                    <span>Until (UTC, optional — leave blank for indefinite)</span>
                    <input type="text" name="until" class="input" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e($oldv('suspend', 'until')) ?>"<?= $fattr('suspend', 'until') ?>>
                </label>
                <?= $ferr('suspend', 'until') ?>
                <div class="form-actions"><button class="btn danger" type="submit">Suspend</button></div>
            </form>

            <h3>Permanent ban</h3>
            <p class="muted">Revokes access until an admin lifts it. Type the username to confirm — this is the record's most consequential action.</p>
            <form method="post" action="/admin/users/<?= $uid ?>/ban" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Reason</span>
                    <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($oldv('ban', 'reason')) ?>"<?= $fattr('ban', 'reason') ?> required>
                </label>
                <?= $ferr('ban', 'reason') ?>
                <label class="field">
                    <span>Type <code>@<?= $e($subject['username']) ?></code>'s username to confirm</span>
                    <input type="text" name="confirm_username" class="input" autocomplete="off" autocapitalize="off" spellcheck="false"<?= $fattr('ban', 'confirm_username') ?> required>
                </label>
                <?= $ferr('ban', 'confirm_username') ?>
                <div class="form-actions"><button class="btn danger" type="submit">Ban permanently</button></div>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Role</h2>
        <?php if (!empty($errs['role']) && $ctx === 'change_role'): ?>
            <p class="field-error"><?= $e($errs['role']) ?></p>
        <?php endif; ?>
        <?php
            $roleOld = $ctx === 'change_role' ? (string) ($old['role'] ?? '') : '';
            $roleSel = $roleOld !== '' ? $roleOld : (string) $subject['role'];
        ?>
        <form method="post" action="/admin/users/<?= $uid ?>/role" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Role</span>
                <select name="role" class="input">
                    <option value="user"<?= $roleSel === 'user' ? ' selected' : '' ?>>user</option>
                    <option value="moderator"<?= $roleSel === 'moderator' ? ' selected' : '' ?>>moderator</option>
                    <option value="admin"<?= $roleSel === 'admin' ? ' selected' : '' ?>>admin</option>
                </select>
            </label>
            <label class="field">
                <span>Your current password</span>
                <input type="password" name="current_password" class="input"<?= $fattr('change_role', 'current_password') ?> required>
            </label>
            <?= $ferr('change_role', 'current_password') ?>
            <div class="form-actions"><button class="btn danger" type="submit">Change role</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Staff actions</h2>
        <h3>Issue a warning</h3>
        <form method="post" action="/admin/users/<?= $uid ?>/warn" class="stacked">
            <?= $this->csrfField() ?>
            <?php $warnKey = $oldv('warn', 'idempotency_key') !== '' ? $oldv('warn', 'idempotency_key') : bin2hex(random_bytes(16)); ?>
            <input type="hidden" name="idempotency_key" value="<?= $e($warnKey) ?>">
            <label class="field">
                <span>Reason (shown to the member)</span>
                <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($oldv('warn', 'reason')) ?>"<?= $fattr('warn', 'reason') ?> required>
            </label>
            <?= $ferr('warn', 'reason') ?>
            <div class="form-actions"><button class="btn" type="submit">Record warning</button></div>
        </form>

        <h3>Private staff note</h3>
        <form method="post" action="/admin/users/<?= $uid ?>/note" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Note (visible to staff only)</span>
                <textarea name="body" class="input" rows="3" maxlength="65535"<?= $fattr('note', 'body') ?>><?= $e($oldv('note', 'body')) ?></textarea>
            </label>
            <?= $ferr('note', 'body') ?>
            <div class="form-actions"><button class="btn" type="submit">Add note</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Cosmetic title</h2>
        <p class="muted">Effective: <strong><?= $e($effective_title) ?></strong> · Derived ladder: <?= $e($derived_title) ?></p>
        <form method="post" action="/admin/users/<?= $uid ?>/title" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Title override</span>
                <input type="text" name="title" class="input" maxlength="64" value="<?= $e($old['title'] ?? ($stored_title ?? '')) ?>"<?= field_attrs($errs, 'title') ?>>
            </label>
            <?= field_error($errs, 'title') ?>
            <div class="form-actions"><button class="btn" type="submit">Save title</button></div>
        </form>
        <form method="post" action="/admin/users/<?= $uid ?>/title" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="hidden" name="title" value="">
            <button class="btn btn-small" type="submit">Clear (revert to derived)</button>
        </form>
    </section>

    <?php if (!empty($profile_media)): ?>
        <section class="card profile-media-card">
            <h2>Profile media</h2>
            <?php if (!empty($subject['avatar_path']) && ($subject['avatar_source'] ?? '') === 'upload'): ?>
                <div class="avatar-row">
                    <img class="monogram avatar-img monogram-gilt" src="<?= $e((string) $subject['avatar_path']) ?>" alt="" width="64" height="64">
                    <form method="post" action="/admin/users/<?= $uid ?>/avatar/remove" class="inline-form">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-small danger" type="submit">Remove avatar</button>
                    </form>
                </div>
            <?php else: ?>
                <p class="muted">No uploaded avatar set.</p>
            <?php endif; ?>
            <?php if (!empty($subject['signature'])): ?>
                <p class="muted">Current signature: <?= nl2br($e($subject['signature'])) ?></p>
                <form method="post" action="/admin/users/<?= $uid ?>/signature/remove" class="inline-form">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-small danger" type="submit">Remove signature</button>
                </form>
            <?php else: ?>
                <p class="muted">No signature set.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Badges</h2>
        <h3>Grant a manual badge</h3>
        <form method="post" action="/admin/users/<?= $uid ?>/badges/grant" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Badge</span>
                <select name="slug" class="input"<?= field_attrs($errs, 'slug') ?> required>
                    <?php foreach ($catalogue as $b): ?>
                        <option value="<?= $e($b['slug']) ?>"><?= $e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?= field_error($errs, 'slug') ?>
            <label class="field">
                <span>Reason (optional)</span>
                <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($oldv('badge_grant', 'reason')) ?>">
            </label>
            <div class="form-actions"><button class="btn" type="submit">Grant badge</button></div>
        </form>

        <h3>Held manual badges</h3>
        <?php if (empty($held_manual)): ?>
            <p class="muted">No manual badges granted.</p>
        <?php else: ?>
            <ul class="link-list">
                <?php foreach ($held_manual as $b): ?>
                    <li>
                        <span class="badge-icon" aria-hidden="true"><?= $e($b['icon'] ?? '*') ?></span>
                        <?= $e($b['name']) ?>
                        <form method="post" action="/admin/users/<?= $uid ?>/badges/revoke" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="slug" value="<?= $e($b['slug']) ?>">
                            <button class="linkbtn danger" type="submit" aria-label="Revoke the <?= $e($b['name']) ?> badge">Revoke</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>History</h2>

        <h3>Warnings</h3>
        <?php if (empty($history['warnings'])): ?>
            <p class="muted">No warnings.</p>
        <?php else: ?>
            <ul class="record-list">
                <?php foreach ($history['warnings'] as $w): ?>
                    <li>
                        <span class="record-when"><?= $e(human_datetime((string) $w['created_at'])) ?></span>
                        <span class="record-body"><?= $e($w['reason']) ?></span>
                        <span class="muted">by @<?= $e($w['issued_by_username'] ?? 'system') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3>Bans &amp; suspensions</h3>
        <?php if (empty($history['bans'])): ?>
            <p class="muted">No ban history.</p>
        <?php else: ?>
            <ul class="record-list">
                <?php foreach ($history['bans'] as $bn): ?>
                    <li>
                        <span class="record-when"><?= $e(human_datetime((string) $bn['created_at'])) ?></span>
                        <span class="record-body">
                            <?= ($bn['type'] ?? '') === 'post' ? 'read-only (suspension)' : 'full ban' ?> · <?= $e($bn['reason']) ?>
                            <?php if (!empty($bn['lifted_at'])): ?>
                                <span class="pill">lifted <?= $e(human_date((string) $bn['lifted_at'])) ?></span>
                            <?php elseif (!empty($bn['expires_at'])): ?>
                                <span class="pill">until <?= $e(human_date((string) $bn['expires_at'])) ?></span>
                            <?php else: ?>
                                <span class="pill">indefinite</span>
                            <?php endif; ?>
                        </span>
                        <span class="muted">by @<?= $e($bn['created_by_username'] ?? 'system') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3>Private staff notes</h3>
        <?php if (empty($history['notes'])): ?>
            <p class="muted">No staff notes.</p>
        <?php else: ?>
            <ul class="record-list">
                <?php foreach ($history['notes'] as $n): ?>
                    <li>
                        <span class="record-when"><?= $e(human_datetime((string) $n['created_at'])) ?></span>
                        <span class="record-body"><?= nl2br($e($n['body'])) ?></span>
                        <span class="muted">by @<?= $e($n['author_username'] ?? 'system') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3>Audit trail</h3>
        <?php if (empty($history['log'])): ?>
            <p class="muted">No audit entries.</p>
        <?php else: ?>
            <ul class="record-list">
                <?php foreach ($history['log'] as $lg): ?>
                    <li>
                        <span class="record-when"><?= $e(human_datetime((string) $lg['created_at'])) ?></span>
                        <span class="record-body"><?= $e($lg['action']) ?><?= !empty($lg['reason']) ? ' — ' . $e($lg['reason']) : '' ?></span>
                        <span class="muted">by @<?= $e($lg['actor_username'] ?? 'system') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="muted"><a href="/admin/audit?target_type=user&amp;target_id=<?= $uid ?>">Full trail in the audit log</a></p>
        <?php endif; ?>
    </section>
    </div>
</div>
