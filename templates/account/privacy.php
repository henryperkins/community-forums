<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Privacy settings');
$visibility = (string) ($row['profile_visibility'] ?? 'public');
$allowDms = (string) ($row['allow_dms'] ?? 'members');
$showPresence = (int) ($row['show_presence'] ?? 1) === 1;
$hideLb = !empty($prefs['hide_from_leaderboard']);
$discoverable = !array_key_exists('discoverable_by_email', $prefs) || !empty($prefs['discoverable_by_email']);
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav') ?>

        <div class="settings-pane">
    <form method="post" action="/settings/privacy" class="stacked scribe-panel">
        <span class="scribe-panel-head">Privacy</span>
        <?= $this->csrfField() ?>
        <label class="field">
            <span>Profile visibility</span>
            <select name="profile_visibility" class="input">
                <option value="public"<?= $visibility === 'public' ? ' selected' : '' ?>>Public — anyone can view</option>
                <option value="members"<?= $visibility === 'members' ? ' selected' : '' ?>>Members only — signed-in members</option>
            </select>
        </label>
        <label class="field">
            <span>Allow direct messages from</span>
            <select name="allow_dms" class="input">
                <option value="everyone"<?= $allowDms === 'everyone' ? ' selected' : '' ?>>Everyone</option>
                <option value="members"<?= $allowDms === 'members' ? ' selected' : '' ?>>Members</option>
                <option value="none"<?= $allowDms === 'none' ? ' selected' : '' ?>>No one</option>
            </select>
        </label>
        <div class="toggle-stack">
            <label class="gem-field"><input class="gem-check gem-leaf" type="checkbox" name="show_presence" value="1"<?= $showPresence ? ' checked' : '' ?>><span>Show when I'm online<span class="gem-sub">A leaf marks your presence beside your name.</span></span></label>
            <label class="gem-field"><input class="gem-check gem-gold" type="checkbox" name="hide_from_leaderboard" value="1"<?= $hideLb ? ' checked' : '' ?>><span>Hide me from leaderboards<span class="gem-sub">You still earn regard; you just won't be ranked publicly.</span></span></label>
            <label class="gem-field"><input class="gem-check gem-river" type="checkbox" name="discoverable_by_email" value="1"<?= $discoverable ? ' checked' : '' ?>><span>Let others find me by email</span></label>
        </div>
        <button class="btn" type="submit">Save privacy settings</button>
    </form>
        </div>
    </div>
</div>
