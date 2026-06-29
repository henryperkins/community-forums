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
        <p class="breadcrumb"><a href="/c/<?= $e($thread['board_slug']) ?>"><span class="hash">#</span><?= $e($thread['board_name']) ?></a></p>
        <h1>
            <?php if ((int) $thread['is_pinned'] === 1): ?><span class="badge">Pinned</span><?php endif; ?>
            <?php if ((int) $thread['is_locked'] === 1): ?><span class="badge badge-muted">Locked</span><?php endif; ?>
            <?php if (($workflow_on ?? false) && !empty($thread['status']) && $thread['status'] !== 'open'): ?>
                <span class="badge"><?= $e($status_labels[$thread['status']] ?? ucwords(str_replace('_', ' ', (string) $thread['status']))) ?></span>
            <?php endif; ?>
            <?php if (($accepted_post_id ?? null) !== null): ?><span class="badge badge-solved">✓ Solved</span><?php endif; ?>
            <?= $e($thread['title']) ?>
        </h1>
        <?php if ($workflow_on ?? false): ?>
            <div class="workflow-bar">
                <span class="muted">Status: <?= $e($status_labels[$thread['status'] ?? 'open'] ?? 'Open') ?></span>
                <?php if (!empty($assignment)): ?>
                    <span class="muted">Assigned to @<?= $e($assignment['assigned_username']) ?></span>
                <?php endif; ?>
                <?php if (!empty($my_snooze)): ?>
                    <span class="muted">Snoozed until <?= $e(human_datetime($my_snooze)) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($current_user !== null): ?>
                <div class="workflow-actions">
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
                            <button class="linkbtn" type="submit">Update status</button>
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
                        <button class="linkbtn" type="submit">Snooze</button>
                    </form>

                    <?php if (!empty($can_self_assign) || !empty($can_staff_assign) || !empty($assignment)): ?>
                        <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/assign">
                            <?= $this->csrfField() ?>
                            <?php if (!empty($can_staff_assign)): ?>
                                <label class="sr-only" for="thread-assignee">Assign to</label>
                                <input id="thread-assignee" class="input input-small" type="text" name="assignee" maxlength="32" placeholder="username">
                                <button class="linkbtn" type="submit">Assign</button>
                            <?php elseif (!empty($can_self_assign)): ?>
                                <input type="hidden" name="self" value="1">
                                <button class="linkbtn" type="submit">Assign to me</button>
                            <?php endif; ?>
                            <?php if (!empty($assignment)): ?>
                                <button class="linkbtn muted" type="submit" name="action" value="unassign">Unassign</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
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
        <?php if (($accepted_post_id ?? null) !== null && !empty($can_mark_solved)): ?>
            <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/unaccept">
                <?= $this->csrfField() ?>
                <button class="linkbtn muted" type="submit">Clear accepted answer</button>
            </form>
        <?php endif; ?>
        <?php if (!empty($polls_on) && !empty($poll)): ?>
            <section class="memory-panel poll-panel">
                <h2><?= $e($poll['question']) ?></h2>
                <?php if (!empty($poll['results_visible'])): ?>
                    <ul class="link-list">
                        <?php foreach ($poll['options'] as $option): ?>
                            <?php $n = (int) $option['vote_count']; ?>
                            <li><strong><?= $e($option['body']) ?></strong> <span class="muted"><?= $n ?> vote<?= $n === 1 ? '' : 's' ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (!empty($poll['can_vote'])): ?>
                    <form method="post" action="/polls/<?= (int) $poll['id'] ?>/vote" class="stacked">
                        <?= $this->csrfField() ?>
                        <?php foreach ($poll['options'] as $option): ?>
                            <label class="checkline">
                                <input type="<?= $poll['mode'] === 'multiple' ? 'checkbox' : 'radio' ?>" name="option_ids[]" value="<?= (int) $option['id'] ?>">
                                <?= $e($option['body']) ?>
                            </label>
                        <?php endforeach; ?>
                        <button class="btn btn-small" type="submit">Vote</button>
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
            <details class="workflow-actions">
                <summary class="linkbtn">Add poll</summary>
                <form class="stacked" method="post" action="/t/<?= (int) $thread['id'] ?>/poll">
                    <?= $this->csrfField() ?>
                    <label class="field"><span>Question</span><input class="input" type="text" name="question" maxlength="255" required></label>
                    <label class="field"><span>Mode</span><select class="input input-small" name="mode"><option value="single">Single choice</option><option value="multiple">Multiple choice</option></select></label>
                    <label class="field"><span>Options</span><textarea class="input" name="options" rows="4" required></textarea></label>
                    <button class="btn btn-small" type="submit">Create poll</button>
                </form>
            </details>
        <?php endif; ?>
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
        <?php if ($is_admin): ?>
            <div class="mod-bar">
                <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/pin">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit"><?= (int) $thread['is_pinned'] === 1 ? 'Unpin' : 'Pin' ?></button>
                </form>
                <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/lock">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit"><?= (int) $thread['is_locked'] === 1 ? 'Unlock' : 'Lock' ?></button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if (empty($posts)): ?>
        <p class="muted empty">This thread has no visible posts.</p>
    <?php else: ?>
        <div class="post-stream">
            <?php foreach ($posts as $p): ?>
                <?= $this->partial('partials/post', [
                    'p' => $p,
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
        <div class="joinbar">You're browsing as a guest — <a href="/login?next=/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>">log in</a> to reply.</div>
    <?php elseif ($current_user !== null && !$current_user->isActive()): ?>
        <div class="joinbar">Your account cannot post right now.</div>
    <?php elseif ($current_user !== null): ?>
        <div class="joinbar">You don't have permission to reply in this board.</div>
    <?php endif; ?>
</article>
