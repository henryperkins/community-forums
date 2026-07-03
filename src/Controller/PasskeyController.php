<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\HttpException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Security\WebAuthn\WebAuthnException;
use App\Service\PasskeyService;
use App\Service\RateLimitService;

final class PasskeyController extends Controller
{
    /** @param array<string,string> $params */
    public function challenge(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $this->gate();
        $svc = $this->container->get(PasskeyService::class);
        $binding = PasskeyService::sessionBinding($this->session());

        try {
            $this->container->get(RateLimitService::class)->enforce('mfa_settings', $request, $user);
        } catch (HttpException $e) {
            return $this->jsonRateLimit($e);
        }

        try {
            $svc->assertFreshFactor($user, $this->str($request, 'current_password'), $this->str($request, 'passkey_assertion'), $binding);
            $options = $svc->beginRegistration($user, $binding);
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()]], $e->statusCode());
        } catch (ValidationException $e) {
            return Response::json(['ok' => false, 'errors' => $e->errors], 422);
        } catch (WebAuthnException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()], 'code' => $e->code], 422);
        }

        return Response::json(['ok' => true, 'options' => $options]);
    }

    /** @param array<string,string> $params */
    public function store(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $this->gate();

        try {
            $this->container->get(RateLimitService::class)->enforce('mfa_settings', $request, $user);
        } catch (HttpException $e) {
            return $this->jsonRateLimit($e);
        }

        try {
            $this->container->get(PasskeyService::class)->completeRegistration(
                $user,
                PasskeyService::sessionBinding($this->session()),
                (string) ($this->str($request, 'credential') ?? ''),
                $this->str($request, 'nickname'),
            );
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()]], $e->statusCode());
        } catch (ValidationException $e) {
            return Response::json(['ok' => false, 'errors' => $e->errors], 422);
        } catch (WebAuthnException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()], 'code' => $e->code], 422);
        }

        $this->revokeOtherSessionsFor($user);
        return Response::json(['ok' => true]);
    }

    /** @param array<string,string> $params */
    public function stepUpChallenge(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $this->gate();

        try {
            $this->container->get(RateLimitService::class)->enforce('mfa_settings', $request, $user);
        } catch (HttpException $e) {
            return $this->jsonRateLimit($e);
        }

        try {
            $options = $this->container->get(PasskeyService::class)
                ->beginStepUp($user, PasskeyService::sessionBinding($this->session()));
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()]], $e->statusCode());
        } catch (WebAuthnException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()], 'code' => $e->code], 422);
        }

        return Response::json(['ok' => true, 'options' => $options]);
    }

    private function str(Request $request, string $key): ?string
    {
        $value = $request->post($key);
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function jsonRateLimit(HttpException $e): Response
    {
        return Response::json(['ok' => false, 'errors' => ['rate_limit' => $e->getMessage()]], $e->statusCode());
    }

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('passkeys')) {
            throw new NotFoundException('Not found.');
        }
    }
}
