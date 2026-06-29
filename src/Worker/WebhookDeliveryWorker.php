<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Config;
use App\Core\EgressBlockedException;
use App\Core\FeatureFlags;
use App\Repository\ModerationLogRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Service\SecretVault;
use App\Service\Webhook\WebhookSigner;
use App\Service\Webhook\WebhookTransport;
use Throwable;

/** Drains the webhook_deliveries ledger under a single MySQL advisory lock. */
final class WebhookDeliveryWorker
{
    public function __construct(
        private WebhookRepository $webhooks,
        private WebhookDeliveryRepository $deliveries,
        private SecretVault $vault,
        private WebhookTransport $transport,
        private FeatureFlags $flags,
        private ModerationLogRepository $log,
        private Config $config,
    ) {
    }

    /** @return array{delivered:int,retrying:int,dead:int,skipped:int} */
    public function run(int $limit = 100): array
    {
        $stats = ['delivered' => 0, 'retrying' => 0, 'dead' => 0, 'skipped' => 0];
        if (!$this->flags->enabled('webhooks')) {
            return $stats;
        }
        if (!$this->deliveries->acquireDrainLock()) {
            return $stats;
        }

        $backoff = (array) $this->config->get('webhooks.backoff_seconds', [60, 300, 1500, 7200, 21600]);
        $threshold = (int) $this->config->get('webhooks.circuit_breaker_threshold', 15);
        $timeout = (int) $this->config->get('webhooks.timeout_seconds', 5);
        $disabledThisRun = [];

        try {
            foreach ($this->deliveries->claim($limit) as $row) {
                $id = (int) $row['id'];
                $webhookId = (int) $row['webhook_id'];

                if (isset($disabledThisRun[$webhookId])) {
                    $stats['skipped']++;
                    continue;
                }

                try {
                    $secrets = $this->vault->usableSecrets((string) $row['secret_ref']);
                } catch (Throwable) {
                    $stats['skipped']++;
                    continue;
                }

                $body = (string) $row['payload'];
                $headers = WebhookSigner::headers(
                    (string) $row['event_type'],
                    (string) $row['event_id'],
                    time(),
                    $body,
                    $secrets,
                );

                try {
                    $resp = $this->transport->deliver((string) $row['url'], $headers, $body, $timeout);
                } catch (EgressBlockedException $e) {
                    $this->deliveries->recordFailure($id, null, 'egress blocked: ' . $e->getMessage(), null, true);
                    $stats['dead']++;
                    continue;
                }

                if ($resp->status >= 200 && $resp->status < 300) {
                    $this->deliveries->markDelivered($id, $resp->status);
                    $this->webhooks->setLastStatus($webhookId, $resp->status, true);
                    $this->webhooks->resetConsecutiveFailures($webhookId);
                    $stats['delivered']++;
                    continue;
                }

                $attempt = (int) $row['attempt_count'] + 1;
                $dead = $attempt >= (int) $row['max_attempts'];
                $next = null;
                if (!$dead) {
                    $idx = min($attempt - 1, count($backoff) - 1);
                    $secs = (int) ($backoff[$idx] ?? 21600);
                    $next = gmdate('Y-m-d H:i:s', time() + $secs);
                }

                $this->deliveries->recordFailure($id, $resp->status ?: null, $resp->error ?? ('HTTP ' . $resp->status), $next, $dead);
                $this->webhooks->setLastStatus($webhookId, $resp->status ?: null, false);

                $newFailures = (int) $row['consecutive_failures'] + 1;
                $this->webhooks->incrementConsecutiveFailures($webhookId);
                if ($newFailures >= $threshold) {
                    $disabledThisRun[$webhookId] = true;
                    if ($this->webhooks->disable($webhookId, 'Auto-paused after ' . $newFailures . ' consecutive delivery failures.') === 1) {
                        $this->log->log([
                            'actor_id' => null,
                            'action' => 'webhook_auto_disabled',
                            'target_type' => 'webhook',
                            'target_id' => $webhookId,
                            'after' => ['consecutive_failures' => $newFailures],
                        ]);
                    }
                }

                $stats[$dead ? 'dead' : 'retrying']++;
            }
        } finally {
            $this->deliveries->releaseDrainLock();
        }

        return $stats;
    }
}
