<?php

declare(strict_types=1);

namespace App\Service\Webhook;

/** Builds signed request headers for one webhook delivery. */
final class WebhookSigner
{
    /**
     * @param array<int,string> $secrets newest first
     * @return array<string,string>
     */
    public static function headers(string $eventType, string $eventId, int $timestamp, string $body, array $secrets): array
    {
        $message = $timestamp . '.' . $body;
        $sigs = [];
        foreach ($secrets as $secret) {
            $sigs[] = 'sha256=' . hash_hmac('sha256', $message, $secret);
        }

        return [
            'Content-Type' => 'application/json',
            'User-Agent' => 'RetroBoards-Webhook/1.0',
            'X-RetroBoards-Event' => $eventType,
            'X-RetroBoards-Delivery' => $eventId,
            'X-RetroBoards-Timestamp' => (string) $timestamp,
            'X-RetroBoards-Signature' => implode(', ', $sigs),
        ];
    }
}
