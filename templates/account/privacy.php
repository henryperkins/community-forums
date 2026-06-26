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
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <form method="post" action="/settings/privacy" class="stacked card">
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
        <label class="checkline"><input type="checkbox" name="show_presence" value="1"<?= $showPresence ? ' checked' : '' ?>> Show when I'm online</label>
        <label class="checkline"><input type="checkbox" name="hide_from_leaderboard" value="1"<?= $hideLb ? ' checked' : '' ?>> Hide me from leaderboards</label>
        <label class="checkline"><input type="checkbox" name="discoverable_by_email" value="1"<?= $discoverable ? ' checked' : '' ?>> Let others find me by email</label>
        <button class="btn" type="submit">Save privacy settings</button>
    </form>
</div>
