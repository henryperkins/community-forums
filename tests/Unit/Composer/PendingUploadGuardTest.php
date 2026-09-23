<?php

declare(strict_types=1);

namespace Tests\Unit\Composer;

use App\Support\PendingUploadGuard;
use PHPUnit\Framework\TestCase;

final class PendingUploadGuardTest extends TestCase
{
    public function test_unfinished_image_destinations_are_blocked_in_inline_and_reference_markdown(): void
    {
        foreach (['Text ![uploading…](rbup-abc123-def456)', "![photo][pending]\n\n[pending]: rbup-abc123-def456",
            '![](<rbup-abc123-def456>)', '> ![photo](rbup-abc123-def456 "title")'] as $body) {
            self::assertTrue(PendingUploadGuard::containsPendingImage($body), $body);
        }
    }

    public function test_literal_examples_and_completed_images_remain_publishable(): void
    {
        foreach (['![photo](/media/42)', 'The identifier rbup-abc123-def456 is an example.',
            '`![uploading…](rbup-abc123-def456)`', "```markdown\n![uploading…](rbup-abc123-def456)\n```",
            '    ![uploading…](rbup-abc123-def456)', '\\![photo](rbup-abc123-def456)',
            '![photo](https://example.com/rbup-abc123-def456.png)'] as $body) {
            self::assertFalse(PendingUploadGuard::containsPendingImage($body), $body);
        }
    }
}
