<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Announcements');
$this->section('variant', 'admin');
$ann = is_array($announcement ?? null) ? $announcement : [];
$active = !empty($ann['active']);
$draftMessage = (string) (($old ?? [])['message'] ?? '');
$draftCount = mb_strlen($draftMessage);
$activeMemberCount = max(0, (int) ($active_member_count ?? 0));
$broadcastReach = $activeMemberCount === 1
    ? '1 active member'
    : number_format($activeMemberCount) . ' active members';
$broadcastEmail = !empty(($old ?? [])['broadcast_email']);
?>
<?= $this->partial('admin/_console', [
    'area' => 'notifications',
    'tab' => 'announcements',
    'pane_class' => 'admin-notifications admin-notifications-announcements',
]) ?>
    <section class="card notification-card announcement-current-card">
        <h2>Current banner</h2>
        <?php if ($active): ?>
            <p class="site-announcement-current"><?= $e((string) ($ann['message'] ?? '')) ?></p>
            <p class="announcement-current-meta">
                <?= !empty($ann['dismissible']) ? 'Dismissible' : 'Not dismissible' ?>
                &middot; version <?= (int) ($ann['version'] ?? 0) ?>
            </p>
            <form method="post" action="/admin/announcements" class="inline-form announcement-clear-form">
                <?= $this->csrfField() ?>
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-secondary" type="submit">Clear banner</button>
            </form>
        <?php else: ?>
            <p class="notification-empty-copy">No banner is currently shown.</p>
        <?php endif; ?>
    </section>

    <section class="card notification-card announcement-publish-card">
        <h2>Publish a banner</h2>
        <form method="post" action="/admin/announcements" data-announcement-form>
            <?= $this->csrfField() ?>
            <label class="announcement-message-field" for="announcement-message">
                <span>Message</span>
                <textarea id="announcement-message" name="message" rows="3" maxlength="500"<?= field_attrs($errors ?? [], 'message', null, 'announcement-message-count') ?> required data-announcement-message><?= $e($draftMessage) ?></textarea>
            </label>
            <p id="announcement-message-count" class="announcement-character-count" data-announcement-count><?= $draftCount ?> / 500</p>

            <div class="announcement-options">
                <label><input type="checkbox" name="dismissible" value="1"<?= !empty(($old ?? [])['dismissible']) ? ' checked' : '' ?>> <span>Members can dismiss this banner</span></label>
                <label><input type="checkbox" name="broadcast" value="1"<?= !empty(($old ?? [])['broadcast']) ? ' checked' : '' ?>> <span>Also send an in-app broadcast notification to all members</span></label>
                <label><input type="checkbox" name="broadcast_email" value="1"<?= $broadcastEmail ? ' checked' : '' ?>> <span>Also queue an email broadcast to active members</span></label>
            </div>

            <p class="announcement-broadcast-warning" data-announcement-broadcast-warning>This will reach <?= $e($broadcastReach) ?> by email. Broadcasts cannot be recalled once the queue starts.</p>

            <div class="announcement-publish-actions">
                <button class="btn" type="submit">Publish banner</button>
                <?= field_error($errors ?? [], 'message') ?>
            </div>
        </form>
    </section>

    <section class="card notification-card announcement-history-card">
        <h2>Recent history</h2>
        <?php if (empty($history ?? [])): ?>
            <p class="notification-empty-copy announcement-history-empty">No announcements have been published yet.</p>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Announcement history">
                <table class="audit announcement-history-table">
                    <thead><tr><th scope="col">When</th><th scope="col">By</th><th scope="col">Event</th><th scope="col">Message</th><th scope="col">Channels</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td class="mono"><?= $e(human_datetime((string) $entry['when'])) ?></td>
                            <td><?= $e($entry['actor'] ?? 'system') ?></td>
                            <td><code><?= $entry['action'] === 'clear_announcement' ? 'Cleared' : 'Published v' . (int) ($entry['version'] ?? 0) ?></code></td>
                            <td class="announcement-history-message">
                                <?php if ($entry['message'] !== null): ?><?= $e($entry['message']) ?><?php else: ?><span class="muted">—</span><?php endif; ?>
                            </td>
                            <td class="announcement-history-channels">
                                <?php if ($entry['action'] === 'set_announcement'): ?>
                                    Banner<?= !empty($entry['broadcast']) ? ' · in-app' : '' ?><?= !empty($entry['email_broadcast']) ? ' · email' : '' ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
