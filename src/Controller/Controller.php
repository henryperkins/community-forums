<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Config;
use App\Core\Container;
use App\Core\Flash;
use App\Core\ForbiddenException;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\User;
use App\Security\Session;

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

    protected function redirectWithFlash(string $to, string $message): Response
    {
        $this->flash()->add($message);
        return $this->redirect($to);
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
