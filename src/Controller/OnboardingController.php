<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Repository\UserRepository;

/**
 * Product-tour state (P3-11). Completion is persisted server-side so it carries
 * across devices; replay clears it. The tour itself is pure progressive
 * enhancement (tour.js) — these endpoints just record the flag, and the forum is
 * fully usable when JavaScript is disabled or the tour script fails.
 */
final class OnboardingController extends Controller
{
    public function complete(Request $request): Response
    {
        $user = $this->requireUser();
        $this->container->get(UserRepository::class)->setOnboarded($user->id(), true);
        if ($request->wantsJson()) {
            return Response::json(['ok' => true]);
        }
        return $this->redirect($this->safeNext($request->str('next')));
    }

    /** Only same-origin root-relative paths are accepted; everything else → '/'. */
    private function safeNext(string $next): string
    {
        if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_starts_with($next, '/\\')) {
            return '/';
        }
        return $next;
    }

    public function replay(Request $request): Response
    {
        $user = $this->requireUser();
        $this->container->get(UserRepository::class)->setOnboarded($user->id(), false);
        if ($request->wantsJson()) {
            return Response::json(['ok' => true]);
        }
        return $this->redirectWithFlash('/', 'The welcome tour will play again on your next page.');
    }
}
