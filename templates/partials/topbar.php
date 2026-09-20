<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The member chrome's top bar — the design system's ForumNav
 * (docs/design-system/imladris/components/forum/ForumNav.jsx), rendered
 * server-side (ADR 0032).
 *
 * One register, identical on every app route: house lockup · surface
 * pills · search · rail toggle · New topic · the viewer. Below 381px the primary
 * routes use their own row so all labels remain readable. The class vocabulary is
 * the design's own and the layered /assets/imladris.css styles it directly;
 * app.css carries only what the layer cannot express — see its "Member chrome"
 * block. The 2026-08-27 transfer copies of this bar (.topbar*) are retired.
 *
 * What the design does not draw, kept because it is real function here: the
 * off-canvas drawer's hamburger (`.nav-toggle`), operator branding (an uploaded
 * logo, and the dynamic site name in the lockup), the two pane toggles as POST
 * forms so the rail and reading-pane state persist without JavaScript, the
 * `99+` cap on counts, a persistent notification bell (ADR 0032 adaptation),
 * the account menu behind the seat, a glyph on
 * New topic so it survives the phone breakpoint, and "Sign up" beside "Log in"
 * for a guest.
 */
$path = (string) ($request_path ?? '/');
$isBoards = $path === '/' || str_starts_with($path, '/c/') || str_starts_with($path, '/tag/');
$isInbox = $path === '/inbox' || str_starts_with($path, '/inbox/');
$isMessages = $path === '/messages' || str_starts_with($path, '/messages/');
$surfaces = is_array($member_surfaces ?? null)
    ? $member_surfaces
    : ['rail_open' => true, 'inbox_reading_open' => true];
