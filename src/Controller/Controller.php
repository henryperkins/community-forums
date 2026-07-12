<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Config;
use App\Core\Container;
use App\Core\FeatureFlags;
use App\Core\Flash;
use App\Core\ForbiddenException;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\View;
use App\Domain\User;
use App\Repository\PostRepository;
use App\Repository\ServerDraftRepository;
use App\Security\Session;
use App\Service\PreferenceService;

/**
 * Base controller: thin helpers for resolving services, rendering views,
 * redirecting, and enforcing auth. Controllers stay free of wiring.
 */
abstract class Controller
{
    public function __construct(protected Container $container)
    {
    }

    protected function request(): Request
    {
        return $this->container->get('request');
    }

    protected function session(): Session
    {
        return $this->container->get(Session::class);
    }

    protected function flash(): Flash
    {
        return $this->container->get(Flash::class);
    }

    protected function config(): Config
    {
        return $this->container->get(Config::class);
    }

    protected function currentUser(): ?User
    {
        return $this->session()->user();
    }

    /** Default destination after a successful authentication journey. */
    protected function authenticatedHome(): string
    {
        return '/inbox';
    }

    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $html = $this->container->get(View::class)->render($template, $data);
        return Response::html($html, $status);
    }

    protected function redirect(string $to, int $status = 303): Response
    {
        return Response::redirect($to, $status);
    }

    /**
     * Exclude a response from indexing — admin consoles and credential/
     * invitation-bearing surfaces (PHASE_5_PLAN §103). Hoisted here from nine
     * per-console copies (Inc 8 deferred follow-up, resolved Inc 9).
     */
    protected function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
    }

    protected function redirectWithFlash(string $to, string $message): Response
    {
        $this->flash()->add($message);
        return $this->redirect($to);
    }

    /**
     * Canonical location of a post within its thread, including the page it
     * falls on, so a no-JS redirect after a write lands on the right page and
     * its #anchor resolves (a paginated thread otherwise dropped the viewer on
     * page 1 where the anchor doesn't exist). Uses the viewer's own
     * posts-per-page preference — the same pagination the thread render uses —
     * falling back to the site default for guests.
     */
    protected function postLocation(int $threadId, string $slug, int $postId): string
    {
        $user = $this->currentUser();
        $perPage = $user !== null
            ? $this->container->get(PreferenceService::class)->postsPerPage($user->id())
            : (int) $this->config()->get('pagination.posts_per_page', 20);
        $page = $this->container->get(PostRepository::class)->pageOfPost($threadId, $postId, $perPage);
        return '/t/' . $threadId . '-' . $slug . ($page > 1 ? '?page=' . $page : '') . '#p' . $postId;
    }

    protected function discardServerDraftFor(User $user, string $contextKey): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('server_drafts')) {
            return;
        }
        try {
            $this->container->get(ServerDraftRepository::class)->discardByContext($user->id(), $contextKey);
        } catch (ValidationException) {
            // Best-effort cleanup only. Successful writes should not fail if the
            // draft surface cannot be trimmed.
        }
    }

    /** Require an authenticated user, otherwise bounce to the login page. */
    protected function requireUser(): User
    {
        $user = $this->currentUser();
        if ($user === null) {
            $next = $this->request()->path();
            throw new HttpException(302, 'Please log in to continue.', '/login?next=' . rawurlencode($next));
        }
        return $user;
    }

    protected function requireAdmin(): User
    {
        $user = $this->requireUser();
        if (!$user->isAdmin()) {
            throw new ForbiddenException('Administrator access required.');
        }
        return $user;
    }

    /**
     * Revoke every other session for the user, keeping the current one. Called
     * after a credential change so a parallel or hijacked session cannot survive
     * (USER §3.3).
     */
    protected function revokeOtherSessionsFor(User $user): void
    {
        $current = $this->session()->currentSessionId();
        if ($current !== null) {
            $this->container->get(\App\Repository\SessionRepository::class)
                ->revokeOthersForUser($user->id(), $current);
        }
    }
}
