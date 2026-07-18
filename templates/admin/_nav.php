<?php /** @var \App\Core\View $this */ ?>
<?php
$active = (string) ($active ?? '');
$features = is_array($features ?? null) ? $features : (array) $this->shared('features', []);
$disabledNote = 'Disabled until the feature flag is enabled';

$always = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin'],
    ['key' => 'audit', 'label' => 'Audit log', 'href' => '/admin/audit'],
    ['key' => 'features', 'label' => 'Feature flags', 'href' => '/admin/features'],
    ['key' => 'thread_intelligence', 'label' => 'Thread Intelligence', 'href' => '/admin/thread-intelligence', 'flags_any' => ['community_memory', 'automated_context']],
    ['key' => 'structure', 'label' => 'Boards & categories', 'href' => '/admin/structure'],
    ['key' => 'users', 'label' => 'Users', 'href' => '/admin/users'],
    ['key' => 'branding', 'label' => 'Branding', 'href' => '/admin/branding', 'flag' => 'branding'],
    ['key' => 'tags', 'label' => 'Tags', 'href' => '/admin/tags', 'flag' => 'tags'],
    ['key' => 'badge_rules', 'label' => 'Badge rules', 'href' => '/admin/badge-rules', 'flag' => 'badge_rules'],
    ['key' => 'email', 'label' => 'Email', 'href' => '/admin/email', 'flag' => 'email'],
    ['key' => 'announcements', 'label' => 'Announcements', 'href' => '/admin/announcements', 'flag' => 'announcements'],
];

$dark = [
    ['key' => 'api_tokens', 'label' => 'API tokens', 'href' => '/admin/api-tokens', 'flag' => 'api_tokens'],
    ['key' => 'webhooks', 'label' => 'Webhooks', 'href' => '/admin/webhooks', 'flag' => 'webhooks'],
    ['key' => 'packages', 'label' => 'Packages', 'href' => '/admin/packages', 'flag' => 'package_registry'],
    ['key' => 'registries', 'label' => 'Registry trust', 'href' => '/admin/registries', 'flag' => 'package_registry'],
    ['key' => 'themes', 'label' => 'Themes', 'href' => '/admin/themes', 'flag' => 'package_themes'],
    ['key' => 'roles', 'label' => 'Roles', 'href' => '/admin/roles', 'flag' => 'capabilities'],
    ['key' => 'providers', 'label' => 'Sign-in providers', 'href' => '/admin/providers', 'flag' => 'provider_registry'],
    ['key' => 'invitations', 'label' => 'Invitations', 'href' => '/admin/invitations', 'flag' => 'invitations'],
    ['key' => 'extensions', 'label' => 'Extensions', 'href' => '/admin/extensions', 'flag' => 'server_extensions'],
];
?>
<nav class="subnav admin-subnav" aria-label="Admin navigation">
    <?php foreach ($always as $item): ?>
        <?php
        // A multi-flag console (Thread Intelligence) renders the same
        // disabled-span treatment as single-flag items when every controlling
        // flag is off — hidden entries read as "removed", a disabled entry
        // explains itself. The route itself stays reachable as a recovery
        // surface (mirrors theme safe-mode).
        $enabled = true;
        if (!empty($item['flags_any'])) {
            $enabled = array_filter($item['flags_any'], static fn ($flag): bool => !empty($features[$flag])) !== [];
        } elseif (!empty($item['flag'])) {
            $enabled = !empty($features[$item['flag']]);
        }
        ?>
        <?php if ($enabled): ?>
            <a href="<?= $e($item['href']) ?>"<?= $active === $item['key'] ? ' class="active" aria-current="page"' : '' ?>><?= $e($item['label']) ?></a>
        <?php else: ?>
            <span class="subnav-action subnav-item is-disabled<?= $active === $item['key'] ? ' active' : '' ?>" aria-disabled="true"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>>
                <span class="subnav-item-label"><?= $e($item['label']) ?></span>
                <span class="subnav-item-note"><?= $e($disabledNote) ?></span>
            </span>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php foreach ($dark as $item): ?>
        <?php if (!empty($features[$item['flag']])): ?>
            <a href="<?= $e($item['href']) ?>"<?= $active === $item['key'] ? ' class="active" aria-current="page"' : '' ?>><?= $e($item['label']) ?></a>
        <?php else: ?>
            <span class="subnav-action subnav-item is-disabled<?= $active === $item['key'] ? ' active' : '' ?>" aria-disabled="true"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>>
                <span class="subnav-item-label"><?= $e($item['label']) ?></span>
                <span class="subnav-item-note"><?= $e($disabledNote) ?></span>
            </span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
