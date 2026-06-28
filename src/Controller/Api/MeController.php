<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Request;
use App\Core\Response;
use App\Security\ApiPrincipal;

final class MeController extends ApiController
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        return $this->respond($request, fn (ApiPrincipal $p): Response => Response::json([
            'name' => $p->name(),
            'scopes' => $p->scopes(),
            'created_at' => $p->createdAt(),
        ]));
    }
}
