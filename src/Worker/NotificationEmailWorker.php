<?php

declare(strict_types=1);

namespace App\Worker;

use App\Controller\UnsubscribeController;
use App\Core\Config;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\PostRepository;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EmailPreferenceService;
use App\Service\EmailDomainVerifier;
use App\Service\NotificationVisibilityService;
use Throwable;

/** Shared durable outbox drainer. The advisory lock serializes transport attempts;
 * a transport success followed by a process/database crash can still be delivered twice. */
final class NotificationEmailWorker
{
    public function __construct(
        private EmailDeliveryRepository $deliveries,
        private EmailSuppressionRepository $suppress,
        private PostRepository $posts,
        private UserRepository $users,
        private Mailer $mailer,
        private Config $config,
        private ?SettingRepository $settings = null,
        private ?EmailDomainVerifier $domainVerifier = null,
        private ?EmailPreferenceService $emailPrefs = null,
        private ?NotificationVisibilityService $visibility = null,
        private ?\App\Service\DigestService $digests = null,
    ) {
        $db = $posts->database();
        $this->visibility ??= new NotificationVisibilityService($db);
        $this->emailPrefs ??= new EmailPreferenceService(new \App\Repository\UserPreferenceRepository($db));
        $this->digests ??= new \App\Service\DigestService(new \App\Repository\DigestActivityRepository($db), $this->visibility, $config, $settings);
    }

