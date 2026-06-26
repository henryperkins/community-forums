<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\ForbiddenException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\SetupService;

/**
 * First-run setup wizard. Reachable only while the install is uninitialized
 * (the kernel's setup gate enforces this); on success the first admin is signed
 * in and sent to /admin.
 */
final class SetupController extends Controller
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        return $this->view('setup/wizard', ['errors' => [], 'old' => []]);
    }

    /** @param array<string,string> $params */
    public function submit(Request $request, array $params): Response
    {
        try {
            $this->container->get(SetupService::class)->run($request->allInput());
        } catch (ValidationException $e) {
            return $this->view('setup/wizard', [
                'errors' => $e->errors,
                'old' => [
                    'site_name' => $request->str('site_name'),
                    'username' => $request->str('username'),
                    'email' => $request->str('email'),
                ],
            ], 422);
        } catch (ForbiddenException) {
            return $this->redirect('/');
        }

        return $this->redirectWithFlash('/admin', 'Welcome! Your community is ready.');
    }
}
