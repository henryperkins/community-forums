<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', '@' . $username);
$this->section('canonical', '/u/' . $username);
$this->section('robots', 'noindex, nofollow');
$this->section('description', 'This profile is visible to signed-in members.');
$this->section('composer', '0');
?>
<div class="profile profile-gated">
    <section class="profile-gated-card card">
        <?= $this->partial('partials/icon', ['name' => 'lock', 'class' => 'profile-gated-ic']) ?>
        <h1>This seat is kept private</h1>
        <p>@<?= $e($username) ?> shows their activity only to <span class="profile-gated-audience">signed-in members</span>.</p>
        <p class="profile-gated-actions"><a class="btn btn-secondary" href="/login?next=/u/<?= $e($username) ?>">Log in to view</a></p>
    </section>
</div>
