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
?>
<nav class="settings-rail" aria-label="Settings sections">
    <?php foreach ($groups as $group): ?>
        <div class="settings-rail-group">
            <span class="settings-rail-title"><?= $e($group['label']) ?></span>
            <?php foreach ($group['items'] as $item): ?>
                <?php if (isset($item['feature']) && empty($features[$item['feature']])): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <?php $isActive = $active === $item['key']; ?>
                <a class="settings-rail-link<?= $isActive ? ' is-active' : '' ?>" data-settings-key="<?= $e($item['key']) ?>" href="<?= $e($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= $this->partial('partials/icon', ['name' => $item['icon']]) ?><span><?= $e($item['label']) ?></span></a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!empty($features['product_tour'])): ?>
        <button class="linkbtn subnav-action" type="button" data-tour-replay>Replay tour</button>
    <?php endif; ?>
</nav>
