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
        return $this->view('appeals/index', [
            'appeals' => $this->container->get(AppealService::class)->forUser($user->id()),
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
            return $this->view('appeals/index', [
                'appeals' => $this->container->get(AppealService::class)->forUser($user->id()),
                'errors' => $e->errors,
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
            return $this->view('appeals/index', [
                'appeals' => $this->container->get(AppealService::class)->forUser($user->id()),
                'errors' => $e->errors,
            ], 422);
        }
        return $this->redirectWithFlash('/appeals', 'Appeal submitted.');
    }

    /** @param array<string,string> $params */
    public function queue(Request $request, array $params): Response
    {
        $actor = $this->requireAppeals();
        return $this->view('mod/appeals', [
            'appeals' => $this->container->get(AppealService::class)->queue($actor),
            'outcomes' => ['upheld', 'modified', 'reversed', 'dismissed'],
        ]);
    }

    /** @param array<string,string> $params */
    public function resolve(Request $request, array $params): Response
    {
        $actor = $this->requireAppeals();
        try {
            $this->container->get(AppealService::class)->resolve(
                $actor,
                (int) ($params['id'] ?? 0),
                $request->str('outcome'),
                $request->str('note'),
            );
        } catch (ValidationException $e) {
            return $this->view('mod/appeals', [
                'appeals' => $this->container->get(AppealService::class)->queue($actor),
                'outcomes' => ['upheld', 'modified', 'reversed', 'dismissed'],
                'errors' => $e->errors,
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
}
