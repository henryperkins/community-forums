<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Notifications'); ?>
<div class="read-main read-pad notifications-view">
    <?= $this->partial('partials/notification_list', ['page' => $notification_page, 'base_path' => '/notifications', 'return_path' => $notification_return]) ?>
</div>
