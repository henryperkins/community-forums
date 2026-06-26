<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\UserRepository;
use App\Service\AccountService;

/**
 * Self-serve account settings: profile basics (display name, bio, location) and
 * password change. Owner-only — guests are bounced to login by requireUser().
 */
final class AccountController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireUser();
        return $this->redirect('/settings/account');
    }

    /** @param array<string,string> $params */
    public function accountForm(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $row = $this->container->get(UserRepository::class)->find($user->id()) ?? [];
        return $this->view('account/settings', [
            'errors' => [],
            'old' => [
                'display_name' => $row['display_name'] ?? '',
                'bio' => $row['bio'] ?? '',
                'location' => $row['location'] ?? '',
                'website' => $row['website'] ?? '',
                'pronouns' => $row['pronouns'] ?? '',
                'signature' => $row['signature'] ?? '',
            ],
            'email' => $row['email'] ?? '',
        ]);
    }

    /** @param array<string,string> $params */
    public function updateAccount(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        try {
            $this->container->get(AccountService::class)->updateProfile($user, $request->allInput());
        } catch (ValidationException $e) {
            $row = $this->container->get(UserRepository::class)->find($user->id()) ?? [];
            return $this->view('account/settings', [
                'errors' => $e->errors,
                'old' => $e->old,
                'email' => $row['email'] ?? '',
            ], 422);
        }
        return $this->redirectWithFlash('/settings/account', 'Your profile has been updated.');
    }

    /** @param array<string,string> $params */
    public function securityForm(Request $request, array $params): Response
    {
        $this->requireUser();
        return $this->view('account/security', ['errors' => []]);
    }

    /** @param array<string,string> $params */
    public function updateSecurity(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        try {
            $this->container->get(AccountService::class)->changePassword($user, $request->allInput());
        } catch (ValidationException $e) {
            return $this->view('account/security', ['errors' => $e->errors], 422);
        }
        // A password change logs out every other session (SESS-1).
        $this->revokeOtherSessionsFor($user);
        return $this->redirectWithFlash('/settings/security', 'Your password has been changed.');
    }
}
