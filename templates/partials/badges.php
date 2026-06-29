<?php /** @var \App\Core\View $this */ ?>
<section class="profile-badges">
    <p class="profile-badges__label">Marks of Esteem</p>
    <ul class="badge-row">
        <?php foreach ($badges as $b): ?>
            <li class="badge-chip" title="<?= $e($b['name']) ?> — <?= $e($b['description']) ?>">
                <span class="badge-icon" aria-hidden="true"><?= $e($b['icon'] ?? '🏷️') ?></span>
                <span class="badge-name"><?= $e($b['name']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
