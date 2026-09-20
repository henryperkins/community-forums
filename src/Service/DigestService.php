<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\DigestActivityRepository;
use App\Repository\SettingRepository;
use App\Controller\UnsubscribeController;
use Throwable;

/** Versioned source snapshots contain no rendered or private content. */
final class DigestService
{
    public function __construct(
        private DigestActivityRepository $activity,
        private NotificationVisibilityService $visibility,
        private Config $config,
        private ?SettingRepository $settings = null,
    ) {}

    public function snapshot(User $viewer, string $fromUtc, string $toUtc, int $maxPostId): array
    {
        return [
            'version' => 1,
            'window_start_utc' => $fromUtc,
            'window_end_utc' => $toUtc,
            'max_post_id' => $maxPostId,
            'sources' => ['subscriptions' => $this->activity->sources($viewer->id()), 'saved_feeds' => []],
        ];
    }

    public static function validPayload(array $payload): bool
    {
        if (($payload['version'] ?? null) !== 1 || !is_int($payload['max_post_id'] ?? null)
            || $payload['max_post_id'] < 0 || !is_array($payload['sources'] ?? null)
            || !is_array($payload['sources']['subscriptions'] ?? null)
            || ($payload['sources']['saved_feeds'] ?? null) !== []) {
            return false;
        }
        foreach (['window_start_utc', 'window_end_utc'] as $field) {
            $value = $payload[$field] ?? null;
            if (!is_string($value)) { return false; }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
            if ($date === false || $date->format('Y-m-d H:i:s') !== $value) { return false; }
        }
        if ($payload['window_start_utc'] > $payload['window_end_utc']) { return false; }
        foreach ($payload['sources']['subscriptions'] as $source) {
            if (!is_array($source) || !in_array($source['target_type'] ?? null, ['thread', 'board'], true)
                || !is_int($source['target_id'] ?? null) || $source['target_id'] <= 0) { return false; }
        }
        return true;
    }

    public function render(?User $viewer, array $payload): ?array
    {
        if ($viewer === null) { return null; }
        if (!self::validPayload($payload)) {
            throw new ValidationException(['digest' => 'invalid_digest_payload']);
        }
        $scope = $this->visibility->scope($viewer, true);
        if (empty($scope['features']['notifications']) || empty($scope['features']['email'])) { return null; }
        $threads = $this->activity->activity($viewer->id(), $payload, $scope);
        if ($threads === []) { return null; }
        $rendered = $this->renderThreads($threads);
        $unsub = UnsubscribeController::link((string) $this->config->get('app.url', ''), $viewer->email(), (string) $this->config->get('app.key', ''));
        $rendered['text'] .= "\n\nUnsubscribe from these emails: " . $unsub;
        $rendered['html'] .= '<p><a href="' . htmlspecialchars($unsub, ENT_QUOTES) . '">Unsubscribe</a></p>';
        return $rendered;
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

    /**
     * @param array<int,array{thread_id:int,title:string,slug:string,n:int}> $threads
     * @return array{subject:string,text:string,html:string}
     */
    private function renderThreads(array $threads): array
    {
        $base = rtrim((string) $this->config->get('app.url', ''), '/');
        $app = $this->siteName();
        $subject = 'Your daily digest — ' . count($threads) . ' active ' . (count($threads) === 1 ? 'thread' : 'threads');
        $lines = [];
        $html = ['<p>New activity since your last digest:</p><ul>'];
        foreach ($threads as $t) {
            $url = $base . '/t/' . $t['thread_id'] . '-' . $t['slug'];
            $lines[] = sprintf('- %s (%d new) %s', $t['title'], $t['n'], $url);
            $html[] = '<li><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars($t['title'], ENT_QUOTES) . '</a> (' . $t['n'] . ' new)</li>';
        }
        $html[] = '</ul>';
        return [
            'subject' => $subject,
            'text' => "$app daily digest\n\n" . implode("\n", $lines),
            'html' => implode('', $html),
        ];
    }

}
