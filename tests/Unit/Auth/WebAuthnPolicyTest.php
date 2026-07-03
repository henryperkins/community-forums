<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Security\WebAuthn\RelyingParty;
use App\Security\WebAuthn\WebAuthnException;
use PHPUnit\Framework\TestCase;

final class WebAuthnPolicyTest extends TestCase
{
    public function test_origin_is_normalized_and_rp_id_defaults_to_the_full_host(): void
    {
        $rp = new RelyingParty('https://forum.example.com:443/', null, 'production');
        self::assertSame('https://forum.example.com', $rp->origin());
        self::assertSame('forum.example.com', $rp->rpId());
        self::assertSame(hash('sha256', 'forum.example.com', true), $rp->rpIdHash());

        $dev = new RelyingParty('http://localhost:8000', null, 'local');
        self::assertSame('http://localhost:8000', $dev->origin());
        self::assertSame('localhost', $dev->rpId());
    }

    public function test_rp_id_override_must_be_a_registrable_suffix_of_the_host(): void
    {
        $rp = new RelyingParty('https://forum.example.com', 'example.com', 'production');
        self::assertSame('example.com', $rp->rpId());

        try {
            new RelyingParty('https://forum.example.com', 'other.com', 'production');
            self::fail('Non-suffix override must refuse');
        } catch (WebAuthnException $e) {
            self::assertSame('invalid_rp_id', $e->code);
        }

        $this->expectException(WebAuthnException::class);
        new RelyingParty('https://forum.example.com', 'ple.com', 'production');
    }

    public function test_production_over_plain_http_hard_refuses_ceremonies(): void
    {
        $rp = new RelyingParty('http://forum.example.com', null, 'production');
        try {
            $rp->assertUsable();
            self::fail('Insecure production origin must hard-refuse');
        } catch (WebAuthnException $e) {
            self::assertSame('insecure_origin', $e->code);
        }

        (new RelyingParty('https://forum.example.com', null, 'production'))->assertUsable();
        (new RelyingParty('http://localhost:8000', null, 'production'))->assertUsable();
        (new RelyingParty('http://forum.example.com', null, 'testing'))->assertUsable();
        $this->addToAssertionCount(3);
    }

    public function test_unusable_app_url_refuses(): void
    {
        $this->expectException(WebAuthnException::class);
        new RelyingParty('not-a-url', null, 'production');
    }
}
