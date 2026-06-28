<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\UserRepository;
use App\Service\ReputationLedgerService;
use App\Service\TitleService;

/**
 * All-time Top Contributors (COMMUNITY §7). A page you choose to visit — never a
 * banner — ranking members by canonical reputation. It excludes opted-out members
 * and banned accounts, and grants nothing: reputation is cosmetic.
 */
final class LeaderboardController extends Controller
{
    public function index(Request $request): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('community')) {
            throw new NotFoundException('Not found.');
        }

        $size = (int) $this->config()->get('community.leaderboard_size', 50);
        $flags = $this->container->get(FeatureFlags::class);
        $ledgerOn = $flags->enabled('reputation_ledger');
        $window = (string) $request->query('window', 'all');
        if (!in_array($window, ['week', 'month', 'all'], true)) {
            $window = 'all';
        }
        $boardId = $request->query('board_id') !== null ? max(1, $request->int('board_id', 0)) : null;
        if (!$ledgerOn) {
            $window = 'all';
            $boardId = null;
        }
        $rows = $window === 'all' && $boardId === null
            ? $this->container->get(UserRepository::class)->leaderboard($size)
            : $this->container->get(ReputationLedgerService::class)->leaderboard($window, $boardId, $size);
        $titles = $this->container->get(TitleService::class);

        $ranked = [];
        $rank = 0;
        foreach ($rows as $r) {
            $rank++;
            $ranked[] = [
                'rank' => $rank,
                'username' => (string) $r['username'],
                'display_name' => ($r['display_name'] ?? '') !== '' ? (string) $r['display_name'] : (string) $r['username'],
                'reputation' => (int) $r['reputation'],
                'post_count' => (int) $r['post_count'],
                'title' => $titles->resolve($r['title'] ?? null, (int) $r['reputation']),
            ];
        }

        return $this->view('leaderboard', ['ranked' => $ranked, 'window' => $window, 'board_id' => $boardId, 'ledger_on' => $ledgerOn]);
    }
}
