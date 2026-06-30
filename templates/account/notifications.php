<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Notification settings');
$tz = (string) ($row['timezone'] ?? '');
$hour = $row['digest_hour'];
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav') ?>

        <div class="settings-pane">
    <form method="post" action="/settings/notifications" class="stacked scribe-panel">
        <h2 class="scribe-panel-head">Daily digest</h2>
        <?= $this->csrfField() ?>
        <label class="field">
            <span>Timezone</span>
            <select name="timezone" class="input">
                <option value="">Not set (UTC)</option>
                <?php foreach ($timezones as $z): ?>
                    <option value="<?= $e($z) ?>"<?= $z === $tz ? ' selected' : '' ?>><?= $e($z) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Digest hour (local)</span>
            <select name="digest_hour" class="input">
                <option value="">Off</option>
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <option value="<?= $h ?>"<?= ($hour !== null && (int) $hour === $h) ? ' selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <button class="btn" type="submit">Save digest settings</button>
    </form>

    <section class="card">
        <h2>Your subscriptions</h2>
        <?php if (empty($subscriptions)): ?>
            <p class="muted">You aren't subscribed to any threads or boards yet.</p>
        <?php else: ?>
            <ul class="sub-list">
                <?php foreach ($subscriptions as $s): ?>
                    <?php
                    $isThread = $s['target_type'] === 'thread';
                    $label = $isThread ? ($s['thread_title'] ?? 'Thread') : ('#' . ($s['board_name'] ?? 'Board'));
                    $link = $isThread
                        ? '/t/' . (int) $s['target_id'] . '-' . $e($s['thread_slug'] ?? '')
                        : '/c/' . $e($s['board_slug'] ?? '');
                    $action = $isThread ? '/t/' . (int) $s['target_id'] . '/subscribe' : '/b/' . (int) $s['target_id'] . '/subscribe';
                    ?>
                    <li class="sub-row">
                        <a href="<?= $link ?>"><?= $e($label) ?></a>
                        <span class="muted">· <?= $e(ucfirst((string) $s['frequency'])) ?><?= (int) $s['email_enabled'] === 1 ? ' · email' : '' ?></span>
                        <form class="inline" method="post" action="<?= $action ?>">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="frequency" value="off">
                            <input type="hidden" name="in_app" value="0">
                            <input type="hidden" name="email" value="0">
                            <button class="linkbtn danger" type="submit">Unsubscribe</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
        </div>
    </div>
</div>
