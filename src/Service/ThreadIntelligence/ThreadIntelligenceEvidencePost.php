<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/**
 * One evidence post as the model may see it: ID, UTC time, a request-local
 * pseudonymous speaker label, and the public body. The speaker pattern is
 * enforced by construction so a real username, email, or account field can
 * never ride along into a provider request.
 */
final readonly class ThreadIntelligenceEvidencePost
{
    private const TIME_PATTERN = '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/';
    private const SPEAKER_PATTERN = '/\Aspeaker-\d+\z/';

    public function __construct(
        public int $postId,
        public string $createdAtUtc,
        public string $speaker,
        public string $body,
    ) {
        if ($postId < 1) {
            throw new InvalidArgumentException('post id must be positive');
        }
        if (preg_match(self::TIME_PATTERN, $createdAtUtc) !== 1) {
            throw new InvalidArgumentException('post time must be ISO-8601 UTC (YYYY-MM-DDTHH:MM:SSZ)');
        }
        if (preg_match(self::SPEAKER_PATTERN, $speaker) !== 1) {
            throw new InvalidArgumentException('speaker must be a request-local pseudonym (speaker-N)');
        }
    }
}
