<?php /** @var \App\Core\View $this */ ?>
<?php
$ann = $site_announcement ?? null;
if (!is_array($ann) || empty($ann['active'])) {
    return;
}
$message = (string) ($ann['message'] ?? '');
$dismissible = !empty($ann['dismissible']);
$version = (int) ($ann['version'] ?? 0);
?>
<div class="site-announcement" role="status"
     data-announcement
     data-announcement-version="<?= $version ?>"
     data-dismissible="<?= $dismissible ? '1' : '0' ?>">
    <p class="site-announcement-message"><?= $e($message) ?></p>
    <?php if ($dismissible): ?>
        <button type="button" class="site-announcement-dismiss" data-announcement-dismiss aria-label="Dismiss announcement">&times;</button>
    <?php endif; ?>
</div>
