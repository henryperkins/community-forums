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
use App\Repository\UserRepository;
use App\Repository\UsernameHistoryRepository;
use App\Service\TitleService;
use App\Support\Markdown;

/**
 * Public profile (/u/{username}): identity, cosmetic title/rank, badges, reputation,
 * follower/following counts, activity, and the Follow / Message / Block / Report
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

        // Profile activity tabs (§5.4): Overview / Threads / Posts / Commends, each
        // a real ?tab= URL so the view works without JS and is crawlable. Unknown
        // values fall back to Overview.
        $tab = (string) ($request->query('tab') ?? 'overview');
        if (!in_array($tab, ['overview', 'threads', 'posts', 'commends'], true)) {
            $tab = 'overview';
        }

        return $this->view('profile/show', [
            'profile' => $profile,
            'tab' => $tab,
            'bio_html' => $bioHtml,
            'title' => $titles->resolve($profile['title'] ?? null, (int) $profile['reputation']),
            'badges' => $community ? $this->container->get(BadgeRepository::class)->forUser($profileId) : [],
            'follower_count' => $follows->followerCount($profileId),
            'following_count' => $follows->followingCount($profileId),
            'solved_count' => $this->container->get(UserRepository::class)->solvedAnswerCount($profileId),
            'recent_threads' => $threadRepo->recentByUser($profileId, 20),
            'recent_posts' => $postRepo->recentByUser($profileId, 20),
            'is_self' => $isSelf,
            'community' => $community,
            'can_follow' => $community && $viewer !== null && !$isSelf && !$blockedEither,
            'is_following' => $viewer !== null && !$isSelf && $follows->isFollowing($viewer->id(), $profileId),
            'can_message' => $canMessage,
            'can_block' => $viewer !== null && !$isSelf,
            'viewer_blocks_profile' => $viewerBlocksProfile,
            'blocked_either' => $blockedEither,
            'presence_online' => $this->presenceOnline($profile, $isSelf),
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
