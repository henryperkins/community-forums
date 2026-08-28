<?php /** @var \\App\\Core\\View $this */ ?>
<?php
$path = (string) ($request_path ?? '/');
$isBoards = $path === '/' || str_starts_with($path, '/c/') || str_starts_with($path, '/tag/');
$isInbox = $path === '/inbox' || str_starts_with($path, '/inbox/');
$isMessages = $path === '/messages' || str_starts_with($path, '/messages/');
$surfaces = is_array($member_surfaces ?? null)
    ? $member_surfaces
    : ['rail_open' => true, 'inbox_reading_open' => true];
$moderationAccess = is_array($moderation_access ?? null) ? $moderation_access : [];
$moderationReportCount = (int) ($moderationAccess['report_count'] ?? 0);
$inboxCount = (int) ($inbox_unread_count ?? 0);
?>
<header class="topbar">
    <div class="topbar-inner">
        <button class="nav-toggle" type="button" data-nav-toggle aria-label="Open board rail" aria-expanded="false" aria-controls="sidebar-nav">
            <?= $this->partial('partials/icon', ['name' => 'menu', 'class' => 'nav-toggle-ic']) ?>
        </button>

        <a class="brand" href="/" aria-label="<?= $e($site_name) ?>">
            <?php if (!empty($branding['logo_path'])): ?>
                <img class="brand-logo" src="<?= $e($branding['logo_path']) ?>" alt="" height="28">
            <?php else: ?>
                <img class="brand-star" src="/assets/elven-star.svg" alt="" width="28" height="28">
                <span class="brand-name"><?= $e($site_name) ?></span>
            <?php endif; ?>
        </a>

        <nav class="topbar-primary" aria-label="Primary">
            <a data-primary-route="boards" class="topbar-primary-link<?= $isBoards ? ' is-active' : '' ?>" href="/"<?= $isBoards ? ' aria-current="page"' : '' ?>>Boards</a>
            <?php if ($current_user !== null && !empty($features['engagement'])): ?>
                <a data-primary-route="inbox" class="topbar-primary-link<?= $isInbox ? ' is-active' : '' ?>" href="/inbox"<?= $isInbox ? ' aria-current="page"' : '' ?>>
                    <span>Inbox</span>
                    <?php if ($inboxCount > 0): ?><span class="topbar-count" data-inbox-unread-count="<?= $inboxCount ?>" aria-label="<?= $inboxCount ?> unread topic<?= $inboxCount === 1 ? '' : 's' ?>"><?= $inboxCount > 99 ? '99+' : $inboxCount ?></span><?php endif; ?>
                </a>
            <?php endif; ?>
            <?php if ($current_user !== null && !empty($features['dms'])): ?>
                <a data-primary-route="messages" class="topbar-primary-link<?= $isMessages ? ' is-active' : '' ?>" href="/messages"<?= $isMessages ? ' aria-current="page"' : '' ?>>Messages</a>
            <?php endif; ?>
        </nav>

        <div class="topbar-right">
            <?php if (!empty($features['search']) && $path !== '/search'): ?>
                <a class="topbar-action topbar-search-entry" href="/search" aria-label="Search">
                    <?= $this->partial('partials/icon', ['name' => 'search']) ?>
                    <span class="topbar-search-copy">Search the council…</span>
                    <kbd>⌘K</kbd>
                </a>
            <?php endif; ?>

            <?php if ($current_user !== null): ?>
                <form class="inline topbar-panel-form" method="post" action="/settings/member-surfaces" data-panel-form="rail">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="rail_open" value="<?= !empty($surfaces['rail_open']) ? '0' : '1' ?>">
                    <input type="hidden" name="return" value="<?= $e($path) ?>">
                    <button class="topbar-action" type="submit" aria-controls="sidebar-nav" aria-expanded="<?= !empty($surfaces['rail_open']) ? 'true' : 'false' ?>" aria-pressed="<?= !empty($surfaces['rail_open']) ? 'true' : 'false' ?>" aria-label="<?= !empty($surfaces['rail_open']) ? 'Hide' : 'Show' ?> board rail" title="<?= !empty($surfaces['rail_open']) ? 'Hide' : 'Show' ?> the board rail (⌘B)">
                        <?= $this->partial('partials/icon', ['name' => 'panel-left']) ?><span>Boards</span>
                    </button>
                </form>

                <?php if ($isInbox): ?>
                    <form class="inline topbar-panel-form" method="post" action="/settings/member-surfaces" data-panel-form="reading">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="inbox_reading_open" value="<?= !empty($surfaces['inbox_reading_open']) ? '0' : '1' ?>">
                        <input type="hidden" name="return" value="<?= $e($path) ?>">
                        <button class="topbar-action" type="submit" aria-controls="inbox-reading-pane" aria-expanded="<?= !empty($surfaces['inbox_reading_open']) ? 'true' : 'false' ?>" aria-pressed="<?= !empty($surfaces['inbox_reading_open']) ? 'true' : 'false' ?>" aria-label="<?= !empty($surfaces['inbox_reading_open']) ? 'Hide' : 'Show' ?> reading pane" title="<?= !empty($surfaces['inbox_reading_open']) ? 'Hide' : 'Show' ?> the reading pane (⌘J)">
                            <?= $this->partial('partials/icon', ['name' => 'panel-right']) ?><span>Reading</span>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($path !== '/compose'): ?>
                    <a class="btn btn-small topbar-new-topic" href="/compose">
                        <?= $this->partial('partials/icon', ['name' => 'plus']) ?><span>New topic</span>
                    </a>
                <?php endif; ?>

                <details class="identity-menu">
                    <summary class="topbar-user" aria-label="Open account menu">
                        <span class="topbar-avatar">
                            <?= $this->partial('partials/monogram', ['name' => $current_user->displayName(), 'username' => $current_user->username()]) ?>
                            <span class="presence-dot" aria-hidden="true"></span>
                        </span>
                        <span class="topbar-name"><?= $e($current_user->displayName()) ?></span>
                        <?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?>
                    </summary>
                    <div class="identity-menu-panel">
                        <a href="/u/<?= $e($current_user->username()) ?>"><?= $this->partial('partials/icon', ['name' => 'user']) ?><span>Profile</span></a>
                        <?php if (!empty($features['notifications'])): ?>
                            <a href="/notifications" data-bell><?= $this->partial('partials/icon', ['name' => 'bell']) ?><span>Notifications</span><span class="bell-count" data-bell-count hidden>0</span></a>
                        <?php endif; ?>
                        <?php if (!empty($features['drafts'])): ?><a href="/drafts"><?= $this->partial('partials/icon', ['name' => 'file']) ?><span>Drafts</span></a><?php endif; ?>
                        <?php if (!empty($features['community'])): ?>
                            <a href="/feed"><?= $this->partial('partials/icon', ['name' => 'users']) ?><span>Following</span></a>
                            <a href="/leaderboard"><?= $this->partial('partials/icon', ['name' => 'commend-star']) ?><span>Top contributors</span></a>
                        <?php endif; ?>
                        <a href="/settings/account"><?= $this->partial('partials/icon', ['name' => 'settings-profile']) ?><span>Settings</span></a>
                        <?php if ($current_user->isAdmin()): ?>
                            <a href="/admin"><?= $this->partial('partials/icon', ['name' => 'shield']) ?><span>Administration</span></a>
                        <?php elseif (!empty($moderationAccess['can_reports'])): ?>
                            <a href="/mod/reports"><?= $this->partial('partials/icon', ['name' => 'shield']) ?><span>Moderation</span><?php if ($moderationReportCount > 0): ?><span class="mod-count"><?= $moderationReportCount ?></span><?php endif; ?></a>
                        <?php endif; ?>
                        <form method="post" action="/logout">
                            <?= $this->csrfField() ?>
                            <button type="submit"><?= $this->partial('partials/icon', ['name' => 'log-out']) ?><span>Log out</span></button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <a class="topbar-link" href="/login">Log in</a>
                <a class="btn btn-small" href="/register">Sign up</a>
            <?php endif; ?>
        </div>
    </div>
</header>
