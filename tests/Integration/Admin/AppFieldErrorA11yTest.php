<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

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
}
