<?php

declare(strict_types=1);
namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionService;
use App\Service\ThreadReadService;

final class SubscriptionController extends Controller
{
    public function updateOwned(Request $request, array $params): Response
    {
        $user = $this->requireNotifications();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(SubscriptionService::class)->updateOwned($user, $id, $request->allInput());
        } catch (ValidationException $e) {
            return $this->settingsError($user, $e, $id);
        }
        return $this->redirectWithFlash('/settings/notifications', 'Subscription updated.');
    }

    public function subscribeThread(Request $request, array $params): Response
    {
        return $this->updateTarget($request, 'thread', (int) ($params['id'] ?? 0));
    }

    public function subscribeBoard(Request $request, array $params): Response
    {
        return $this->updateTarget($request, 'board', (int) ($params['id'] ?? 0));
    }

    private function updateTarget(Request $request, string $type, int $id): Response
    {
        $user = $this->requireNotifications();
        $service = $this->container->get(SubscriptionService::class);
        try {
            $service->updateTarget($user, $type, $id, $request->allInput());
        } catch (ValidationException $e) {
            if ($type === 'thread') {
                try {
                    $thread = $this->container->get(ThreadReadService::class)->loadForUser($user, $id);
                    return (new ThreadController($this->container))->renderThread($request, $thread, [
                        'subscription_errors' => $e->errors, 'subscription_old' => $e->old,
                    ])->withStatus(422);
                } catch (NotFoundException) { /* Retain the draft without leaking target details. */ }
            }
            $row = $this->container->get(SubscriptionRepository::class)->get($user->id(), $type, $id);
            return $this->settingsError($user, $e, (int) ($row['id'] ?? 0), ['type' => $type, 'id' => $id]);
        }
        try { $url = $service->targetUrl($user, $type, $id); }
        catch (NotFoundException) { $url = '/settings/notifications'; }
        return $this->redirectWithFlash($url, 'Subscription updated.');
    }

    private function settingsError(User $user, ValidationException $e, int $id, ?array $target = null): Response
    {
        return (new SettingsController($this->container))->notificationsView($user, [
            'subscription_errors' => $e->errors, 'subscription_old' => $e->old,
            'subscription_error_id' => $id, 'subscription_target' => $target,
        ], 422);
    }

    private function requireNotifications(): User
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('notifications')) { throw new NotFoundException('Not found.'); }
        return $this->requireUser();
    }
}
