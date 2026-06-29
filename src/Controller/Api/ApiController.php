<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Controller;
use App\Core\FeatureFlags;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Security\ApiForbiddenException;
use App\Security\ApiPrincipal;
use App\Service\ApiTokenService;
use App\Service\RateLimitService;

/**
 * Base for /api/v1 controllers. Self-authenticates by Bearer and emits every
 * failure as JSON itself — the kernel (HTML errors, CSRF) is never reached for
 * a registered GET endpoint. Order: flag → authenticate → rate-limit → action.
 */
abstract class ApiController extends Controller
{
    /** @param callable(ApiPrincipal):Response $action */
    protected function respond(Request $request, callable $action): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('api_tokens')) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $service = $this->container->get(ApiTokenService::class);
        $principal = $service->authenticate((string) $request->header('Authorization'));
        if ($principal === null) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        try {
            $this->container->get(RateLimitService::class)->enforceSubject('api', $request, $principal->tokenHash());
        } catch (HttpException) {
            return Response::json(['error' => 'rate_limited'], 429);
        }
        try {
            return $action($principal);
        } catch (ApiForbiddenException $e) {
            $service->auditScopeDenied($principal, $e->scope());
            return Response::json(['error' => 'forbidden', 'scope' => $e->scope()], 403);
        }
    }

    protected function requireScope(ApiPrincipal $principal, string $scope): void
    {
        if (!$principal->hasScope($scope)) {
            throw new ApiForbiddenException($scope);
        }
    }
}
