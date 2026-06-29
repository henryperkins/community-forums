<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\EgressBlockedException;
use App\Security\EgressGuard;
use App\Service\Webhook\CurlWebhookTransport;
use App\Service\Webhook\FakeWebhookTransport;
use App\Service\Webhook\WebhookResponse;
use PHPUnit\Framework\TestCase;

final class WebhookTransportTest extends TestCase
{
    public function test_fake_records_calls_and_returns_canned_response(): void
    {
        $fake = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(202, null));
        $resp = $fake->deliver('https://x.test/h', ['A' => 'b'], '{}', 5);
        self::assertSame(202, $resp->status);
        self::assertCount(1, $fake->calls);
        self::assertSame('https://x.test/h', $fake->calls[0]['url']);
    }

    public function test_curl_transport_blocks_denied_target_before_any_request(): void
    {
        $guard = new EgressGuard(false, [], static fn (): array => ['10.0.0.5']);
        $transport = new CurlWebhookTransport($guard);
        $this->expectException(EgressBlockedException::class);
        $transport->deliver('https://evil.test/hook', [], '{}', 5);
    }
}
