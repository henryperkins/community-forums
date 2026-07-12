<?php /** @var \App\Core\View $this */ ?>
<?php
$moderationAccess = is_array($moderation_access ?? null) ? $moderation_access : [];
$moderationReportCount = (int) ($moderationAccess['report_count'] ?? 0);
?>
<header class="topbar">
    <div class="topbar-inner">
        <button class="nav-toggle" type="button" data-nav-toggle aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar-nav">
            <svg class="nav-toggle-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <a class="brand" href="/"><?php if (!empty($branding['logo_path'])): ?><img class="brand-logo" src="<?= $e($branding['logo_path']) ?>" alt="<?= $e($site_name) ?>" height="28"><?php else: ?><svg class="brand-star" viewBox="0 0 100 100" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="3.4" stroke-linejoin="round" stroke-linecap="round"><path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"/><path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z" opacity="0.5"/><circle cx="50" cy="50" r="5" fill="currentColor" stroke="none"/></g></svg><span class="brand-name"><?= $e($site_name) ?></span><?php endif; ?></a>
        <?php if (!empty($features['search'])): ?>
            <form class="topbar-search" method="get" action="/search" role="search">
                <input class="input input-small" type="search" name="q" placeholder="Search…" aria-label="Search">
            </form>
        <?php endif; ?>
        <div class="topbar-right">
            <?php if ($current_user !== null): ?>
                <?php /* Primary nav (Inbox/Messages/Drafts/Following/Top) lives in the
                          sidebar rail in the Imladris layout; the topbar keeps search,
                          the bell, and identity. */ ?>
                <?php if (!empty($features['notifications'])): ?>
                    <a class="topbar-link bell" href="/notifications" data-bell title="Notifications">
                        <svg class="bell-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        <span class="bell-count" data-bell-count hidden>0</span>
                        <span class="sr-only">Notifications</span>
                    </a>
                <?php endif; ?>
                <a class="topbar-user" href="/u/<?= $e($current_user->username()) ?>" aria-label="<?= $e($current_user->displayName()) ?>">
                    <span class="topbar-avatar">
                        <?= $this->partial('partials/monogram', ['name' => $current_user->displayName(), 'username' => $current_user->username()]) ?>
                        <span class="presence-dot" aria-hidden="true"></span>
                    </span>
                    <span class="topbar-name"><?= $e($current_user->displayName()) ?></span>
                </a>
                <?php if ($current_user->isAdmin()): ?>
                    <a class="topbar-link" href="/admin" aria-label="Admin"><span>Admin</span></a>
                <?php endif; ?>
                <?php if (!$current_user->isAdmin() && !empty($moderationAccess['can_reports'])): ?>
                    <a class="topbar-link topbar-moderation" href="/mod/reports" aria-label="Moderation, <?= $moderationReportCount ?> open report<?= $moderationReportCount === 1 ? '' : 's' ?>"><span class="topbar-action-label">Moderation</span> <span class="mod-count"><?= $moderationReportCount ?></span></a>
                <?php endif; ?>
                <a class="topbar-link" href="/settings/account" title="Settings">
                    <svg class="topbar-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span class="sr-only">Settings</span>
                </a>
                <form class="inline topbar-logout" method="post" action="/logout">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit" aria-label="Log out">
                        <?= $this->partial('partials/icon', ['name' => 'log-out']) ?>
                        <span class="topbar-action-label">Log out</span>
                    </button>
                </form>
            <?php else: ?>
                <span class="pill">Guest</span>
                <a class="topbar-link" href="/login">Log in</a>
                <a class="btn btn-small" href="/register">Sign up</a>
            <?php endif; ?>
        </div>
    </div>
</header>
