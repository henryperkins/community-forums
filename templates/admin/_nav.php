<?php /** @var \App\Core\View $this */ ?>
<?php
$active = (string) ($active ?? '');
$features = is_array($features ?? null) ? $features : (array) $this->shared('features', []);
$disabledNote = 'Disabled until the feature flag is enabled';

$groups = [
    'Dashboard' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin'],
    ],
    'Moderation' => [
        ['key' => 'reports', 'label' => 'Reports', 'href' => '/mod/reports', 'flag' => 'moderation_queue'],
        ['key' => 'approvals', 'label' => 'Approvals', 'href' => '/mod/approvals', 'flag' => 'moderation_queue'],
        ['key' => 'appeals', 'label' => 'Appeals', 'href' => '/mod/appeals', 'flag' => 'appeals'],
        ['key' => 'audit', 'label' => 'Audit log', 'href' => '/admin/audit'],
        ['key' => 'moderation', 'label' => 'Anti-abuse', 'href' => '/admin/moderation', 'flag' => 'anti_abuse'],
    ],
    'Content' => [
        ['key' => 'structure', 'label' => 'Boards & categories', 'href' => '/admin/structure'],
        ['key' => 'tags', 'label' => 'Tags', 'href' => '/admin/tags', 'flag' => 'tags'],
    ],
    'People' => [
        ['key' => 'users', 'label' => 'Users', 'href' => '/admin/users'],
        ['key' => 'roles', 'label' => 'Roles', 'href' => '/admin/roles', 'flag' => 'capabilities'],
        ['key' => 'invitations', 'label' => 'Invitations', 'href' => '/admin/invitations', 'flag' => 'invitations'],
        ['key' => 'badge_rules', 'label' => 'Badge rules', 'href' => '/admin/badge-rules', 'flag' => 'badge_rules'],
    ],
    'Appearance' => [
        ['key' => 'branding', 'label' => 'Branding', 'href' => '/admin/branding', 'flag' => 'branding'],
        ['key' => 'themes', 'label' => 'Themes', 'href' => '/admin/themes', 'flag' => 'package_themes'],
        ['key' => 'custom_emoji', 'label' => 'Custom emoji', 'href' => '/admin/custom-emoji', 'flag' => 'custom_emoji'],
    ],
    'Notifications' => [
        ['key' => 'email', 'label' => 'Email', 'href' => '/admin/email', 'flag' => 'email'],
        ['key' => 'announcements', 'label' => 'Announcements', 'href' => '/admin/announcements', 'flag' => 'announcements'],
    ],
    'Integrations' => [
        ['key' => 'packages', 'label' => 'Packages', 'href' => '/admin/packages', 'flag' => 'package_registry'],
        ['key' => 'registries', 'label' => 'Registry trust', 'href' => '/admin/registries', 'flag' => 'package_registry'],
        ['key' => 'webhooks', 'label' => 'Webhooks', 'href' => '/admin/webhooks', 'flag' => 'webhooks'],
        ['key' => 'api_tokens', 'label' => 'API tokens', 'href' => '/admin/api-tokens', 'flag' => 'api_tokens'],
        ['key' => 'providers', 'label' => 'Sign-in providers', 'href' => '/admin/providers', 'flag' => 'provider_registry'],
        ['key' => 'extensions', 'label' => 'Extensions', 'href' => '/admin/extensions', 'flag' => 'server_extensions'],
    ],
    'Settings' => [
        ['key' => 'settings', 'label' => 'General & registration', 'href' => '/admin/settings'],
        ['key' => 'features', 'label' => 'Feature flags', 'href' => '/admin/features'],
        ['key' => 'thread_intelligence', 'label' => 'Thread Intelligence', 'href' => '/admin/thread-intelligence', 'flags_any' => ['community_memory', 'automated_context']],
    ],
];
?>
<button class="admin-sections-toggle" type="button" aria-controls="admin-navigation" aria-expanded="false" data-admin-nav-toggle hidden>
    <span>Admin sections</span>
    <span aria-hidden="true">›</span>
</button>
<nav id="admin-navigation" class="subnav admin-subnav" aria-label="Admin navigation" data-admin-nav>
    <div class="admin-nav-drawer-head">
        <strong>Admin sections</strong>
        <button class="admin-nav-close" type="button" aria-label="Close admin sections" data-admin-nav-close hidden>×</button>
    </div>
    <?php foreach ($groups as $group => $items): ?>
        <section class="admin-nav-group" aria-labelledby="admin-nav-<?= $e(strtolower(str_replace(' ', '-', $group))) ?>">
            <h2 id="admin-nav-<?= $e(strtolower(str_replace(' ', '-', $group))) ?>" class="admin-nav-group-title"><?= $e($group) ?></h2>
            <ul class="admin-nav-group-list">
                <?php foreach ($items as $item): ?>
                    <?php
                    $enabled = true;
                    if (!empty($item['flags_any'])) {
                        $enabled = array_filter(
                            $item['flags_any'],
                            static fn (string $flag): bool => !empty($features[$flag]),
                        ) !== [];
                    } elseif (!empty($item['flag'])) {
                        $enabled = !empty($features[$item['flag']]);
                    }
                    ?>
                    <li>
                        <?php if ($enabled): ?>
                            <a href="<?= $e($item['href']) ?>" class="admin-nav-link<?= $active === $item['key'] ? ' active' : '' ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>><?= $e($item['label']) ?></a>
                        <?php else: ?>
                            <span class="admin-nav-link is-disabled<?= $active === $item['key'] ? ' active' : '' ?>" aria-disabled="true" data-destination="<?= $e($item['href']) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>>
                                <span class="subnav-item-label"><?php if ($item['key'] === 'thread_intelligence'): ?><span>Thread</span> Intelligence<?php else: ?><?= $e($item['label']) ?><?php endif; ?></span>
                                <span class="subnav-item-note"><?= $e($disabledNote) ?></span>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</nav>
<button class="admin-nav-scrim" type="button" aria-label="Close admin sections" data-admin-nav-scrim hidden></button>
