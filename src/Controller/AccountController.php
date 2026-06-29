<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\UserRepository;
use App\Service\AccountService;
use App\Service\MfaService;
use App\Service\ProfileMediaService;

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
                'avatar_path' => $row['avatar_path'] ?? '',
            ],
            'email' => $row['email'] ?? '',
            'email_verified' => ($row['email_verified_at'] ?? null) !== null,
            'profile_media' => $this->container->get(FeatureFlags::class)->enabled('profile_media'),
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
            $old = $e->old;
            if (!array_key_exists('avatar_path', $old)) {
                $old['avatar_path'] = $row['avatar_path'] ?? '';
            }
            return $this->view('account/settings', [
                'errors' => $e->errors,
                'old' => $old,
                'email' => $row['email'] ?? '',
                'email_verified' => ($row['email_verified_at'] ?? null) !== null,
                'profile_media' => $this->container->get(FeatureFlags::class)->enabled('profile_media'),
            ], 422);
        }
        return $this->redirectWithFlash('/settings/account', 'Your profile has been updated.');
    }

    public function uploadAvatar(Request $request, array $params): Response
    {
        $this->requireProfileMedia();
        $user = $this->requireUser();
        try {
            $this->container->get(ProfileMediaService::class)->uploadAvatar($user, $request->file('avatar'));
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/settings/account', $e->first());
        }
        return $this->redirectWithFlash('/settings/account', 'Avatar updated.');
    }

    public function removeAvatar(Request $request, array $params): Response
    {
        $this->requireProfileMedia();
        $this->container->get(ProfileMediaService::class)->removeAvatar($this->requireUser());
        return $this->redirectWithFlash('/settings/account', 'Avatar removed.');
    }

    /** @param array<string,string> $params */
    public function securityForm(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        return $this->securityView($user);
    }

    /** @param array<string,string> $params */
    public function updateSecurity(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        try {
            $this->container->get(AccountService::class)->changePassword($user, $request->allInput());
        } catch (ValidationException $e) {
            return $this->securityView($user, ['errors' => $e->errors], 422);
        }
        // A password change logs out every other session (SESS-1).
        $this->revokeOtherSessionsFor($user);
        return $this->redirectWithFlash('/settings/security', 'Your password has been changed.');
    }

    /** @param array<string,string> $params */
    public function startTotpEnrollment(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        try {
            $setup = $this->container->get(MfaService::class)
                ->startEnrollment($user, (string) $request->post('current_password', ''));
        } catch (ValidationException $e) {
            return $this->securityView($user, ['errors' => $e->errors], 422);
        }

        return $this->securityView($user, ['totp_setup' => $setup]);
    }

    /** @param array<string,string> $params */
    public function confirmTotpEnrollment(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        try {
            $codes = $this->container->get(MfaService::class)->confirmEnrollment(
                $user,
                (string) $request->post('current_password', ''),
                (string) $request->post('totp_code', ''),
            );
        } catch (ValidationException $e) {
            return $this->securityView($user, ['errors' => $e->errors], 422);
        }

        return $this->securityView($user, ['new_recovery_codes' => $codes]);
    }

    /** @param array<string,string> $params */
    public function rotateRecoveryCodes(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        try {
            $codes = $this->container->get(MfaService::class)
                ->rotateRecoveryCodes($user, (string) $request->post('current_password', ''));
        } catch (ValidationException $e) {
            return $this->securityView($user, ['errors' => $e->errors], 422);
        }

        return $this->securityView($user, ['new_recovery_codes' => $codes]);
    }

    /** @param array<string,string> $params */
    public function disableTotp(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        try {
            $this->container->get(MfaService::class)->disable(
                $user,
                (string) $request->post('current_password', ''),
                (string) $request->post('disable_code', ''),
            );
        } catch (ValidationException $e) {
            return $this->securityView($user, ['errors' => $e->errors], 422);
        }

        return $this->redirectWithFlash('/settings/security', 'Two-factor authentication has been disabled.');
    }

    /** @param array<string,mixed> $data */
    private function securityView(\App\Domain\User $user, array $data = [], int $status = 200): Response
    {
        return $this->view('account/security', array_replace([
            'errors' => [],
            'totp' => $this->container->get(MfaService::class)->status($user->id()),
            'totp_setup' => null,
            'new_recovery_codes' => [],
        ], $data), $status);
    }

    private function requireProfileMedia(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('profile_media')) {
            throw new NotFoundException('Not found.');
        }
    }
}
