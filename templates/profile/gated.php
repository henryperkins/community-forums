<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', '@' . $username); ?>
<div class="profile profile-gated">
    <section class="profile-gated-card">
        <?= $this->partial('partials/icon', ['name' => 'lock', 'class' => 'profile-gated-ic']) ?>
        <h1>This seat is kept private</h1>
        <p>@<?= $e($username) ?> shows their activity only to signed-in members.</p>
        <p class="profile-gated-actions"><a class="btn btn-small" href="/login?next=/u/<?= $e($username) ?>">Log in to view</a></p>
    </section>
</div>
