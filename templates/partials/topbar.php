<?php /** @var \App\Core\View $this */ ?>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/"><?php if (!empty($branding['logo_path'])): ?><img class="brand-logo" src="<?= $e($branding['logo_path']) ?>" alt="<?= $e($site_name) ?>" height="28"><?php else: ?><?= $e($site_name) ?><?php endif; ?></a>
        <?php if (!empty($features['search'])): ?>
            <form class="topbar-search" method="get" action="/search" role="search">
                <input class="input input-small" type="search" name="q" placeholder="Search…" aria-label="Search">
            </form>
        <?php endif; ?>
        <div class="topbar-right">
            <?php if ($current_user !== null): ?>
                <?php if (!empty($features['engagement'])): ?>
                    <a class="topbar-link" href="/inbox">Inbox</a>
                <?php endif; ?>
                <?php if (!empty($features['dms'])): ?>
                    <a class="topbar-link" href="/messages">Messages</a>
                <?php endif; ?>
                <?php if (!empty($features['drafts'])): ?>
                    <a class="topbar-link" href="/drafts">Drafts</a>
                <?php endif; ?>
                <?php if (!empty($features['community'])): ?>
                    <a class="topbar-link" href="/feed">Following</a>
                    <a class="topbar-link" href="/leaderboard">Top</a>
                <?php endif; ?>
                <?php if (!empty($features['notifications'])): ?>
                    <a class="topbar-link bell" href="/notifications" data-bell title="Notifications">
                        <span aria-hidden="true">🔔</span>
                        <span class="bell-count" data-bell-count hidden>0</span>
                        <span class="sr-only">Notifications</span>
                    </a>
                <?php endif; ?>
                <a class="topbar-user" href="/u/<?= $e($current_user->username()) ?>">
                    <?= $this->partial('partials/monogram', ['name' => $current_user->displayName(), 'username' => $current_user->username()]) ?>
                    <span class="topbar-name"><?= $e($current_user->displayName()) ?></span>
                </a>
                <?php if ($current_user->isAdmin()): ?>
                    <a class="topbar-link" href="/admin">Admin</a>
                <?php endif; ?>
                <a class="topbar-link" href="/settings/account">Settings</a>
                <form class="inline" method="post" action="/logout">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit">Log out</button>
                </form>
            <?php else: ?>
                <span class="pill">Guest</span>
                <a class="topbar-link" href="/login">Log in</a>
                <a class="btn btn-small" href="/register">Sign up</a>
            <?php endif; ?>
        </div>
    </div>
</header>
