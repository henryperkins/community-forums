<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Messages'); $this->section('robots', 'noindex, nofollow'); ?>
<div class="dm-shell">
    <?= $this->partial('partials/dm_list', ['conversations' => $conversations, 'filter' => $filter ?? 'all', 'active_id' => null, 'q' => $q ?? '', 'allow_groups' => $allow_groups ?? false]) ?>

    <section class="dm-threadpane">
        <div class="dm-empty">
            <div class="dm-empty-inner">
                <span class="star" aria-hidden="true">✦</span>
                <h2>Choose a thread of counsel</h2>
                <p>Select a conversation from the left, or begin a new private message.</p>
            </div>
        </div>
    </section>
</div>
