<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', '@' . $username); ?>
<div class="profile profile-gated">
    <h1>@<?= $e($username) ?></h1>
    <p class="muted">This member limits their profile to signed-in members.</p>
    <p><a class="btn btn-small" href="/login?next=/u/<?= $e($username) ?>">Log in to view</a></p>
</div>
