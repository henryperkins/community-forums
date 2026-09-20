<?php

declare(strict_types=1);
namespace App\Service;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardRepository;
use App\Repository\SubscriptionRepository;
use App\Security\WriteGate;

/** Owner-only delivery reductions are available independently of content access. */
final class SubscriptionService
{
    public function __construct(
        private Database $db,
        private SubscriptionRepository $subscriptions,
        private NotificationVisibilityService $visibility,
        private ThreadReadService $threads,
        private BoardRepository $boards,
        private WriteGate $writeGate,
    ) {}

    public function listForUser(User $viewer): array
    {
        return $this->subscriptions->listForUserWithContext($viewer->id(), $this->visibility->scope($viewer));
    }

    public function updateOwned(User $viewer, int $subscriptionId, array $input): void
    {
        $this->db->transaction(function () use ($viewer, $subscriptionId, $input): void {
            $row = $this->subscriptions->findOwned($viewer->id(), $subscriptionId, true);
            if ($row === null) { throw new NotFoundException('Subscription not found.'); }
            $this->apply($viewer, (string) $row['target_type'], (int) $row['target_id'], $input, $row);
        });
    }

    public function updateTarget(User $viewer, string $targetType, int $targetId, array $input): void
    {
        $this->db->transaction(function () use ($viewer, $targetType, $targetId, $input): void {
            $row = $this->subscriptions->get($viewer->id(), $targetType, $targetId, true);
            $this->apply($viewer, $targetType, $targetId, $input, $row);
        });
    }

    private function apply(User $viewer, string $type, int $id, array $input, ?array $old): void
    {
        $errors = [];
        $draft = [];
        foreach (['frequency', 'in_app', 'email'] as $key) {
            $draft[$key] = is_scalar($input[$key] ?? null) ? (string) $input[$key] : '';
        }
        $frequency = $draft['frequency'];
        if (!in_array($frequency, ['instant', 'daily', 'off'], true)) {
            $errors['frequency'] = 'Choose Instant, Daily, or Off.';
        }
        foreach (['in_app', 'email'] as $key) {
            if (isset($input[$key]) && !in_array($input[$key], ['0', '1', 0, 1, false, true, ''], true)) {
                $errors[$key] = 'Choose whether this channel is enabled.';
            }
        }
        if ($errors !== []) { throw new ValidationException($errors, $draft); }
        $inApp = $draft['in_app'] === '1';
        $email = $draft['email'] === '1';
        if ($frequency === 'off' || (!$inApp && !$email)) { $frequency = 'off'; $inApp = $email = false; }
        $reduction = $old !== null && ($frequency === 'off' || (
            $frequency === $old['frequency']
            && (!$inApp || (bool) $old['in_app_enabled'])
            && (!$email || (bool) $old['email_enabled'])
        ));
        if (!$reduction) {
            $this->writeGate->assertCanWrite($viewer);
            $this->targetUrl($viewer, $type, $id);
        }
        $this->subscriptions->set($viewer->id(), $type, $id, $inApp, $email, $frequency);
    }

    /** Canonical read check; callers may use the safe URL after a successful reduction. */
    public function targetUrl(User $viewer, string $type, int $id): string
    {
        if ($type === 'thread') {
            $thread = $this->threads->loadForUser($viewer, $id);
            if ((int) $thread['is_pending'] === 1) { throw new NotFoundException('Subscription not found.'); }
            return '/t/' . $id . '-' . $thread['slug'];
        }
        if ($type !== 'board') { throw new NotFoundException('Subscription not found.'); }
        $board = $this->boards->find($id);
        $scope = $this->visibility->scope($viewer, true);
        if ($board === null || ($board['visibility'] === 'private' && !$viewer->isAdmin()
            && !in_array($id, $scope['member_board_ids'], true) && !in_array($id, $scope['assigned_board_ids'], true))) {
            throw new NotFoundException('Subscription not found.');
        }
        return '/c/' . $board['slug'];
    }
}
