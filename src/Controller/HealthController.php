<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class HealthController extends Controller
{
    /** @param array<string,string> $params */
    public function check(Request $request, array $params): Response
    {
        $dbOk = $this->container->get(Database::class)->ping();

        return Response::json([
            'status' => $dbOk ? 'ok' : 'error',
            'database' => $dbOk ? 'ok' : 'down',
        ], $dbOk ? 200 : 503);
    }
}
