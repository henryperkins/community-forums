<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * Accessible field errors (round-2 audit finding 11): a 422 re-render marks the
 * offending input aria-invalid, links it to its error line via aria-describedby
 * → id, and autofocuses the first errored field. Exemplar surfaces here; the
 * shared helpers (field_error / field_attrs) carry the rest of the console.
 */
final class AppFieldErrorA11yTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_tag_create_422_wires_the_name_error_to_its_input(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'a11y_tags_admin']));
        $res = $this->post('/admin/tags', ['name' => str_repeat('x', 81), 'slug' => '']);

        $this->assertStatus(422, $res);
        $body = $res->body();
        self::assertStringContainsString('id="err-name"', $body);
        self::assertMatchesRegularExpression('/name="name"[^>]*aria-invalid="true"/', $body);
        self::assertMatchesRegularExpression('/name="name"[^>]*aria-describedby="err-name"/', $body);
        self::assertMatchesRegularExpression('/name="name"[^>]*autofocus/', $body);
    }

    public function test_suspend_422_wires_the_context_scoped_until_error(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'a11y_susp_admin']));
        $sid = (int) $this->makeUser(['username' => 'a11y_subject'])['id'];
        $res = $this->post('/admin/users/' . $sid . '/suspend', ['reason' => 'ok reason', 'until' => 'not-a-date']);

        $this->assertStatus(422, $res);
        $body = $res->body();
        self::assertStringContainsString('id="err-suspend-until"', $body);
        self::assertMatchesRegularExpression('/name="until"[^>]*aria-describedby="err-suspend-until"/', $body);
        self::assertMatchesRegularExpression('/name="until"[^>]*aria-invalid="true"/', $body);
    }

    public function test_badge_rule_select_errors_are_programmatically_linked(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'a11y_badge_rule_admin']));
        $res = $this->post('/admin/badge-rules', [
            'badge_id' => '999999',
            'rule_type' => 'not-a-rule',
            'threshold' => '0',
            'board_id' => '999999',
        ]);

        $this->assertStatus(422, $res);
        $this->assertFieldWired($res->body(), 'select', 'badge_id');
        $this->assertFieldWired($res->body(), 'select', 'rule_type');
        $this->assertFieldWired($res->body(), 'select', 'board_id');
    }

    public function test_board_edit_select_errors_are_programmatically_linked(): void
    {
        $admin = $this->makeAdmin(['username' => 'a11y_board_admin']);
        $board = $this->makeBoard($this->makeCategory('A11y Board'), ['slug' => 'a11y-board']);
        $this->actingAs($admin);
        $res = $this->post('/admin/boards/' . (int) $board['id'], [
            'category_id' => '999999',
            'name' => 'A11y Board',
            'slug' => 'a11y-board',
            'visibility' => 'secret',
            'post_min_role' => 'owner',
        ]);

        $this->assertStatus(422, $res);
        $this->assertFieldWired($res->body(), 'select', 'category_id');
        $this->assertFieldWired($res->body(), 'select', 'visibility');
        $this->assertFieldWired($res->body(), 'select', 'post_min_role');
    }

    public function test_provider_errors_preserve_help_text_and_link_each_field(): void
    {
        $this->actingAs($this->makeAdmin([
            'username' => 'a11y_provider_admin',
            'password' => 'password123',
        ]));
        $res = $this->post('/admin/providers', [
            'provider_key' => 'Bad key!',
            'display_name' => 'Broken provider',
            'issuer' => 'not-a-url',
            'client_id' => 'client',
            'client_secret' => '',
            'claim_map_json' => '{',
            'current_password' => 'password123',
        ]);

        $this->assertStatus(422, $res);
        $providerKey = $this->assertFieldWired($res->body(), 'input', 'provider_key');
        self::assertSame(1, substr_count($providerKey, 'aria-describedby='));
        self::assertStringContainsString('provider-key-help', $providerKey);
        $this->assertFieldWired($res->body(), 'input', 'issuer');
        $this->assertFieldWired($res->body(), 'input', 'client_secret');
        $this->assertFieldWired($res->body(), 'textarea', 'claim_map_json');
    }

    public function test_custom_css_error_is_programmatically_linked(): void
    {
        (new SettingRepository($this->db))->set('features', ['custom_css' => true]);
        $this->actingAs($this->makeAdmin(['username' => 'a11y_branding_admin']));
        $res = $this->post('/admin/branding', [
            'site_name' => 'A11y Site',
            'theme_default' => 'system',
            'theme_preset' => 'classic',
            'custom_css_enabled' => '1',
            'custom_css' => '.example { color: red; }',
        ]);

        $this->assertStatus(422, $res);
        $this->assertFieldWired($res->body(), 'textarea', 'custom_css');
    }

    public function test_invitation_board_error_is_programmatically_linked(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'a11y_invitation_admin']));
        $res = $this->post('/admin/invitations', ['onboarding_board_id' => '999999']);

        $this->assertStatus(422, $res);
        $this->assertFieldWired($res->body(), 'select', 'onboarding_board_id');
    }

    public function test_user_badge_and_email_errors_are_programmatically_linked(): void
    {
        $admin = $this->makeAdmin(['username' => 'a11y_badge_email_admin']);
        $target = $this->makeUser(['username' => 'a11y_badge_subject']);
        $this->actingAs($admin);

        $badge = $this->post('/admin/users/' . (int) $target['id'] . '/badges/grant', [
            'slug' => 'not-a-real-badge',
            'reason' => 'Testing validation linkage.',
        ]);
        $this->assertStatus(422, $badge);
        $this->assertFieldWired($badge->body(), 'select', 'slug');

        $email = $this->post('/admin/email/suppressions', ['email' => 'not-an-email']);
        $this->assertStatus(422, $email);
        $this->assertFieldWired($email->body(), 'input', 'email', 'err-suppress-email', 'suppress-email');
    }

    private function assertFieldWired(
        string $body,
        string $tag,
        string $field,
        ?string $errorId = null,
        ?string $elementId = null,
    ): string {
        $idAssertion = $elementId === null
            ? ''
            : '(?=[^>]*\\bid="' . preg_quote($elementId, '/') . '")';
        $matched = preg_match(
            '/<' . preg_quote($tag, '/') . '\\b'
                . '(?=[^>]*\\bname="' . preg_quote($field, '/') . '")'
                . $idAssertion
                . '[^>]*>/s',
            $body,
            $matches,
        );
        self::assertSame(1, $matched, 'Expected to find the ' . $field . ' field.');
        $element = $matches[0];
        $errorId ??= 'err-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $field);
        self::assertStringContainsString('aria-invalid="true"', $element);
        self::assertMatchesRegularExpression(
            '/aria-describedby="[^"]*' . preg_quote($errorId, '/') . '[^"]*"/',
            $element,
        );

        return $element;
    }
}
