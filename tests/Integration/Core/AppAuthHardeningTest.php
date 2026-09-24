<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Response;
use Tests\Support\TestCase;

/**
 * The login/logout harden pass (2026-09-24 audit): what a signed-in page leaves
 * in the browser, a Log out pressed from a stale tab, a refused sign-in reaching
 * a screen reader, and a guest's way back to the page they were reading.
 */
final class AppAuthHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // an initialised install, so the auth routes answer
    }

    public function test_a_signed_in_page_is_not_kept_by_the_browser(): void
    {
        $this->actingAs($this->makeUser());

        foreach (['/inbox', '/settings/account', '/'] as $path) {
            $response = $this->get($path);
            $this->assertStatus(200, $response);
            self::assertSame('private, no-store', $response->getHeader('Cache-Control'), $path);
        }
    }

    public function test_a_signed_in_json_answer_keeps_its_default_caching(): void
    {
        $this->actingAs($this->makeUser());
        $this->get('/');

        // History never shows these, and the composer's draft discard never
        // reads its body: under no-store Chromium keeps that request open.
        $discard = $this->post('/api/drafts/~2Fthreads/discard');
        $this->assertStatus(200, $discard);
        self::assertStringStartsWith('application/json', (string) $discard->getHeader('Content-Type'));
        self::assertNull($discard->getHeader('Cache-Control'));

        $bell = $this->get('/notifications/bell', ['format' => 'json']);
        self::assertStringStartsWith('application/json', (string) $bell->getHeader('Content-Type'));
        self::assertNull($bell->getHeader('Cache-Control'));
    }

    public function test_a_guest_page_gains_no_caching_policy(): void
    {
        $response = $this->get('/');
        $this->assertStatus(200, $response);
        self::assertNull($response->getHeader('Cache-Control'));
    }

    public function test_a_route_with_its_own_policy_keeps_it_for_members(): void
    {
        $this->actingAs($this->makeUser());

        // ADR 0031 §12: the roster keeps the back/forward cache on purpose.
        self::assertSame('private, no-cache', $this->get('/users-online')->getHeader('Cache-Control'));
    }

    public function test_log_out_from_a_second_tab_confirms_instead_of_failing(): void
    {
        $this->actingAs($this->makeUser());
        $this->get('/');
        $staleTabToken = $this->csrfToken();

        $this->assertRedirect($this->post('/logout'), '/');

        $second = $this->post('/logout', ['_token' => $staleTabToken]);
        $this->assertRedirect($second, '/');
        $this->assertSeeText($this->get('/'), 'You are already signed out.');
    }

    public function test_a_live_session_with_a_forged_token_is_still_refused(): void
    {
        $this->actingAs($this->makeUser());
        $this->get('/');

        $this->assertStatus(403, $this->post('/logout', ['_token' => str_repeat('0', 64)]));
        $this->assertSeeText($this->get('/'), 'Log out');
    }

    public function test_a_refused_sign_in_is_read_out_from_the_password_field(): void
    {
        $this->makeUser(['email' => 'member@example.test', 'password' => 'password123']);
        $this->get('/login');

        $response = $this->post('/login', ['email' => 'member@example.test', 'password' => 'not-it']);
        $this->assertStatus(422, $response);

        $xpath = $this->xpath($response);
        $message = $xpath->query('//*[@id="login-error"]');
        self::assertSame(1, $message->length);
        self::assertSame('alert', $message->item(0)?->attributes?->getNamedItem('role')?->nodeValue);

        $email = $this->input($xpath, 'email');
        $password = $this->input($xpath, 'password');
        self::assertSame('login-error', $email->getAttribute('aria-describedby'));
        self::assertSame('login-error', $password->getAttribute('aria-describedby'));
        // The message names no field on purpose, so neither is marked invalid.
        self::assertFalse($email->hasAttribute('aria-invalid'));
        self::assertFalse($password->hasAttribute('aria-invalid'));
        self::assertSame('member@example.test', $email->getAttribute('value'));
        self::assertTrue($password->hasAttribute('autofocus'));
        self::assertSame(1, $xpath->query('//*[@autofocus]')->length);
    }

    public function test_a_fresh_login_page_starts_at_the_email(): void
    {
        $xpath = $this->xpath($this->get('/login'));

        $email = $this->input($xpath, 'email');
        self::assertTrue($email->hasAttribute('autofocus'));
        self::assertFalse($email->hasAttribute('aria-describedby'));
        self::assertSame(1, $xpath->query('//*[@autofocus]')->length);
    }

    public function test_the_passkey_error_line_is_a_live_region_from_the_start(): void
    {
        $line = $this->xpath($this->get('/login'))->query('//*[@data-passkey-signin-error]');

        self::assertSame(1, $line->length);
        $node = $line->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);
        self::assertSame('alert', $node->getAttribute('role'));
        self::assertFalse($node->hasAttribute('hidden'), 'a hidden live region is not announced when its text arrives');
        self::assertSame('', trim($node->textContent));
    }

    public function test_topbar_log_in_brings_a_guest_back_to_the_page_they_were_reading(): void
    {
        $this->makeUser(['email' => 'reader@example.test', 'password' => 'password123']);
        $board = $this->makeBoard($this->makeCategory());
        $path = '/c/' . $board['slug'];

        $href = $this->signInHref($this->get($path));
        self::assertSame('/login?next=' . rawurlencode($path), $href);

        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
        $this->get('/login', $query);
        $response = $this->post('/login', [
            'email' => 'reader@example.test',
            'password' => 'password123',
            'next' => $query['next'],
        ]);
        $this->assertRedirect($response, $path);
    }

    public function test_topbar_log_in_keeps_the_default_landing_from_the_index(): void
    {
        self::assertSame('/login', $this->signInHref($this->get('/')));
    }

    public function test_topbar_log_in_re_encodes_the_path_it_returns_to(): void
    {
        // Request::path() is decoded: a space must reach `next` as %20, not raw.
        $response = $this->get('/c/no such board');
        $this->assertStatus(404, $response);

        self::assertSame('/login?next=' . rawurlencode('/c/no%20such%20board'), $this->signInHref($response));
    }

    private function xpath(Response $response): \DOMXPath
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML($response->body());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($doc);
    }

    private function input(\DOMXPath $xpath, string $name): \DOMElement
    {
        $node = $xpath->query('//form[@action="/login"]//input[@name="' . $name . '"]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $node, $name);

        return $node;
    }

    private function signInHref(Response $response): string
    {
        $link = $this->xpath($response)->query('//a[contains(concat(" ", @class, " "), " forum-bar-signin ")]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $link);

        return $link->getAttribute('href');
    }
}
