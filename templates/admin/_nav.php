<?php /** @var \App\Core\View $this */ ?>
<?php
$active = (string) ($active ?? '');
$features = is_array($features ?? null) ? $features : (array) $this->shared('features', []);
$disabledNote = 'Disabled until the feature flag is enabled';

// Grouped per ADMIN.md §9.2 (round-2 audit finding 13): Dashboard · Moderation ·
// Content · People · Appearance · Notifications · Integrations · Settings.
// §9.2's People "Approval queue" is the (deferred) REGISTRATION approval mode —
// the content approval-hold queue belongs to Moderation, whose spec row already
// reads "Automation rules (filters, throttles, approvals)". Item schema is
// unchanged: key/label/href plus optional flag or flags_any gating; a gated-off
// item renders as an explanatory disabled span, never disappears (F2 posture).
$groups = [
    ['label' => null, 'items' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin'],
    ]],
    ['label' => 'Moderation', 'items' => [
        ['key' => 'reports', 'label' => 'Reports queue', 'href' => '/mod/reports', 'flag' => 'moderation_queue'],
        ['key' => 'approvals', 'label' => 'Approvals', 'href' => '/mod/approvals', 'flag' => 'moderation_queue'],
        ['key' => 'appeals', 'label' => 'Appeals', 'href' => '/mod/appeals', 'flag' => 'appeals'],
        ['key' => 'audit', 'label' => 'Audit log', 'href' => '/admin/audit'],
        ['key' => 'thread_intelligence', 'label' => 'Thread Intelligence', 'href' => '/admin/thread-intelligence', 'flags_any' => ['community_memory', 'automated_context']],
    ]],
    ['label' => 'Content', 'items' => [
        ['key' => 'structure', 'label' => 'Boards & categories', 'href' => '/admin/structure'],
        ['key' => 'tags', 'label' => 'Tags', 'href' => '/admin/tags', 'flag' => 'tags'],
    ]],
    ['label' => 'People', 'items' => [
        ['key' => 'users', 'label' => 'Users', 'href' => '/admin/users'],
        ['key' => 'roles', 'label' => 'Roles', 'href' => '/admin/roles', 'flag' => 'capabilities'],
        ['key' => 'badge_rules', 'label' => 'Badge rules', 'href' => '/admin/badge-rules', 'flag' => 'badge_rules'],
    ]],
    ['label' => 'Appearance', 'items' => [
        ['key' => 'branding', 'label' => 'Branding', 'href' => '/admin/branding', 'flag' => 'branding'],
        ['key' => 'themes', 'label' => 'Themes', 'href' => '/admin/themes', 'flag' => 'package_themes'],
    ]],
    ['label' => 'Notifications', 'items' => [
        ['key' => 'email', 'label' => 'Email', 'href' => '/admin/email', 'flag' => 'email'],
        ['key' => 'announcements', 'label' => 'Announcements', 'href' => '/admin/announcements', 'flag' => 'announcements'],
    ]],
    ['label' => 'Integrations', 'items' => [
        ['key' => 'packages', 'label' => 'Packages', 'href' => '/admin/packages', 'flag' => 'package_registry'],
        ['key' => 'registries', 'label' => 'Registry trust', 'href' => '/admin/registries', 'flag' => 'package_registry'],
        ['key' => 'webhooks', 'label' => 'Webhooks', 'href' => '/admin/webhooks', 'flag' => 'webhooks'],
        ['key' => 'api_tokens', 'label' => 'API tokens', 'href' => '/admin/api-tokens', 'flag' => 'api_tokens'],
        ['key' => 'extensions', 'label' => 'Extensions', 'href' => '/admin/extensions', 'flag' => 'server_extensions'],
    ]],
    ['label' => 'Settings', 'items' => [
        ['key' => 'features', 'label' => 'Feature flags', 'href' => '/admin/features'],
        ['key' => 'providers', 'label' => 'Sign-in providers', 'href' => '/admin/providers', 'flag' => 'provider_registry'],
        ['key' => 'invitations', 'label' => 'Invitations', 'href' => '/admin/invitations', 'flag' => 'invitations'],
    ]],
];
?>
<nav class="subnav admin-subnav" aria-label="Admin navigation">
    <?php foreach ($groups as $group): ?>
        <div class="subnav-group">
            <?php if ($group['label'] !== null): ?>
                <span class="subnav-group-label"><?= $e($group['label']) ?></span>
            <?php endif; ?>
            <?php foreach ($group['items'] as $item): ?>
                <?php
                // A multi-flag console (Thread Intelligence) renders the same
                // disabled-span treatment as single-flag items when every
                // controlling flag is off — hidden entries read as "removed", a
                // disabled entry explains itself. The route itself stays
                // reachable as a recovery surface (mirrors theme safe-mode).
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
        </div>
    <?php endforeach; ?>
</nav>
