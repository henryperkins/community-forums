<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Core\Config;
use App\Mail\ArrayMailer;
use App\Mail\CloudflareMailer;
use App\Mail\CloudflareSmtpMailer;
use App\Mail\CloudflareTransport;
use App\Mail\MailException;
use App\Mail\MailerFactory;
use App\Mail\SendmailMailer;
use PHPUnit\Framework\TestCase;

/**
 * Cloudflare Email Sending over the REST API — payload shape and the failure
 * paths the email worker depends on. The REST field names differ from the
 * Workers binding (`from.address` not `from.email`), and getting that wrong
 * fails at the API rather than locally, so it is pinned here.
 */
final class CloudflareMailerTest extends TestCase
{
    private function transport(int $status, string $body): FakeCloudflareTransport
    {
        return new FakeCloudflareTransport($status, $body);
    }

    private function ok(): string
    {
        return json_encode([
            'success' => true,
            'errors' => [],
            'result' => ['delivered' => ['member@example.test'], 'permanent_bounces' => [], 'queued' => []],
        ], JSON_THROW_ON_ERROR);
    }

    private function mailer(CloudflareTransport $transport, string $fromName = 'Retro Town'): CloudflareMailer
    {
        return new CloudflareMailer($transport, 'acct-123', 'token-abc', 'no-reply@example.test', $fromName);
    }

    public function test_posts_the_rest_payload_shape_to_the_account_endpoint(): void
    {
        $transport = $this->transport(200, $this->ok());
        $this->mailer($transport)->send('member@example.test', 'Verify your address', 'text body', '<p>html body</p>');

        self::assertSame(
            'https://api.cloudflare.com/client/v4/accounts/acct-123/email/sending/send',
            $transport->url,
        );
        self::assertSame('token-abc', $transport->token);
        self::assertSame('member@example.test', $transport->payload['to']);
        self::assertSame('Verify your address', $transport->payload['subject']);
        self::assertSame('text body', $transport->payload['text']);
        self::assertSame('<p>html body</p>', $transport->payload['html']);
        // REST uses `address`; the Workers binding uses `email`. Not interchangeable.
        self::assertSame(['address' => 'no-reply@example.test', 'name' => 'Retro Town'], $transport->payload['from']);
        self::assertArrayNotHasKey('email', (array) $transport->payload['from']);
    }

    public function test_sends_a_bare_from_address_when_no_display_name_is_configured(): void
    {
        $transport = $this->transport(200, $this->ok());
        $this->mailer($transport, '')->send('member@example.test', 'Subject', 'text');

        self::assertSame('no-reply@example.test', $transport->payload['from']);
    }

    public function test_omits_the_html_part_when_there_is_none(): void
    {
        $transport = $this->transport(200, $this->ok());
        $this->mailer($transport)->send('member@example.test', 'Subject', 'text only');

        self::assertArrayNotHasKey('html', $transport->payload);
        self::assertSame('text only', $transport->payload['text']);
    }

    public function test_is_unconfigured_until_account_token_and_from_are_all_present(): void
    {
        $transport = $this->transport(200, $this->ok());
        self::assertFalse((new CloudflareMailer($transport, '', 'token', 'no-reply@example.test'))->isConfigured());
        self::assertFalse((new CloudflareMailer($transport, 'acct', '', 'no-reply@example.test'))->isConfigured());
        self::assertFalse((new CloudflareMailer($transport, 'acct', 'token', ''))->isConfigured());
        self::assertFalse((new CloudflareMailer($transport, 'acct', 'token', 'not-an-address'))->isConfigured());
        self::assertTrue((new CloudflareMailer($transport, 'acct', 'token', 'no-reply@example.test'))->isConfigured());
    }

    public function test_unconfigured_send_throws_without_touching_the_network(): void
    {
        $transport = $this->transport(200, $this->ok());
        $mailer = new CloudflareMailer($transport, '', '', '');

        $this->expectException(MailException::class);
        try {
            $mailer->send('member@example.test', 'Subject', 'text');
        } finally {
            self::assertSame(0, $transport->calls);
        }
    }

    public function test_api_error_surfaces_the_code_and_message(): void
    {
        $body = json_encode([
            'success' => false,
            'errors' => [['code' => 1000, 'message' => 'Sender domain not verified']],
            'result' => null,
        ], JSON_THROW_ON_ERROR);

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('[1000] Sender domain not verified');

        $this->mailer($this->transport(400, $body))->send('member@example.test', 'Subject', 'text');
    }

    public function test_transport_failure_throws_so_the_worker_can_retry(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('unreachable');

        $this->mailer($this->transport(0, 'curl error 28'))->send('member@example.test', 'Subject', 'text');
    }

