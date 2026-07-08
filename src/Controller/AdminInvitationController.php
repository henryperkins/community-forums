<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\HttpException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardRepository;
use App\Service\InvitationService;
use App\Service\RateLimitService;

/**
 * Operator console for the invitation lifecycle (P5-13), behind the dark
 * `invitations` flag. Issuance is admin-only (TM-IN-07) and rate-limited.
 * The raw token is rendered DIRECTLY in the create response — exactly once,
 * never via the cookie-backed Flash (which would leak it into a Set-Cookie
 * header; AdminApiTokenController precedent). A reload re-POSTs (issues a
 * fresh invitation) — the accepted minor wart of that pattern.
 */
final class AdminInvitationController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->consoleView();
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(RateLimitService::class)->enforce('invite_create', $request, $admin);
        } catch (HttpException) {
            return $this->consoleView(
                ['create' => 'Too many invitations created just now. Please wait before issuing more.'],
                $this->oldCreate($request),
                429,
            );
        }

        try {
            $result = $this->container->get(InvitationService::class)->create($admin, $request->allInput());
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $e->old, 422);
        }

        $base = rtrim((string) $this->container->get(Config::class)->get('app.url', ''), '/');
        return $this->consoleView([], [], 200, [
            'token' => $result['token'],
            'url' => $base . '/invite/' . $result['token'],
        ]);
    }

    /** @param array<string,string> $params */
    public function revoke(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $this->container->get(InvitationService::class)->revoke($admin, (int) ($params['id'] ?? 0));
        return $this->noindex($this->redirectWithFlash('/admin/invitations', 'Invitation revoked.'));
    }

    // ---- internals ---------------------------------------------------------

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('invitations')) {
            throw new NotFoundException('Not found.');
        }
    }

    /** @return array<string,string> */
    private function oldCreate(Request $request): array
    {
        return [
            'email' => $request->str('email'),
            'domain' => $request->str('domain'),
            'max_uses' => $request->str('max_uses'),
            'expires_in_days' => $request->str('expires_in_days'),
            'onboarding_board_id' => $request->str('onboarding_board_id'),
        ];
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     * @param array{token:string,url:string}|null $newInvitation
     */
    private function consoleView(array $errors = [], array $old = [], int $status = 200, ?array $newInvitation = null): Response
    {
        return $this->noindex($this->view('admin/invitations', [
            'rows' => $this->container->get(InvitationService::class)->list(),
            'boards' => $this->container->get(BoardRepository::class)->allOrdered(),
            'errors' => $errors,
            'old' => $old,
            'new_invitation' => $newInvitation,
        ], $status));
    }

    private function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
    }
}
