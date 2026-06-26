<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\BlockRepository;
use App\Repository\UserRepository;

/**
 * Privacy-respecting presence roster (P2-11). A short-poll JSON endpoint listing
 * members seen within the online window who have presence ENABLED. A hidden user
 * (show_presence = 0) never appears in the roster, count, or detail; the viewer
 * and anyone in a block relationship with them are excluded. Heartbeats are
 * written by the kernel on normal requests — no separate write here.
 */
final class PresenceController extends Controller
{
    public function index(Request $request): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('presence')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();

        $window = (int) $this->config()->get('presence.online_window_seconds', 300);
        $since = gmdate('Y-m-d H:i:s', time() - $window);

        $rows = $this->container->get(UserRepository::class)->onlineSince($since);

        // Drop the viewer themselves and anyone blocked either way.
        $ids = array_map(static fn (array $r): int => $r['id'], $rows);
        $blocked = $this->container->get(BlockRepository::class)->blockedMap($user->id(), $ids);

        $online = [];
        foreach ($rows as $r) {
            if ($r['id'] === $user->id() || isset($blocked[$r['id']])) {
                continue;
            }
            $online[] = [
                'username' => $r['username'],
                'display_name' => ($r['display_name'] ?? '') !== '' ? $r['display_name'] : $r['username'],
                'last_seen_at' => $r['last_seen_at'],
            ];
        }

        return Response::json(['count' => count($online), 'online' => $online]);
    }
}
