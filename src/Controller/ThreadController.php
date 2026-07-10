<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\BoardMemberRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
use App\Security\AuthorityGate;
use App\Security\BoardPolicy;
use App\Security\Cap;
use App\Security\WriteGate;
use App\Service\PreferenceService;
use App\Service\CommunityMemoryService;
use App\Service\ContentReferenceService;
use App\Service\CustomEmojiService;
use App\Service\LinkPreviewService;
use App\Service\ModerationService;
use App\Service\PollService;
use App\Service\ReactionService;
use App\Service\SinceLastReadContextService;
use App\Service\ThreadWorkflowService;
use App\Service\ThreadIntelligence\ThreadIntelligenceViewService;
use App\Support\Markdown;

/**
 * A thread (conversation): the paginated post stream plus the reply composer or
 * the guest join-bar. Canonical-URL 301s keep /t/{id} and stale slugs tidy.
 */
final class ThreadController extends Controller
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $redirectTarget = $this->container->get(ThreadRepository::class)->redirectTarget($id);
        if ($redirectTarget !== null) {
            return $this->redirect('/t/' . (int) $redirectTarget['id'] . '-' . (string) $redirectTarget['slug'], 301);
        }
        $thread = $this->loadReadableThread($id);

        // Canonicalise the URL (id+slug) for SEO and consistency.
        $expectedSlug = (string) $thread['slug'];
        $givenSlug = $params['slug'] ?? null;
        if ($givenSlug !== $expectedSlug) {
            $query = $request->query('page') !== null ? '?page=' . (int) $request->int('page', 1) : '';
            return $this->redirect('/t/' . $id . '-' . $expectedSlug . $query, 301);
        }

        return $this->renderThread($request, $thread);
    }

    /**
     * Render a thread page. Reused by PostController when a reply fails
     * validation so the typed text is preserved.
     *
     * @param array<string,mixed> $thread thread joined with board (findWithBoard)
     * @param array<string,mixed> $extra reply_errors / reply_old to repopulate
     */
    public function renderThread(Request $request, array $thread, array $extra = []): Response
    {
        $user = $this->currentUser();
        $prefSvc = $this->container->get(PreferenceService::class);
        $perPage = $user !== null
            ? $prefSvc->postsPerPage($user->id())
            : (int) $this->config()->get('pagination.posts_per_page', 20);
        // Reading display prefs (P3-01): avatar / signature / reaction visibility.
        $reading = $user !== null ? $prefSvc->reading($user->id()) : $prefSvc->readingDefaults();
        $postRepo = $this->container->get(PostRepository::class);
        $markdown = $this->container->get(Markdown::class);

        $total = $postRepo->countByThread((int) $thread['id']);
        $pages = max(1, (int) ceil($total / $perPage));
        // When re-rendering for a failed inline edit and the caller didn't pin an
        // explicit ?page, open the page that actually contains the edited post so
        // its re-opened edit form (with the rejected text) is on screen.
        $editPostId = (int) ($extra['edit_post_id'] ?? 0);
        $page = $editPostId > 0 && $request->query('page') === null
            ? min($pages, max(1, $postRepo->pageOfPost((int) $thread['id'], $editPostId, $perPage)))
            : min($pages, max(1, $request->int('page', 1)));

        $posts = $postRepo->listByThread((int) $thread['id'], $perPage, ($page - 1) * $perPage);

        // Topic-header participant stack (§5.1): the distinct visible authors, capped
        // to a handful of monograms with a "+N" overflow. Anonymous posters are
        // excluded by the repository so the stack can never deanonymise a masked author.
        $participants = $postRepo->participantsForThread((int) $thread['id'], 5);
        $participantCount = $postRepo->participantCountForThread((int) $thread['id']);

        $isMember = $user !== null
            && $this->container->get(BoardMemberRepository::class)->isMember((int) $thread['board_id'], $user->id());
        $locked = (int) $thread['is_locked'] === 1;
        // Shared for both posting-floor sites below (reply gate + tag member arm)
        // so a custom role's core.post.create/core.thread.tag grant is honored
        // under enforce (Phase 5 Inc 6 Task 5) without re-fetching from the container.
        $policy = $this->container->get(BoardPolicy::class);
        $gate = $this->container->get(AuthorityGate::class);
        $canReply = $user !== null
            && $this->container->get(WriteGate::class)->canWrite($user)
            && $gate->allows(
                fn (): bool => $policy->canPost(
                    ['visibility' => $thread['board_visibility'], 'post_min_role' => $thread['board_post_min_role'] ?? 'user'],
                    $user,
                    $isMember,
                ),
                $user,
                Cap::POST_CREATE,
                ['board_id' => (int) $thread['board_id']],
                'ThreadController::renderThread',
            )
            && !$locked;

        // Engagement: grouped reaction counts for the visible posts, the
        // viewer's own reactions, the star state, and advancing the read
        // position to the newest post shown on this page (P2-01/P2-02).
        $featureFlags = $this->container->get(FeatureFlags::class);
        $engagement = (bool) $featureFlags->enabled('engagement');
        $automatedContext = (bool) $featureFlags->enabled('automated_context');
        $postIds = array_map(static fn (array $p): int => (int) $p['id'], $posts);
        $reactionRepo = $this->container->get(ReactionRepository::class);
        $reactionCounts = $engagement ? $reactionRepo->countsForPosts($postIds) : [];
        $myReactions = [];
        $isStarred = false;
        $referenceCards = [];
        $linkPreviewCards = [];
        $sinceLastReadContext = null;
        $allowedEmoji = ReactionService::ALLOWED;
        if ($featureFlags->enabled('custom_emoji')) {
            $allowedEmoji = array_merge($allowedEmoji, $this->container->get(CustomEmojiService::class)->reactionShortcodes());
        }

        if ($featureFlags->enabled('content_references')) {
            $referenceCards = $this->container->get(ContentReferenceService::class)->cardsForSources('post', $postIds, $user);
        }
        if ($featureFlags->enabled('link_previews')) {
            $linkPreviewCards = $this->container->get(LinkPreviewService::class)->cardsForSources('post', $postIds);
        }
        if ($user !== null && $automatedContext) {
            $sinceLastReadContext = $this->container->get(SinceLastReadContextService::class)
                ->forThread($user->id(), (int) $thread['id']);
        }

        if ($user !== null) {
            $tuRepo = $this->container->get(ThreadUserRepository::class);
            if ($engagement) {
                $myReactions = $reactionRepo->userReactionsForPosts($user->id(), $postIds);
                $isStarred = $tuRepo->isStarred($user->id(), (int) $thread['id']);
            }
            if (($engagement || $automatedContext) && $postIds !== []) {
                $tuRepo->markRead($user->id(), (int) $thread['id'], max($postIds));
            }
        }

        if ($sinceLastReadContext !== null) {
            $threadUrl = '/t/' . (int) $thread['id'] . '-' . (string) $thread['slug'];
            foreach ($sinceLastReadContext['items'] as &$item) {
                $targetPage = $postRepo->pageOfPost((int) $thread['id'], (int) $item['post_id'], $perPage);
                if ($targetPage === $page) {
                    $item['url'] = '#p' . (int) $item['post_id'];
                    continue;
                }
                $item['url'] = $threadUrl . ($targetPage > 1 ? '?page=' . $targetPage : '') . '#p' . (int) $item['post_id'];
            }
            unset($item);
        }

        $canWriteUser = $user !== null
            && $this->container->get(WriteGate::class)->canWrite($user);
        // The moderation TOOLBAR must never gate an individual button on a coarse
        // "is a moderator" flag (Phase 5 Inc 6 Task 4b): a custom role holding only
        // one capability key must still see only its own control. Each button below
        // gets its own canModerate() check against its specific key; under
        // legacy/shadow mode AuthorityGate ignores the key entirely, so every
        // per-action flag collapses back to the same coarse boolean and an existing
        // board moderator/admin keeps seeing every control exactly as before (see
        // AuthorityGate::allows()).
        $canPin = $user !== null
            && $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id'], Cap::THREAD_PIN);
        $canLock = $user !== null
            && $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id'], Cap::THREAD_LOCK);
        // core.thread.move has no thread-view control to gate yet — moving a
        // thread to another board isn't reachable from templates/thread.php or
        // templates/partials/post.php today (only /admin/boards/{id}/move exists,
        // which is unrelated board-reordering, not this capability). Computed
        // here and exposed as can_move so a future move-thread control can
        // consume it without another controller change.
        $canMove = $user !== null
            && $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id'], Cap::THREAD_MOVE);
        $canSplitMerge = $user !== null
            && $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id'], Cap::THREAD_SPLIT_MERGE);
        $canDeletePosts = $user !== null
            && $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id'], Cap::POST_DELETE_ANY);

        // Accepted-answer ("solved") state (P2-09). The OP or a board moderator
        // may accept/clear an answer; everyone sees the accepted marker. The
        // moderator arm keys on core.thread.mark_solved — the exact capability
        // SolvedAnswerService::authorize enforces on the write path — not the
        // coarse delete_any flag, so a per-action deputy never sees an Accept
        // control their key cannot exercise (review V3). The OP arm below covers
        // the owner half of this dual-path capability.
        $community = (bool) $this->container->get(FeatureFlags::class)->enabled('community');
        $acceptedPostId = $thread['accepted_answer_post_id'] !== null ? (int) $thread['accepted_answer_post_id'] : null;
        $canMarkSolved = $community && $user !== null
            && $canWriteUser
            && (
                (int) $thread['user_id'] === $user->id()
                || $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id'], Cap::THREAD_MARK_SOLVED)
            );

        // Anonymous-author reveal (P2-08): an admin or this board's moderator may
        // unmask an anonymous post; the byline stays masked for everyone — the
        // reveal is a separate audited action.
        $canRevealAnon = $user !== null
            && $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id'], Cap::POST_REVEAL_AUTHOR);

        // Subscription state for the subscribe control (P2-03).
        $notificationsOn = (bool) $this->container->get(FeatureFlags::class)->enabled('notifications');
        $subscription = null;
        if ($notificationsOn && $user !== null) {
            $subscription = $this->container->get(SubscriptionRepository::class)
                ->effectiveForThread($user->id(), (int) $thread['id'], (int) $thread['board_id']);
        }

        // Phase 4 topic workflow: canonical status, personal snooze, and
        // optional board assignment are separate from pin/lock/solved chips.
        $workflowOn = (bool) $this->container->get(FeatureFlags::class)->enabled('topic_workflow');
        $statusLabels = ThreadWorkflowService::STATUSES;
        $statusHistory = [];
        $assignment = null;
        $canSelfAssign = false;
        $canStaffAssign = false;
        $canChangeStatuses = [];
        $mySnooze = null;
        if ($workflowOn) {
            $workflow = $this->container->get(ThreadWorkflowService::class);
            $statusHistory = $this->container->get(ThreadRepository::class)->statusHistory((int) $thread['id'], 5);
            $assignment = $workflow->currentAssignment((int) $thread['id']);
            if ($user !== null) {
                $canSelfAssign = $workflow->canSelfAssign($user, $thread);
                $canStaffAssign = $workflow->canStaffAssignThread($user, $thread);
                foreach (array_keys($statusLabels) as $status) {
                    $canChangeStatuses[$status] = $workflow->canChangeStatus($user, $thread, $status);
                }
                $myState = $this->container->get(ThreadUserRepository::class)->find($user->id(), (int) $thread['id']);
                $mySnooze = $myState['snoozed_until'] ?? null;
            }
        }

        $tagsOn = (bool) $this->container->get(FeatureFlags::class)->enabled('tags');
        $threadTags = [];
        $allTags = [];
        $canEditTags = false;
        if ($tagsOn && (int) ($thread['board_tags_enabled'] ?? 1) === 1) {
            $tagRepo = $this->container->get(TagRepository::class);
            $threadTags = $tagRepo->forThread((int) $thread['id']);
            if ($user !== null && $this->container->get(WriteGate::class)->canWrite($user)) {
                // Display mirrors TagController::updateThread one-to-one:
                // posting rights are the single tagging gate — no staff
                // carve-out ([STATE-KEEP]) — so there is exactly one arm. A
                // staff-only moderator arm here was a phantom control (shown,
                // then 403 on submit) and, paired with the user-baseline
                // core.thread.tag key, emitted a resolver shadow mismatch on
                // nearly every member thread view (V7).
                $canEditTags = $gate->allows(
                    fn (): bool => $policy->canPost(
                        [
                            'visibility' => $thread['board_visibility'],
                            'post_min_role' => $thread['board_post_min_role'] ?? 'user',
                            'is_archived' => $thread['board_is_archived'] ?? 0,
                        ],
                        $user,
                        $isMember,
                    ),
                    $user,
                    Cap::THREAD_TAG,
                    ['board_id' => (int) $thread['board_id']],
                    'ThreadController::renderThread',
                );
            }
            if ($canEditTags) {
                $allTags = $tagRepo->allEnabled();
            }
        }

        $memoryOn = (bool) $featureFlags->enabled('community_memory');
        $livingBrief = null;
        $livingBriefSources = [];
        $livingBriefRelated = [];
        $relatedFallback = [];
        $memoryHistory = [];
        $memoryRefresh = [];
        $memoryAutomationPaused = false;
        $canCurateMemory = false;
        $canCurateWiki = false;
        $wikiRevisions = [];
        if ($memoryOn) {
            $memory = $this->container->get(CommunityMemoryService::class);
            $memoryModel = $this->container->get(ThreadIntelligenceViewService::class)
                ->forThread((int) $thread['id'], $user);
            $livingBrief = $memoryModel['living_brief'];
            $livingBriefSources = $memoryModel['sources'];
            foreach ($livingBriefSources as &$source) {
                $source['url'] = $this->postLocation(
                    (int) $source['thread_id'],
                    (string) $source['thread_slug'],
                    (int) $source['id'],
                );
            }
            unset($source);
            $livingBriefRelated = $memoryModel['related'];
            foreach ($livingBriefRelated as &$related) {
                $related['url'] = '/t/' . (int) $related['thread_id'] . '-' . (string) $related['slug'];
            }
            unset($related);
            $relatedFallback = $memoryModel['fallback_related'];
            foreach ($relatedFallback as &$related) {
                $related['url'] = '/t/' . (int) $related['thread_id'] . '-' . (string) $related['slug'];
            }
            unset($related);
            $memoryHistory = $memoryModel['history'];
            $memoryRefresh = $memoryModel['refresh'];
            $memoryAutomationPaused = $memoryModel['automation_paused'];
            if ($livingBrief !== null && $featureFlags->enabled('content_references')) {
                $livingBrief['reference_cards'] = $this->container->get(ContentReferenceService::class)
                    ->cardsForSources('summary', [(int) $livingBrief['id']], $user)[(int) $livingBrief['id']] ?? [];
            }
            $canCurateMemory = $user !== null
                && $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id'], Cap::MEMORY_CURATE);
            $canCurateWiki = $canCurateMemory && (int) ($thread['board_wiki_enabled'] ?? 0) === 1;
            if ($canCurateWiki) {
                foreach ($posts as $post) {
                    if ((int) ($post['is_wiki'] ?? 0) === 1) {
                        $wikiRevisions[(int) $post['id']] = $memory->revisions((int) $post['id']);
                    }
                }
            }
        }

        $pollsOn = (bool) $this->container->get(FeatureFlags::class)->enabled('polls');
        $poll = null;
        $canCreatePoll = false;
        if ($pollsOn) {
            $pollService = $this->container->get(PollService::class);
            $poll = $pollService->forThread((int) $thread['id'], $user);
            $canCreatePoll = $poll === null
                && $user !== null
                && $this->container->get(WriteGate::class)->canWrite($user)
                && $pollService->canManageThread($user, $thread);
        }

        return $this->view('thread', array_merge([
            'thread' => $thread,
            'posts' => $posts,
            'participants' => $participants,
            'participant_count' => $participantCount,
            'markdown' => $markdown,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => $perPage,
            'can_reply' => $canReply,
            'locked' => $locked,
            'is_admin' => $user?->isAdmin() ?? false,
            'can_pin' => $canPin,
            'can_lock' => $canLock,
            'can_move' => $canMove,
            'can_split_merge' => $canSplitMerge,
            'can_delete_posts' => $canDeletePosts,
            'engagement' => $engagement,
            'show_signatures' => $reading['show_signatures'],
            'show_avatars' => $reading['show_avatars'],
            'show_reactions' => $reading['show_reactions'],
            'reaction_counts' => $reactionCounts,
            'my_reactions' => $myReactions,
            'allowed_emoji' => $allowedEmoji,
            'reference_cards' => $referenceCards,
            'link_preview_cards' => $linkPreviewCards,
            'since_last_read_context' => $sinceLastReadContext,
            'is_starred' => $isStarred,
            'community' => $community,
            'accepted_post_id' => $acceptedPostId,
            'can_mark_solved' => $canMarkSolved,
            'can_reveal_anon' => $canRevealAnon,
            'notifications_on' => $notificationsOn,
            'subscription' => $subscription,
            'workflow_on' => $workflowOn,
            'status_labels' => $statusLabels,
            'status_history' => $statusHistory,
            'assignment' => $assignment,
            'can_self_assign' => $canSelfAssign,
            'can_staff_assign' => $canStaffAssign,
            'can_change_statuses' => $canChangeStatuses,
            'my_snooze' => $mySnooze,
            'tags_on' => $tagsOn,
            'thread_tags' => $threadTags,
            'all_tags' => $allTags,
            'can_edit_tags' => $canEditTags,
            'memory_on' => $memoryOn,
            'living_brief' => $livingBrief,
            'living_brief_sources' => $livingBriefSources,
            'living_brief_related' => $livingBriefRelated,
            'related_fallback' => $relatedFallback,
            'memory_history' => $memoryHistory,
            'memory_refresh' => $memoryRefresh,
            'memory_automation_paused' => $memoryAutomationPaused,
            'can_curate_memory' => $canCurateMemory,
            'can_curate_wiki' => $canCurateWiki,
            'wiki_revisions_by_post' => $wikiRevisions,
            'polls_on' => $pollsOn,
            'poll' => $poll,
            'can_create_poll' => $canCreatePoll,
            'reply_errors' => [],
            'reply_old' => [],
            'edit_post_id' => 0,
            'edit_old' => '',
            'edit_error' => '',
        ], $extra));
    }

    /** @return array<string,mixed> */
    private function loadReadableThread(int $id): array
    {
        $thread = $this->container->get(ThreadRepository::class)->findWithBoard($id);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $policy = $this->container->get(BoardPolicy::class);
        $user = $this->currentUser();
        $isMember = $user !== null
            && $this->container->get(BoardMemberRepository::class)->isMember((int) $thread['board_id'], $user->id());
        if (!$policy->canRead(['visibility' => $thread['board_visibility']], $user, $isMember)) {
            throw new NotFoundException('Thread not found.');
        }
        // A held (pending) thread is not public yet: only its author or a
        // moderator of THIS board may load it (mirrors the held-media gate). P3-05.
        // Board-scoped canModerate() (not the site-wide core.content.view_pending
        // key): the legacy projection grants every global moderator a site-scoped
        // view_pending to match the bare isModerator() site probes at /mod/approvals
        // and the held-media view, and a site grant satisfies any board target — so
        // keying this board-scoped view on it would let an unassigned global
        // moderator open held threads they never could pre-cutover (review S1).
        if ((int) ($thread['is_pending'] ?? 0) === 1) {
            $isAuthor = $user !== null && $user->owns((int) $thread['user_id']);
            $canMod = $user !== null
                && $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id']);
            if (!$isAuthor && !$canMod) {
                throw new NotFoundException('Thread not found.');
            }
        }
        return $thread;
    }
}
