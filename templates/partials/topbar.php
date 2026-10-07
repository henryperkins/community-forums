<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The member chrome's top bar — the design system's ForumNav
 * (docs/design-system/imladris/components/forum/ForumNav.jsx), rendered
 * server-side (ADR 0032).
 *
 * One register, identical on every app route: house lockup · surface
 * pills · search · rail toggle · New topic · the viewer. Below 861px the primary
 * routes use their own row so all labels remain readable; app.js moves that row
 * last in the reading order to match, and a lone route is not drawn there because
 * the lockup leads to the same place (ADR 0042, 2026-10-07). The class vocabulary is
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
 * for a guest. New topic also opens the composer on the board being read
 * (`compose_board`, from the layout) rather than on the first board listed.
 */
$path = (string) ($request_path ?? '/');
$hasBoardRail = !empty($has_board_rail);
$hasReadingPane = !empty($has_reading_pane);
$composeBoard = (string) ($compose_board ?? '');
$composeHref = '/compose' . ($composeBoard !== '' ? '?board=' . rawurlencode($composeBoard) : '');
$isBoards = $hasBoardRail && ($path === '/' || str_starts_with($path, '/c/')
    || $path === '/tags' || str_starts_with($path, '/tags/')
    || (int) ($active_thread_board_id ?? 0) > 0);
$isInbox = $hasBoardRail && ($path === '/inbox' || str_starts_with($path, '/inbox/'));
$isMessages = $hasBoardRail && ($path === '/messages' || str_starts_with($path, '/messages/'));
// The bell is the Notifications surface's entry, so it is current there.
$isNotifications = $path === '/notifications' || str_starts_with($path, '/notifications/');
$surfaces = is_array($member_surfaces ?? null)
    ? $member_surfaces
    : ['rail_open' => true, 'inbox_reading_open' => true];
