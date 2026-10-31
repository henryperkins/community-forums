<?php

declare(strict_types=1);

namespace App\Service;

/**
 * A branding change started from a revision that is no longer current
 * (ADR 0054). The caller returns the editor's draft with these newer revisions
 * named, and only an explicit confirmation of `currentRevision` may replace
 * them. Not an HttpException: controllers render it themselves.
 */
final class BrandingConflictException extends \RuntimeException
{
    /** @param list<array<string,mixed>> $newer revisions after the editor's base, oldest first */
    public function __construct(public readonly int $currentRevision, public readonly array $newer)
    {
        parent::__construct('Branding changed after this editor started.');
    }
}
