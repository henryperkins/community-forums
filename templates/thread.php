<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', $thread['title']);
$this->section('route', 'thread');
// SEO (P3-10): canonical URL + description for public threads; threads in a
// private/hidden board are excluded from indexing (defence in depth — the read
// gate already blocks crawlers).
$this->section('canonical', '/t/' . (int) $thread['id'] . '-' . $thread['slug']);
$threadPageUrl = '/t/' . (int) $thread['id'] . '-' . $thread['slug'] . '?page=' . max(1, (int) $page);
$this->section('og_type', 'article');
$this->section('description', mb_strimwidth(preg_replace('/\s+/', ' ', (string) $thread['title']) ?? '', 0, 160, '…'));
if (($thread['board_visibility'] ?? 'public') !== 'public') {
    $this->section('robots', 'noindex, nofollow');
}
// One expression, two consumers: the Topic tools drawer's Living Brief section
// and the memory slot below. The drawer's only content is an anchor into markup
// that the slot alone renders, so the two must not be able to drift apart.
$canCurateMemory = $current_user !== null && !empty($can_write) && !empty($can_curate_memory);
$topicToolSections = [
    'watch' => $current_user !== null && !empty($can_write) && (($notifications_on ?? false) || ($workflow_on ?? false)),
    'standing' => $current_user !== null && ($workflow_on ?? false),
    'tags' => $current_user !== null && ($tags_on ?? false) && (!empty($thread_tags) || !empty($can_edit_tags)),
    'memory' => $canCurateMemory,
    'management' => $current_user !== null && !empty($can_write) && (
        !empty($can_self_assign) || !empty($can_staff_assign) || !empty($assignment)
        || !empty($can_mark_solved) || !empty($can_pin) || !empty($can_lock)
        || !empty($can_create_poll) || !empty($poll['can_close']) || !empty($can_split_merge)
        || !empty($can_move)
    ),
];
$hasTopicTools = in_array(true, $topicToolSections, true);
$status = ($workflow_on ?? false)
    ? (string) ($thread['status'] ?? 'open')
    : (($accepted_post_id ?? null) !== null ? 'solved' : null);
$statusLabel = $status !== null ? ($status_labels[$status] ?? ucwords(str_replace('_', ' ', $status))) : null;
// The standing chips are a row of their own, above the title. Precomputed here
// rather than tested with :empty, because the row's markup carries whitespace
// even when every chip is conditional-false and :empty would never match.
$hasStandingChips = (int) $thread['is_pinned'] === 1 || (int) $thread['is_locked'] === 1 || $status !== null;
// The reader's own watch, stated on the Topic tools pill rather than only inside
// the drawer (ThreadView.dc.html:187 `watchLabel`): the control that opens the
// drawer says what the drawer would tell you about the one setting a reader
// changes most. Absent for a guest, who has no watch to state.
$watchLabels = ['instant' => 'every reply', 'daily' => 'daily digest', 'off' => 'only when named'];
$watchLabel = ($notifications_on ?? false) && $current_user !== null
    ? ($watchLabels[(string) ($subscription['frequency'] ?? '')] ?? null)
    : null;
