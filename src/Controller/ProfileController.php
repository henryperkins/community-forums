<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\BadgeRepository;
use App\Repository\BlockRepository;
use App\Repository\FollowRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserProfileFieldRepository;
use App\Repository\UserRepository;
use App\Repository\UsernameHistoryRepository;
use App\Service\TitleService;
use App\Service\UserModerationService;
use App\Support\Markdown;

/**
 * Public profile (/u/{username}): identity, cosmetic title/rank, badges, reputation,
 * follower/following counts, activity, and the Follow / Message / Block
 * actions (COMMUNITY §8). Enforces profile visibility and blocks; email is never
 * shown to anyone. A renamed member's old handle 301-redirects to the new one.
 */
final class ProfileController extends Controller
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $username = $params['username'] ?? '';
        $profile = $this->resolveProfileOrRedirect($username);
        if ($profile instanceof Response) {
            return $profile;
        }

        $viewer = $this->currentUser();
        $profileId = (int) $profile['id'];
        $isSelf = $viewer !== null && $viewer->id() === $profileId;

        // Members-only profiles are hidden from guests (USER §4.7).
        if (!$isSelf && (string) ($profile['profile_visibility'] ?? 'public') === 'members' && $viewer === null) {
            return $this->view('profile/gated', [
                'username' => (string) $profile['username'],
            ], 200);
        }

        $community = $this->container->get(FeatureFlags::class)->enabled('community');
        $blocks = $this->container->get(BlockRepository::class);
        $blockedEither = $viewer !== null && !$isSelf && $blocks->blockedEitherWay($viewer->id(), $profileId);
        $viewerBlocksProfile = $viewer !== null && !$isSelf && $blocks->blocks($viewer->id(), $profileId);

        $follows = $this->container->get(FollowRepository::class);
        $titles = $this->container->get(TitleService::class);

        $bioHtml = '';
        if (is_string($profile['bio'] ?? null) && $profile['bio'] !== '') {
            $bioHtml = $this->container->get(Markdown::class)->render((string) $profile['bio']);
        }

        $threadRepo = $this->container->get(ThreadRepository::class);
        $postRepo = $this->container->get(PostRepository::class);

        // DM button: shown when DMs are on, the viewer isn't self/blocked, and the
        // target accepts DMs (final eligibility is still enforced on send).
        $allowDms = (string) ($profile['allow_dms'] ?? 'members');
        $canMessage = $viewer !== null && !$isSelf && !$blockedEither
            && $this->container->get(FeatureFlags::class)->enabled('dms')
            && $allowDms !== 'none';

        // Five real GET-backed tabs. Commends and Connections belong to the
        // community layer and fall back to Overview when that layer is disabled.
        $tabValue = $request->query('tab');
        $tab = is_string($tabValue) ? $tabValue : 'overview';
        if (!in_array($tab, ['overview', 'threads', 'posts', 'commends', 'connections'], true)) {
            $tab = 'overview';
        }
        if (!$community && ($tab === 'commends' || $tab === 'connections')) {
            $tab = 'overview';
        }
        // Match the existing /followers and /following privacy contract: a
        // blocked pair cannot use the combined Connections surface as a bypass.
        if ($tab === 'connections' && $blockedEither) {
            throw new NotFoundException('That member could not be found.');
        }

        $rawQuery = $request->query('q');
        $searchQuery = is_string($rawQuery) ? trim($rawQuery) : '';
        $sort = $request->query('sort') === 'commends' ? 'commends' : 'newest';
        $rawPage = $request->query('page');
        $page = max(1, is_scalar($rawPage) ? (int) $rawPage : 1);
        $perPage = 20;
        $listRows = [];
        $listTotal = 0;
        if ($tab === 'threads' || $tab === 'posts') {
            $listRepo = $tab === 'threads' ? $threadRepo : $postRepo;
            $listTotal = $listRepo->countByUser($profileId, $searchQuery);
            $page = min($page, max(1, (int) ceil($listTotal / $perPage)));
            $listRows = $listRepo->listByUser(
                $profileId,
                $sort,
                $searchQuery,
                $perPage,
                ($page - 1) * $perPage,
            );
        }

        $connMode = $request->query('c') === 'following' ? 'following' : 'followers';
        $rawConnQuery = $request->query('cq');
        $connQuery = is_string($rawConnQuery) ? trim($rawConnQuery) : '';
        $connList = [];
        if ($tab === 'connections') {
            $connList = $connMode === 'following'
                ? $follows->listFollowing($profileId, 100, 0, $connQuery)
                : $follows->listFollowers($profileId, 100, 0, $connQuery);
        }

        $canViewMemberRecord = $viewer !== null
            && !$isSelf
            && $this->container->get(UserModerationService::class)->canViewPanelFor($viewer, $profileId);

        return $this->view('profile/show', [
            'profile' => $profile,
            'tab' => $tab,
            'bio_html' => $bioHtml,
            'title' => $titles->resolve($profile['title'] ?? null, (int) $profile['reputation']),
            'badges' => $community ? $this->container->get(BadgeRepository::class)->forUser($profileId) : [],
            'follower_count' => $follows->followerCount($profileId),
            'following_count' => $follows->followingCount($profileId),
            'solved_count' => $this->container->get(UserRepository::class)->solvedAnswerCount($profileId),
            'recent_threads' => $tab === 'overview' ? $threadRepo->recentByUser($profileId, 5) : [],
            'recent_posts' => $tab === 'overview' ? $postRepo->recentByUser($profileId, 5) : [],
            'board_activity' => $tab === 'overview' ? $postRepo->boardActivityForUser($profileId, 4) : [],
            'top_commended' => $tab === 'commends' ? $postRepo->topCommendedByUser($profileId, 5) : [],
            'list_rows' => $listRows,
            'list_total' => $listTotal,
            'search_query' => $searchQuery,
            'sort' => $sort,
            'page' => $page,
            'page_count' => max(1, (int) ceil($listTotal / $perPage)),
            'conn_mode' => $connMode,
            'conn_query' => $connQuery,
            'conn_list' => $connList,
            'can_remove_followers' => $isSelf && $connMode === 'followers',
            'custom_fields' => $this->container->get(FeatureFlags::class)->enabled('custom_profile_fields')
                ? $this->container->get(UserProfileFieldRepository::class)->forUser($profileId)
                : [],
            'is_self' => $isSelf,
            'community' => $community,
            'can_follow' => $community && $viewer !== null && !$isSelf && !$blockedEither,
            'is_following' => $viewer !== null && !$isSelf && $follows->isFollowing($viewer->id(), $profileId),
            'can_message' => $canMessage,
            'can_block' => $viewer !== null && !$isSelf,
            'viewer_blocks_profile' => $viewerBlocksProfile,
            'blocked_either' => $blockedEither,
            'presence_online' => $this->presenceOnline($profile, $isSelf),
            'can_view_member_record' => $canViewMemberRecord,
            'profile_status' => (string) ($profile['status'] ?? 'active'),
            'profile_suspended_until' => $profile['suspended_until'] ?? null,
        ]);
    }

    /** Followers / following lists (COMMUNITY §8), subject to visibility + blocks. */
    public function followers(Request $request, array $params): Response
    {
        return $this->connections($request, $params, 'followers');
    }

    public function following(Request $request, array $params): Response
    {
        return $this->connections($request, $params, 'following');
    }

    /** @param array<string,string> $params */
    private function connections(Request $request, array $params, string $mode): Response
    {
        $profile = $this->resolveProfileOrRedirect($params['username'] ?? '');
        if ($profile instanceof Response) {
            return $profile;
        }
        $viewer = $this->currentUser();
        $profileId = (int) $profile['id'];
        $isSelf = $viewer !== null && $viewer->id() === $profileId;

        if (!$isSelf && (string) ($profile['profile_visibility'] ?? 'public') === 'members' && $viewer === null) {
            return $this->view('profile/gated', ['username' => (string) $profile['username']], 200);
        }
        if ($viewer !== null && !$isSelf && $this->container->get(BlockRepository::class)->blockedEitherWay($viewer->id(), $profileId)) {
            throw new NotFoundException('That member could not be found.');
        }

        $follows = $this->container->get(FollowRepository::class);
        $list = $mode === 'followers'
            ? $follows->listFollowers($profileId, 100)
            : $follows->listFollowing($profileId, 100);

        return $this->view('profile/connections', [
            'profile' => $profile,
            'mode' => $mode,
            'people' => $list,
            'can_remove_followers' => $isSelf && $mode === 'followers',
        ]);
    }

    /**
     * Resolve a username to a profile row, or a 301 Response to the member's
     * current handle if the requested one was a former username. Throws 404 when
     * neither matches.
     *
     * @return array<string,mixed>|Response
     */
    private function resolveProfileOrRedirect(string $username): array|Response
    {
        $users = $this->container->get(UserRepository::class);
        $profile = $users->findByUsername($username);
        if ($profile !== null) {
            return $profile;
        }

        $formerOwner = $this->container->get(UsernameHistoryRepository::class)->currentUserIdForOldUsername($username);
        if ($formerOwner !== null) {
            $current = $users->find($formerOwner);
            if ($current !== null && (string) $current['username'] !== $username) {
                return $this->redirect('/u/' . (string) $current['username'], 301);
            }
        }
        throw new NotFoundException('That member could not be found.');
    }

    /** @param array<string,mixed> $profile */
    private function presenceOnline(array $profile, bool $isSelf): bool
    {
        if (!$isSelf && (int) ($profile['show_presence'] ?? 1) !== 1) {
            return false;
        }
        $lastSeen = $profile['last_seen_at'] ?? null;
        if (!is_string($lastSeen) || $lastSeen === '') {
            return false;
        }
        $ts = strtotime($lastSeen . ' UTC');
        $window = (int) $this->config()->get('presence.online_window_seconds', 300);
        return $ts !== false && $ts >= time() - $window;
    }
}
