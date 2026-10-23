<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SubscriptionRepository;
use Tests\Support\TestCase;

final class AppSettingsCopyTest extends TestCase
{
    private array $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        $this->member = $this->makeUser();
        $this->actingAs($this->member);
    }

    private function dom(string $body): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($body);
        return new \DOMXPath($document);
    }

    public function test_creation_fields_have_persistent_specific_labels_and_a_shared_settings_introduction(): void
    {
        $response = $this->get('/settings/boards');
        $this->assertStatus(200, $response);
        $dom = $this->dom($response->body());
        foreach (['Board folder name', 'Feed name', 'Bookmark folder name'] as $label) {
            self::assertSame(1, $dom->query('//label[span="' . $label . '"]/input[@name="name"]')->length);
        }
        self::assertSame(1, $dom->query('//label[span="Board filter"]/select[@id="saved-feed-board-id"]')->length);
        self::assertStringContainsString('Manage your profile, preferences, and account security.', $response->body());
        self::assertStringNotContainsString('aria-label="Name"', $response->body());
    }

    public function test_rejected_digest_time_keeps_its_timezone_help_and_its_error_association(): void
    {
        $response = $this->post('/settings/notifications', ['timezone' => 'UTC', 'digest_hour' => '26']);
        $this->assertStatus(422, $response);
        $dom = $this->dom($response->body());
        self::assertSame(1, $dom->query('//label[span="Daily digest time"]/select[@name="digest_hour" and @autofocus and @aria-invalid="true" and contains(@aria-describedby,"digest-time-hint") and contains(@aria-describedby,"err-digest_hour")]')->length);
        self::assertSame(1, $dom->query('//p[@id="digest-time-hint" and contains(.,"UTC if none is set")]')->length);
        self::assertSame(1, $dom->query('//select[@name="digest_hour"]/option[@value="26" and @selected]')->length);
    }

    public function test_notification_action_names_do_not_disclose_an_unavailable_topic(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $this->member, 'Private reading title', 'Body');
        (new SubscriptionRepository($this->db))->set((int) $this->member['id'], 'thread', (int) $thread['thread_id'], true, true, 'daily');
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [(int) $board['id']]);

        $response = $this->get('/settings/notifications');
        $this->assertStatus(200, $response);
        $dom = $this->dom($response->body());
        self::assertStringNotContainsString('Private reading title', $response->body());
        self::assertSame(1, $dom->query('//button[@aria-label="Save subscription for Unavailable subscription"]')->length);
        self::assertSame(1, $dom->query('//button[@aria-label="Turn off notifications for Unavailable subscription" and normalize-space(.)="Turn off notifications"]')->length);
    }

    public function test_reading_and_density_descriptions_use_plain_task_language(): void
    {
        $reading = $this->get('/settings/preferences');
        $this->assertStatus(200, $reading);
        self::assertStringContainsString('Forum panes', $reading->body());
        self::assertStringNotContainsString('Member surface panes', $reading->body());
        $appearance = $this->get('/settings/appearance');
        $this->assertStatus(200, $appearance);
        self::assertStringContainsString('More space for reading', $appearance->body());
        self::assertStringContainsString('Less space for scanning', $appearance->body());
    }
}
