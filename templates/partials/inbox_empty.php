<?php /** @var \App\Core\View $this */ ?>
<div class="inbox-empty-state" tabindex="-1" data-inbox-empty-state>
    <?= $this->partial('partials/icon', ['name' => 'eight-point-star', 'class' => 'inbox-empty-star']) ?>
    <p class="inbox-empty-title"><?= $e($emptyTitle) ?></p>
    <?php if ($scope !== 'for_you'): ?><a class="btn btn-small" href="<?= $e(\App\Support\InboxView::query('for_you', $order)) ?>">Back to For You</a><?php endif; ?>
</div>
