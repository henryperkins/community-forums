<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\UserRepository;
use App\Repository\UserProfileFieldRepository;
use App\Service\AccountLifecycleService;
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
        $customEnabled = $this->container->get(FeatureFlags::class)->enabled('custom_profile_fields');
        return $this->view('account/settings', [
            'errors' => [],
            'old' => $this->accountOld($row, $customEnabled ? $this->container->get(UserProfileFieldRepository::class)->forUser($user->id()) : []),
            'email' => $row['email'] ?? '',
            'email_verified' => ($row['email_verified_at'] ?? null) !== null,
            'profile_media' => $this->container->get(FeatureFlags::class)->enabled('profile_media'),
            'custom_profile_fields' => $customEnabled,
        ]);
    }

    /** @param array<string,mixed> $row @param array<int,array{label:string,value:string,position:int}> $custom */
    private function accountOld(array $row, array $custom = []): array
    {
        $old = [
                'display_name' => $row['display_name'] ?? '',
                'bio' => $row['bio'] ?? '',
                'location' => $row['location'] ?? '',
                'website' => $row['website'] ?? '',
                'pronouns' => $row['pronouns'] ?? '',
                'signature' => $row['signature'] ?? '',
                'avatar_path' => $row['avatar_path'] ?? '',
        ];
        foreach ($custom as $index => $field) {
            $n = $index + 1;
            if ($n > 3) {
                break;
            }
            $old['custom_label_' . $n] = $field['label'];
            $old['custom_value_' . $n] = $field['value'];
        }
        return $old;
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
                'custom_profile_fields' => $this->container->get(FeatureFlags::class)->enabled('custom_profile_fields'),
            ], 422);
        }
        return $this->redirectWithFlash('/settings/account', 'Your profile has been updated.');
    }

    /** @param array<string,string> $params */
    public function exportAccount(Request $request, array $params): Response
    {
        $this->requireAccountLifecycle();
        $user = $this->requireUser();
        $payload = $this->container->get(AccountLifecycleService::class)->export($user);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="retroboards-account-export.json"',
        ]);
    }

    /** @param array<string,string> $params */
    public function lifecycleForm(Request $request, array $params): Response
    {
        $this->requireAccountLifecycle();
        $user = $this->requireUser();
        return $this->lifecycleView($user);
    }

    /** @param array<string,string> $params */
    public function deactivate(Request $request, array $params): Response
    {
        $this->requireAccountLifecycle();
        $user = $this->requireUser();
        try {
            $this->container->get(AccountLifecycleService::class)->deactivate(
                $user,
                (string) $request->post('current_password', ''),
                $this->session()->currentSessionId(),
            );
        } catch (ValidationException $e) {
            return $this->lifecycleView($user, ['errors' => $e->errors], 422);
        }
        return $this->redirectWithFlash('/settings/account/lifecycle', 'Your account has been deactivated. You can reactivate it from this page.');
    }

    /** @param array<string,string> $params */
    public function reactivate(Request $request, array $params): Response
    {
        $this->requireAccountLifecycle();
        $user = $this->requireUser();
        try {
            $this->container->get(AccountLifecycleService::class)->reactivate($user);
        } catch (ValidationException $e) {
            return $this->lifecycleView($user, ['errors' => $e->errors], 422);
        }
        return $this->redirectWithFlash('/settings/account/lifecycle', 'Your account has been reactivated.');
    }

    /** @param array<string,string> $params */
    public function requestDeletion(Request $request, array $params): Response
    {
        $this->requireAccountLifecycle();
        $user = $this->requireUser();
        try {
            $this->container->get(AccountLifecycleService::class)->requestDeletion(
                $user,
                (string) $request->post('current_password', ''),
                $this->session()->currentSessionId(),
            );
        } catch (ValidationException $e) {
            return $this->lifecycleView($user, ['errors' => $e->errors], 422);
        }
        return $this->redirectWithFlash('/settings/account/lifecycle', 'Account deletion requested. You can cancel during the 30-day grace period.');
    }

    /** @param array<string,string> $params */
    public function cancelDeletion(Request $request, array $params): Response
    {
        $this->requireAccountLifecycle();
        $user = $this->requireUser();
        try {
            $this->container->get(AccountLifecycleService::class)->cancelDeletion($user);
        } catch (ValidationException $e) {
            return $this->lifecycleView($user, ['errors' => $e->errors], 422);
        }
        return $this->redirectWithFlash('/settings/account/lifecycle', 'Account deletion request canceled.');
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

    /** @param array<string,mixed> $data */
    private function lifecycleView(\App\Domain\User $user, array $data = [], int $status = 200): Response
    {
        $row = $this->container->get(UserRepository::class)->find($user->id()) ?? [];
        return $this->view('account/lifecycle', array_replace([
            'errors' => [],
            'row' => $row,
            'pending_deletion' => $this->container->get(AccountLifecycleService::class)->pendingDeletion($user),
        ], $data), $status);
    }

    private function requireProfileMedia(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('profile_media')) {
            throw new NotFoundException('Not found.');
        }
    }

    private function requireAccountLifecycle(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('account_lifecycle')) {
            throw new NotFoundException('Not found.');
        }
    }
}
