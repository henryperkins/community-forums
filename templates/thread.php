<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', $thread['title']);
$this->section('route', 'thread');
// SEO (P3-10): canonical URL + description for public threads; threads in a
// private/hidden board are excluded from indexing (defence in depth — the read
// gate already blocks crawlers).
$this->section('canonical', '/t/' . (int) $thread['id'] . '-' . $thread['slug']);
$this->section('og_type', 'article');
$this->section('description', mb_strimwidth(preg_replace('/\s+/', ' ', (string) $thread['title']) ?? '', 0, 160, '…'));
if (($thread['board_visibility'] ?? 'public') !== 'public') {
    $this->section('robots', 'noindex, nofollow');
}
$topicToolSections = [
    'watch' => $current_user !== null && !empty($can_write) && (($notifications_on ?? false) || ($workflow_on ?? false)),
    'standing' => $current_user !== null && ($workflow_on ?? false),
    'tags' => $current_user !== null && ($tags_on ?? false) && (!empty($thread_tags) || !empty($can_edit_tags)),
    'memory' => $current_user !== null && !empty($can_write) && !empty($can_curate_memory),
    'management' => $current_user !== null && !empty($can_write) && (
        !empty($can_self_assign) || !empty($can_staff_assign) || !empty($assignment)
        || !empty($can_mark_solved) || !empty($can_pin) || !empty($can_lock)
        || !empty($can_create_poll) || !empty($poll['can_close']) || !empty($can_split_merge)
    ),
];
$hasTopicTools = in_array(true, $topicToolSections, true);
$status = ($workflow_on ?? false)
    ? (string) ($thread['status'] ?? 'open')
    : (($accepted_post_id ?? null) !== null ? 'solved' : null);
