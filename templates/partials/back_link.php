<?php /** @var \App\Core\View $this */ ?>
<?php
$backHref = (string) ($href ?? '');
$backLabel = (string) ($label ?? '');
$backClass = isset($class) && is_string($class) && $class !== '' ? $class : 'admin-back';
?>
<a class="<?= $e($backClass) ?>" href="<?= $e($backHref) ?>"><?= $this->partial('partials/icon', ['name' => 'chevron-left']) ?><span><?= $e($backLabel) ?></span></a>
