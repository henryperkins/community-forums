<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\AppealService;

final class AppealController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $user = $this->requireAppeals();
        return $this->view('appeals/index', $this->container->get(AppealService::class)->memberViewModel($user->id()) + [
            'errors' => [],
        ]);
    }

    /** @param array<string,string> $params */
    public function openPost(Request $request, array $params): Response
    {
        $user = $this->requireAppeals();
        try {
            $this->container->get(AppealService::class)
                ->openForPost($user, (int) ($params['id'] ?? 0), $request->str('reason'));
        } catch (ValidationException $e) {
            return $this->view('appeals/index', $this->container->get(AppealService::class)->memberViewModel($user->id()) + [
                'errors' => $e->errors,
                'old' => $this->oldReason('post', (int) ($params['id'] ?? 0), $request),
            ], 422);
        }
        return $this->redirectWithFlash('/appeals', 'Appeal submitted.');
    }

    /** @param array<string,string> $params */
    public function openModerationLog(Request $request, array $params): Response
    {
        $user = $this->requireAppeals();
        try {
            $this->container->get(AppealService::class)
                ->openForModerationLog($user, (int) ($params['id'] ?? 0), $request->str('reason'));
        } catch (ValidationException $e) {
            return $this->view('appeals/index', $this->container->get(AppealService::class)->memberViewModel($user->id()) + [
                'errors' => $e->errors,
                'old' => $this->oldReason('moderation_log', (int) ($params['id'] ?? 0), $request),
            ], 422);
        }
        return $this->redirectWithFlash('/appeals', 'Appeal submitted.');
    }

    /** @param array<string,string> $params */
    public function queue(Request $request, array $params): Response
    {
        $actor = $this->requireAppeals();
        return $this->view('mod/appeals', $this->container->get(AppealService::class)->queueViewModel($actor));
    }

    /** @param array<string,string> $params */
    public function resolve(Request $request, array $params): Response
    {
        $actor = $this->requireAppeals();
        $appealId = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(AppealService::class)->resolve(
                $actor,
                $appealId,
                $request->str('outcome'),
                $request->str('note'),
            );
        } catch (ValidationException $e) {
            // 422 re-render preserving the typed resolution (anti-draft-loss):
            // the failing appeal keeps its chosen outcome + note on screen.
            return $this->view('mod/appeals', $this->container->get(AppealService::class)->queueViewModel($actor) + [
                'errors' => $e->errors,
                'old' => [
                    'appeal_id' => $appealId,
                    'outcome' => $request->str('outcome'),
                    'note' => $request->str('note'),
                ],
            ], 422);
        }
        return $this->redirectWithFlash('/mod/appeals', 'Appeal resolved.');
    }

    private function requireAppeals(): \App\Domain\User
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('appeals')) {
            throw new NotFoundException('Not found.');
        }
        return $this->requireUser();
    }

    /** @return array{target_type:string,target_id:int,reason:string} */
    private function oldReason(string $targetType, int $targetId, Request $request): array
    {
        $reason = $request->post('reason', '');
        return [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => is_scalar($reason) ? (string) $reason : '',
        ];
    }
}
