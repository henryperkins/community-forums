<?php /** @var \App\Core\View $this */ ?>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/"><?= $e($site_name) ?></a>
        <div class="topbar-right">
            <?php if ($current_user !== null): ?>
                <a class="topbar-user" href="/u/<?= $e($current_user->username()) ?>">
                    <?= $this->partial('partials/monogram', ['name' => $current_user->displayName(), 'username' => $current_user->username()]) ?>
                    <span class="topbar-name"><?= $e($current_user->displayName()) ?></span>
                </a>
                <?php if ($current_user->isAdmin()): ?>
                    <a class="topbar-link" href="/admin">Admin</a>
                <?php endif; ?>
                <a class="topbar-link" href="/settings/account">Settings</a>
                <form class="inline" method="post" action="/logout">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit">Log out</button>
                </form>
            <?php else: ?>
                <span class="pill">Guest</span>
                <a class="topbar-link" href="/login">Log in</a>
                <a class="btn btn-small" href="/register">Sign up</a>
            <?php endif; ?>
        </div>
    </div>
</header>