$statusLabel = $status !== null ? ($status_labels[$status] ?? ucwords(str_replace('_', ' ', $status))) : null;
?>
<article class="thread thread-conversation thread-study" data-thread-study>
    <div class="thread-scroll">
    <header class="thread-head thread-study-head">
        <p class="breadcrumb"><a class="breadcrumb-back" href="/"><svg class="breadcrumb-back-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>Home</a><span class="breadcrumb-sep" aria-hidden="true">/</span><a class="breadcrumb-board" href="/c/<?= $e($thread['board_slug']) ?>"><span class="hash">#</span><?= $e($thread['board_name']) ?></a></p>
        <h1 class="thread-study-title">
            <?php if ((int) $thread['is_pinned'] === 1): ?><span class="thread-state-chip is-pinned">Pinned</span><?php endif; ?>
            <?php if ((int) $thread['is_locked'] === 1): ?><span class="thread-state-chip is-locked">Locked</span><?php endif; ?>
            <?php if ($status !== null): ?><span class="thread-status-chip is-<?= $e($status) ?>" data-thread-status="<?= $e($status) ?>"><?= $status === 'solved' ? '✓ ' : '' ?><?= $e($statusLabel) ?></span><?php endif; ?>
            <?= $e($thread['title']) ?>
        </h1>
        <div class="thread-facts">
        <?php
        // "Opened by" byline — derive OP anonymity from the OP post on this page so an
        // anonymous opener is never deanonymised; omit the opener name if the OP isn't loaded here.
        $opAnon = null;
        foreach (($posts ?? []) as $opPost) {
            if ((int) ($opPost['is_op'] ?? 0) === 1) { $opAnon = (int) ($opPost['is_anonymous'] ?? 0) === 1; break; }
        }
        $byReplies = (int) ($thread['reply_count'] ?? 0);
        ?>
        <p class="thread-byline"><?php if ($opAnon !== null): $ba = mask_author($thread['author_display_name'] ?? null, $thread['author_username'] ?? null, 'user', $opAnon); ?>Opened by <?= $e($ba['label']) ?> · <?php endif; ?><?= $byReplies ?> repl<?= $byReplies === 1 ? 'y' : 'ies' ?><?php if (!empty($assignment)): ?> · Tended by @<?= $e($assignment['assigned_username']) ?><?php endif; ?><?php if (!empty($my_snooze)): ?> · Quiet until <?= $e(human_datetime($my_snooze)) ?><?php endif; ?></p>
        <?php foreach (($thread_tags ?? []) as $tag): ?><a class="tag" href="/tags/<?= $e($tag['slug']) ?>"><?= $e($tag['name']) ?></a><?php endforeach; ?>
        <?php // Participant avatar stack (§5.1): distinct non-anonymous authors, +N overflow. ?>
        <?php if (($participant_count ?? 0) >= 2 && !empty($participants)): ?>
            <span class="thread-participants-label">In council</span>
            <div class="thread-participants" aria-label="Participants">
                <?php foreach ($participants as $pp): ?>
                    <?php $pa = mask_author($pp['author_display_name'] ?? null, $pp['author_username'] ?? null, $pp['author_role'] ?? 'user', false); ?>
                    <span class="participant" title="<?= $e($pa['label']) ?>"><?= $this->partial('partials/monogram', ['name' => $pa['mono_name'], 'username' => $pa['mono_seed']]) ?></span>
                <?php endforeach; ?>
                <?php $shownParticipants = count($participants); if ((int) ($participant_count ?? 0) > $shownParticipants): ?>
                    <span class="participant-more">+<?= (int) $participant_count - $shownParticipants ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (($engagement ?? false) && $current_user !== null && !empty($can_write)): ?>
            <form class="inline star-form" method="post" action="/t/<?= (int) $thread['id'] ?>/star">
                <?= $this->csrfField() ?>
                <input type="hidden" name="return" value="/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>">
                <button class="linkbtn star-btn<?= ($is_starred ?? false) ? ' star-on' : '' ?>" type="submit" aria-pressed="<?= ($is_starred ?? false) ? 'true' : 'false' ?>"><?= ($is_starred ?? false) ? '★ Starred' : '☆ Star' ?></button>
            </form>
        <?php endif; ?>
        <?php if ($hasTopicTools): ?>
            <button type="button" class="topic-tools-open" data-topic-tools-open hidden aria-controls="topic-tools-<?= (int) $thread['id'] ?>" aria-expanded="false"><?= $this->partial('partials/icon', ['name' => 'eight-point-star']) ?><span>Topic tools</span></button>
        <?php endif; ?>
        </div>
        <?php if ($current_user === null): ?><?= $this->partial('partials/thread_status_history', compact('status_history', 'status_labels')) ?><?php endif; ?>
    </header>
        <?php if (!empty($polls_on) && !empty($poll)): ?>
            <section class="poll-card poll-panel">
                <div class="poll-head">
                    <span class="poll-icon" aria-hidden="true">✦</span>
                    <span>
                        <span class="poll-eyebrow">Poll</span>
                        <span class="poll-sub"><?= $poll['mode'] === 'multiple' ? 'Choose any' : 'Choose one' ?></span>
                    </span>
                    <span class="poll-status<?= (string) ($poll['status'] ?? '') === 'closed' ? ' is-closed' : '' ?>">
                        <?= (string) ($poll['status'] ?? '') === 'closed' ? 'Closed' : 'Open' ?>
                    </span>
                </div>
                <h2 class="poll-question"><?= $e($poll['question']) ?></h2>
                <?php if (!empty($poll['results_visible'])): ?>
                    <?php $pollTotal = max(1, array_sum(array_map(static fn ($option): int => (int) $option['vote_count'], $poll['options']))); ?>
                    <ul class="poll-results link-list">
                        <?php foreach ($poll['options'] as $option): ?>
                            <?php $n = (int) $option['vote_count']; ?>
                            <li class="poll-result<?= !empty($option['viewer_voted']) ? ' is-mine' : '' ?>">
                                <span class="poll-result-row">
                                    <strong><?= $e($option['body']) ?></strong>
                                    <?php if (!empty($option['viewer_voted'])): ?><span class="poll-result-mine">Your vote</span><?php endif; ?>
                                    <span class="poll-result-count"><?= $n ?> vote<?= $n === 1 ? '' : 's' ?></span>
                                </span>
                                <span class="poll-result-bar"><meter min="0" max="<?= $pollTotal ?>" value="<?= $n ?>"><?= $n ?></meter></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (!empty($poll['can_vote'])): ?>
                    <form method="post" action="/polls/<?= (int) $poll['id'] ?>/vote" class="poll-options">
                        <?= $this->csrfField() ?>
                        <?php foreach ($poll['options'] as $option): ?>
                            <label class="poll-option">
                                <input type="<?= $poll['mode'] === 'multiple' ? 'checkbox' : 'radio' ?>" name="option_ids[]" value="<?= (int) $option['id'] ?>">
                                <span class="poll-option-mark" aria-hidden="true"></span>
                                <?= $e($option['body']) ?>
                            </label>
                        <?php endforeach; ?>
                        <div class="poll-foot">
                            <span class="poll-meta">Open to the council</span>
                            <button class="btn btn-small" type="submit">Vote</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="muted">Results are visible after voting or after the poll closes.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php if ($living_brief !== null || $related_fallback !== []): ?>
    <div class="thread-memory-slot">
        <?php if ($living_brief !== null): ?>
            <?= $this->partial('partials/living_brief', compact('living_brief', 'living_brief_sources', 'living_brief_related')) ?>
        <?php elseif ($related_fallback !== []): ?>
            <section class="related-topic-fallback" aria-labelledby="related-topic-fallback-heading">
                <h2 id="related-topic-fallback-heading">Related topics</h2>
                <?php foreach ($related_fallback as $related): ?>
                    <a href="<?= $e($related['url']) ?>"><?= $e($related['title']) ?></a>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($since_last_read_context)): ?>
        <section class="memory-panel since-last-read">
            <h2>Since you last read</h2>
            <p class="muted"><?= (int) $since_last_read_context['post_count'] ?> new post<?= (int) $since_last_read_context['post_count'] === 1 ? '' : 's' ?></p>
            <ul class="link-list">
                <?php foreach (($since_last_read_context['items'] ?? []) as $item): ?>
                    <li>
                        <a href="<?= $e($item['url'] ?? ('#p' . (int) $item['post_id'])) ?>">#<?= (int) $item['post_id'] ?></a>
                        <strong>@<?= $e($item['author']) ?></strong>
                        <span class="muted"><?= $e($item['excerpt']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if (empty($posts)): ?>
        <p class="muted empty">This thread has no visible posts.</p>
    <?php else: ?>
        <div class="post-stream">
            <?php $prevAuthorId = null; $prevAnon = true; $prevAt = 0; ?>
            <?php foreach ($posts as $p): ?>
                <?php
                // Group a reply with the one above it when the same non-anonymous
                // author posted again within ten minutes (§5.1); the partial keeps the
                // OP and the accepted answer ungrouped so their headers always show.
                $thisAnon = (int) ($p['is_anonymous'] ?? 0) === 1;
                $thisAt = strtotime((string) $p['created_at'] . ' UTC') ?: 0;
                $grouped = $prevAuthorId !== null && !$thisAnon && !$prevAnon
                    && (int) $p['user_id'] === $prevAuthorId
                    && ($thisAt - $prevAt) >= 0 && ($thisAt - $prevAt) <= 600;
                $prevAuthorId = (int) $p['user_id'];
                $prevAnon = $thisAnon;
                $prevAt = $thisAt;
                ?>
                <?= $this->partial('partials/post', [
                    'p' => $p,
                    'grouped' => $grouped,
                    'thread' => $thread,
                    'engagement' => $engagement ?? false,
                    'counts' => ($reaction_counts ?? [])[(int) $p['id']] ?? [],
                    'mine' => ($my_reactions ?? [])[(int) $p['id']] ?? [],
                    'allowed_emoji' => $allowed_emoji ?? [],
                    'reference_cards' => ($reference_cards ?? [])[(int) $p['id']] ?? [],
                    'link_preview_cards' => ($link_preview_cards ?? [])[(int) $p['id']] ?? [],
                    'accepted' => ($accepted_post_id ?? null) === (int) $p['id'],
                    'can_mark_solved' => $can_mark_solved ?? false,
                    'can_reveal_anon' => $can_reveal_anon ?? false,
                    'can_delete_posts' => $can_delete_posts ?? false,
                    'can_curate_memory' => $can_curate_memory ?? false,
                    'can_curate_wiki' => $can_curate_wiki ?? false,
                    'wiki_revisions' => ($wiki_revisions_by_post ?? [])[(int) $p['id']] ?? [],
                    'memory_on' => $memory_on ?? false,
                    'show_avatars' => $show_avatars ?? true,
                    'show_signatures' => $show_signatures ?? true,
                    'show_reactions' => $show_reactions ?? true,
                    'edit_post_id' => $edit_post_id ?? 0,
                    'edit_old' => $edit_old ?? '',
                    'edit_error' => $edit_error ?? '',
                ]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $this->partial('partials/pagination', [
        'page' => $page,
        'pages' => $pages,
        'base_url' => '/t/' . (int) $thread['id'] . '-' . $thread['slug'] . '?',
    ]) ?>
    </div>

    <div class="thread-dock">
        <?php if ($locked): ?>
            <div class="joinbar">This thread is locked and is not accepting replies.</div>
        <?php elseif ($can_reply): ?>
            <?= $this->partial('partials/composer', ['thread' => $thread, 'reply_errors' => $reply_errors, 'reply_old' => $reply_old]) ?>
        <?php elseif ($current_user === null): ?>
            <div class="joinbar"><span>You're browsing as a guest — <em>log in to add your counsel.</em></span><a class="btn" href="/login?next=/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>">Log in</a></div>
        <?php elseif ($current_user !== null && !$current_user->isActive()): ?>
            <div class="joinbar">Your account cannot post right now.</div>
        <?php elseif ($current_user !== null): ?>
            <div class="joinbar">You don't have permission to reply in this board.</div>
        <?php endif; ?>
    </div>
    <?= $this->partial('partials/thread_restructure', [
        'thread' => $thread,
        'posts' => $posts,
        'features' => $features,
        'can_write' => $can_write,
        'can_split_merge' => $can_split_merge,
    ]) ?>
    <?= $this->partial('partials/thread_tools', [
        'thread' => $thread,
        'topic_tool_sections' => $topicToolSections,
        'subscription' => $subscription,
        'notifications_on' => $notifications_on,
        'workflow_on' => $workflow_on,
        'can_write' => $can_write,
        'can_change_statuses' => $can_change_statuses,
        'status_labels' => $status_labels,
        'status_history' => $status_history,
        'tags_on' => $tags_on,
        'thread_tags' => $thread_tags,
        'all_tags' => $all_tags,
        'can_edit_tags' => $can_edit_tags,
        'living_brief' => $living_brief,
        'memory_history' => $memory_history,
        'memory_refresh' => $memory_refresh,
        'memory_automation_paused' => $memory_automation_paused,
        'assignment' => $assignment,
        'can_self_assign' => $can_self_assign,
        'can_staff_assign' => $can_staff_assign,
        'accepted_post_id' => $accepted_post_id,
        'can_mark_solved' => $can_mark_solved,
        'can_pin' => $can_pin,
        'can_lock' => $can_lock,
        'poll' => $poll,
        'can_create_poll' => $can_create_poll,
        'can_split_merge' => $can_split_merge,
    ]) ?>
</article>
