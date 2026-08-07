<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * Shared members-area chrome.
 *
 * The outer console owns the one area heading and one Member sections tab
 * strip. This wrapper gives all four members surfaces the design's smaller
 * `directory|invitations` interface without rendering a second navigation.
 */
$active = ($active ?? 'directory') === 'invitations' ? 'invitations' : 'directory';
$leafPaneClass = trim((string) ($pane_class ?? ''));
$memberPaneClass = trim('admin-members ' . $leafPaneClass);
?>
<?= $this->partial('admin/_console', [
    'area' => 'members',
    'tab' => $active === 'invitations' ? 'invitations' : 'users',
    'pane_class' => $memberPaneClass,
    'defer_flash' => !empty($defer_flash),
]) ?>
