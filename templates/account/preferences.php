<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Reading');
$this->section('robots', 'noindex, nofollow');
$tpp = (int) ($prefs['threads_per_page'] ?? 20);
$ppp = (int) ($prefs['posts_per_page'] ?? 20);
$sort = (string) ($prefs['thread_sort'] ?? 'last_post');
$sig = !empty($prefs['show_signatures']);
$av = !empty($prefs['show_avatars']);
$rx = !empty($prefs['show_reactions']);
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
                <option value="20"<?= $opt(20, $tpp) ?>>20</option>
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
        <label class="checkline"><input type="checkbox" name="show_signatures" value="1"<?= $sig ? ' checked' : '' ?>> Show signatures</label>
        <label class="checkline"><input type="checkbox" name="show_avatars" value="1"<?= $av ? ' checked' : '' ?>> Show avatars</label>
        <label class="checkline"><input type="checkbox" name="show_reactions" value="1"<?= $rx ? ' checked' : '' ?>> Show reactions</label>
        <button class="btn" type="submit">Save reading preferences</button>
    </form>
</div>
