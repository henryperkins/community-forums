<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Messages'); $this->section('robots', 'noindex, nofollow'); ?>
<div class="dm-shell">
    <?= $this->partial('partials/dm_list', ['conversations' => $conversations, 'filter' => $filter ?? 'all', 'active_id' => null, 'q' => $q ?? '', 'show_avatars' => $show_avatars ?? true, 'first_run' => $first_run ?? false, 'new_user_throttled' => $new_user_throttled ?? false]) ?>

    <section class="dm-threadpane">
        <div class="dm-empty">
            <div class="dm-empty-inner">
                <span class="star" aria-hidden="true">✦</span>
                <h2><?= !empty($first_run) ? 'Start a conversation' : 'Choose a conversation' ?></h2>
                <p><?= !empty($first_run) ? 'Send a private message to another member. Only those named can read your messages.' : 'Open a conversation from the list, or start a new message.' ?></p>
                <?= $this->partial('partials/dm_empty_actions', ['new_user_throttled' => $new_user_throttled ?? false]) ?>
            </div>
        </div>
    </section>
</div>
<?= $this->partial('partials/dm_compose_panel', ['allow_groups' => $allow_groups ?? false, 'show_avatars' => $show_avatars ?? true, 'compose' => $compose ?? []]) ?>
