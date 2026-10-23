<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Notification settings');
$tz = (string) ($old['timezone'] ?? $row['timezone'] ?? '');
$hour = $old['digest_hour'] ?? $row['digest_hour'];
if ($old !== []) { $pause_all_email = ($old['pause_all_email'] ?? '') === '1'; }
?>
<div class="settings-screen">
    <header class="settings-head">
        <?= $this->partial('partials/page_heading', ['heading' => 'Account settings']) ?>
        <?= $this->partial('partials/settings_intro') ?>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'notifications']) ?>

        <div class="settings-pane">
    <p class="notification-prose"><a href="/notifications">View notifications</a> or change delivery below.</p>
    <form method="post" action="/settings/notifications" class="stacked scribe-panel">
        <h2 class="scribe-panel-head">Daily digest</h2>
        <?= $this->csrfField() ?>
        <div class="field-grid">
            <div class="field-cell">
            <label class="field">
                <span>Timezone</span>
                <select name="timezone" class="input"<?= field_attrs($errors, 'timezone') ?>>
                    <option value="">Not set (UTC)</option>
                    <?php if ($tz !== '' && !in_array($tz, $timezones, true)): ?><option value="<?= $e($tz) ?>" selected><?= $e($tz) ?></option><?php endif; ?>
                    <?php foreach ($timezones as $z): ?>
                        <option value="<?= $e($z) ?>"<?= $z === $tz ? ' selected' : '' ?>><?= $e($z) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?= field_error($errors, 'timezone') ?>
            </div>
            <div class="field-cell">
            <label class="field">
                <span>Daily digest time</span>
                <select name="digest_hour" class="input"<?= field_attrs($errors, 'digest_hour', null, 'digest-time-hint') ?>>
                    <option value="">Off</option>
                    <?php if ($hour !== null && $hour !== '' && !in_array((string) $hour, array_map('strval', range(0, 23)), true)): ?><option value="<?= $e($hour) ?>" selected><?= $e($hour) ?></option><?php endif; ?>
                    <?php for ($h = 0; $h < 24; $h++): ?>
                        <option value="<?= $h ?>"<?= ($hour !== null && (string) $hour === (string) $h) ? ' selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <p class="field-help" id="digest-time-hint">Uses the selected timezone, or UTC if none is set.</p>
            <?= field_error($errors, 'digest_hour') ?>
            </div>
        </div>
        <div class="switch-stack switch-stack-tight switch-stack-divided">
            <div>
                <label class="switchline"><input class="switch" type="checkbox" role="switch" name="pause_all_email" value="1"<?= !empty($pause_all_email) ? ' checked' : '' ?><?= field_attrs($errors, 'pause_all_email', null, 'pause-email-hint') ?>><span class="switch-text">Pause all email</span></label>
                <p class="switch-sub" id="pause-email-hint">In-app notifications still arrive.</p>
                <?= field_error($errors, 'pause_all_email') ?>
            </div>
        </div>
        <button class="btn" type="submit">Save digest settings</button>
    </form>

    <?php if (!empty($subscription_errors) && empty($subscription_error_id)): ?>
        <form class="stacked scribe-panel" method="post" action="/<?= ($subscription_target['type'] ?? '') === 'thread' ? 't' : 'b' ?>/<?= (int) ($subscription_target['id'] ?? 0) ?>/subscribe">
            <?= $this->csrfField() ?>
            <h2>Subscription settings</h2>
            <?php foreach ($subscription_errors as $error): ?><p class="field-error" role="alert"><?= $e($error) ?></p><?php endforeach; ?>
            <?= $this->partial('partials/subscription_controls', ['subscription' => [], 'subscription_old' => $subscription_old ?? []]) ?>
            <button class="btn" type="submit">Save subscription</button>
        </form>
    <?php endif; ?>
    <section class="scribe-panel is-list account-subscription-list">
        <h2 class="scribe-panel-head">Your subscriptions</h2>
        <?php if (empty($subscriptions)): ?>
            <p class="account-note">No subscriptions yet. Watch a topic to manage its notifications here.</p>
        <?php else: ?>
            <ul class="account-ruled-list">
                <?php foreach ($subscriptions as $s): ?>
                    <?php
                    $isThread = $s['target_type'] === 'thread';
                    $label = $isThread ? ($s['thread_title'] ?? 'Thread') : ('#' . ($s['board_name'] ?? 'Board'));
                    $link = $isThread
                        ? '/t/' . (int) $s['target_id'] . '-' . $e($s['thread_slug'] ?? '')
                        : '/c/' . $e($s['board_slug'] ?? '');
                    $action = '/settings/notifications/subscriptions/' . (int) $s['id'];
                    $draft = (int) ($subscription_error_id ?? 0) === (int) $s['id'] ? ($subscription_old ?? []) : [];
                    $controlLabel = !empty($s['available']) ? $label : 'Unavailable subscription';
                    ?>
                    <li class="account-ruled-row account-subscription-row">
                        <span class="account-row-main">
                            <?php if (!empty($s['available'])): ?><a class="account-row-name" href="<?= $link ?>"><?= $e($label) ?></a>
                            <?php else: ?><span class="account-row-name">Unavailable subscription</span><?php endif; ?>
                        </span>
                        <?php if ((int) ($subscription_error_id ?? 0) === (int) $s['id']): ?>
                            <?php foreach (($subscription_errors ?? []) as $error): ?><p class="field-error" role="alert"><?= $e($error) ?></p><?php endforeach; ?>
                        <?php endif; ?>
                        <form class="stacked" method="post" action="<?= $action ?>">
                            <?= $this->csrfField() ?>
                            <?= $this->partial('partials/subscription_controls', ['subscription' => $s, 'subscription_old' => $draft]) ?>
                            <button class="btn" type="submit" aria-label="Save subscription for <?= $e($controlLabel) ?>">Save subscription</button>
                        </form>
                        <form method="post" action="<?= $action ?>">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="frequency" value="off">
                            <button class="linkbtn danger" type="submit" aria-label="Turn off notifications for <?= $e($controlLabel) ?>">Turn off notifications</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
        </div>
    </div>
</div>
