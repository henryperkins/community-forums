<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\EgressBlockedException;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Core\WebhooksDisabledException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\EgressGuard;
use App\Security\ReauthGate;
use App\Security\WebhookEvents;
use App\Security\WriteGate;

/** Register/manage webhook endpoints and enqueue outbound deliveries. */
final class WebhookService
{
    public function __construct(
        private Database $db,
        private WebhookRepository $webhooks,
        private WebhookDeliveryRepository $deliveries,
        private SecretVault $vault,
        private ModerationLogRepository $log,
        private FeatureFlags $flags,
        private Config $config,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private EgressGuard $egress,
    ) {
    }

    /**
     * @param array<int,mixed> $events
     * @return array{id:int,secret:string}
     */
    public function register(User $admin, string $currentPassword, string $name, string $url, array $events): array
    {
        $this->writeGate->assertCanWrite($admin);
        $this->assertEnabled();
        $this->assertSecretStoreEnabled();
        $this->assertPassword($admin, $currentPassword);

        $name = trim($name);
        $url = trim($url);
        $this->assertValidName($name);
        $this->assertValidUrl($url);
        $clean = $this->cleanEvents($events);

        $secret = bin2hex(random_bytes(32));
        $id = $this->db->transaction(function () use ($name, $url, $clean, $secret, $admin): int {
            $id = $this->webhooks->insert($name, $url, json_encode($clean) ?: '[]', '', $admin->id());
            $ref = $this->vault->store('webhook', $id, 'Webhook signing secret: ' . $name, $secret, $admin);
            $this->webhooks->setSecretRef($id, $ref);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'webhook_registered',
                'target_type' => 'webhook',
                'target_id' => $id,
                'after' => ['name' => $name, 'url' => $url, 'events' => $clean, 'secret_ref' => $ref],
            ]);
            return $id;
        });

        return ['id' => $id, 'secret' => $secret];
    }

    public function rotateSecret(User $admin, string $currentPassword, int $webhookId): string
    {
        $this->writeGate->assertCanWrite($admin);
        $this->assertEnabled();
        $this->assertSecretStoreEnabled('current_password');
        $this->assertPassword($admin, $currentPassword);

        $wh = $this->webhooks->findById($webhookId);
        if ($wh === null) {
            throw new ValidationException(['current_password' => 'Unknown webhook.']);
        }

        $ref = (string) $wh['secret_ref'];
        $newSecret = bin2hex(random_bytes(32));
        $this->db->transaction(function () use ($ref, $newSecret, $admin, $webhookId): void {
            $this->vault->rotate($ref, $newSecret, $admin);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'webhook_rotated',
                'target_type' => 'webhook',
                'target_id' => $webhookId,
            ]);
        });
        return $newSecret;
    }

    /** @param array<int,mixed> $events */
    public function update(User $admin, int $webhookId, string $name, string $url, array $events): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->assertEnabled();

        $name = trim($name);
        $url = trim($url);
        $this->assertValidName($name);
        $this->assertValidUrl($url);
        $clean = $this->cleanEvents($events);

        $this->db->transaction(function () use ($webhookId, $name, $url, $clean, $admin): void {
            $this->webhooks->update($webhookId, $name, $url, json_encode($clean) ?: '[]');
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'webhook_updated',
                'target_type' => 'webhook',
                'target_id' => $webhookId,
                'after' => ['name' => $name, 'url' => $url, 'events' => $clean],
            ]);
        });
    }

    public function setActive(User $admin, int $webhookId, bool $active): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->db->transaction(function () use ($webhookId, $active, $admin): void {
            $changed = $active ? $this->webhooks->enable($webhookId) : $this->webhooks->disable($webhookId, 'Disabled by admin.');
            if ($changed !== 1) {
                return;
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => $active ? 'webhook_enabled' : 'webhook_disabled',
                'target_type' => 'webhook',
                'target_id' => $webhookId,
            ]);
        });
    }

    /**
     * Deleting an endpoint discards its delivery history and revokes its
     * signing secret, so — like rotateSecret, and unlike the reversible
     * pause — it is password-reauthed.
     */
    public function delete(User $admin, string $currentPassword, int $webhookId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->assertPassword($admin, $currentPassword);
        $this->remove($admin, $webhookId);
    }

    /**
     * Reauth-free deletion for the package-credential revocation path
     * (PackageIntegrationService::revokeCredential joins its transaction):
     * defensive revokes are deliberately friction-free — WriteGate only.
     * Operator-UI deletion goes through delete(), which reauths.
     */
    public function deleteWithoutReauth(User $actor, int $webhookId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $this->remove($actor, $webhookId);
    }

    private function remove(User $actor, int $webhookId): void
    {
        $wh = $this->webhooks->findById($webhookId);
        if ($wh === null) {
            return;
        }

        $ref = (string) $wh['secret_ref'];
        $this->db->transaction(function () use ($webhookId, $ref, $actor): void {
            $this->webhooks->delete($webhookId);
            if ($ref !== '') {
                $this->vault->revoke($ref, $actor);
            }
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => 'webhook_deleted',
                'target_type' => 'webhook',
                'target_id' => $webhookId,
            ]);
        });
    }

    /** @param array<string,mixed> $payload */
    public function dispatch(string $eventType, array $payload, ?string $eventId = null): int
    {
        if (!$this->flags->enabled('webhooks')) {
            return 0;
        }
        if (!WebhookEvents::isValid($eventType)) {
            throw new ValidationException(['event' => 'Unknown event type.']);
        }

        $eventId ??= bin2hex(random_bytes(16));
        $maxAttempts = (int) $this->config->get('webhooks.max_attempts', 6);
        $count = 0;
        foreach ($this->webhooks->activeEndpoints() as $wh) {
            $subscribed = json_decode((string) $wh['events'], true);
            if (!is_array($subscribed) || !in_array($eventType, $subscribed, true)) {
                continue;
            }
            $envelope = $this->envelope($eventType, $eventId, (int) $wh['id'], $payload);
            if ($this->deliveries->enqueue((int) $wh['id'], $eventType, $eventId, $envelope, $maxAttempts) > 0) {
                $count++;
            }
        }
        return $count;
    }

    public function sendTestEvent(User $admin, int $webhookId): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->assertEnabled();
        if ($this->webhooks->findById($webhookId) === null) {
            throw new ValidationException(['webhook' => 'Unknown webhook.']);
        }

        $eventId = bin2hex(random_bytes(16));
        $envelope = $this->envelope('ping', $eventId, $webhookId, ['message' => 'This is a test event from RetroBoards.']);
        $maxAttempts = (int) $this->config->get('webhooks.max_attempts', 6);
        return $this->db->transaction(function () use ($webhookId, $eventId, $envelope, $maxAttempts, $admin): int {
            $n = $this->deliveries->enqueue($webhookId, 'ping', $eventId, $envelope, $maxAttempts);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'webhook_test_sent',
                'target_type' => 'webhook',
                'target_id' => $webhookId,
                'after' => ['event_id' => $eventId],
            ]);
            return $n;
        });
    }

    public function replay(User $admin, int $webhookId, int $deliveryId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->db->transaction(function () use ($webhookId, $deliveryId, $admin): void {
            if ($this->deliveries->requeue($webhookId, $deliveryId) !== 1) {
                return;
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'webhook_delivery_replayed',
                'target_type' => 'webhook',
                'target_id' => $webhookId,
                'after' => ['delivery_id' => $deliveryId],
            ]);
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function list(): array
    {
        return $this->webhooks->list();
    }

    /** @return array<string,mixed>|null */
    public function get(int $id): ?array
    {
        return $this->webhooks->findById($id);
    }

    /** @return array<int,array<string,mixed>> */
    public function deliveriesFor(int $webhookId, int $limit = 50): array
    {
        return $this->deliveries->listForWebhook($webhookId, $limit);
    }

    /** @param array<string,mixed> $payload */
    private function envelope(string $eventType, string $eventId, int $webhookId, array $payload): string
    {
        return json_encode([
            'event' => $eventType,
            'id' => $eventId,
            'occurred_at' => gmdate('c'),
            'webhook_id' => $webhookId,
            'data' => $payload,
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function assertEnabled(): void
    {
        if (!$this->flags->enabled('webhooks')) {
            throw new WebhooksDisabledException('Webhooks are disabled.');
        }
    }

    private function assertSecretStoreEnabled(string $field = 'name'): void
    {
        if (!$this->flags->enabled('service_secrets')) {
            throw new ValidationException([$field => 'Enable the service-secret store before creating webhooks.']);
        }
    }

    private function assertPassword(User $admin, string $password): void
    {
        $this->reauth->requirePassword($admin, $password);
    }

    private function assertValidName(string $name): void
    {
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ValidationException(['name' => 'Name must be 1-80 characters.']);
        }
    }

    private function assertValidUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new ValidationException(['url' => 'Enter a valid URL.']);
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'https' && $scheme !== 'http') {
            throw new ValidationException(['url' => 'URL must be http or https.']);
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ValidationException(['url' => 'URL must not contain credentials.']);
        }
        if (strlen($url) > 512) {
            throw new ValidationException(['url' => 'URL is too long.']);
        }
        try {
            $this->egress->validateStatic($url);
        } catch (EgressBlockedException) {
            throw new ValidationException(['url' => 'That URL is not an allowed destination.']);
        }
    }

    /**
     * @param array<int,mixed> $events
     * @return array<int,string>
     */
    private function cleanEvents(array $events): array
    {
        $clean = [];
        foreach ($events as $e) {
            if (!is_string($e) || !WebhookEvents::isValid($e)) {
                throw new ValidationException(['events' => 'Unknown event type.']);
            }
            if (in_array($e, $clean, true)) {
                throw new ValidationException(['events' => 'Duplicate event type.']);
            }
            $clean[] = $e;
        }
        if ($clean === []) {
            throw new ValidationException(['events' => 'Select at least one event.']);
        }
        return $clean;
    }
}
