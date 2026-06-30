<?php /** @var \App\Core\View $this */ ?>
<section class="profile-badges">
    <p class="profile-badges-label">Marks of esteem</p>
    <ul class="badge-row">
        <?php foreach ($badges as $b): ?>
            <?php // Brand dot, never an emoji — the design system forbids emoji in UI;
                  // a mark's identity is carried by its name in lapidary caps. ?>
            <li class="badge-chip" title="<?= $e($b['name']) ?> — <?= $e($b['description']) ?>">
                <span class="b-dot" aria-hidden="true"></span>
                <span class="badge-name"><?= $e($b['name']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