    /** A 200 whose result accepted nothing must not be reported as sent. */
    public function test_permanent_bounce_is_a_failure_not_a_silent_success(): void
    {
        $body = json_encode([
            'success' => true,
            'errors' => [],
            'result' => ['delivered' => [], 'permanent_bounces' => ['member@example.test'], 'queued' => []],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('permanently bounced');

        $this->mailer($this->transport(200, $body))->send('member@example.test', 'Subject', 'text');
    }

    public function test_queued_counts_as_accepted(): void
    {
        $body = json_encode([
            'success' => true,
            'errors' => [],
            'result' => ['delivered' => [], 'permanent_bounces' => [], 'queued' => ['member@example.test']],
        ], JSON_THROW_ON_ERROR);

        $id = $this->mailer($this->transport(200, $body))->send('member@example.test', 'Subject', 'text');

        self::assertStringStartsWith('cf-', $id);
    }

    public function test_factory_selects_the_driver_and_falls_back_to_the_site_name(): void
    {
        $siteName = static fn (): string => 'Retro Town';

        $cloudflare = MailerFactory::fromConfig(new Config(['mail' => [
            'driver' => 'cloudflare',
            'from' => 'no-reply@example.test',
            'cloudflare' => ['account_id' => 'acct', 'api_token' => 'token'],
        ]]), $siteName);
        self::assertInstanceOf(CloudflareMailer::class, $cloudflare);
        self::assertTrue($cloudflare->isConfigured());

        self::assertInstanceOf(ArrayMailer::class, MailerFactory::fromConfig(
            new Config(['mail' => ['driver' => 'array']]),
            $siteName,
        ));
        self::assertInstanceOf(SendmailMailer::class, MailerFactory::fromConfig(
            new Config(['mail' => ['driver' => 'sendmail', 'from' => 'no-reply@example.test']]),
            $siteName,
        ));
        // No driver configured at all still yields the historical default.
        self::assertInstanceOf(SendmailMailer::class, MailerFactory::fromConfig(new Config([]), $siteName));
    }

    /**
     * `cloudflare_smtp` is a second, separate Cloudflare transport, and it is the
     * driver the deployed Worker pins (wrangler.jsonc). It reads its own flat
     * credentials rather than the `cloudflare` sub-array. An unknown driver falls
     * back to sendmail, so if this arm ever goes missing the failure is silent:
     * a container with no MTA simply stops delivering mail. Pinned here because
     * the fall-through cannot distinguish "typo" from "dropped during a merge".
     */
    public function test_factory_selects_the_smtp_driver_with_its_own_credentials(): void
    {
        $mailer = MailerFactory::fromConfig(new Config(['mail' => [
            'driver' => 'cloudflare_smtp',
            'from' => 'no-reply@example.test',
            'cloudflare_api_token' => 'token',
            'timeout_seconds' => 30,
        ]]), static fn (): string => 'Retro Town');

        self::assertInstanceOf(CloudflareSmtpMailer::class, $mailer);
        self::assertTrue($mailer->isConfigured());
    }

    public function test_factory_leaves_cloudflare_smtp_unconfigured_without_a_token(): void
    {
        $mailer = MailerFactory::fromConfig(
            new Config(['mail' => ['driver' => 'cloudflare_smtp', 'from' => 'no-reply@example.test']]),
            static fn (): string => 'Retro Town',
        );

        self::assertInstanceOf(CloudflareSmtpMailer::class, $mailer);
        self::assertFalse($mailer->isConfigured(), 'Email must fail closed until the token is set.');
    }

    public function test_factory_leaves_cloudflare_unconfigured_without_credentials(): void
    {
        $mailer = MailerFactory::fromConfig(
            new Config(['mail' => ['driver' => 'cloudflare', 'from' => 'no-reply@example.test']]),
            static fn (): string => 'Retro Town',
        );

        self::assertInstanceOf(CloudflareMailer::class, $mailer);
        self::assertFalse($mailer->isConfigured(), 'Email must fail closed until the token and account are set.');
    }
}

/** Records the one HTTP hop so the payload can be asserted without a network. */
final class FakeCloudflareTransport implements CloudflareTransport
{
    public string $url = '';
    public string $token = '';
    /** @var array<string,mixed> */
    public array $payload = [];
    public int $calls = 0;

    public function __construct(private int $status, private string $body)
    {
    }

    public function post(string $url, string $token, array $payload, int $timeoutSeconds): array
    {
        $this->calls++;
        $this->url = $url;
        $this->token = $token;
        $this->payload = $payload;

        return ['status' => $this->status, 'body' => $this->body];
    }
}
