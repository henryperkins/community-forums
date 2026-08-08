<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'User · ' . ($subject['username'] ?? ''));
$this->section('variant', 'admin');
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
<?= $this->partial('admin/_member_tabs', ['active' => 'directory', 'pane_class' => 'member-record']) ?>
        <a class="admin-back member-record-back" href="/admin/users">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            All members
        </a>

        <div class="member-record-identity">
            <span class="member-record-monogram">
                <?= $this->partial('partials/monogram', [
                    'name' => $display,
                    'username' => (string) $subject['username'],
                    'avatar_path' => (string) ($subject['avatar_path'] ?? ''),
                    'gilt' => true,
                ]) ?>
            </span>
            <span>
                <h2><?= $e($display) ?></h2>
                <span class="member-record-handle">@<?= $e($subject['username']) ?></span>
            </span>
        </div>

        <div class="member-record-summary-grid">
            <section class="card member-record-card">
                <h3>Status</h3>
                <dl class="member-record-status-list">
                    <div><dt>Role</dt><dd><?= $e($subject['role']) ?></dd></div>
                    <div><dt>State</dt><dd><?= $e($status) ?></dd></div>
                    <div><dt>Reputation</dt><dd><?= number_format((int) $subject['reputation']) ?></dd></div>
                    <div><dt>Posts</dt><dd><?= number_format((int) ($subject['post_count'] ?? 0)) ?></dd></div>
                    <div><dt>Joined</dt><dd><?= $e(human_date((string) $subject['created_at'])) ?></dd></div>
                    <div><dt>Last seen</dt><dd><?= !empty($subject['last_seen_at']) ? $e(human_date((string) $subject['last_seen_at'])) : 'never' ?></dd></div>
                </dl>
                <?php if ($status === 'suspended'): ?>
                    <p class="member-record-suspended">Suspended until <?= $subject['suspended_until'] ? $e(human_datetime((string) $subject['suspended_until'])) : 'Indefinite' ?></p>
                <?php endif; ?>
                <p class="member-record-profile-link"><a href="/u/<?= $e($subject['username']) ?>">View public profile</a></p>
            </section>

            <section class="card member-record-card member-record-contact">
                <h3>Contact &amp; signals</h3>
                <?php if ($pii !== null): ?>
                    <dl class="member-record-pii-list">
                        <div><dt>Email</dt><dd class="member-record-pii-value"><?= $e($pii['email']) ?></dd></div>
                        <div><dt>Recent session IPs</dt><dd class="member-record-pii-value"><?= empty($pii['session_ips']) ? '<span class="muted">none recorded</span>' : $e(implode(', ', $pii['session_ips'])) ?></dd></div>
                        <div><dt>Recent post IPs</dt><dd class="member-record-pii-value"><?= empty($pii['post_ips']) ? '<span class="muted">none recorded</span>' : $e(implode(', ', $pii['post_ips'])) ?></dd></div>
                    </dl>
                    <p class="member-record-copy">Shown for this view only; this access was written to the audit log. IPs are anonymised on the retention schedule.</p>
                <?php else: ?>
                    <p class="member-record-copy">Email and recently observed IPs are hidden by default (ADMIN §5.5). Revealing them writes a <code>view_pii</code> audit entry naming you.</p>
                    <form method="post" action="/admin/users/<?= $uid ?>/pii" class="inline-form">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-small btn-secondary" type="submit">Reveal email &amp; IPs (audited)</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <section class="card member-record-card member-record-restrictions">
            <h3>Account restrictions</h3>
            <?php if (!empty($errs['user']) && in_array($ctx, ['suspend', 'ban'], true)): ?>
                <p class="field-error" role="alert"><?= $e($errs['user']) ?></p>
            <?php endif; ?>
            <?php if (!empty($is_self)): ?>
                <p class="member-record-copy">You cannot suspend or ban your own account.</p>
            <?php elseif (empty($can_govern)): ?>
                <p class="member-record-copy">Administrators cannot be suspended or banned here.</p>
            <?php else: ?>
                <?php if ($status !== 'active'): ?>
                    <div class="member-record-restriction-banner">
                        <span><?= $status === 'banned' ? 'This account is banned. Lifting restores it to active.' : 'This account is suspended (read-only).' ?></span>
                        <form method="post" action="/admin/users/<?= $uid ?>/lift" class="inline-form">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-small" type="submit">Lift restriction</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="member-record-restriction-grid">
                    <div>
                        <h4>Suspend</h4>
                        <p class="member-record-copy">Temporary read-only state; reversible with Lift and auto-expires when an Until is set.</p>
                        <form method="post" action="/admin/users/<?= $uid ?>/suspend" class="stacked member-record-form">
                            <?= $this->csrfField() ?>
                            <label class="field">
                                <span>Reason</span>
                                <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($oldv('suspend', 'reason')) ?>"<?= $fattr('suspend', 'reason') ?> required>
                            </label>
                            <?= $ferr('suspend', 'reason') ?>
                            <label class="field">
                                <span>Until (UTC, optional)</span>
                                <input type="text" name="until" class="input" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e($oldv('suspend', 'until')) ?>"<?= $fattr('suspend', 'until') ?>>
                            </label>
                            <?= $ferr('suspend', 'until') ?>
                            <div class="form-actions"><button class="btn btn-small danger" type="submit">Suspend</button></div>
                        </form>
                    </div>

                    <div>
                        <h4>Permanent ban</h4>
                        <p class="member-record-copy">Revokes access until an admin lifts it. Type the username to confirm — this is the record’s most consequential action.</p>
                        <form method="post" action="/admin/users/<?= $uid ?>/ban" class="stacked member-record-form">
                            <?= $this->csrfField() ?>
                            <label class="field">
                                <span>Reason</span>
                                <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($oldv('ban', 'reason')) ?>"<?= $fattr('ban', 'reason') ?> required>
                            </label>
                            <?= $ferr('ban', 'reason') ?>
                            <label class="field">
                                <span>Type <?= $e($subject['username']) ?> to confirm</span>
                                <input type="text" name="confirm_username" class="input" autocomplete="off" autocapitalize="off" spellcheck="false"<?= $fattr('ban', 'confirm_username') ?> required>
                            </label>
                            <?= $ferr('ban', 'confirm_username') ?>
                            <div class="form-actions"><button class="btn btn-small danger" type="submit">Ban permanently</button></div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <div class="member-record-action-grid">
            <section class="card member-record-card member-record-role">
                <h3>Role</h3>
                <?php if (!empty($errs['role']) && $ctx === 'change_role'): ?>
                    <p class="field-error" role="alert"><?= $e($errs['role']) ?></p>
                <?php endif; ?>
                <?php
                    $roleOld = $ctx === 'change_role' ? (string) ($old['role'] ?? '') : '';
                    $roleSel = $roleOld !== '' ? $roleOld : (string) $subject['role'];
                ?>
                <form method="post" action="/admin/users/<?= $uid ?>/role" class="stacked member-record-form">
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
                        <input type="password" name="current_password" class="input" autocomplete="current-password"<?= $fattr('change_role', 'current_password') ?> required>
                    </label>
                    <?= $ferr('change_role', 'current_password') ?>
                    <div class="form-actions"><button class="btn btn-small danger" type="submit">Change role</button></div>
                </form>
            </section>

            <section class="card member-record-card member-record-staff-actions">
                <h3>Staff actions</h3>
                <form method="post" action="/admin/users/<?= $uid ?>/warn" class="stacked member-record-form">
                    <?= $this->csrfField() ?>
                    <?php $warnKey = $oldv('warn', 'idempotency_key') !== '' ? $oldv('warn', 'idempotency_key') : bin2hex(random_bytes(16)); ?>
                    <input type="hidden" name="idempotency_key" value="<?= $e($warnKey) ?>">
                    <label class="field">
                        <span>Warning reason (shown to the member)</span>
                        <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($oldv('warn', 'reason')) ?>"<?= $fattr('warn', 'reason') ?> required>
                    </label>
                    <?= $ferr('warn', 'reason') ?>
                    <div class="form-actions"><button class="btn btn-small" type="submit">Record warning</button></div>
                </form>

                <form method="post" action="/admin/users/<?= $uid ?>/note" class="stacked member-record-form member-record-note-form">
                    <?= $this->csrfField() ?>
                    <label class="field">
                        <span>Private staff note</span>
                        <textarea name="body" class="input" rows="3" maxlength="65535"<?= $fattr('note', 'body') ?>><?= $e($oldv('note', 'body')) ?></textarea>
                    </label>
                    <?= $ferr('note', 'body') ?>
                    <div class="form-actions"><button class="btn btn-small btn-secondary" type="submit">Add note</button></div>
                </form>
            </section>

            <section class="card member-record-card member-record-title">
                <h3>Cosmetic title</h3>
                <p class="member-record-copy">Effective: <strong><?= $e($effective_title) ?></strong> · Derived ladder: <?= $e($derived_title) ?></p>
                <div class="member-record-title-controls">
                    <form method="post" action="/admin/users/<?= $uid ?>/title" class="stacked member-record-title-save">
                        <?= $this->csrfField() ?>
                        <label class="field">
                            <span>Title override</span>
                            <input type="text" name="title" class="input" maxlength="64" placeholder="(none)" value="<?= $e($old['title'] ?? ($stored_title ?? '')) ?>"<?= field_attrs($errs, 'title') ?>>
                        </label>
                        <?= field_error($errs, 'title') ?>
                        <div class="form-actions"><button class="btn btn-small" type="submit">Save title</button></div>
                    </form>
                    <form method="post" action="/admin/users/<?= $uid ?>/title" class="inline-form member-record-title-clear">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="title" value="">
                        <button class="btn btn-small btn-ghost" type="submit">Clear (revert to derived)</button>
                    </form>
                </div>
            </section>

            <?php if (!empty($profile_media)): ?>
                <section class="card member-record-card member-record-profile-media">
                    <h3>Profile media</h3>
                    <?php if (!empty($subject['avatar_path']) && ($subject['avatar_source'] ?? '') === 'upload'): ?>
                        <div class="member-record-media-row">
                            <img class="monogram avatar-img monogram-gilt" src="<?= $e((string) $subject['avatar_path']) ?>" alt="" width="64" height="64">
                            <form method="post" action="/admin/users/<?= $uid ?>/avatar/remove" class="inline-form">
                                <?= $this->csrfField() ?>
                                <button class="linkbtn danger" type="submit">Remove avatar</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p class="member-record-empty">No uploaded avatar set.</p>
                    <?php endif; ?>
                    <?php if (!empty($subject['signature'])): ?>
                        <p class="member-record-copy">Current signature: <?= nl2br($e($subject['signature'])) ?></p>
                        <form method="post" action="/admin/users/<?= $uid ?>/signature/remove" class="inline-form">
                            <?= $this->csrfField() ?>
                            <button class="linkbtn danger" type="submit">Remove signature</button>
                        </form>
                    <?php else: ?>
                        <p class="member-record-empty">No signature set.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="card member-record-card member-record-badges">
                <h3>Badges</h3>
                <form method="post" action="/admin/users/<?= $uid ?>/badges/grant" class="stacked member-record-form">
                    <?= $this->csrfField() ?>
                    <label class="field">
                        <span>Grant a manual badge</span>
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
                    <div class="form-actions"><button class="btn btn-small" type="submit">Grant badge</button></div>
                </form>

                <?php if (empty($held_manual)): ?>
                    <p class="member-record-empty">No manual badges granted.</p>
                <?php else: ?>
                    <ul class="member-record-badge-list">
                        <?php foreach ($held_manual as $b): ?>
                            <li>
                                <span class="member-record-badge-icon" aria-hidden="true"><?= $e($b['icon'] ?? '✦') ?></span>
                                <span><?= $e($b['name']) ?></span>
                                <form method="post" action="/admin/users/<?= $uid ?>/badges/revoke" class="inline-form">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="slug" value="<?= $e($b['slug']) ?>">
                                    <button class="linkbtn danger" type="submit" aria-label="Revoke the <?= $e($b['name']) ?> badge">Revoke</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <section class="card member-record-card member-record-history">
            <h3>History</h3>
            <div class="member-record-history-grid">
                <div>
                    <h4>Warnings</h4>
                    <?php if (empty($history['warnings'])): ?>
                        <p class="member-record-empty">No warnings.</p>
                    <?php else: ?>
                        <ul class="member-record-history-list">
                            <?php foreach ($history['warnings'] as $w): ?>
                                <li>
                                    <span class="member-record-when"><?= $e(human_datetime((string) $w['created_at'])) ?></span>
                                    <span class="member-record-body"><?= $e($w['reason']) ?></span>
                                    <span class="member-record-by">by @<?= $e($w['issued_by_username'] ?? 'system') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div>
                    <h4>Bans &amp; suspensions</h4>
                    <?php if (empty($history['bans'])): ?>
                        <p class="member-record-empty">No ban history.</p>
                    <?php else: ?>
                        <ul class="member-record-history-list">
                            <?php foreach ($history['bans'] as $bn): ?>
                                <li>
                                    <span class="member-record-when"><?= $e(human_datetime((string) $bn['created_at'])) ?></span>
                                    <span class="member-record-body">
                                        <?= ($bn['type'] ?? '') === 'post' ? 'read-only (suspension)' : 'full ban' ?> · <?= $e($bn['reason']) ?>
                                        <?php if (!empty($bn['lifted_at'])): ?>
                                            <span class="pill">lifted <?= $e(human_date((string) $bn['lifted_at'])) ?></span>
                                        <?php elseif (!empty($bn['expires_at'])): ?>
                                            <span class="member-record-ban-period">· until <?= $e(human_date((string) $bn['expires_at'])) ?></span>
                                        <?php else: ?>
                                            <span class="member-record-ban-period">· indefinite</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="member-record-by">by @<?= $e($bn['created_by_username'] ?? 'system') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div>
                    <h4>Private staff notes</h4>
                    <?php if (empty($history['notes'])): ?>
                        <p class="member-record-empty">No staff notes.</p>
                    <?php else: ?>
                        <ul class="member-record-history-list">
                            <?php foreach ($history['notes'] as $n): ?>
                                <li>
                                    <span class="member-record-when"><?= $e(human_datetime((string) $n['created_at'])) ?></span>
                                    <span class="member-record-body"><?= nl2br($e($n['body'])) ?></span>
                                    <span class="member-record-by">by @<?= $e($n['author_username'] ?? 'system') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div>
                    <h4>Audit trail</h4>
                    <?php if (empty($history['log'])): ?>
                        <p class="member-record-empty">No audit entries.</p>
                    <?php else: ?>
                        <ul class="member-record-history-list">
                            <?php foreach ($history['log'] as $lg): ?>
                                <li>
                                    <span class="member-record-when"><?= $e(human_datetime((string) $lg['created_at'])) ?></span>
                                    <span class="member-record-body member-record-audit-action"><?= $e($lg['action']) ?><?= !empty($lg['reason']) ? ' — ' . $e($lg['reason']) : '' ?></span>
                                    <span class="member-record-by">by @<?= $e($lg['actor_username'] ?? 'system') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="member-record-audit-link"><a href="/admin/audit?target_type=user&amp;target_id=<?= $uid ?>">Full trail in the audit log</a></p>
                </div>
            </div>
        </section>
<?= $this->partial('admin/_console_end') ?>
