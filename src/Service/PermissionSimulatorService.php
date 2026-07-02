<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityDecision;
use App\Security\CapabilityResolver;

/**
 * Permission simulation on the real resolver, with target labels redacted
 * against the viewing admin's own read access.
 */
final class PermissionSimulatorService
{
    public function __construct(
        private CapabilityResolver $resolver,
        private UserRepository $users,
        private BoardRepository $boards,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
    ) {
    }

    /** @return array{decision:?CapabilityDecision, actor_label:string, target_label:?string, error:?string} */
    public function simulate(User $viewer, string $actorRef, string $capability, ?int $boardId, ?string $at): array
    {
        $result = [
            'decision' => null,
            'actor_label' => '',
            'target_label' => null,
            'error' => null,
        ];

        $actorRef = trim($actorRef);
        $actor = null;
        if ($actorRef === '' || strtolower($actorRef) === 'guest') {
            $result['actor_label'] = 'guest';
        } else {
            $row = ctype_digit($actorRef)
                ? $this->users->find((int) $actorRef)
                : $this->users->findByUsername($actorRef);
            if ($row === null) {
                $result['error'] = 'No member matches "' . $actorRef . '"; use a username, a numeric id, or "guest".';
                return $result;
            }

            $actor = User::fromRow($row);
            $result['actor_label'] = $actor->username()
                . ' (#' . $actor->id() . ', ' . $actor->role() . ', ' . $actor->status() . ')';
        }

        $atTime = null;
        if ($at !== null && trim($at) !== '') {
            $atTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', trim($at), new \DateTimeZone('UTC'))
                ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($at), new \DateTimeZone('UTC'));
            if ($atTime === false || $atTime === null) {
                $result['error'] = 'Time must be UTC "YYYY-MM-DD HH:MM".';
                return $result;
            }
        }

        $target = [];
        if ($boardId !== null && $boardId > 0) {
            $target['board_id'] = $boardId;
            $board = $this->boards->find($boardId);
            if ($board === null) {
                $result['target_label'] = 'Board #' . $boardId . ' (missing)';
            } else {
                $viewerIsMember = $this->members->isMember($boardId, $viewer->id());
                $result['target_label'] = $this->policy->canRead($board, $viewer, $viewerIsMember)
                    ? 'Board #' . $boardId . ' - ' . (string) $board['name']
                    : 'Board #' . $boardId . ' (restricted)';
            }
        }

        $result['decision'] = $this->resolver->can($actor, $capability, $target, $atTime);

        return $result;
    }
}