$railOpen = !empty($surfaces['rail_open']);
$readingOpen = !empty($surfaces['inbox_reading_open']);
$moderationAccess = is_array($moderation_access ?? null) ? $moderation_access : [];
$moderationReportCount = (int) ($moderationAccess['report_count'] ?? 0);
$inboxCount = (int) ($inbox_unread_count ?? 0);
$notificationCount = $current_user !== null && !empty($features['notifications']) ? $notification_unread() : 0;
$notificationLabel = $notificationCount > 0 ? 'Notifications, ' . $notificationCount . ' unread' : 'Notifications';
?>
<header class="forum-bar">
    <?php // The phone drawer's opener. Desktop never shows it (app.css); the design has no drawer. ?>
    <button class="nav-toggle" type="button" data-nav-toggle aria-label="Open board rail" aria-expanded="false" aria-controls="sidebar-nav">
        <?= $this->partial('partials/icon', ['name' => 'menu', 'class' => 'nav-toggle-ic']) ?>
    </button>

    <a class="forum-bar-brand" href="/" aria-label="<?= $e($site_name) ?>">
        <?php if (!empty($branding['logo_path'])): ?>
            <img class="brand-logo" src="<?= $e($branding['logo_path']) ?>" alt="" height="28">
        <?php else: ?>
            <?php // The house mark, inline so it takes --accent through currentColor and flips in twilight (EightPointStar). ?>
            <svg class="forum-bar-mark" viewBox="0 0 100 100" width="24" height="24" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="3.4" stroke-linejoin="round" stroke-linecap="round"><path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"/><path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z" opacity="0.5"/><circle cx="50" cy="50" r="5" fill="currentColor" stroke="none"/></g></svg>
            <span class="forum-bar-wordmark"><?= $e($site_name) ?></span>
        <?php endif; ?>
    </a>

    <?php
    // The active pill keeps its href, where the component renders it inert: a
    // surface here spans a family of routes (Boards covers /, /c/* and /tag/*),
    // so the pill is still the way back to the surface's root (ADR 0028's
    // shared-navigation contract).
    ?>
    <nav class="forum-bar-surfaces" aria-label="Primary">
        <?php if ($isBoards): ?>
            <a data-primary-route="boards" class="forum-bar-surface is-active" href="/" aria-current="page">Boards</a>
        <?php else: ?>
            <a data-primary-route="boards" class="forum-bar-surface" href="/">Boards</a>
        <?php endif; ?>
        <?php if ($current_user !== null && !empty($features['engagement'])): ?>
            <?php $inboxPill = $inboxCount > 0
                ? '<span class="forum-bar-count" data-inbox-unread-count="' . $inboxCount . '" aria-label="' . $inboxCount . ' unread topic' . ($inboxCount === 1 ? '' : 's') . '">' . ($inboxCount > 99 ? '99+' : $inboxCount) . '</span>'
                : ''; ?>
            <?php if ($isInbox): ?>
                <a data-primary-route="inbox" class="forum-bar-surface is-active" href="/inbox" aria-current="page">Inbox<?= $inboxPill ?></a>
            <?php else: ?>
                <a data-primary-route="inbox" class="forum-bar-surface" href="/inbox">Inbox<?= $inboxPill ?></a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($current_user !== null && !empty($features['dms'])): ?>
            <?php if ($isMessages): ?>
                <a data-primary-route="messages" class="forum-bar-surface is-active" href="/messages" aria-current="page">Messages</a>
            <?php else: ?>
                <a data-primary-route="messages" class="forum-bar-surface" href="/messages">Messages</a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <?php // The Search surface carries its own query field, so the bar's entry stands down there (showSearch=false). ?>
    <?php if (!empty($features['search']) && $path !== '/search'): ?>
        <span class="forum-bar-searchwrap">
            <a class="forum-bar-search" href="/search" aria-label="Search the council — Command K">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <span class="forum-bar-search-label">Search the council…</span>
                <span class="forum-bar-kbd">⌘K</span>
            </a>
        </span>
    <?php endif; ?>

    <span class="forum-bar-right">
        <?php if ($current_user !== null): ?>
            <?php
            // The pane toggles. POST forms remain canonical (the state persists
            // without JavaScript; app.js applies it in place and saves it in the
            // background). The glyph is the design's: an outlined panel whose
            // shown band is filled — the rail on the left, the reading pane on
            // the right — and the band is drawn only while the pane is shown.
            ?>
            <form class="inline forum-bar-panel-form" method="post" action="/settings/member-surfaces" data-panel-form="rail">
                <?= $this->csrfField() ?>
                <input type="hidden" name="rail_open" value="<?= $railOpen ? '0' : '1' ?>">
                <input type="hidden" name="return" value="<?= $e($path) ?>">
                <button class="forum-bar-railtoggle<?= $railOpen ? ' is-on' : '' ?>" type="submit" aria-controls="sidebar-nav" aria-expanded="<?= $railOpen ? 'true' : 'false' ?>" aria-pressed="<?= $railOpen ? 'true' : 'false' ?>" aria-label="<?= $railOpen ? 'Hide' : 'Show' ?> the board rail" title="<?= $railOpen ? 'Hide' : 'Show' ?> the board rail (⌘B)">
                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1.6" y="2.6" width="12.8" height="10.8" rx="1.6" fill="none" stroke="currentColor" stroke-width="1.3"/><rect class="forum-bar-toggle-band" x="2.2" y="3.2" width="4" height="9.6" rx="1" fill="currentColor" opacity=".5"/></svg>
                </button>
            </form>

            <?php if ($isInbox): ?>
                <form class="inline forum-bar-panel-form" method="post" action="/settings/member-surfaces" data-panel-form="reading">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="inbox_reading_open" value="<?= $readingOpen ? '0' : '1' ?>">
                    <input type="hidden" name="return" value="<?= $e($path) ?>">
                    <button class="forum-bar-railtoggle<?= $readingOpen ? ' is-on' : '' ?>" type="submit" aria-controls="inbox-reading-pane" aria-expanded="<?= $readingOpen ? 'true' : 'false' ?>" aria-pressed="<?= $readingOpen ? 'true' : 'false' ?>" aria-label="<?= $readingOpen ? 'Hide' : 'Show' ?> the reading pane" title="<?= $readingOpen ? 'Hide' : 'Show' ?> the reading pane (⌘J)">
                        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1.6" y="2.6" width="12.8" height="10.8" rx="1.6" fill="none" stroke="currentColor" stroke-width="1.3"/><rect class="forum-bar-toggle-band" x="9.8" y="3.2" width="4" height="9.6" rx="1" fill="currentColor" opacity=".5"/></svg>
                    </button>
                </form>
            <?php endif; ?>

            <span class="forum-bar-divider" aria-hidden="true"></span>

            <?php if ($path !== '/compose'): ?>
                <span class="forum-bar-compose">
                    <a class="btn btn-small" href="/compose" aria-label="New topic"><?= $this->partial('partials/icon', ['name' => 'plus']) ?><span>New topic</span></a>
                </span>
            <?php endif; ?>

            <?php
            // The seat. The design's is a link to the profile; ours opens the
            // account menu, so it is a <details> summary wearing the same anatomy,
            // and the chevron is the menu's cue.
            ?>
            <?php if (!empty($features['notifications'])): ?>
                <a class="forum-bar-bell bell" href="/notifications" data-bell data-notification-link aria-label="<?= $e($notificationLabel) ?>" title="Notifications">
                    <?= $this->partial('partials/icon', ['name' => 'bell']) ?>
                    <span class="bell-count" data-notification-count aria-hidden="true"<?= $notificationCount === 0 ? ' hidden' : '' ?>><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span>
                </a>
            <?php endif; ?>
            <details class="identity-menu">
                <summary class="forum-bar-user" aria-label="Open account menu for <?= $e($current_user->displayName()) ?>">
                    <span class="avatar-wrap">
                        <?= $this->partial('partials/monogram', ['name' => $current_user->displayName(), 'username' => $current_user->username()]) ?>
                        <?php
                        // The viewer's own leaf, drawn for 'online' | 'away' only: a
                        // member who switched presence off must not see one beside
                        // their own name, and it goes out with the flag (ADR 0031).
                        // Decorative — it is the viewer's own avatar, and the account
                        // menu states presence in words.
                        $selfState = is_callable($presence_snapshot ?? null)
                            ? (string) (($presence_snapshot)()['self_state'] ?? 'offline')
                            : 'offline';
                        ?>
                        <?php if ($selfState === 'online' || $selfState === 'away'): ?>
                            <span class="presence-dot<?= $selfState === 'away' ? ' is-away' : '' ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                    </span>
                    <span class="forum-bar-username"><?= $e($current_user->displayName()) ?></span>
                    <?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?>
                </summary>
                <div class="identity-menu-panel">
                    <a href="/u/<?= $e($current_user->username()) ?>"><?= $this->partial('partials/icon', ['name' => 'user']) ?><span>Profile</span></a>
                    <?php if (!empty($features['notifications'])): ?>
                        <a href="/notifications" data-notification-link aria-label="<?= $e($notificationLabel) ?>"><?= $this->partial('partials/icon', ['name' => 'bell']) ?><span>Notifications</span><span class="notification-count" data-notification-count aria-hidden="true"<?= $notificationCount === 0 ? ' hidden' : '' ?>><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span></a>
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
            <a class="forum-bar-signup" href="/register">Sign up</a>
            <a class="forum-bar-signin" href="/login">Log in</a>
        <?php endif; ?>
    </span>
</header>
