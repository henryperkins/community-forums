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
}
