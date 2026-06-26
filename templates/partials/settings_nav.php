<?php /** @var \App\Core\View $this */ ?>
<?php
$here = $request_path ?? '';
$items = [
    '/settings/account' => 'Profile',
    '/settings/security' => 'Password',
    '/settings/privacy' => 'Privacy',
    '/settings/preferences' => 'Preferences',
    '/settings/notifications' => 'Notifications',
];
if (!empty($features['oauth'])) {
    $items['/settings/connections'] = 'Connections';
}
$items['/settings/sessions'] = 'Sessions';
$items['/settings/blocks'] = 'Blocks';
$items['/settings/boards'] = 'Boards';
?>
<nav class="subnav">
    <?php foreach ($items as $href => $label): ?>
        <a class="<?= $here === $href ? 'active' : '' ?>" href="<?= $e($href) ?>"><?= $e($label) ?></a>
    <?php endforeach; ?>
</nav>
