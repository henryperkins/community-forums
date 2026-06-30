<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\ServerDraftRepository;

/**
 * Drafts view (P3-03). Browser-local drafts remain the offline fallback; the
 * server_drafts flag adds an authenticated sync ledger and no-JS management.
 */
final class DraftController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('drafts')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $serverDrafts = [];
        if ($this->serverDraftsEnabled()) {
            $serverDrafts = $this->repo()->listForUser($user->id());
        }
        return $this->view('account/drafts', [
            'server_drafts_enabled' => $this->serverDraftsEnabled(),
            'server_drafts' => $serverDrafts,
        ]);
    }

    /** @param array<string,string> $params */
    public function load(Request $request, array $params): Response
    {
        $user = $this->requireServerDrafts();
        $draft = $this->repo()->findByContext($user->id(), (string) ($params['key'] ?? ''));
        return Response::json(['draft' => $draft]);
    }

    /** @param array<string,string> $params */
    public function save(Request $request, array $params): Response
    {
        $user = $this->requireServerDrafts();
        try {
            $result = $this->repo()->save(
                $user->id(),
                (string) ($params['key'] ?? ''),
                max(0, $request->int('revision', 0)),
                $request->str('title'),
                (string) $request->post('body', ''),
                $this->metadata($request),
            );
        } catch (ValidationException $e) {
            return Response::json(['error' => 'validation', 'messages' => $e->errors], 422);
        }
        if ($result['status'] === 'conflict') {
            return Response::json(['error' => 'conflict', 'server' => $result['server'] ?? null], 409);
        }
        return Response::json(['draft' => $result['draft'] ?? null]);
    }

    /** @param array<string,string> $params */
    public function discard(Request $request, array $params): Response
    {
        $user = $this->requireServerDrafts();
        $this->repo()->discardByContext($user->id(), (string) ($params['key'] ?? ''));
        return Response::json(['discarded' => true]);
    }

    /** @param array<string,string> $params */
    public function discardPage(Request $request, array $params): Response
    {
        $user = $this->requireServerDrafts();
        $this->repo()->discardById($user->id(), (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/drafts', 'Draft discarded.');
    }

    private function requireServerDrafts(): \App\Domain\User
    {
        if (!$this->serverDraftsEnabled()) {
            throw new NotFoundException('Not found.');
        }
        return $this->requireUser();
    }

    private function serverDraftsEnabled(): bool
    {
        return $this->container->get(FeatureFlags::class)->enabled('server_drafts');
    }

    private function repo(): ServerDraftRepository
    {
        return $this->container->get(ServerDraftRepository::class);
    }

    /** @return array<string,mixed> */
    private function metadata(Request $request): array
    {
        $raw = (string) $request->post('metadata', '{}');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
