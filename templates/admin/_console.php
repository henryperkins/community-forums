<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The operator console chrome (ADR 0024, ADMIN.md §9.2).
 *
 * One partial emits the whole composition — identity row, area tier, page
 * heading, section tabs — because the design treats them as a single unit whose
 * three ranks read against each other. Splitting them would let a leaf template
 * render a heading with no tabs.
 *
 * Call it with the area and the *parent* tab; a drill-in keeps its parent lit,
 * exactly as the design does (a role record keeps Roles active, an install plan
 * keeps Packages active). Both keys are explicit and never derived from the
 * request path — /admin/boards/12/edit, /admin/users/7 and
 * /admin/packages/3/consent match no tab href.
 *
 *     <?= $this->partial('admin/_console', ['area' => 'content', 'tab' => 'structure']) ?>
 *         … pane content …
 *     <?= $this->partial('admin/_console_end') ?>
 *
 * The heading belongs to the area, not the leaf: /admin/structure and
 * /admin/tags are both "Boards & tags". No leaf sets its own <h1>.
 */
$area = (string) ($area ?? '');
$tab = (string) ($tab ?? '');
/*
 * Optional per-tab queue counts, e.g. ['reports' => 4, 'approvals' => 2]. Only
 * the Moderation area passes them today: the `mod-count` badge on the Reports
 * tab is ADR 0024 `feature-added` (production has it, the design never modelled
 * it), so it is kept and styled rather than dropped in the chrome swap. Absent
 * or zero renders nothing, so every other area is unchanged.
 */
$counts = is_array($counts ?? null) ? $counts : [];
$urgentTabs = is_array($urgent_tabs ?? null) ? $urgent_tabs : [];
$tabCount = function (string $key) use ($counts, $urgentTabs, $e): string {
    $n = (int) ($counts[$key] ?? 0);
    if ($n <= 0) {
        return '';
    }
    $class = 'mod-count' . (!empty($urgentTabs[$key]) ? ' is-urgent' : '');
    return ' <span class="' . $class . '">' . $e((string) $n) . '</span>';
};
// A leaf may scope its own pane styles (the old <div class="admin x"> wrapper
// carried this); everything else styles off .admin-console[data-area].
$paneClass = trim((string) ($pane_class ?? ''));
$deferFlash = !empty($defer_flash);
$features = is_array($features ?? null) ? $features : (array) $this->shared('features', []);
$brandName = (string) ($branding['name'] ?? $site_name ?? 'Admin');
$viewer = $current_user ?? null;

/**
 * area => [label, h1, aria, tabs => [tab => [label, href, flag|flags_any]]]
 * Tier order is the design's ADMIN_AREAS with Moderation inserted at index 1
 * (ADR 0024 decision 2): it preserves the design's relative order and keeps
 * ADMIN.md's "Moderation second".
 */
