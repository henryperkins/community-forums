<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', $thread['title']);
// SEO (P3-10): canonical URL + description for public threads; threads in a
// private/hidden board are excluded from indexing (defence in depth — the read
// gate already blocks crawlers).
$this->section('canonical', '/t/' . (int) $thread['id'] . '-' . $thread['slug']);
$this->section('og_type', 'article');
$this->section('description', mb_strimwidth(preg_replace('/\s+/', ' ', (string) $thread['title']) ?? '', 0, 160, '…'));
if (($thread['board_visibility'] ?? 'public') !== 'public') {
    $this->section('robots', 'noindex, nofollow');
}
?>
<article class="thread">
    <header class="thread-head">
        <p class="breadcrumb"><a class="breadcrumb-back" href="/"><svg class="breadcrumb-back-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>Inbox</a><span class="breadcrumb-sep" aria-hidden="true">/</span><a class="breadcrumb-board" href="/c/<?= $e($thread['board_slug']) ?>"><span class="hash">#</span><?= $e($thread['board_name']) ?></a></p>
        <h1>
            <?php if ((int) $thread['is_pinned'] === 1): ?><span class="badge">Pinned</span><?php endif; ?>
            <?php if ((int) $thread['is_locked'] === 1): ?><span class="badge badge-muted">Locked</span><?php endif; ?>
            <?php if (($workflow_on ?? false) && !empty($thread['status']) && $thread['status'] !== 'open'): ?>
                <span class="badge"><?= $e($status_labels[$thread['status']] ?? ucwords(str_replace('_', ' ', (string) $thread['status']))) ?></span>
            <?php endif; ?>
            <?php if (($accepted_post_id ?? null) !== null): ?><span class="badge badge-solved">✓ Solved</span><?php endif; ?>
            <?= $e($thread['title']) ?>
        </h1>
        <?php
        // "Opened by" byline — derive OP anonymity from the OP post on this page so an
        // anonymous opener is never deanonymised; omit the opener name if the OP isn't loaded here.
        $opAnon = null;
        foreach (($posts ?? []) as $opPost) {
            if ((int) ($opPost['is_op'] ?? 0) === 1) { $opAnon = (int) ($opPost['is_anonymous'] ?? 0) === 1; break; }
        }
        $byReplies = (int) ($thread['reply_count'] ?? 0);
        ?>
        <p class="thread-byline"><?php if ($opAnon !== null): $ba = mask_author($thread['author_display_name'] ?? null, $thread['author_username'] ?? null, 'user', $opAnon); ?>Opened by <?= $e($ba['label']) ?> · <?php endif; ?><?= $byReplies ?> repl<?= $byReplies === 1 ? 'y' : 'ies' ?></p>
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
        <?php
        // Topic action bar (§5.1): the always-on member/mod controls — star, notify,
        // clear-accepted, pin/lock — gathered onto one calm line instead of a stack of
        // raw links. (The deploy-dark workflow controls below keep their own bar.)
        $hasThreadActions = (($engagement ?? false) && $current_user !== null)
            || (($notifications_on ?? false) && $current_user !== null)
            || (($accepted_post_id ?? null) !== null && !empty($can_mark_solved))
            || ($is_admin ?? false);
        ?>
        <?php if ($hasThreadActions): ?>
            <div class="thread-actions">
                <?php if (($engagement ?? false) && $current_user !== null): ?>
                    <form class="inline star-form" method="post" action="/t/<?= (int) $thread['id'] ?>/star">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="return" value="/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>">
                        <button class="linkbtn star-btn<?= ($is_starred ?? false) ? ' star-on' : '' ?>" type="submit" aria-pressed="<?= ($is_starred ?? false) ? 'true' : 'false' ?>">
                            <?= ($is_starred ?? false) ? '★ Starred' : '☆ Star' ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if (($notifications_on ?? false) && $current_user !== null): ?>
                    <?php $freq = $subscription['frequency'] ?? 'off'; ?>
                    <form class="inline subscribe-form" method="post" action="/t/<?= (int) $thread['id'] ?>/subscribe">
                        <?= $this->csrfField() ?>
                        <label class="sr-only" for="sub-freq">Notify me</label>
                        <select class="input input-small" id="sub-freq" name="frequency">
                            <option value="instant"<?= $freq === 'instant' ? ' selected' : '' ?>>Notify: Instant</option>
                            <option value="daily"<?= $freq === 'daily' ? ' selected' : '' ?>>Notify: Daily</option>
                            <option value="off"<?= $freq === 'off' ? ' selected' : '' ?>>Notify: Off</option>
                        </select>
                        <input type="hidden" name="in_app" value="1">
                        <input type="hidden" name="email" value="1">
                        <button class="linkbtn" type="submit">Save</button>
                    </form>
                <?php endif; ?>
                <?php if (($accepted_post_id ?? null) !== null && !empty($can_mark_solved)): ?>
                    <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/unaccept">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn muted" type="submit">Clear accepted answer</button>
                    </form>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                    <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/pin">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn" type="submit"><?= (int) $thread['is_pinned'] === 1 ? 'Unpin' : 'Pin' ?></button>
                    </form>
                    <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/lock">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn" type="submit"><?= (int) $thread['is_locked'] === 1 ? 'Unlock' : 'Lock' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($workflow_on ?? false): ?>
            <div class="workflow-bar wf-bar">
                <span class="wf-bar-label">Workflow</span>
                <span class="muted">Status: <?= $e($status_labels[$thread['status'] ?? 'open'] ?? 'Open') ?></span>
                <?php if (!empty($assignment)): ?>
                    <span class="muted">Assigned to @<?= $e($assignment['assigned_username']) ?></span>
                <?php endif; ?>
                <?php if (!empty($my_snooze)): ?>
                    <span class="muted">Snoozed until <?= $e(human_datetime($my_snooze)) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($current_user !== null): ?>
                <div class="workflow-actions wf-actions">
                    <?php if (!empty(array_filter($can_change_statuses ?? []))): ?>
                        <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/status">
                            <?= $this->csrfField() ?>
                            <label class="sr-only" for="thread-status">Topic status</label>
                            <select id="thread-status" class="input input-small" name="status">
                                <?php foreach ($status_labels as $value => $label): ?>
                                    <?php if (!empty($can_change_statuses[$value]) || $value === ($thread['status'] ?? 'open')): ?>
                                        <option value="<?= $e($value) ?>"<?= $value === ($thread['status'] ?? 'open') ? ' selected' : '' ?>><?= $e($label) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <input class="input input-small" type="text" name="reason" maxlength="255" placeholder="Reason">
                            <button class="linkbtn wf-btn" type="submit">Update status</button>
                        </form>
                    <?php endif; ?>

                    <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/snooze">
                        <?= $this->csrfField() ?>
                        <label class="sr-only" for="thread-snooze">Snooze</label>
                        <select id="thread-snooze" class="input input-small" name="until">
                            <option value="">Clear snooze</option>
                            <option value="later_today">Later today</option>
                            <option value="tomorrow">Tomorrow</option>
                            <option value="week">Next week</option>
                        </select>
                        <button class="linkbtn wf-btn" type="submit">Snooze</button>
                    </form>

                    <?php if (!empty($can_self_assign) || !empty($can_staff_assign) || !empty($assignment)): ?>
                        <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/assign">
                            <?= $this->csrfField() ?>
                            <?php if (!empty($can_staff_assign)): ?>
                                <label class="sr-only" for="thread-assignee">Assign to</label>
                                <input id="thread-assignee" class="input input-small" type="text" name="assignee" maxlength="32" placeholder="username">
                                <button class="linkbtn wf-btn" type="submit">Assign</button>
                            <?php elseif (!empty($can_self_assign)): ?>
                                <input type="hidden" name="self" value="1">
                                <button class="linkbtn wf-btn" type="submit">Assign to me</button>
                            <?php endif; ?>
                            <?php if (!empty($assignment)): ?>
                                <button class="linkbtn wf-btn muted" type="submit" name="action" value="unassign">Unassign</button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <span class="wf-btn muted" aria-disabled="true">Assign</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($status_history)): ?>
                <details class="workflow-history wf-history">
                    <summary>Status history</summary>
                    <ol class="wf-history-list">
                        <?php foreach ($status_history as $wfEvent): ?>
                            <li class="wf-history-item">
                                <span class="wf-history-to"><?= $e($status_labels[$wfEvent['new_status']] ?? $wfEvent['new_status']) ?></span>
                                <?php if (!empty($wfEvent['previous_status'])): ?>
                                    <span class="muted">from <?= $e($status_labels[$wfEvent['previous_status']] ?? $wfEvent['previous_status']) ?></span>
                                <?php endif; ?>
                                <span class="wf-history-actor"><?= $e($wfEvent['actor_display_name'] ?? $wfEvent['actor_username'] ?? 'system') ?></span>
                                <span class="muted"><?= $e(human_datetime($wfEvent['created_at'])) ?></span>
                                <?php if (!empty($wfEvent['reason'])): ?>
                                    <span class="wf-history-reason">— <?= $e($wfEvent['reason']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </details>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (($tags_on ?? false) && !empty($thread_tags)): ?>
            <div class="workflow-bar" aria-label="Topic tags">
                <?php foreach ($thread_tags as $tag): ?>
                    <a class="tag" href="/tags/<?= $e($tag['slug']) ?>"><?= $e($tag['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (($tags_on ?? false) && !empty($all_tags) && !empty($can_edit_tags)): ?>
            <details class="workflow-actions">
                <summary class="linkbtn">Edit tags</summary>
                <form class="inline-form" method="post" action="/t/<?= (int) $thread['id'] ?>/tags">
                    <?= $this->csrfField() ?>
                    <?php $currentTagIds = array_flip(array_map(static fn ($tag) => (int) $tag['id'], $thread_tags ?? [])); ?>
                    <?php foreach ($all_tags as $tag): ?>
                        <label class="checkline">
                            <input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>" <?= isset($currentTagIds[(int) $tag['id']]) ? 'checked' : '' ?>>
                            <?= $e($tag['name']) ?>
                        </label>
                    <?php endforeach; ?>
                    <button class="btn btn-small" type="submit">Save tags</button>
                </form>
            </details>
        <?php endif; ?>
        <?php if (($memory_on ?? false) && !empty($summary)): ?>
            <section class="memory-panel">
                <h2>Summary</h2>
                <div class="post-body"><?= $summary['body_html'] ?></div>
                <p class="muted">Version <?= (int) $summary['version'] ?> by @<?= $e($summary['author_username']) ?></p>
                <?php if (!empty($summary_sources)): ?>
                    <ul class="muted">
                        <?php foreach ($summary_sources as $src): ?>
                            <li>Source <a href="/t/<?= (int) $src['thread_id'] ?>-<?= $e($src['thread_slug']) ?>#p<?= (int) $src['id'] ?>">#<?= (int) $src['id'] ?></a> by <?= ($src['author_username'] ?? '') !== '' ? '@' . $e($src['author_username']) : 'Anonymous' ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!empty($summary_reference_cards)): ?>
                    <div class="reference-cards" aria-label="Referenced content">
                        <?php foreach ($summary_reference_cards as $card): ?>
                            <a class="reference-card" href="<?= $e($card['url']) ?>">
                                <span class="badge badge-muted"><?= $e($card['type']) ?></span>
                                <strong><?= $e($card['title']) ?></strong>
                                <?php if (($card['meta'] ?? '') !== ''): ?><span class="muted"><?= $e($card['meta']) ?></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($can_curate_memory)): ?>
                    <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/summary/retire">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn muted" type="submit">Retire summary</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php if (($memory_on ?? false) && !empty($related_threads)): ?>
            <section class="memory-panel">
                <h2>Related topics</h2>
                <ul>
                    <?php foreach ($related_threads as $related): ?>
                        <li><a href="/t/<?= (int) $related['related_thread_id'] ?>-<?= $e($related['slug']) ?>"><?= $e($related['title']) ?></a>
                            <?php if (!empty($related['reason'])): ?><span class="muted">— <?= $e($related['reason']) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <?php if (($memory_on ?? false) && !empty($can_curate_memory)): ?>
            <details class="workflow-actions">
                <summary class="linkbtn">Curate topic memory</summary>
                <form class="composer" method="post" action="/t/<?= (int) $thread['id'] ?>/summary">
                    <?= $this->csrfField() ?>
                    <label for="summary-body">Summary</label>
                    <textarea id="summary-body" class="composer-input" name="body" rows="4" maxlength="20000"></textarea>
                    <label for="summary-sources">Source post IDs</label>
                    <input id="summary-sources" class="input" type="text" name="source_post_ids" placeholder="1, 2, 3">
                    <button class="btn btn-small" type="submit">Publish summary</button>
                </form>
                <?php if (!empty($summary_history)): ?>
                    <form class="inline-form" method="post" action="/t/<?= (int) $thread['id'] ?>/summary/restore">
                        <?= $this->csrfField() ?>
                        <label class="sr-only" for="summary-restore">Restore summary</label>
                        <select id="summary-restore" class="input input-small" name="summary_id">
                            <?php foreach ($summary_history as $item): ?>
                                <option value="<?= (int) $item['id'] ?>">v<?= (int) $item['version'] ?> · <?= $e($item['status']) ?> · @<?= $e($item['author_username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-small" type="submit">Restore summary</button>
                    </form>
                <?php endif; ?>
                <form class="inline-form" method="post" action="/t/<?= (int) $thread['id'] ?>/related">
                    <?= $this->csrfField() ?>
                    <input class="input input-small" type="number" name="related_thread_id" min="1" placeholder="Thread ID" required>
                    <input class="input" type="text" name="reason" maxlength="255" placeholder="Reason">
                    <button class="btn btn-small" type="submit">Add related topic</button>
                </form>
            </details>
        <?php endif; ?>
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
                <?php if (!empty($poll['can_close'])): ?>
                    <form class="inline" method="post" action="/polls/<?= (int) $poll['id'] ?>/close">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn muted" type="submit">Close poll</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php elseif (!empty($polls_on) && !empty($can_create_poll)): ?>
            <details class="workflow-actions poll-builder">
                <summary class="linkbtn">Add poll</summary>
                <form class="stacked" method="post" action="/t/<?= (int) $thread['id'] ?>/poll">
                    <?= $this->csrfField() ?>
                    <div class="builder-head">
                        <span class="poll-icon" aria-hidden="true">✦</span>
                        <span class="poll-eyebrow">Add a poll</span>
                        <span class="builder-hint">A topic may hold one poll.</span>
                    </div>
                    <label class="field"><span>Question</span><input class="input" type="text" name="question" maxlength="255" required></label>
                    <label class="field"><span>Mode</span><select class="input input-small" name="mode"><option value="single">Single choice</option><option value="multiple">Multiple choice</option></select></label>
                    <label class="field"><span>Closes</span><select class="input input-small" name="closes_in"><option value="never">Never</option><option value="1d">In 1 day</option><option value="3d">In 3 days</option><option value="1w">In 1 week</option></select></label>
                    <label class="field"><span>Options</span><textarea class="input" name="options" rows="4" required></textarea></label>
                    <button class="btn btn-small" type="submit">Create poll</button>
                </form>
            </details>
        <?php endif; ?>
        <?php if (!empty($features['split_merge']) && !empty($can_moderate_board)): ?>
            <?php $movablePosts = array_values(array_filter($posts ?? [], static fn ($post): bool => (int) ($post['is_op'] ?? 0) !== 1)); ?>
            <section class="sm-panel topic-restructure" aria-label="Split or merge topic">
                <div class="sm-panel-head">
                    <span class="sm-panel-title">Split a topic, or merge two</span>
                    <span class="sm-note">Selected replies move into a new topic; merging redirects this topic to the chosen target.</span>
                </div>
                <div class="sm-grid">
                    <form class="stacked" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/split">
                        <?= $this->csrfField() ?>
                        <h2>Split into a new topic</h2>
                        <?php if (empty($movablePosts)): ?>
                            <p class="muted">There are no replies to split yet.</p>
                        <?php else: ?>
                            <div class="sm-post-list">
                                <?php foreach ($movablePosts as $post): ?>
                                    <?php $pa = mask_author($post['author_display_name'] ?? null, $post['author_username'] ?? null, $post['author_role'] ?? 'user', (int) ($post['is_anonymous'] ?? 0) === 1); ?>
                                    <label class="sm-post">
                                        <input type="checkbox" name="post_ids[]" value="<?= (int) $post['id'] ?>">
                                        <span class="sm-post-main">
                                            <span class="sm-post-by"><?= $e($pa['label']) ?> · #<?= (int) $post['id'] ?></span>
                                            <span class="sm-post-body"><?= $e(mb_strimwidth(strip_tags((string) ($post['body_html'] ?? '')), 0, 120, '…')) ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <label class="field"><span>New topic title</span><input class="input" type="text" name="title" maxlength="255" required></label>
                        <button class="btn btn-small" type="submit"<?= empty($movablePosts) ? ' disabled' : '' ?>>Split replies out</button>
                    </form>
                    <form class="stacked" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/merge">
                        <?= $this->csrfField() ?>
                        <h2>Merge this topic</h2>
                        <p class="merge-from"><span>From</span><strong><?= $e($thread['title']) ?></strong><span>#<?= $e($thread['board_slug']) ?></span></p>
                        <label class="field"><span>Target topic ID</span><input class="input" type="number" name="target_thread_id" min="1" required></label>
                        <p class="sm-note">All posts move into the chosen topic. The move is logged and reversible through repair tooling.</p>
                        <button class="btn btn-small" type="submit">Merge topics</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </header>

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
</article>
