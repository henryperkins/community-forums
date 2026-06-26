<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Preferences');
$tpp = (int) ($prefs['threads_per_page'] ?? 0);
$ppp = (int) ($prefs['posts_per_page'] ?? 0);
$sort = (string) ($prefs['thread_sort'] ?? 'last_post');
$theme = (string) ($prefs['theme'] ?? 'system');
$density = (string) ($prefs['density'] ?? 'comfortable');
$sig = !array_key_exists('show_signatures', $prefs) || !empty($prefs['show_signatures']);
$av = !array_key_exists('show_avatars', $prefs) || !empty($prefs['show_avatars']);
$rx = !array_key_exists('show_reactions', $prefs) || !empty($prefs['show_reactions']);
$opt = static fn (int $v, int $cur): string => $v === $cur ? ' selected' : '';
?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <form method="post" action="/settings/preferences" class="stacked card">
        <?= $this->csrfField() ?>
        <label class="field">
            <span>Threads per page</span>
            <select name="threads_per_page" class="input">
                <option value="25"<?= $opt(25, $tpp) ?>>25</option>
                <option value="50"<?= $opt(50, $tpp) ?>>50</option>
                <option value="100"<?= $opt(100, $tpp) ?>>100</option>
            </select>
        </label>
        <label class="field">
            <span>Posts per page</span>
            <select name="posts_per_page" class="input">
                <option value="10"<?= $opt(10, $ppp) ?>>10</option>
                <option value="20"<?= $opt(20, $ppp) ?>>20</option>
                <option value="40"<?= $opt(40, $ppp) ?>>40</option>
            </select>
        </label>
        <label class="field">
            <span>Default thread sort</span>
            <select name="thread_sort" class="input">
                <option value="last_post"<?= $sort === 'last_post' ? ' selected' : '' ?>>Last post</option>
                <option value="newest"<?= $sort === 'newest' ? ' selected' : '' ?>>Newest</option>
                <option value="replies"<?= $sort === 'replies' ? ' selected' : '' ?>>Most replies</option>
            </select>
        </label>
        <label class="field">
            <span>Theme</span>
            <select name="theme" class="input">
                <option value="system"<?= $theme === 'system' ? ' selected' : '' ?>>System</option>
                <option value="light"<?= $theme === 'light' ? ' selected' : '' ?>>Light</option>
                <option value="dark"<?= $theme === 'dark' ? ' selected' : '' ?>>Dark</option>
            </select>
        </label>
        <label class="field">
            <span>Density</span>
            <select name="density" class="input">
                <option value="comfortable"<?= $density === 'comfortable' ? ' selected' : '' ?>>Comfortable</option>
                <option value="compact"<?= $density === 'compact' ? ' selected' : '' ?>>Compact</option>
            </select>
        </label>
        <label class="checkline"><input type="checkbox" name="show_signatures" value="1"<?= $sig ? ' checked' : '' ?>> Show signatures</label>
        <label class="checkline"><input type="checkbox" name="show_avatars" value="1"<?= $av ? ' checked' : '' ?>> Show avatars</label>
        <label class="checkline"><input type="checkbox" name="show_reactions" value="1"<?= $rx ? ' checked' : '' ?>> Show reactions</label>
        <button class="btn" type="submit">Save preferences</button>
    </form>
</div>
