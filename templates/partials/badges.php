<?php /** @var \App\Core\View $this */ ?>
<section class="profile-badges">
    <p class="profile-badges-label">Marks of esteem</p>
    <ul class="badge-row">
        <?php foreach ($badges as $b): ?>
            <?php // The supplied Imladris Commend Star replaces seed emoji. ?>
            <li>
                <details class="badge-info">
                    <summary class="badge-chip">
                        <?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'badge-star']) ?>
                        <span class="badge-name"><?= $e($b['name']) ?></span>
                        <?= $this->partial('partials/icon', ['name' => 'chevron-down', 'class' => 'badge-disclosure']) ?>
                    </summary>
                    <p class="badge-description"><?= $e($b['description']) ?></p>
                </details>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
