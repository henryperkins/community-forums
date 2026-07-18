<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Security\WebhookEvents;
use App\Service\RateLimitService;
use App\Service\WebhookService;

final class AdminWebhookController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('webhooks')) {
            throw new NotFoundException();
        }
    }

    private function service(): WebhookService
    {
        return $this->container->get(WebhookService::class);
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->view('admin/webhooks', [
            'webhooks' => $this->service()->list(),
            'events_catalogue' => WebhookEvents::all(),
            'errors' => [],
            'old' => [],
            'new_secret' => null,
        ]);
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $service = $this->service();
        try {
            $result = $service->register(
                $admin,
                (string) $request->post('current_password', ''),
                $request->str('name'),
                $request->str('url'),
                (array) $request->post('events', []),
            );
            return $this->view('admin/webhooks', [
                'webhooks' => $service->list(),
                'events_catalogue' => WebhookEvents::all(),
                'errors' => [],
                'old' => [],
                'new_secret' => $result['secret'],
            ]);
        } catch (ValidationException $e) {
            return $this->view('admin/webhooks', [
                'webhooks' => $service->list(),
                'events_catalogue' => WebhookEvents::all(),
                'errors' => $e->errors,
                'old' => $e->old + [
                    'name' => $request->str('name'),
                    'url' => $request->str('url'),
                    'events' => (array) $request->post('events', []),
                ],
                'new_secret' => null,
            ], 422);
        }
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params, ?string $newSecret = null, int $status = 200): Response
    {
        $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        $model = $this->service()->detailModel($id);
        if ($model === null) {
            throw new NotFoundException();
        }
        return $this->view('admin/webhook_detail', $model + [
            'errors' => [],
            'old' => [],
            'error_context' => null,
            'new_secret' => $newSecret,
        ], $status);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->service()->update($admin, $id, $request->str('name'), $request->str('url'), (array) $request->post('events', []));
            return $this->redirectWithFlash('/admin/webhooks/' . $id, 'Webhook updated.');
        } catch (ValidationException $e) {
            $model = $this->service()->detailModel($id);
            if ($model === null) {
                throw new NotFoundException();
            }
            return $this->view('admin/webhook_detail', $model + [
                'errors' => $e->errors,
                'old' => $e->old + [
                    'name' => $request->str('name'),
                    'url' => $request->str('url'),
                    'events' => (array) $request->post('events', []),
                ],
                'error_context' => 'update',
                'new_secret' => null,
            ], 422);
        }
    }

    /** @param array<string,string> $params */
    public function toggle(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        $active = (string) $request->post('active', '0') === '1';
        $this->service()->setActive($admin, $id, $active);
        return $this->redirectWithFlash(
            '/admin/webhooks/' . $id,
            $active ? 'Webhook resumed — deliveries will flow on the next worker run.' : 'Webhook paused — no deliveries will be attempted.',
        );
    }

    /** @param array<string,string> $params */
    public function rotate(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $secret = $this->service()->rotateSecret($admin, (string) $request->post('current_password', ''), $id);
            return $this->show($request, $params, $secret);
        } catch (ValidationException $e) {
            $model = $this->service()->detailModel($id);
            if ($model === null) {
                throw new NotFoundException();
            }
            return $this->view('admin/webhook_detail', $model + [
                'errors' => $e->errors,
                'old' => [],
                'error_context' => 'rotate',
                'new_secret' => null,
            ], 422);
        }
    }

    /** @param array<string,string> $params */
    public function test(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $this->container->get(RateLimitService::class)->enforce('webhook_test', $request, $admin);
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->service()->sendTestEvent($admin, $id);
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/webhooks', $e->first());
        }
        return $this->redirectWithFlash('/admin/webhooks/' . $id, 'Test event queued. Run the webhook worker to deliver it.');
    }

    /** @param array<string,string> $params */
    public function delete(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        $outcome = $this->service()->deleteConsole(
            $admin,
            (string) $request->post('current_password', ''),
            $id,
        );
        if (!$outcome['deleted']) {
            return $this->view('admin/webhook_detail', (array) $outcome['model'], $outcome['status']);
        }
        return $this->redirectWithFlash('/admin/webhooks', 'Webhook deleted.');
    }

    /** @param array<string,string> $params */
    public function replay(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        $this->service()->replay($admin, $id, (int) ($params['deliveryId'] ?? 0));
        return $this->redirectWithFlash('/admin/webhooks/' . $id, 'Delivery re-queued.');
    }
}
