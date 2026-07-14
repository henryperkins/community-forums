<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Composing');
$this->section('robots', 'noindex, nofollow');
$enter = !empty($prefs['enter_to_send']);
$preview = !empty($prefs['show_preview']);
$smart = !empty($prefs['smart_lists']);
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav') ?>

        <div class="settings-pane">
    <form method="post" action="/settings/composing" class="stacked scribe-panel">
        <span class="scribe-panel-head">Composing</span>
        <?= $this->csrfField() ?>
        <p class="muted">These control how the shared Markdown composer behaves for new topics, replies, direct messages, and edits.</p>
        <label class="switchline"><input class="switch" type="checkbox" name="enter_to_send" value="1"<?= $enter ? ' checked' : '' ?>><span class="switch-text">Press <kbd>Enter</kbd> to send outside lists, quotes, and code on desktop. <kbd>Ctrl</kbd>/<kbd>Cmd</kbd>+<kbd>Enter</kbd> always sends; <kbd>Shift</kbd>+<kbd>Enter</kbd> inserts a new line. On touch devices, use Send.</span></label>
        <label class="switchline"><input class="switch" type="checkbox" name="show_preview" value="1"<?= $preview ? ' checked' : '' ?>><span class="switch-text">Start with the preview pane open (source mode)</span></label>
        <label class="switchline"><input class="switch" type="checkbox" name="smart_lists" value="1"<?= $smart ? ' checked' : '' ?>><span class="switch-text">Continue lists and quotes on the next line</span></label>
        <button class="btn" type="submit">Save composing preferences</button>
    </form>
        </div>
    </div>
</div>
