<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\UserRepository;
use App\Service\UserModerationService;

/**
 * Staff user-moderation actions (P2-08): warn / note (staff) and
 * suspend / ban / lift (admin). Authorization is enforced in
 * UserModerationService; the controller requires a logged-in user and routes
 * the result back to the subject's profile.
 */
final class UserModerationController extends Controller
{
    /** @param array<string,string> $params subject user id */
    public function warn(Request $request, array $params): Response
    {
        return $this->run($params, fn ($svc, $actor, $id) =>
            $svc->warn($actor, $id, $request->str('reason'), $request->int('board_id', 0) ?: null), 'Warning recorded.');
    }

    /** @param array<string,string> $params */
    public function note(Request $request, array $params): Response
    {
        return $this->run($params, fn ($svc, $actor, $id) =>
            $svc->addNote($actor, $id, $request->str('body')), 'Note added.');
    }

    /** @param array<string,string> $params */
    public function suspend(Request $request, array $params): Response
    {
        $until = $request->str('until');
        return $this->run($params, fn ($svc, $actor, $id) =>
            $svc->suspend($actor, $id, $until !== '' ? $until : null, $request->str('reason')), 'User suspended.');
    }

    /** @param array<string,string> $params */
    public function ban(Request $request, array $params): Response
    {
        return $this->run($params, fn ($svc, $actor, $id) =>
            $svc->ban($actor, $id, $request->str('reason')), 'User banned.');
    }

    /** @param array<string,string> $params */
    public function lift(Request $request, array $params): Response
    {
        return $this->run($params, fn ($svc, $actor, $id) =>
            $svc->lift($actor, $id), 'Account restriction lifted.');
    }

    /** @param array<string,string> $params */
    private function run(array $params, callable $action, string $okMessage): Response
    {
        $actor = $this->requireUser();
        $subjectId = (int) ($params['id'] ?? 0);
        $subject = $this->container->get(UserRepository::class)->find($subjectId);
        if ($subject === null) {
            throw new NotFoundException('User not found.');
        }
        $back = '/u/' . rawurlencode((string) $subject['username']);

        try {
            $action($this->container->get(UserModerationService::class), $actor, $subjectId);
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($back, $e->first());
        }
        return $this->redirectWithFlash($back, $okMessage);
    }
}