$railOpen = !empty($surfaces['rail_open']);
$readingOpen = !empty($surfaces['inbox_reading_open']);
$moderationAccess = is_array($moderation_access ?? null) ? $moderation_access : [];
$moderationReportCount = (int) ($moderationAccess['report_count'] ?? 0);
$inboxCount = (int) ($inbox_unread_count ?? 0);
$notificationCount = $current_user !== null && !empty($features['notifications']) ? $notification_unread() : 0;
$dmCount = isset($dm_unread) && is_callable($dm_unread) ? $dm_unread() : 0;
$notificationLabel = $notificationCount > 0 ? 'Notifications, ' . $notificationCount . ' unread' : 'Notifications';
?>
<header class="forum-bar">
    <?php // The phone drawer's opener. Desktop never shows it (app.css); the design has no drawer. ?>
    <?php if ($hasBoardRail): ?>
    <a class="nav-toggle" data-nav-fallback href="#sidebar-nav" aria-label="Open board rail">
        <?= $this->partial('partials/icon', ['name' => 'menu', 'class' => 'nav-toggle-ic']) ?>
    </a>
    <button class="nav-toggle" type="button" data-nav-toggle hidden aria-label="Open board rail" aria-expanded="false" aria-controls="sidebar-nav">
        <?= $this->partial('partials/icon', ['name' => 'menu', 'class' => 'nav-toggle-ic']) ?>
    </button>
    <?php endif; ?>

    <?php
    // The lockup's wrapper. On a phone it takes the row's spare room and is
    // the size container that decides whether the name fits, so the link
    // inside draws only the mark and the name it can show; a blank stretch is
    // never part of the link. Above the drawer breakpoint it is display:
    // contents and the link is the bar's own flex item.
    ?>
    <span class="forum-bar-lockup">
        <a class="forum-bar-brand" href="/" aria-label="<?= $e($site_name) ?>">
            <?php if (!empty($branding['logo_path'])): ?>
                <img class="brand-logo" src="<?= $e($branding['logo_path']) ?>" alt="" height="28">
            <?php else: ?>
                <?php // The house mark, inline so it takes --accent through currentColor and flips in twilight (EightPointStar). ?>
                <svg class="forum-bar-mark" viewBox="0 0 100 100" width="24" height="24" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="3.4" stroke-linejoin="round" stroke-linecap="round"><path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"/><path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z" opacity="0.5"/><circle cx="50" cy="50" r="5" fill="currentColor" stroke="none"/></g></svg>
                <span class="forum-bar-wordmark"><?= $e($site_name) ?></span>
            <?php endif; ?>
        </a>
    </span>

    <?php
    // The active pill keeps its href, where the component renders it inert: a
    // surface here spans a family of routes (Boards covers /, /c/*, /tags/* and
    // authorized canonical topics),
    // so the pill is still the way back to the surface's root (ADR 0028's
    // shared-navigation contract).
    ?>
    <nav class="forum-bar-surfaces" aria-label="Primary">
        <?php if ($isBoards): ?>
            <a data-primary-route="boards" class="forum-bar-surface is-active" href="/" aria-current="page">Boards</a>
        <?php else: ?>
            <a data-primary-route="boards" class="forum-bar-surface" href="/">Boards</a>
        <?php endif; ?>
        <?php
        // A count's words live on its link, as the bell's do: the link reads
        // "Inbox, 4 unread topics" and the digits are aria-hidden. A role-less
        // span may not carry a name of its own, and assistive technology
        // treated the old aria-label on one inconsistently. app.js keeps the
        // link's name in step (data-count-name) when the count changes.
        ?>
        <?php if ($current_user !== null && !empty($features['engagement'])): ?>
            <?php $inboxPill = $inboxCount > 0
                ? '<span class="forum-bar-count" data-inbox-unread-count="' . $inboxCount . '" aria-hidden="true">' . ($inboxCount > 99 ? '99+' : $inboxCount) . '</span>'
                : ''; ?>
            <?php $inboxName = $inboxCount > 0 ? ' aria-label="Inbox, ' . $inboxCount . ' unread topic' . ($inboxCount === 1 ? '' : 's') . '"' : ''; ?>
            <?php if ($isInbox): ?>
                <a data-primary-route="inbox" class="forum-bar-surface is-active" href="/inbox" aria-current="page" data-count-name="Inbox"<?= $inboxName ?>>Inbox<?= $inboxPill ?></a>
            <?php else: ?>
                <a data-primary-route="inbox" class="forum-bar-surface" href="/inbox" data-count-name="Inbox"<?= $inboxName ?>>Inbox<?= $inboxPill ?></a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($current_user !== null && !empty($features['dms'])): ?>
            <?php $dmName = $dmCount > 0 ? ' aria-label="Messages, ' . $dmCount . ' unread conversation' . ($dmCount === 1 ? '' : 's') . '"' : ''; ?>
            <?php if ($isMessages): ?>
                <a data-primary-route="messages" class="forum-bar-surface is-active" href="/messages" aria-current="page" data-count-name="Messages"<?= $dmName ?>>Messages<span class="forum-bar-count" data-dm-unread-count aria-hidden="true"<?= $dmCount === 0 ? ' hidden' : '' ?>><?= $dmCount > 99 ? '99+' : $dmCount ?></span></a>
            <?php else: ?>
                <a data-primary-route="messages" class="forum-bar-surface" href="/messages" data-count-name="Messages"<?= $dmName ?>>Messages<span class="forum-bar-count" data-dm-unread-count aria-hidden="true"<?= $dmCount === 0 ? ' hidden' : '' ?>><?= $dmCount > 99 ? '99+' : $dmCount ?></span></a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <?php // The Search surface carries its own query field, so the bar's entry stands down there (showSearch=false). ?>
    <?php
    // Keyboard shortcuts exist only where app.js answers them, so the server
    // claims none: data-shortcut names the key, and app.js writes the hint with
    // the platform's own modifier (⌘ on Apple devices, Ctrl elsewhere; both
    // work) and adds aria-keyshortcuts.
    ?>
    <?php if (!empty($features['search']) && $path !== '/search'): ?>
        <span class="forum-bar-searchwrap">
            <a class="forum-bar-search" href="/search" aria-label="Search the council" data-shortcut="k">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <span class="forum-bar-search-label">Search the council…</span>
                <span class="forum-bar-kbd" data-shortcut-hint aria-hidden="true" hidden></span>
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
            // Each is one toggle button: a fixed name and aria-pressed, as the
            // star toggle is, so the state is announced once rather than three
            // times (a Hide/Show name, aria-pressed and aria-expanded).
            ?>
            <?php if ($hasBoardRail): ?>
            <form class="inline forum-bar-panel-form" method="post" action="/settings/member-surfaces" data-panel-form="rail">
                <?= $this->csrfField() ?>
                <input type="hidden" name="rail_open" value="<?= $railOpen ? '0' : '1' ?>">
                <input type="hidden" name="return" value="<?= $e($path) ?>">
                <button class="forum-bar-railtoggle<?= $railOpen ? ' is-on' : '' ?>" type="submit" aria-controls="sidebar-nav" aria-pressed="<?= $railOpen ? 'true' : 'false' ?>" aria-label="Board rail" title="Board rail" data-shortcut="b">
                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1.6" y="2.6" width="12.8" height="10.8" rx="1.6" fill="none" stroke="currentColor" stroke-width="1.3"/><rect class="forum-bar-toggle-band" x="2.2" y="3.2" width="4" height="9.6" rx="1" fill="currentColor" opacity=".5"/></svg>
                </button>
            </form>
            <?php endif; ?>

            <?php if ($hasReadingPane): ?>
                <form class="inline forum-bar-panel-form" method="post" action="/settings/member-surfaces" data-panel-form="reading">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="inbox_reading_open" value="<?= $readingOpen ? '0' : '1' ?>">
                    <input type="hidden" name="return" value="<?= $e($path) ?>">
                    <button class="forum-bar-railtoggle<?= $readingOpen ? ' is-on' : '' ?>" type="submit" aria-controls="inbox-reading-pane" aria-pressed="<?= $readingOpen ? 'true' : 'false' ?>" aria-label="Reading pane" title="Reading pane" data-shortcut="j">
                        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1.6" y="2.6" width="12.8" height="10.8" rx="1.6" fill="none" stroke="currentColor" stroke-width="1.3"/><rect class="forum-bar-toggle-band" x="9.8" y="3.2" width="4" height="9.6" rx="1" fill="currentColor" opacity=".5"/></svg>
                    </button>
                </form>
            <?php endif; ?>

            <?php // The divider sets the pane toggles apart from the actions, so it is drawn only beside one. ?>
            <?php if ($hasBoardRail): ?>
                <span class="forum-bar-divider" aria-hidden="true"></span>
            <?php endif; ?>

            <?php if ($path !== '/compose'): ?>
                <span class="forum-bar-compose">
                    <a class="btn btn-small" href="<?= $e($composeHref) ?>" aria-label="New topic"><?= $this->partial('partials/icon', ['name' => 'plus']) ?><span>New topic</span></a>
                </span>
            <?php endif; ?>

            <?php
            // The seat. The design's is a link to the profile; ours opens the
            // account menu, so it is a <details> summary wearing the same anatomy,
            // and the chevron is the menu's cue.
            ?>
            <?php if (!empty($features['notifications'])): ?>
                <a class="forum-bar-bell bell<?= $isNotifications ? ' is-active' : '' ?>" href="/notifications" data-bell data-notification-link<?= $isNotifications ? ' aria-current="page"' : '' ?> aria-label="<?= $e($notificationLabel) ?>" title="Notifications">
                    <?= $this->partial('partials/icon', ['name' => 'bell']) ?>
                    <span class="bell-count" data-notification-count aria-hidden="true"<?= $notificationCount === 0 ? ' hidden' : '' ?>><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span>
                </a>
            <?php endif; ?>
            <details class="identity-menu">
                <summary class="forum-bar-user" aria-label="Account menu for <?= $e($current_user->displayName()) ?>">
                    <span class="avatar-wrap">
                        <?= $this->partial('partials/monogram', ['name' => $current_user->displayName(), 'username' => $current_user->username(), 'avatar_path' => $current_user->avatarPath()]) ?>
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
                <?php
                // Three groups: your places; your settings and authority; leaving.
                // .identity-menu-break opens each later group with a hairline.
                ?>
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
                    <a class="identity-menu-break" href="/settings/account"><?= $this->partial('partials/icon', ['name' => 'settings']) ?><span>Settings</span></a>
                    <?php if ($current_user->isAdmin()): ?>
                        <a href="/admin"><?= $this->partial('partials/icon', ['name' => 'shield']) ?><span>Administration</span></a>
                    <?php elseif (!empty($moderationAccess['can_reports'])): ?>
                        <a href="/mod/reports"<?= $moderationReportCount > 0 ? ' aria-label="Moderation, ' . $moderationReportCount . ' open report' . ($moderationReportCount === 1 ? '' : 's') . '"' : '' ?>><?= $this->partial('partials/icon', ['name' => 'shield']) ?><span>Moderation</span><?php if ($moderationReportCount > 0): ?><span class="mod-count" aria-hidden="true"><?= $moderationReportCount ?></span><?php endif; ?></a>
                    <?php endif; ?>
                    <form class="identity-menu-break" method="post" action="/logout">
                        <?= $this->csrfField() ?>
                        <button type="submit"><?= $this->partial('partials/icon', ['name' => 'log-out']) ?><span>Log out</span></button>
                    </form>
                </div>
            </details>
        <?php else: ?>
            <a class="forum-bar-signup" href="/register">Sign up</a>
            <?php /* A guest who signs in from here comes back to the page they were reading (App::loginReturnPath). */ ?>
            <a class="forum-bar-signin" href="/login<?= !empty($login_return) ? '?next=' . $e(rawurlencode((string) $login_return)) : '' ?>">Log in</a>
        <?php endif; ?>
    </span>
</header>
