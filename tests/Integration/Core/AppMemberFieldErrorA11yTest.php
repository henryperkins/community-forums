<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\AssertsFieldWiring;
use Tests\Support\TestCase;

/**
 * Accessible field errors on the MEMBER surface — the admin console got this
 * with the round-2 audit ({@see \Tests\Integration\Admin\AppFieldErrorA11yTest});
 * the auth, account, and composer forms kept hand-rolled error markup with no
 * programmatic link between an input and its message.
 *
 * Two things are asserted here that the admin twin does not need:
 *
 *  - The auth forms and the setup wizard hard-code autofocus on their first
 *    field. That attribute has to yield to field_attrs()' error focus, or a 422
 *    on a later field leaves focus on a control that is perfectly fine.
 *  - A composer mount whose wrapper carries its own fields (title, board) must
 *    not also autofocus its body, or the document holds two autofocus targets
 *    and the SECOND error silently wins the browser's "first in tree order".
 *
 * There is no JavaScript in this path, so the rendered markup IS the behaviour.
 */
final class AppMemberFieldErrorA11yTest extends TestCase
{
    use AssertsFieldWiring;

    protected function setUp(): void
    {
        parent::setUp();
        // Satisfies the setup gate, which would otherwise redirect every POST.
        $this->makeAdmin();
    }

    public function test_register_422_focuses_the_errored_field_not_the_static_first_field(): void
    {
        $this->get('/register'); // seeds the guest CSRF secret, as a real signup would
        $response = $this->post('/register', [
            'username' => 'a11y_new_member',
            'display_name' => '',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);

        $this->assertStatus(422, $response);
        $body = $response->body();
        self::assertStringContainsString('id="err-email"', $body);

        $email = $this->assertFieldWired($body, 'input', 'email');
        self::assertStringContainsString('autofocus', $email);

        // The regression this guards: username hard-codes autofocus, and two
        // autofocus attributes leave focus on the field that was already valid.
        $matched = preg_match('/<input\b(?=[^>]*\bname="username")[^>]*>/', $body, $matches);
        self::assertSame(1, $matched, 'Expected to find the username field.');
        self::assertStringNotContainsString('autofocus', $matches[0]);
        self::assertStringNotContainsString('aria-invalid', $matches[0]);
    }

    public function test_account_profile_422_wires_the_website_error_to_its_input(): void
    {
        $this->actingAs($this->makeUser(['username' => 'a11y_profile_member']));
        $response = $this->post('/settings/account', [
            'display_name' => 'Fine name',
            'website' => 'not-a-url',
        ]);

        $this->assertStatus(422, $response);
        $body = $response->body();
        self::assertStringContainsString('id="err-website"', $body);
        $this->assertFieldWired($body, 'input', 'website');

        // The other three fields in the same .field-grid stay untouched.
        self::assertSame(1, substr_count($body, 'aria-invalid="true"'));
    }

    public function test_reply_422_wires_the_composer_textarea_to_its_error(): void
    {
        $author = $this->makeUser(['username' => 'a11y_reply_member']);
        $board = $this->makeBoard($this->makeCategory('A11y Replies'), ['slug' => 'a11y-replies']);
        $threadId = (int) $this->makeThread($board, $author)['thread_id'];

        $this->actingAs($author);
        $response = $this->post('/t/' . $threadId . '/reply', ['body' => '   ']);

        $this->assertStatus(422, $response);
        $body = $response->body();
        $errorId = 'composer-body-error-reply-thread-' . $threadId;
        self::assertStringContainsString('id="' . $errorId . '"', $body);

        $textarea = $this->assertFieldWired(
            $body,
            'textarea',
            'body',
            $errorId,
            'composer-body-reply-thread-' . $threadId,
        );
        // Nothing else on the thread page claims focus, so the body takes it.
        self::assertStringContainsString('autofocus', $textarea);
    }

    public function test_new_topic_422_gives_the_title_focus_and_the_body_none(): void
    {
        $author = $this->makeUser(['username' => 'a11y_topic_member']);
        $board = $this->makeBoard($this->makeCategory('A11y Topics'), ['slug' => 'a11y-topics']);

        $this->actingAs($author);
        $response = $this->post('/threads', [
            'board_id' => (string) (int) $board['id'],
            'title' => '',
            'body' => '',
        ]);

        $this->assertStatus(422, $response);
        $body = $response->body();

        $title = $this->assertFieldWired($body, 'input', 'title');
        self::assertStringContainsString('autofocus', $title);

        // Both fields errored. The title errored FIRST, so the composer body
        // carries its wiring but explicitly declines the focus.
        $textarea = $this->assertFieldWired(
            $body,
            'textarea',
            'body',
            'composer-body-error-new-thread-page',
            'composer-body-new-thread-page',
        );
        self::assertStringNotContainsString('autofocus', $textarea);
        self::assertSame(1, substr_count($body, 'autofocus'));
    }

    public function test_security_page_scopes_its_error_to_the_form_that_raised_it(): void
    {
        $this->actingAs($this->makeUser(['username' => 'a11y_security_member']));
        $response = $this->post('/settings/security', [
            'current_password' => 'definitely-wrong',
            'new_password' => 'anewpassword123',
            'new_password_confirm' => 'anewpassword123',
        ]);

        $this->assertStatus(422, $response);
        $body = $response->body();

        // Five forms on this page carry a current_password field. Only the one
        // that raised the error may be marked, and its id must appear once.
        $this->assertFieldWiredInForm(
            $body,
            '/settings/security',
            'input',
            'current_password',
            'err-password-current_password',
        );
        self::assertSame(1, substr_count($body, 'aria-invalid="true"'));
        self::assertSame(1, substr_count($body, 'id="err-password-current_password"'));
        self::assertStringNotContainsString('err-totp_enroll-current_password', $body);
    }

    public function test_a_failed_action_whose_form_is_gone_still_surfaces_its_error(): void
    {
        $this->actingAs($this->makeUser(['username' => 'a11y_orphan_member']));

        // Two-factor is off, so the panel does not render the disable form —
        // but the password check runs before the not-enabled check, so this
        // 422s with an error whose field is nowhere on the page. Scoping the
        // error to its form must not turn that into a silently blank response.
        $response = $this->post('/settings/security/totp/disable', [
            'current_password' => 'definitely-wrong',
            'disable_code' => '000000',
        ]);

        $this->assertStatus(422, $response);
        $body = $response->body();
        self::assertSame(1, substr_count($body, 'Your current password is incorrect.'));
        // Surfaced centrally, not attributed to the password-change field.
        self::assertSame(0, substr_count($body, 'aria-invalid="true"'));
        self::assertSame(1, substr_count($body, 'autofocus'));
    }
}
