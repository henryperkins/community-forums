<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Thrown inside a posting transaction when the idempotency key was already taken
 * by a concurrent submit (P3-03). It rolls the transaction back — undoing the
 * just-created thread/post — so the caller can replay the original result
 * instead of leaving a duplicate behind (PHASE_3_PLAN §8.5).
 */
final class DuplicateSubmissionException extends RuntimeException
{
}
