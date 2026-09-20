<?php /** @var \App\Core\View $this */ ?>
<?php
$active = isset($active) && is_string($active) ? $active : '';
$groups = [
    [
        'label' => 'Account',
        'items' => [
            ['key' => 'profile', 'label' => 'Profile', 'href' => '/settings/account', 'icon' => 'settings-profile'],
            ['key' => 'security', 'label' => 'Security', 'href' => '/settings/security', 'icon' => 'shield'],
            ['key' => 'privacy', 'label' => 'Privacy', 'href' => '/settings/privacy', 'icon' => 'eye'],
        ],
    ],
    [
        'label' => 'Reading & writing',
        'items' => [
            ['key' => 'appearance', 'label' => 'Appearance', 'href' => '/settings/appearance', 'icon' => 'sun'],
            ['key' => 'reading', 'label' => 'Reading', 'href' => '/settings/preferences', 'icon' => 'book'],
            ['key' => 'composing', 'label' => 'Composing', 'href' => '/settings/composing', 'icon' => 'edit-3'],
            ['key' => 'drafts', 'label' => 'Drafts', 'href' => '/drafts', 'icon' => 'file', 'feature' => 'drafts'],
            ['key' => 'boards', 'label' => 'Boards', 'href' => '/settings/boards', 'icon' => 'menu'],
        ],
    ],
    [
        'label' => 'Community',
        'items' => [
            ['key' => 'notifications', 'label' => 'Notifications', 'href' => '/settings/notifications', 'icon' => 'bell'],
            ['key' => 'connections', 'label' => 'Connections', 'href' => '/settings/connections', 'icon' => 'link', 'feature' => 'oauth'],
            ['key' => 'blocks', 'label' => 'Blocks', 'href' => '/settings/blocks', 'icon' => 'ban'],
            ['key' => 'sessions', 'label' => 'Sessions', 'href' => '/settings/sessions', 'icon' => 'monitor'],
            ['key' => 'account', 'label' => 'Account', 'href' => '/settings/account/lifecycle', 'icon' => 'archive', 'feature' => 'account_lifecycle'],
            ['key' => 'appeals', 'label' => 'Appeals', 'href' => '/appeals', 'icon' => 'flag', 'feature' => 'appeals'],
        ],
    ],
];
$this->section('account_settings', '1');
$activeLabel = 'Account';
foreach ($groups as $group) {
    foreach ($group['items'] as $item) {
        if ($active === $item['key'] && (!isset($item['feature']) || !empty($features[$item['feature']]))) {
            $activeLabel = $item['label'];
        }
    }
}
?>
<nav class="settings-rail settings-rail-desktop" aria-label="Settings sections">
    <?= $this->partial('partials/settings_nav_links', ['groups' => $groups, 'active' => $active]) ?>
</nav>
<details class="settings-mobile-nav" data-settings-mobile-nav>
    <summary>Settings: <?= $e($activeLabel) ?></summary>
    <nav class="settings-rail" aria-label="Settings sections">
        <?= $this->partial('partials/settings_nav_links', ['groups' => $groups, 'active' => $active]) ?>
    </nav>
</details>