$areas = [
    'overview' => ['label' => 'Overview', 'h1' => 'Admin console', 'aria' => 'Admin sections', 'tabs' => [
        'dashboard' => ['label' => 'Dashboard', 'href' => '/admin'],
        'audit' => ['label' => 'Audit log', 'href' => '/admin/audit'],
    ]],
    'moderation' => ['label' => 'Moderation', 'h1' => 'Queues & anti-abuse', 'aria' => 'Moderation sections', 'tabs' => [
        'reports' => ['label' => 'Reports', 'href' => '/mod/reports', 'flag' => 'moderation_queue'],
        'approvals' => ['label' => 'Approvals', 'href' => '/mod/approvals', 'flag' => 'moderation_queue'],
        'appeals' => ['label' => 'Appeals', 'href' => '/mod/appeals', 'flag' => 'appeals'],
        // admin_only: the Moderation area is the one area a board moderator can
        // open, but this tab lands on /admin/moderation, which is behind
        // requireAdmin(). Rendering it to a moderator is the show-and-deny the
        // tier filter below exists to prevent.
        'antiabuse' => ['label' => 'Anti-abuse', 'href' => '/admin/moderation', 'flag' => 'anti_abuse', 'admin_only' => true],
    ]],
    'content' => ['label' => 'Content', 'h1' => 'Boards & tags', 'aria' => 'Content sections', 'tabs' => [
        'structure' => ['label' => 'Boards & categories', 'href' => '/admin/structure'],
        'tags' => ['label' => 'Tags', 'href' => '/admin/tags', 'flag' => 'tags'],
    ]],
    'people' => ['label' => 'People', 'h1' => 'Roles & capabilities', 'aria' => 'People sections', 'tabs' => [
        'roles' => ['label' => 'Roles', 'href' => '/admin/roles', 'flag' => 'capabilities'],
        'simulator' => ['label' => 'Permission simulator', 'href' => '/admin/roles/simulator', 'flag' => 'capabilities'],
    ]],
    'members' => ['label' => 'Members', 'h1' => 'Members & invitations', 'aria' => 'Member sections', 'tabs' => [
        'users' => ['label' => 'Directory', 'href' => '/admin/users'],
        'invitations' => ['label' => 'Invitations', 'href' => '/admin/invitations', 'flag' => 'invitations'],
    ]],
    'appearance' => ['label' => 'Appearance', 'h1' => 'Branding & themes', 'aria' => 'Appearance sections', 'tabs' => [
        'branding' => ['label' => 'Branding', 'href' => '/admin/branding', 'flag' => 'branding'],
        'themes' => ['label' => 'Themes', 'href' => '/admin/themes', 'flag' => 'package_themes'],
    ]],
    'notifications' => ['label' => 'Notifications', 'h1' => 'Email & announcements', 'aria' => 'Notification sections', 'tabs' => [
        'email' => ['label' => 'Email', 'href' => '/admin/email', 'flag' => 'email'],
        'announcements' => ['label' => 'Announcements', 'href' => '/admin/announcements', 'flag' => 'announcements'],
    ]],
    'integrations' => ['label' => 'Integrations', 'h1' => 'Tokens, webhooks & sign-in', 'aria' => 'Integration sections', 'tabs' => [
        'api_tokens' => ['label' => 'API tokens', 'href' => '/admin/api-tokens', 'flag' => 'api_tokens'],
        'webhooks' => ['label' => 'Webhooks', 'href' => '/admin/webhooks', 'flag' => 'webhooks'],
        'providers' => ['label' => 'Sign-in providers', 'href' => '/admin/providers', 'flag' => 'provider_registry'],
    ]],
    'packages' => ['label' => 'Packages', 'h1' => 'Packages & registries', 'aria' => 'Supply chain sections', 'tabs' => [
        'packages' => ['label' => 'Packages', 'href' => '/admin/packages', 'flag' => 'package_registry'],
        'registries' => ['label' => 'Registry trust', 'href' => '/admin/registries', 'flag' => 'package_registry'],
        'extensions' => ['label' => 'Extensions', 'href' => '/admin/extensions', 'flag' => 'server_extensions'],
    ]],
    'features' => ['label' => 'Features', 'h1' => 'Features & badges', 'aria' => 'Capability sections', 'tabs' => [
        'features' => ['label' => 'Feature flags', 'href' => '/admin/features'],
        'badge_rules' => ['label' => 'Badge rules', 'href' => '/admin/badge-rules', 'flag' => 'badge_rules'],
        'custom_emoji' => ['label' => 'Custom emoji', 'href' => '/admin/custom-emoji', 'flag' => 'custom_emoji'],
        'link_previews' => ['label' => 'Link previews', 'href' => '/admin/link-previews', 'flag' => 'link_previews'],
    ]],
    'settings' => ['label' => 'Settings', 'h1' => 'General & intelligence', 'aria' => 'Settings sections', 'tabs' => [
        'settings' => ['label' => 'General & registration', 'href' => '/admin/settings'],
        'thread_intelligence' => ['label' => 'Thread Intelligence', 'href' => '/admin/thread-intelligence', 'flags_any' => ['community_memory', 'automated_context']],
    ]],
];

$tabEnabled = static function (array $item) use ($features): bool {
    if (!empty($item['flags_any'])) {
        foreach ($item['flags_any'] as $flag) {
            if (!empty($features[$flag])) {
                return true;
            }
        }

        return false;
    }

    return empty($item['flag']) || !empty($features[$item['flag']]);
};

// Least privilege (ADMIN.md §9.4): /admin/* needs requireAdmin(), /mod/* only a
// moderator. Showing a board moderator ten areas that all 403 would be
// show-and-deny, so the tier is reduced to what they can actually open.
// A null viewer is treated as unfiltered — this partial only renders on authed
// admin/mod routes, and the tier has always read it that way.
$adminViewer = $viewer === null || $viewer->isAdmin();
$tierAreas = $areas;
if (!$adminViewer) {
    $tierAreas = array_intersect_key($areas, ['moderation' => true]);
}

// The same rule one level down. Reducing the AREA tier is not enough: the one
// area a moderator keeps has an admin-only tab in it, so the tab row needs the
// identical filter or the least-privilege guarantee only holds on the rail.
$visibleTabs = static function (array $tabs) use ($adminViewer): array {
    if ($adminViewer) {
        return $tabs;
    }

    return array_filter($tabs, static fn (array $item): bool => empty($item['admin_only']));
};

