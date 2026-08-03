<?php /** @var \App\Core\View $this */ ?>
<section class="profile-badges">
    <p class="profile-badges-label">Marks of esteem</p>
    <ul class="badge-row">
        <?php foreach ($badges as $b): ?>
            <?php // The supplied Imladris Commend Star replaces seed emoji. ?>
            <li class="badge-chip" title="<?= $e($b['name']) ?> — <?= $e($b['description']) ?>">
                <?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'badge-star']) ?>
                <span class="badge-name"><?= $e($b['name']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
