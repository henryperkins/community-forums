<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Error ' . $status); $this->section('variant', 'plain'); ?>
<div class="auth-card error-card">
    <h1><?= (int) $status ?></h1>
    <p><?= $e($message) ?></p>
    <p><a class="btn" href="/">Back to home</a></p>
</div>
