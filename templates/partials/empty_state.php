<?php /** @var \App\Core\View $this */ ?>
<?php
$emptyHeading = (string) ($heading ?? '');
$emptyMessage = (string) ($message ?? '');
$emptyActionHref = isset($action_href) ? (string) $action_href : '';
$emptyActionLabel = isset($action_label) ? (string) $action_label : '';
?>
<section class="state-empty">
    <h3><?= $e($emptyHeading) ?></h3>
    <p><?= $e($emptyMessage) ?></p>
    <?php if ($emptyActionHref !== '' && $emptyActionLabel !== ''): ?>
        <a href="<?= $e($emptyActionHref) ?>"><?= $e($emptyActionLabel) ?></a>
    <?php endif; ?>
</section>
