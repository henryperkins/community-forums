<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\DuplicateSubmissionException;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardModeratorRepository;
use App\Repository\UserRepository;
use App\Service\UserModerationService;

/**
 * Staff user-moderation surface (P2-08 / ADMIN §3.4): a GET panel at
 * /mod/u/{id} where board moderators (and admins) warn and note a member, plus
 * the POST actions. Authorization for each write is enforced in
 * UserModerationService (warn/note = staff, suspend/ban/lift = admin); a
 * validation failure re-renders the panel at 422 with the typed input
 * preserved (anti-draft-loss), never a flash redirect that drops it.
 */
final class UserModerationController extends Controller
{
    /** @param array<string,string> $params subject user id */
    public function show(Request $request, array $params): Response
    {
        $this->requireStaff();
        return $this->panel((int) ($params['id'] ?? 0));
    }

    /** @param array<string,string> $params subject user id */
    public function warn(Request $request, array $params): Response
    {
        return $this->run($params, fn ($svc, User $actor, int $id) =>
            $svc->warn(
                $actor,
                $id,
                $request->str('reason'),
                $request->int('board_id', 0) ?: null,
                $request->str('idempotency_key') ?: null,
            ), 'Warning recorded.', 'warn', [
                'reason' => $request->str('reason'),
                'board_id' => $request->str('board_id'),
                'idempotency_key' => $request->str('idempotency_key'),
            ]);
    }

    /** @param array<string,string> $params */
    public function note(Request $request, array $params): Response
    {
        return $this->run($params, fn ($svc, User $actor, int $id) =>
            $svc->addNote($actor, $id, $request->str('body')), 'Note added.', 'note', ['body' => $request->str('body')]);
    }

    /** @param array<string,string> $params */
    public function suspend(Request $request, array $params): Response
    {
        $until = $request->str('until');
        return $this->run($params, fn ($svc, User $actor, int $id) =>
            $svc->suspend($actor, $id, $until !== '' ? $until : null, $request->str('reason')), 'User suspended.', 'suspend', ['reason' => $request->str('reason'), 'until' => $until]);
    }

    /** @param array<string,string> $params */
    public function ban(Request $request, array $params): Response
    {
        return $this->run($params, fn ($svc, User $actor, int $id) =>
            $svc->ban($actor, $id, $request->str('reason')), 'User banned.', 'ban', ['reason' => $request->str('reason')]);
    }

    /** @param array<string,string> $params */
    public function lift(Request $request, array $params): Response
    {
        return $this->run($params, fn ($svc, User $actor, int $id) =>
            $svc->lift($actor, $id), 'Account restriction lifted.', 'lift');
    }

    /**
     * Run one moderation action. Success returns to the staff panel with a
     * flash; a ValidationException re-renders the panel at 422 carrying the
     * failing form's context + typed input.
     *
     * @param array<string,string> $params
     * @param array<string,string> $old
     */
    private function run(array $params, callable $action, string $okMessage, string $context, array $old = []): Response
    {
        $actor = $this->requireStaff();
        $subjectId = (int) ($params['id'] ?? 0);
        $this->requireSubject($subjectId);

        try {
            $action($this->container->get(UserModerationService::class), $actor, $subjectId);
        } catch (ValidationException $e) {
            return $this->panel($subjectId, $e, 422, $context, $old);
        } catch (DuplicateSubmissionException) {
            // The original submit already committed — replay its outcome.
            return $this->redirectWithFlash('/mod/u/' . $subjectId, $okMessage);
        }
        return $this->redirectWithFlash('/mod/u/' . $subjectId, $okMessage);
    }

    /**
     * Render the staff panel (reduced ADMIN §5.1 record: identity summary +
     * moderation history, no PII, no role controls).
     *
     * @param array<string,string> $old
     */
    private function panel(
        int $subjectId,
        ?ValidationException $error = null,
        int $status = 200,
        ?string $errorContext = null,
        array $old = [],
    ): Response {
        $actor = $this->requireStaff();
        // Actor-aware, board-scoped model (spec §2) — used for the 200 AND
        // every 422 re-render, so a scoped moderator never sees the full
        // record even on error, and out-of-scope subjects 404 uniformly.
        $model = $this->container->get(UserModerationService::class)->panelFor($actor, $subjectId);

        return $this->view('mod/user', $model + [
            'is_admin' => $actor->isAdmin(),
            'is_self' => (int) $model['subject']['id'] === $actor->id(),
            'error_context' => $errorContext,
            'errors' => $error?->errors ?? [],
            'old' => $old !== [] ? $old : ($error?->old ?? []),
        ], $status);
    }

    /**
     * Panel access mirrors UserModerationService::assertStaff (admin, or
     * moderator of at least one board) without consuming a write: the GET must
     * not 403 a suspended staff account out of *reading* the panel, and the
     * service still gates every write state-first.
     */
    private function requireStaff(): User
    {
        $user = $this->requireUser();
        if ($user->isAdmin()) {
            return $user;
        }
        if ($this->container->get(BoardModeratorRepository::class)->boardsFor($user->id()) !== []) {
            return $user;
        }
        throw new ForbiddenException('Staff access required.');
    }

    /** @return array<string,mixed> */
    private function requireSubject(int $id): array
    {
        $subject = $this->container->get(UserRepository::class)->find($id);
        if ($subject === null) {
            throw new NotFoundException('User not found.');
        }
        return $subject;
    }
}
