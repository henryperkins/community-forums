<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Messages'); $this->section('robots', 'noindex, nofollow'); ?>
<div class="dm-shell">
    <?= $this->partial('partials/dm_list', ['conversations' => $conversations, 'filter' => $filter ?? 'all', 'active_id' => null, 'q' => $q ?? '', 'allow_groups' => $allow_groups ?? false, 'show_avatars' => $show_avatars ?? true, 'compose' => $compose ?? [], 'first_run' => $first_run ?? false, 'new_user_throttled' => $new_user_throttled ?? false]) ?>

    <section class="dm-threadpane">
        <div class="dm-empty">
            <div class="dm-empty-inner">
                <span class="star" aria-hidden="true">✦</span>
                <h2><?= !empty($first_run) ? 'Begin your first private counsel' : 'Choose a conversation' ?></h2>
                <p><?= !empty($first_run) ? 'Write to a member from their profile, or start here. Only the people you name can read what you write.' : 'Open one from the list, or begin a new private message.' ?></p>
                <?= $this->partial('partials/dm_empty_actions', ['new_user_throttled' => $new_user_throttled ?? false]) ?>
            </div>
        </div>
    </section>
</div>
