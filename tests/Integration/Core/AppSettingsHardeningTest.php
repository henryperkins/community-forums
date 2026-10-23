<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppSettingsHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        $this->actingAs($this->makeUser());
    }

    private function dom(string $body): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($body);
        return new \DOMXPath($document);
    }

    public function test_custom_profile_pairs_have_persistent_distinct_labels_and_retain_failed_input(): void
    {
        $response = $this->post('/settings/account', [
            'custom_label_1' => 'A public fact', 'custom_value_1' => 'A retained value',
            'website' => 'ftp://example.test',
        ]);
        $this->assertStatus(422, $response);
        $dom = $this->dom($response->body());
        for ($i = 1; $i <= 3; $i++) {
            foreach (['label' => 'Label', 'value' => 'Value'] as $key => $label) {
                self::assertSame(1, $dom->query('//label[span="' . $label . ' ' . $i . '"]/input[@name="custom_' . $key . '_' . $i . '"]')->length);
            }
        }
        self::assertSame(1, $dom->query('//input[@name="custom_value_1" and @value="A retained value"]')->length);
        self::assertSame(1, $dom->query('//*[@autofocus]')->length);
    }

    public function test_board_controls_name_the_board_and_expose_persisted_state(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Reading & research']);
        $response = $this->get('/settings/boards');
        $this->assertStatus(200, $response);
        $dom = $this->dom($response->body());
        foreach (['Favorite', 'Mute'] as $action) {
            self::assertSame(1, $dom->query('//button[@aria-label="' . $action . ' Reading & research" and @aria-pressed="false"]')->length);
        }
        $this->assertRedirect($this->post('/settings/boards/toggle', ['board_id' => $board['id'], 'pref' => 'mute']));
        $dom = $this->dom($this->get('/settings/boards')->body());
        self::assertSame(1, $dom->query('//button[@aria-label="Mute Reading & research" and @aria-pressed="true"]')->length);
    }

    public function test_appeals_has_current_navigation_and_an_honest_mobile_section_name(): void
    {
        $response = $this->get('/appeals');
        $this->assertStatus(200, $response);
        $dom = $this->dom($response->body());
        self::assertSame(2, $dom->query('//a[@data-settings-key="appeals" and @aria-current="page"]')->length);
        self::assertSame(1, $dom->query('//summary[normalize-space(.)="Settings: Appeals"]')->length);
    }

    public function test_notification_errors_are_wired_and_focus_only_the_first_invalid_control(): void
    {
        $response = $this->post('/settings/notifications', [
            'timezone' => 'Invalid/Zone', 'digest_hour' => '26', 'pause_all_email' => 'invalid',
        ]);
        $this->assertStatus(422, $response);
        $dom = $this->dom($response->body());
        foreach (['timezone', 'digest_hour', 'pause_all_email'] as $field) {
            self::assertSame(1, $dom->query('//*[@name="' . $field . '" and @aria-invalid="true" and contains(@aria-describedby,"err-' . $field . '")]')->length);
            self::assertSame(1, $dom->query('//*[@id="err-' . $field . '"]')->length);
        }
        self::assertSame(1, $dom->query('//select[@name="timezone"]/*[@selected and @value="Invalid/Zone"]')->length);
        self::assertSame(1, $dom->query('//*[@autofocus]')->length);
    }

    public function test_organization_errors_use_the_shared_alert_and_preserve_digest_selection(): void
    {
        $response = $this->post('/settings/saved-feeds', ['name' => ' ', 'board_id' => '', 'digest_enabled' => '1']);
        $this->assertStatus(422, $response);
        $dom = $this->dom($response->body());
        self::assertSame(1, $dom->query('//*[@id="feed-create-errors" and contains(@class,"callout-danger") and @autofocus]')->length);
        self::assertSame(1, $dom->query('//form[@action="/settings/saved-feeds"]//input[@role="switch" and @checked]')->length);
        self::assertSame(1, $dom->query('//*[@autofocus]')->length);
    }

    public function test_orphan_security_errors_use_the_shared_alert_without_password_echo(): void
    {
        $response = $this->post('/settings/security/totp/disable', ['current_password' => 'wrong-secret', 'disable_code' => '000000']);
        $this->assertStatus(422, $response);
        $dom = $this->dom($response->body());
        self::assertSame(1, $dom->query('//*[@role="alert" and contains(@class,"callout-danger") and @autofocus]')->length);
        self::assertStringNotContainsString('wrong-secret', $response->body());
    }
}
