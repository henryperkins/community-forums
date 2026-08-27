<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Service\PresenceService;

/**
 * Privacy-respecting presence roster (P2-11). A short-poll JSON endpoint listing
 * members seen within the online window who have presence ENABLED. A hidden user
 * (show_presence = 0) never appears in the roster, count, or detail; a signed-in
 * viewer and anyone in a block relationship with them are excluded. Heartbeats
 * are written by the kernel on normal requests — no separate write here.
 */
final class PresenceController extends Controller
{
    public function page(Request $request): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('presence')) {
            throw new NotFoundException('Not found.');
        }
        return $this->view('users_online', [
            'online' => $this->container->get(PresenceService::class)->roster($this->currentUser()),
        ]);
    }

    public function index(Request $request): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('presence')) {
            throw new NotFoundException('Not found.');
        }
        $online = $this->container->get(PresenceService::class)
            ->roster($this->currentUser());

        return Response::json(['count' => count($online), 'online' => $online]);
    }
}
