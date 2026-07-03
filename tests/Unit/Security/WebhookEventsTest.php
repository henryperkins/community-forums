<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\WebhookEvents;
use PHPUnit\Framework\TestCase;

final class WebhookEventsTest extends TestCase
{
    public function test_catalogue_includes_ping_and_validates(): void
    {
        self::assertTrue(WebhookEvents::isValid('ping'));
        self::assertTrue(WebhookEvents::isValid('topic.created'));
        self::assertFalse(WebhookEvents::isValid('not.a.real.event'));
        self::assertArrayHasKey('ping', WebhookEvents::all());
    }

    public function test_mask_anonymous_author_passes_through_when_not_anonymous(): void
    {
        $out = WebhookEvents::maskAnonymousAuthor(
            ['author_id' => 42, 'edited_by_id' => 42, 'thread_id' => 7],
            false,
            ['author_id'],
            ['edited_by_id'],
        );

        self::assertSame(42, $out['author_id']);
        self::assertSame(42, $out['edited_by_id']);
        self::assertSame(7, $out['thread_id']);
        self::assertFalse($out['is_anonymous']);
    }

    public function test_mask_anonymous_author_nulls_author_id_and_stamps_flag(): void
    {
        $out = WebhookEvents::maskAnonymousAuthor(
            ['author_id' => 42, 'thread_id' => 7],
            true,
            ['author_id'],
        );

        self::assertNull($out['author_id'], 'anonymous author id must be redacted');
        self::assertTrue($out['is_anonymous']);
        self::assertSame(7, $out['thread_id'], 'non-author fields are untouched');
    }

    public function test_mask_anonymous_author_nulls_actor_id_only_when_it_equals_the_author(): void
    {
        // Self-edit: the actor IS the masked author, so leaving edited_by_id would
        // re-identify them — it must be nulled too.
        $selfEdit = WebhookEvents::maskAnonymousAuthor(
            ['author_id' => 42, 'edited_by_id' => 42],
            true,
            ['author_id'],
            ['edited_by_id'],
        );
        self::assertNull($selfEdit['author_id']);
        self::assertNull($selfEdit['edited_by_id']);

        // Moderator edit: the actor is a different account (the moderator, not the
        // anonymous author), so their id is preserved.
        $modEdit = WebhookEvents::maskAnonymousAuthor(
            ['author_id' => 42, 'edited_by_id' => 9],
            true,
            ['author_id'],
            ['edited_by_id'],
        );
        self::assertNull($modEdit['author_id']);
        self::assertSame(9, $modEdit['edited_by_id']);
    }
}
