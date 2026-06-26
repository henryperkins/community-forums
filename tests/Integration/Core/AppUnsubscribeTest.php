<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Controller\UnsubscribeController;
use App\Repository\EmailSuppressionRepository;
use App\Support\SignedToken;
use Tests\Support\TestCase;

/**
 * Login-free signed unsubscribe (P2-04): a valid token suppresses on confirm,
 * a forged token is rejected, and re-subscribe (recovery) requires the token too.
 */
final class AppUnsubscribeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function key(): string
    {
        return (string) $this->config->get('app.key', '');
    }

    public function testValidTokenSuppressesOnConfirm(): void
    {
        $email = 'member@example.test';
        $token = SignedToken::sign('unsubscribe', strtolower($email), $this->key());

        // GET shows a confirmation (no suppression yet — prefetch-safe).
        $get = $this->get('/unsubscribe', ['email' => $email, 'token' => $token]);
        $this->assertStatus(200, $get);
        self::assertFalse((new EmailSuppressionRepository($this->db))->isSuppressed($email));

        // POST confirms.
        $post = $this->post('/unsubscribe', ['email' => $email, 'token' => $token]);
        $this->assertStatus(200, $post);
        self::assertTrue((new EmailSuppressionRepository($this->db))->isSuppressed($email));
    }

    public function testForgedTokenIsRejected(): void
    {
        $r = $this->get('/unsubscribe', ['email' => 'member@example.test', 'token' => 'not-a-valid-token']);
        $this->assertStatus(404, $r);
        self::assertFalse((new EmailSuppressionRepository($this->db))->isSuppressed('member@example.test'));
    }

    public function testResubscribeLiftsSuppression(): void
    {
        $email = 'member@example.test';
        $token = SignedToken::sign('unsubscribe', strtolower($email), $this->key());
        (new EmailSuppressionRepository($this->db))->suppress($email, 'unsubscribe');

        $this->get('/unsubscribe', ['email' => $email, 'token' => $token]); // establish guest CSRF cookie
        $r = $this->post('/resubscribe', ['email' => $email, 'token' => $token]);
        $this->assertStatus(200, $r);
        self::assertFalse((new EmailSuppressionRepository($this->db))->isSuppressed($email));
    }

    public function testLinkHelperRoundTrips(): void
    {
        $link = UnsubscribeController::link('https://forum.test/', 'A@Example.test', $this->key());
        self::assertStringContainsString('/unsubscribe?email=', $link);
        parse_str((string) parse_url($link, PHP_URL_QUERY), $q);
        self::assertTrue(SignedToken::verify('unsubscribe', strtolower($q['email']), $q['token'], $this->key()));
    }
}
