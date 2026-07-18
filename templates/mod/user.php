<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Moderate · ' . ($subject['username'] ?? ''));
$display = ($subject['display_name'] ?? '') !== '' ? $subject['display_name'] : ($subject['username'] ?? '');
$uid = (int) $subject['id'];
$status = (string) ($subject['status'] ?? 'active');
$history = $history ?? ['warnings' => [], 'notes' => [], 'bans' => [], 'log' => []];
$ctx = $error_context ?? null;
$errs = $errors ?? [];
$old = $old ?? [];
/** Field error scoped to the originating form (so a warn error is not echoed under note). */
$ferr = function (string $context, string $field) use ($ctx, $errs, $e): string {
    if ($ctx !== $context || empty($errs[$field])) {
        return '';
    }
    return '<p class="field-error">' . $e($errs[$field]) . '</p>';
};
/** Old value scoped to the originating form. */
$oldv = function (string $context, string $field) use ($ctx, $old): string {
    return $ctx === $context ? (string) ($old[$field] ?? '') : '';
};
?>
<div class="mod reports-view">
    <header class="mod-head">
        <span>
            <span class="eyebrow">Warden's table</span>
            <h1><?= $e($display) ?> <span class="muted">@<?= $e($subject['username']) ?></span></h1>
        </span>
        <span class="pill mod-pill">Moderation</span>
    </header>

    <nav class="mod-subnav" aria-label="Moderation queues">
        <a href="/mod/reports">Reports</a>
        <a href="/mod/approvals">Approval hold</a>
        <a href="/mod/appeals">Appeals</a>
    </nav>

    <div class="mod-pane">
        <?php if ($ctx !== null && $errs !== [] && (!empty($is_self) || !in_array($ctx, ['warn', 'note'], true))): ?>
            <?php
            // Failed action whose form is not on this page (suspend/ban/lift
            // post from elsewhere; warn/note forms are hidden on self):
            // surface the error and, for suspend/ban, a retry form carrying
            // the typed input so nothing is lost (anti-draft-loss).
            ?>
            <section class="card mod-action-retry">
                <h2>Moderation action failed</h2>
                <?php foreach ($errs as $message): ?>
                    <p class="field-error"><?= $e($message) ?></p>
                <?php endforeach; ?>
                <?php if (empty($is_self) && in_array($ctx, ['suspend', 'ban'], true)): ?>
                    <form method="post" action="/mod/u/<?= $uid ?>/<?= $e($ctx) ?>" class="stacked">
                        <?= $this->csrfField() ?>
                        <label class="field">
                            <span>Reason</span>
                            <input type="text" name="reason" class="input" maxlength="255" value="<?= $e((string) ($old['reason'] ?? '')) ?>" required>
                        </label>
                        <?php if ($ctx === 'suspend'): ?>
                            <label class="field">
                                <span>Until (UTC, optional — leave blank for indefinite)</span>
                                <input type="text" name="until" class="input" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e((string) ($old['until'] ?? '')) ?>">
                            </label>
                        <?php endif; ?>
                        <div class="form-actions"><button class="btn danger" type="submit">Try again</button></div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2>Member</h2>
            <dl class="profile-stats">
                <div><dt>Role</dt><dd><span class="role-pill role-<?= $e($subject['role']) ?>"><?= $e($subject['role']) ?></span></dd></div>
                <div><dt>State</dt><dd><span class="state state-<?= $e($status) ?>"><?= $e($status) ?></span></dd></div>
                <?php if ($status === 'suspended' && !empty($subject['suspended_until'])): ?>
                    <div><dt>Suspended until</dt><dd><?= $e(human_datetime((string) $subject['suspended_until'])) ?></dd></div>
                <?php endif; ?>
                <div><dt>Joined</dt><dd><?= $e(human_date((string) $subject['created_at'])) ?></dd></div>
                <div><dt>Last seen</dt><dd><?= !empty($subject['last_seen_at']) ? $e(human_date((string) $subject['last_seen_at'])) : 'never' ?></dd></div>
                <div><dt>Posts</dt><dd><?= (int) ($subject['post_count'] ?? 0) ?></dd></div>
                <div><dt>Reputation</dt><dd><?= (int) ($subject['reputation'] ?? 0) ?></dd></div>
                <div><dt>Profile</dt><dd><a href="/u/<?= $e($subject['username']) ?>">View public profile</a></dd></div>
            </dl>
            <?php if (!empty($is_admin)): ?>
                <p><a class="btn btn-small" href="/admin/users/<?= $uid ?>">Open the full admin record (suspend, ban, role, badges)</a></p>
            <?php endif; ?>
        </section>

        <?php if (!empty($is_self)): ?>
            <section class="card">
                <p class="muted">This is your own account — staff actions are disabled.</p>
            </section>
        <?php else: ?>
            <section class="card">
                <h2>Issue a warning</h2>
                <form method="post" action="/mod/u/<?= $uid ?>/warn" class="stacked">
                    <?= $this->csrfField() ?>
                    <?php if ($oldv('warn', 'board_id') !== ''): ?>
                        <input type="hidden" name="board_id" value="<?= (int) $oldv('warn', 'board_id') ?>">
                    <?php endif; ?>
                    <label class="field">
                        <span>Reason (shown to the member)</span>
                        <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($oldv('warn', 'reason')) ?>" required>
                    </label>
                    <?= $ferr('warn', 'reason') ?>
                    <div class="form-actions"><button class="btn" type="submit">Record warning</button></div>
                </form>
            </section>

            <section class="card">
                <h2>Private staff note</h2>
                <form method="post" action="/mod/u/<?= $uid ?>/note" class="stacked">
                    <?= $this->csrfField() ?>
                    <label class="field">
                        <span>Note (visible to staff only)</span>
                        <textarea name="body" class="input" rows="3"><?= $e($oldv('note', 'body')) ?></textarea>
                    </label>
                    <?= $ferr('note', 'body') ?>
                    <div class="form-actions"><button class="btn" type="submit">Add note</button></div>
                </form>
            </section>
        <?php endif; ?>

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
            <?php endif; ?>
        </section>
    </div>
</div>