// Related topics are ONE row, after the stream, whether they came from the
// brief's own overlay or the deterministic fallback — the design has a single
// `Related` rail and no cards inside the brief (ThreadView.dc.html:659).
$relatedTopics = !empty($living_brief_related) ? $living_brief_related : ($related_fallback ?? []);
?>
<article class="thread thread-conversation thread-study" data-thread-study>
    <div class="thread-scroll">
    <header class="thread-head thread-study-head">
        <nav class="breadcrumb" aria-label="Breadcrumb"><a class="breadcrumb-back" href="/"><svg class="breadcrumb-back-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>Forum index</a><span class="breadcrumb-sep" aria-hidden="true">/</span><a class="breadcrumb-board" href="/c/<?= $e($thread['board_slug']) ?>"><span class="hash">#</span><?= $e($thread['board_name']) ?></a></nav>
        <?php /* Standing chips sit above the title, never inside it: an <h1> holding
                 three conditional chips announces itself as "Pinned Locked Solved Where
                 should ratified decisions live…" to anything that reads the page aloud.
                 Same rule the board row already follows for Pinned/Locked. Status is a
                 word and a colour — no glyph; the Topic tools drawer states the identical
                 state glyph-less, and two labels for one status is the defect. */ ?>
        <?php if ($hasStandingChips): ?>
        <div class="thread-study-chips">
            <?php if ((int) $thread['is_pinned'] === 1): ?><span class="thread-state-chip is-pinned">Pinned</span><?php endif; ?>
            <?php if ((int) $thread['is_locked'] === 1): ?><span class="thread-state-chip is-locked">Locked</span><?php endif; ?>
            <?php if ($status !== null): ?><span class="thread-status-chip is-<?= $e($status) ?>" data-thread-status="<?= $e($status) ?>"><?= $e($statusLabel) ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <h1 class="thread-study-title"><?= $e($thread['title']) ?></h1>
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
        <?php /* Two groups, one line. The row is flex-wrap: nowrap deliberately — a
                 wrapping flex container breaks its lines from the items' CONTENT widths
                 BEFORE flex-shrink applies, so a shrinkable byline on its own changes
                 nothing and the Star pill widening to "Starred" (~20px) shoved the whole
                 control group onto a second line. The identity side gives up width to an
                 ellipsis; the controls never do.

                 The identity side carries the byline and the roster and NOTHING else
                 (ThreadView.dc.html:164-181). It used to carry the tag chips, a visible
                 "In council" label and a Tended-by/Quiet-until group as well; with five
                 competing items on a nowrap row the one shrinkable item gave up all of
                 its width, and the topic's own byline rendered as "Opened by Erestor ·
                 5 repl". The tags moved to their own row below, the roster's label is
                 the stack's accessible name, the assignment is stated where it is
                 changed (the drawer's Topic management summary), and the snooze — which
                 is the reader's own, like the reply count beside it — folds into the
                 byline exactly as the design's `bylineTail` does. */ ?>
        <div class="thread-facts-identity">
        <p class="thread-byline"><?php if ($opAnon !== null): $ba = mask_author($thread['author_display_name'] ?? null, $thread['author_username'] ?? null, 'user', $opAnon); ?>Opened by <?= $e($ba['label']) ?> · <?php endif; ?><?php if (($thread['created_at'] ?? '') !== ''): ?><time datetime="<?= $e(iso_datetime($thread['created_at'])) ?>" title="<?= $e(human_datetime($thread['created_at'])) ?>"><?= $e(gmdate('M j', strtotime((string) $thread['created_at'] . ' UTC') ?: 0)) ?></time> · <?php endif; ?><?= $byReplies ?> repl<?= $byReplies === 1 ? 'y' : 'ies' ?><?php if (!empty($my_snooze)): ?> · Quiet until <?= $e(human_datetime($my_snooze)) ?><?php endif; ?></p>
        <?php // Participant avatar stack (§5.1): distinct non-anonymous authors, +N overflow. ?>
        <?php if (($participant_count ?? 0) >= 2 && !empty($participants)): ?>
            <span class="thread-participants-rule">
            <ul class="thread-participants" aria-label="In council">
                <?php foreach ($participants as $pp): ?>
                    <?php $pa = mask_author($pp['author_display_name'] ?? null, $pp['author_username'] ?? null, $pp['author_role'] ?? 'user', false); ?>
                    <li class="participant" title="<?= $e($pa['label']) ?>"><?= $this->partial('partials/monogram', ['name' => $pa['mono_name'], 'username' => $pa['mono_seed']]) ?></li>
                <?php endforeach; ?>
                <?php $shownParticipants = count($participants); if ((int) ($participant_count ?? 0) > $shownParticipants): ?>
                    <li class="participant-more">+<?= (int) $participant_count - $shownParticipants ?></li>
                <?php endif; ?>
            </ul>
            </span>
        <?php endif; ?>
        </div>
        <div class="thread-facts-actions">
        <?php if (($engagement ?? false) && $current_user !== null && !empty($can_write)): ?>
            <form class="inline star-form" method="post" action="/t/<?= (int) $thread['id'] ?>/star">
                <?= $this->csrfField() ?>
                <input type="hidden" name="return" value="<?= $e($threadPageUrl) ?>">
                <?php /* One esteem glyph in the system: the four-point commend star that
                         already marks regard and the accepted answer. ★/☆ beside ✦ was two
                         glyphs for one idea. The label keeps its own <span> so the button's
                         accessible name is exactly "Star" / "Starred". */ ?>
                <button class="linkbtn star-btn<?= ($is_starred ?? false) ? ' star-on' : '' ?>" type="submit" aria-pressed="<?= ($is_starred ?? false) ? 'true' : 'false' ?>"><?= $this->partial('partials/icon', ['name' => 'commend-star']) ?><span><?= ($is_starred ?? false) ? 'Starred' : 'Star' ?></span></button>
            </form>
        <?php endif; ?>
        <?php if ($hasTopicTools): ?>
            <button type="button" class="topic-tools-open" data-topic-tools-open hidden aria-controls="topic-tools-<?= (int) $thread['id'] ?>" aria-expanded="false"><?= $this->partial('partials/icon', ['name' => 'eight-point-star']) ?><span>Topic tools</span><?php if ($watchLabel !== null): ?><span class="topic-tools-watch">· <?= $e($watchLabel) ?></span><?php endif; ?></button>
        <?php endif; ?>
        </div>
        </div>
        <?php /* Tags are a row of their own, not passengers on the facts line. The
                 design keeps them out of the header entirely and reads them in the
                 drawer, but the drawer is signed-in only — a guest would lose every
                 route from a topic into /tags/{slug}, and those links are how a
                 forum's cross-board taxonomy is crawled at all. They keep the head,
                 one line lower, where nothing has to give up width for them. */ ?>
        <?php if (!empty($thread_tags)): ?>
        <div class="thread-study-tags"><?php foreach ($thread_tags as $tag): ?><a class="tag" href="/tags/<?= $e($tag['slug']) ?>"><?= $e($tag['name']) ?></a><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ($current_user === null): ?><?= $this->partial('partials/thread_status_history', compact('status_history', 'status_labels')) ?><?php endif; ?>
    </header>
    <?php
    /* FT-01. Topic tools and the split/merge disclosure belong to the topic head, and
       they must render INSIDE .thread-scroll. Every in-flow sibling of .thread-scroll
       takes its height out of the fixed-height column, so rendering them after
       .thread-dock left the unenhanced reading pane at 12px of an 854px viewport.
       They stay SIBLINGS here: nesting the restructure markup inside [data-topic-tools]
       would make app.js hide the dialog's own ancestor when opening it. */
    ?>
    <?= $this->partial('partials/thread_tools', [
        'thread' => $thread,
        'topic_tool_sections' => $topicToolSections,
        'subscription' => $subscription,
        'notifications_on' => $notifications_on,
        'workflow_on' => $workflow_on,
        'my_snooze' => $my_snooze,
        'is_staff' => !empty($can_pin) || !empty($can_lock) || !empty($can_split_merge) || !empty($can_move),
        'can_write' => $can_write,
        'can_change_statuses' => $can_change_statuses,
        'status_labels' => $status_labels,
        'status_history' => $status_history,
        'tags_on' => $tags_on,
        'thread_tags' => $thread_tags,
        'all_tags' => $all_tags,
        'can_edit_tags' => $can_edit_tags,
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
        'can_move' => $can_move,
        'move_boards' => $move_boards ?? [],
        'move_error' => $move_error ?? '',
        'move_selected' => $move_selected ?? 0,
    ]) ?>
    <?= $this->partial('partials/thread_restructure', [
        'thread' => $thread,
        'posts' => $posts,
        'features' => $features,
        'can_write' => $can_write,
        'can_split_merge' => $can_split_merge,
        'restructure_error' => $restructure_error ?? '',
        'restructure_context' => $restructure_context ?? '',
        'restructure_old' => $restructure_old ?? [],
        'page' => $page,
    ]) ?>
    <?php
    /* The poll and the living brief belong to the OPENING post: the opening post
       asked the question the poll puts to a vote, and the brief summarises that
       same question (ThreadView.dc.html:459-465 — `pollAfter`/`briefRegion` are
       both `raw.op`). They used to render above the stream, so every reader met a
       ballot and an AI-written summary before a single word of the topic. Both are
       built here, once, and echoed inside the stream after the opening post's row.

       A page that does not carry the opening post still gets them, at the head of
       its own stream. The design's stream is four posts long and its brief simply
       vanishes on page 2; on a topic with a hundred replies that would hide the one
       artifact written to spare a reader the backlog, from exactly the reader who
       is deepest in it. */
    $afterOpeningPost = '';
    if (!empty($polls_on) && !empty($poll)) {
        $afterOpeningPost .= $this->partial('partials/poll', ['poll' => $poll]);
    }
    $afterOpeningPost .= $this->partial('partials/thread_memory_slot', [
        'thread' => $thread,
        'living_brief' => $living_brief,
        'living_brief_sources' => $living_brief_sources,
        'can_curate_memory' => $canCurateMemory,
        'memory_automation_paused' => $memory_automation_paused,
        'memory_history' => $memory_history,
        'memory_refresh' => $memory_refresh,
    ]);
    $openingPostOnThisPage = false;
    foreach (($posts ?? []) as $streamPost) {
        if ((int) ($streamPost['is_op'] ?? 0) === 1) {
            $openingPostOnThisPage = true;
            break;
        }
    }
    ?>

    <?php // Catch me up leads the stream — it is the answer to the question a
          // returning reader arrives with, and it costs one line until asked. ?>
    <?php if (!empty($since_last_read_context)): ?>
        <?= $this->partial('partials/catch_up', ['since_last_read_context' => $since_last_read_context]) ?>
    <?php endif; ?>

    <?php if (!$openingPostOnThisPage): ?><?= $afterOpeningPost ?><?php $afterOpeningPost = ''; endif; ?>

    <?php if (empty($posts)): ?>
        <p class="muted empty">This thread has no visible posts.</p>
    <?php else: ?>
        <div class="post-stream">
            <?php $prevAuthorId = null; $prevAnon = true; $prevAt = 0; $previousDay = null; ?>
            <?php foreach ($posts as $p): ?>
                <?php $postDay = substr((string) $p['created_at'], 0, 10); ?>
                <?php if ($previousDay !== null && $postDay !== $previousDay): ?>
                    <div class="post-day-divider" data-post-day="<?= $e($postDay) ?>"><span></span><time datetime="<?= $e($postDay) ?>"><?= $e(gmdate('F j, Y', strtotime($postDay . ' UTC') ?: 0)) ?></time><span></span></div>
                <?php endif; ?>
                <?php $previousDay = $postDay; ?>
                <?php if ((int) ($p['is_deleted'] ?? 0) === 1): ?>
                    <?php // Staff-only restorable stub (ADMIN §3.3) — the with-deleted
                          // list variant only ever reaches staff. Clearing the author
                          // run is enough to break grouping for the next live reply. ?>
                    <?php $prevAuthorId = null; ?>
                    <?= $this->partial('partials/post_deleted', [
                        'p' => $p,
                        'can_restore_posts' => $can_restore_posts ?? false,
                    ]) ?>
                    <?php // A soft-deleted opening post still opens the topic, so the poll
                          // and the brief still follow it — a warden reading the staff
                          // variant sees them where every other reader does. ?>
                    <?php if ((int) $p['is_op'] === 1): ?><?= $afterOpeningPost ?><?php $afterOpeningPost = ''; endif; ?>
                    <?php continue; ?>
                <?php endif; ?>
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
                    'page' => $page,
                    'features' => $features ?? [],
                    'can_write' => $can_write ?? false,
                    'can_reply' => $can_reply ?? false,
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
                    'unread_count' => (int) ($since_last_read_context['post_count'] ?? 0),
                ]) ?>
                <?php if ((int) $p['is_op'] === 1): ?><?= $afterOpeningPost ?><?php $afterOpeningPost = ''; endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $this->partial('partials/pagination', [
        'page' => $page,
        'pages' => $pages,
        'base_url' => '/t/' . (int) $thread['id'] . '-' . $thread['slug'] . '?',
    ]) ?>

    <?php /* One Related row, after the stream (ThreadView.dc.html:659): where a
             reader who has finished the topic is, rather than in front of one who
             has not started it. It used to be two mutually exclusive blocks — a
             three-card grid inside the brief when there was one, a headed
             "Related topics" section above the stream when there was not — so the
             same idea arrived in two shapes at two ends of the page depending on a
             state the reader cannot see. The overlay's `reason` keeps its place as
             the chip's title; the label is the topic. */ ?>
    <?php if (!empty($relatedTopics)): ?>
        <nav class="thread-related" aria-label="Related topics">
            <span class="thread-related-label">Related</span>
            <?php foreach ($relatedTopics as $related): ?>
                <a class="thread-related-chip" href="<?= $e($related['url']) ?>"<?= ($related['reason'] ?? '') !== '' ? ' title="' . $e($related['reason']) . '"' : '' ?>><?= $e($related['title']) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    </div>

    <div class="thread-dock">
        <?php if ($locked): ?>
            <div class="joinbar">This thread is locked and is not accepting replies.</div>
        <?php elseif ($can_reply): ?>
            <?= $this->partial('partials/composer', [
                'thread' => $thread,
                'reply_errors' => $reply_errors,
                'reply_old' => $reply_old,
                'page' => $page,
                'show_avatars' => $show_avatars ?? true,
            ]) ?>
        <?php elseif ($current_user === null): ?>
            <div class="joinbar"><span>You're browsing as a guest — <em>log in to add your counsel.</em></span><a class="btn" href="/login?next=/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>">Log in</a></div>
        <?php elseif ($current_user !== null && !$current_user->isActive()): ?>
            <div class="joinbar">Your account cannot post right now.</div>
        <?php elseif ($current_user !== null): ?>
            <div class="joinbar">You don't have permission to reply in this board.</div>
        <?php endif; ?>
    </div>
</article>
