<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\CloudflareSmtpMailer;
use App\Mail\MailException;
use PHPUnit\Framework\TestCase;

final class CloudflareSmtpMailerTest extends TestCase
{
    public function test_builds_multipart_message_and_returns_cloudflare_message_id(): void
    {
        $captured = [];
        $mailer = new CloudflareSmtpMailer(
            'token',
            'noreply@candidary.online',
            'Candidary Forum',
            submit: static function (string $from, string $to, string $message) use (&$captured): string {
                $captured = compact('from', 'to', 'message');
                return "250 2.0.0 Ok <cloudflare-message@candidary.online>\r\n";
            },
        );

        $id = $mailer->send('member@example.com', 'Welcome', 'Plain body', '<p>HTML body</p>');

        self::assertTrue($mailer->isConfigured());
        self::assertSame('<cloudflare-message@candidary.online>', $id);
        self::assertSame('noreply@candidary.online', $captured['from']);
        self::assertSame('member@example.com', $captured['to']);
        self::assertStringContainsString('From: Candidary Forum <noreply@candidary.online>', $captured['message']);
        self::assertStringContainsString('Content-Type: multipart/alternative;', $captured['message']);
        self::assertStringContainsString("Plain body\r\n", $captured['message']);
        self::assertStringContainsString("<p>HTML body</p>\r\n", $captured['message']);
    }

    public function test_fails_closed_without_token_or_valid_sender(): void
    {
        self::assertFalse((new CloudflareSmtpMailer('', 'noreply@candidary.online'))->isConfigured());
        self::assertFalse((new CloudflareSmtpMailer('token', 'invalid'))->isConfigured());
    }

    public function test_returns_generated_message_id_when_the_accepted_reply_has_none(): void
    {
        $message = '';
        $mailer = new CloudflareSmtpMailer(
            'token',
            'noreply@candidary.online',
            submit: static function (string $from, string $to, string $submitted) use (&$message): string {
                $message = $submitted;
                return "250 2.0.0 Ok\r\n";
            },
        );

        $id = $mailer->send('member@example.com', 'Welcome', 'Body');

        self::assertMatchesRegularExpression('/^<[a-f0-9]{24}@candidary\.online>$/', $id);
        self::assertStringContainsString('Message-ID: ' . $id . "\r\n", $message);
    }

    public function test_real_curl_connection_failure_throws_mail_exception_without_exposing_the_token(): void
    {
        // Reserve a TCP port without listening, keeping connection refusal
        // deterministic without risking a connection to another local service.
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND);
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        self::assertIsString($address);
        $port = (int) substr($address, strrpos($address, ':') + 1);
        $apiToken = 'smtp-test-token-must-stay-private';
        $mailer = new CloudflareSmtpMailer(
            $apiToken,
            'noreply@candidary.online',
            host: '127.0.0.1',
            port: $port,
            timeoutSeconds: 1,
        );

        try {
            $mailer->send('member@example.com', 'Welcome', 'Body');
            self::fail('A refused SMTP connection must fail delivery.');
        } catch (MailException $exception) {
            self::assertStringStartsWith('Cloudflare SMTP delivery failed:', $exception->getMessage());
            self::assertStringNotContainsString($apiToken, $exception->getMessage());
        } finally {
            fclose($socket);
        }
    }

    public function test_rejects_header_injection_before_transport(): void
    {
        $mailer = new CloudflareSmtpMailer(
            'token',
            'noreply@candidary.online',
            submit: static fn (): string => throw new \RuntimeException('transport should not run'),
        );

        $this->expectException(MailException::class);
        $mailer->send('member@example.com', "Subject\r\nBcc: attacker@example.com", 'Body');
    }
}
