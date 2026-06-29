<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repository\BoardRepository;
use App\Repository\ThreadRepository;
use App\Security\ApiPrincipal;

final class BoardsController extends ApiController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        return $this->respond($request, function (ApiPrincipal $p): Response {
            $this->requireScope($p, 'read:boards');
            $public = array_filter(
                $this->container->get(BoardRepository::class)->allOrdered(),
                static fn (array $b): bool => ($b['visibility'] ?? '') === 'public',
            );
            return Response::json(['boards' => array_map(static fn (array $b): array => [
                'id' => (int) $b['id'],
                'slug' => (string) $b['slug'],
                'name' => (string) $b['name'],
                'thread_count' => (int) ($b['thread_count'] ?? 0),
                'post_count' => (int) ($b['post_count'] ?? 0),
            ], array_values($public))]);
        });
    }

    /** @param array<string,string> $params */
    public function threads(Request $request, array $params): Response
    {
        return $this->respond($request, function (ApiPrincipal $p) use ($request, $params): Response {
            $this->requireScope($p, 'read:threads');
            $boardId = (int) ($params['id'] ?? 0);
            $board = $this->container->get(BoardRepository::class)->find($boardId);
            if ($board === null || ($board['visibility'] ?? '') !== 'public') {
                return Response::json(['error' => 'not_found'], 404);
            }
            $limit = min(50, max(1, $request->int('limit', 20)));
            $rows = $this->container->get(ThreadRepository::class)->listByBoard($boardId, $limit, 0, 'newest');
            return Response::json(['threads' => array_map(static fn (array $t): array => [
                'id' => (int) $t['id'],
                'slug' => (string) $t['slug'],
                'title' => (string) $t['title'],
                'reply_count' => (int) ($t['reply_count'] ?? 0),
            ], $rows)]);
        });
    }
}
