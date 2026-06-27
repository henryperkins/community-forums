<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Composing');
$this->section('robots', 'noindex, nofollow');
$enter = !empty($prefs['enter_to_send']);
$preview = !empty($prefs['show_preview']);
$smart = !empty($prefs['smart_lists']);
?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <form method="post" action="/settings/composing" class="stacked card">
        <?= $this->csrfField() ?>
        <p class="muted">These control how the shared Markdown composer behaves for new topics, replies, direct messages, and edits.</p>
        <label class="checkline"><input type="checkbox" name="enter_to_send" value="1"<?= $enter ? ' checked' : '' ?>> Press <kbd>Enter</kbd> to send (use <kbd>Shift</kbd>+<kbd>Enter</kbd> for a new line)</label>
        <label class="checkline"><input type="checkbox" name="show_preview" value="1"<?= $preview ? ' checked' : '' ?>> Show a live preview while composing</label>
        <label class="checkline"><input type="checkbox" name="smart_lists" value="1"<?= $smart ? ' checked' : '' ?>> Continue lists and quotes on the next line</label>
        <button class="btn" type="submit">Save composing preferences</button>
    </form>
</div>