$current = $areas[$area] ?? null;
$disabledNote = 'Disabled until the feature flag is enabled';
?>
<div class="admin-bar">
    <div class="admin-bar-id">
        <a class="admin-bar-brand" href="/" aria-label="<?= $e($brandName) ?>">
            <?php if (!empty($branding['logo_path'])): ?>
                <img class="admin-bar-logo" src="<?= $e($branding['logo_path']) ?>" alt="<?= $e($brandName) ?>" height="24">
            <?php else: ?>
                <svg class="admin-bar-star" viewBox="0 0 100 100" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="3.4" stroke-linejoin="round" stroke-linecap="round"><path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"/><path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z" opacity="0.5"/><circle cx="50" cy="50" r="5" fill="currentColor" stroke="none"/></g></svg>
                <span class="admin-bar-wordmark"><?= $e($brandName) ?></span>
            <?php endif; ?>
        </a>
        <a class="admin-bar-exit" href="/">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            Back to the forum
        </a>
        <div class="admin-bar-right">
            <?php if (!empty($features['search'])): ?>
                <form class="admin-bar-search" method="get" action="/search" role="search">
                    <input class="input input-small" type="search" enterkeyhint="search" name="q" placeholder="Search…" aria-label="Search">
                </form>
            <?php endif; ?>
            <?php if ($viewer !== null): ?>
                <?php if (!empty($features['notifications'])): ?>
                    <a class="topbar-link bell" href="/notifications" data-bell title="Notifications">
                        <svg class="bell-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        <span class="bell-count" data-bell-count hidden>0</span>
                        <span class="sr-only">Notifications</span>
                    </a>
                <?php endif; ?>
                <a class="admin-bar-user" href="/u/<?= $e($viewer->username()) ?>" aria-label="<?= $e($viewer->displayName()) ?>">
                    <?= $this->partial('partials/monogram', ['name' => $viewer->displayName(), 'username' => $viewer->username()]) ?>
                    <span class="admin-bar-username"><?= $e($viewer->displayName()) ?></span>
                </a>
                <form class="inline" method="post" action="/logout">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit" aria-label="Log out">
                        <?= $this->partial('partials/icon', ['name' => 'log-out']) ?>
                        <span class="admin-bar-action-label">Log out</span>
                    </button>
                </form>
            <?php endif; ?>
            <?php /* ADMIN.md §9.4 wants a persistent indicator so this is never
                     confused with the public site — but a board moderator on
                     /mod/* is not in admin mode, and every /admin/* destination
                     403s for them. Name the mode they are actually in; the
                     retired /mod chrome called it "Moderation" too. */ ?>
            <span class="admin-bar-mode"><?= $adminViewer ? 'Admin mode' : 'Moderation' ?></span>
        </div>
    </div>
    <nav class="admin-tier" aria-label="Admin areas" data-admin-tier>
        <?php foreach ($tierAreas as $key => $meta): ?>
            <?php
            $areaTabs = $visibleTabs($meta['tabs']);
            $enabledTabs = array_filter($areaTabs, $tabEnabled);
            // $areaTabs can now be empty in principle (an area whose every tab is
            // admin_only, seen by a moderator), and reset([]) is false — indexing
            // that warns, which failOnWarning turns red. Resolve through a list.
            $fallback = array_values($areaTabs);
            $firstHref = $enabledTabs === []
                ? (string) ($fallback[0]['href'] ?? '/admin')
                : (string) (reset($enabledTabs)['href']);
            ?>
            <?php if ($key === $area): ?>
                <span class="admin-tier-item is-active" aria-current="page"><?= $e($meta['label']) ?></span>
            <?php elseif ($enabledTabs === []): ?>
                <span class="admin-tier-item is-disabled" aria-disabled="true" data-destination="<?= $e($firstHref) ?>">
                    <span class="subnav-item-label"><?= $e($meta['label']) ?></span>
                    <span class="subnav-item-note"><?= $e($disabledNote) ?></span>
                </span>
            <?php else: ?>
                <a class="admin-tier-item" href="<?= $e($firstHref) ?>"><?= $e($meta['label']) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</div>
<main class="admin-console" id="main"<?= $current !== null ? ' data-area="' . $e($area) . '"' : '' ?>>
    <?php if ($current !== null): ?>
        <h1 class="admin-title"><?= $e($current['h1']) ?></h1>
        <nav class="admin-tabs" aria-label="<?= $e($current['aria']) ?>">
            <?php foreach ($visibleTabs($current['tabs']) as $key => $item): ?>
                <?php if (!$tabEnabled($item)): ?>
                    <span class="admin-tab is-disabled" aria-disabled="true" data-destination="<?= $e($item['href']) ?>">
                        <span class="subnav-item-label"><?= $e($item['label']) ?></span>
                        <span class="subnav-item-note"><?= $e($disabledNote) ?></span>
                    </span>
                <?php elseif ($key === $tab): ?>
                    <span class="admin-tab is-active" aria-current="page"><?= $e($item['label']) ?><?= $tabCount($key) ?></span>
                <?php else: ?>
                    <a class="admin-tab" href="<?= $e($item['href']) ?>"><?= $e($item['label']) ?><?= $tabCount($key) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <?php if (!$deferFlash): ?>
        <?= $this->partial('partials/flash') ?>
    <?php endif; ?>
    <div class="admin-pane<?= $paneClass !== '' ? ' ' . $e($paneClass) : '' ?>">
