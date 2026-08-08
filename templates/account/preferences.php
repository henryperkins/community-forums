<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Reading');
$this->section('robots', 'noindex, nofollow');
$tpp = (int) ($prefs['threads_per_page'] ?? 20);
$ppp = (int) ($prefs['posts_per_page'] ?? 20);
$sig = !empty($prefs['show_signatures']);
$av = !empty($prefs['show_avatars']);
$rx = !empty($prefs['show_reactions']);
$opt = static fn (int $v, int $cur): string => $v === $cur ? ' selected' : '';
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'reading']) ?>

        <div class="settings-pane">
    <form method="post" action="/settings/preferences" class="stacked scribe-panel">
        <h2 class="scribe-panel-head">Pagination</h2>
        <?= $this->csrfField() ?>
        <div class="field-grid">
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
        </div>
        <h3 class="account-subhead">What appears in a thread</h3>
        <div class="switch-stack switch-stack-tight">
            <label class="switchline"><input class="switch" type="checkbox" role="switch" name="show_signatures" value="1"<?= $sig ? ' checked' : '' ?>><span class="switch-text">Show signatures</span></label>
            <label class="switchline"><input class="switch" type="checkbox" role="switch" name="show_avatars" value="1"<?= $av ? ' checked' : '' ?>><span class="switch-text">Show avatars</span></label>
            <label class="switchline"><input class="switch" type="checkbox" role="switch" name="show_reactions" value="1"<?= $rx ? ' checked' : '' ?>><span class="switch-text">Show reactions</span></label>
        </div>
        <button class="btn" type="submit">Save reading preferences</button>
    </form>
        </div>
    </div>
</div>