    /** @return array{sent:int,suppressed:int,retrying:int,failed:int,skipped:int,blocked_reason:?string} */
    public function run(int $limit = 100, ?string $kind = null): array
    {
        $stats = ['sent' => 0, 'suppressed' => 0, 'retrying' => 0, 'failed' => 0, 'skipped' => 0, 'blocked_reason' => null];
        if (!$this->mailer->isConfigured()) {
            $stats['blocked_reason'] = 'sender_unconfigured';
            return $stats;
        }
        if ($this->domainVerifier !== null && $this->domainVerifier->blockedReason() !== null) {
            $stats['blocked_reason'] = 'domain_unverified';
            return $stats;
        }
        if (!$this->deliveries->acquireDrainLock()) { return $stats; }
        try {
            foreach ($this->deliveries->pending($limit, $kind) as $row) {
                $id = (int) $row['id'];
                try {
                    $email = (string) $row['email'];
                    $recipient = $this->users->findEntity((int) ($row['user_id'] ?? 0));
                    $reason = match (true) {
                        $recipient === null => 'recipient_missing',
                        $recipient->status() === 'deleted' => 'recipient_deleted',
                        $recipient->status() === 'banned' => 'recipient_banned',
                        !in_array($recipient->status(), ['active', 'suspended', 'deactivated', 'pending_deletion'], true) => 'recipient_ineligible',
                        $row['kind'] !== 'test' && $this->emailPrefs->pauseAllEmail($recipient->id()) => 'recipient_paused',
                        $row['kind'] !== 'test' && $this->suppress->isSuppressed($email) => 'address_suppressed',
                        $row['kind'] === 'system' && empty($this->visibility->scope($recipient, true)['features']['email']) => 'delivery_disabled',
                        $row['kind'] === 'digest' && $recipient->toArray()['digest_hour'] === null => 'delivery_disabled',
                        default => null,
                    };
                    if ($reason !== null) {
                        $this->deliveries->markSuppressed($id, $reason);
                        $stats['suppressed']++;
                        continue;
                    }
                    if ($row['kind'] === 'digest') {
                        if ($row['payload'] === null || $row['payload'] === '') {
                            $this->deliveries->markFailed($id, 'unreplayable_legacy_digest');
                            $stats['failed']++;
                            continue;
                        }
                        $payload = json_decode((string) $row['payload'], true);
                        if (!is_array($payload) || !\App\Service\DigestService::validPayload($payload)) {
                            $this->deliveries->markFailed($id, 'invalid_digest_payload');
                            $stats['failed']++;
                            continue;
                        }
                    }
                    $rendered = match ($row['kind']) {
                        'instant' => $this->renderInstant($row),
                        'digest' => $this->digests->render($recipient, $payload),
                        'system' => $this->renderSystem($row),
                        'test' => ['subject' => 'RetroBoards email delivery test', 'text' => 'This is a test email from RetroBoards. If you received it, your outbound email is working.', 'html' => null],
                        default => throw new \App\Core\ValidationException(['kind' => 'unsupported_kind']),
                    };
                    if ($rendered === null) {
                        $this->deliveries->markSuppressed($id, 'content_unavailable');
                        $stats['suppressed']++;
                        $stats['skipped']++;
                        continue;
                    }
                    $messageId = $this->mailer->send($email, $rendered['subject'], $rendered['text'], $rendered['html']);
                    $this->deliveries->markSent($id, $messageId);
                    $stats['sent']++;
                } catch (\App\Core\ValidationException $e) {
                    $this->deliveries->markFailed($id, $row['kind'] === 'digest' ? 'invalid_digest_payload' : $e->first());
                    $stats['failed']++;
                } catch (Throwable $e) {
                    // If recording also fails, propagate: the batch cannot claim progress.
                    $status = $this->deliveries->markAttemptFailed($id, $e->getMessage());
                    $stats[$status === 'failed' ? 'failed' : 'retrying']++;
                }
            }
        } finally {
            $this->deliveries->releaseDrainLock();
        }
        return $stats;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{subject:string,text:string,html:string}|null
     */
    private function renderInstant(array $row): ?array
    {
        $key = (string) ($row['idempotency_key'] ?? '');
        $postId = (int) explode(':', $key)[0];
        if ($postId <= 0) {
            return null;
        }
        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            return null;
        }

        // Re-apply the read gate for the recipient at send time.
        $recipientId = (int) ($row['user_id'] ?? 0);
        $recipient = $this->users->findEntity($recipientId);
        $actors = $this->instantActors($row, $post);
        if ($recipient === null || $actors === []
            || !$this->visibility->canReadPost($recipient, $post, $this->visibility->scope($recipient, true), $actors)) {
            return null;
        }

        $subscription = (new \App\Repository\SubscriptionRepository($this->posts->database()))
            ->effectiveForThread($recipientId, (int) $post['thread_id'], (int) $post['board_id']);
        if ($subscription !== null && ($subscription['frequency'] === 'off' || !(bool) $subscription['email_enabled'])) {
            return null;
        }

        $appUrl = (string) $this->config->get('app.url', '');
        $url = rtrim($appUrl, '/') . '/t/' . (int) $post['thread_id'] . '-' . $post['thread_slug'] . '#p' . $postId;
        $unsub = UnsubscribeController::link($appUrl, (string) $row['email'], (string) $this->config->get('app.key', ''));
        $siteName = $this->siteName();
        $subject = 'New activity on ' . $siteName;
        $text = "There's new activity in a thread you're following on {$siteName}.\n\n" . $url
            . "\n\nUnsubscribe from these emails: " . $unsub;
        $html = '<p>There&#39;s new activity in a thread you&#39;re following on ' . htmlspecialchars($siteName, ENT_QUOTES) . '.</p>'
            . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">View the thread</a></p>'
            . '<p style="font-size:12px;color:#888"><a href="' . htmlspecialchars($unsub, ENT_QUOTES) . '">Unsubscribe</a></p>';

        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    /**
     * Event provenance survives notification clearing. Legacy self-authored or edited
     * targets cannot safely identify an actor without a surviving event row.
     * @return list<int>
     */
    private function instantActors(array $row, array $post): array
    {
        if (($row['payload'] ?? null) !== null) {
            $payload = json_decode((string) $row['payload'], true);
            if (!is_array($payload) || ($payload['version'] ?? null) !== 1
                || ($payload['type'] ?? null) !== 'instant'
                || !in_array($payload['event_type'] ?? null, ['reply', 'new_thread', 'mention', 'solved'], true)
                || !is_int($payload['actor_id'] ?? null) || $payload['actor_id'] <= 0
                || ($payload['post_id'] ?? null) !== (int) $post['id']) {
                return [];
            }
            return [$payload['actor_id']];
        }
        $actors = (new NotificationRepository($this->posts->database()))->legacyInstantActors((int) $row['user_id'], (int) $post['id']);
        if ($actors !== []) {
            $ids = array_map(static fn (array $actor): int => (int) ($actor['actor_id'] ?? 0), $actors);
            // A later edit mention can create the only surviving notice while the
            // older email-only job wins post:user deduplication. Its inferred actor
            // must never remove the original post-author gate from a legacy job.
            $ids[] = (int) $post['user_id'];
            return in_array(0, $ids, true) ? [] : array_values(array_unique($ids));
        }
        // Ordinary initial-post mail can be recovered from an unchanged post. A
        // solved event targets its own author; an edit mention may have another actor.
        $authorId = (int) $post['user_id'];
        return $authorId !== (int) $row['user_id'] && $authorId > 0 && ($post['edited_at'] ?? null) === null
            ? [$authorId] : [];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{subject:string,text:string,html:string}|null
     */
    private function renderSystem(array $row): ?array
    {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload) || ($payload['type'] ?? '') !== 'announcement') {
            return null;
        }
        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return null;
        }

        $siteName = $this->siteName();
        $subject = (string) ($row['subject'] ?? '');
        if ($subject === '') {
            $subject = 'Announcement from ' . $siteName;
        }
        $appUrl = (string) $this->config->get('app.url', '');
        $url = rtrim($appUrl, '/') . '/';
        // A broadcast announcement is bulk mail; carry a one-click unsubscribe
        // link like every other email path (CAN-SPAM / deliverability).
        $unsub = UnsubscribeController::link($appUrl, (string) ($row['email'] ?? ''), (string) $this->config->get('app.key', ''));
        $text = $siteName . " announcement\n\n" . $message . "\n\n" . $url
            . "\n\nUnsubscribe from these emails: " . $unsub;
        $html = '<p><strong>' . htmlspecialchars($siteName, ENT_QUOTES) . ' announcement</strong></p>'
            . '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES)) . '</p>'
            . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">View the forum</a></p>'
            . '<p style="font-size:12px;color:#888"><a href="' . htmlspecialchars($unsub, ENT_QUOTES) . '">Unsubscribe</a></p>';

        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    private function siteName(): string
    {
        $fallback = (string) $this->config->get('app.name', 'RetroBoards');
        if ($this->settings === null) {
            return $fallback;
        }
        try {
            return $this->settings->getString('site_name', $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }
}
