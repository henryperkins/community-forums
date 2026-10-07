<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\ConversationRepository;
use App\Repository\SettingRepository;
use DOMDocument;
use DOMXPath;
use Tests\Support\TestCase;

final class AppCreateMenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML($html);
        return new DOMXPath($document);
    }

    public function test_members_can_create_from_every_member_surface_even_the_destinations_and_errors(): void
    {
        $member = $this->makeUser(['username' => 'creator']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'create-room']);
        $topic = $this->makeThread($board, $member);
        $this->actingAs($member);

        foreach (['/', '/c/create-room', '/tags', '/t/' . $topic['thread_id'] . '-' . $topic['slug'], '/inbox',
            '/messages', '/messages/new', '/compose', '/search', '/notifications',
            '/feed', '/u/creator', '/drafts', '/settings/account', '/missing-create-page'] as $path) {
            $response = $this->get($path);
            self::assertNotSame('', $response->body(), $path . ' status ' . $response->status());
            $dom = $this->dom($response->body());
            self::assertSame(1, $dom->query('//main[@id="main"]/*[1][@data-subheader]')->length, $path);
            self::assertSame(0, $dom->query('//header[contains(@class,"forum-bar")]//a[starts-with(@href,"/compose")]')->length, $path);
            $topicLinks = $dom->query('//*[@data-subheader]//a[starts-with(@href,"/compose")]');
            self::assertSame(1, $topicLinks->length, $path);
            $expected = str_starts_with($path, '/c/') || str_starts_with($path, '/t/')
                ? '/compose?board=create-room' : '/compose';
            self::assertSame($expected, $topicLinks->item(0)->getAttribute('href'), $path);
            $messageLinks = $dom->query('//*[@data-subheader]//a[@href="/messages/new"]');
            self::assertSame(1, $messageLinks->length, $path);
            self::assertSame($path === '/compose' ? 'page' : '', $topicLinks->item(0)->getAttribute('aria-current'));
            self::assertSame($path === '/messages/new' ? 'page' : '', $messageLinks->item(0)->getAttribute('aria-current'));
        }
    }

    public function test_guests_get_only_leading_content_and_no_empty_band_or_create_control(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $topic = $this->makeThread($board, $this->makeUser());
        foreach (['/' => 1, '/c/' . $board['slug'] => 1, '/t/' . $topic['thread_id'] . '-' . $topic['slug'] => 1,
            '/tags' => 0, '/search' => 0, '/missing-create-page' => 0] as $path => $bands) {
            $response = $this->get($path);
            self::assertNotSame('', $response->body(), $path . ' status ' . $response->status());
            $dom = $this->dom($response->body());
            self::assertSame($bands, $dom->query('//*[@data-subheader]')->length, $path);
            self::assertSame(0, $dom->query('//*[@data-create-menu] | //*[@data-create-trigger]')->length, $path);
        }
    }

    public function test_leading_navigation_moves_once_and_the_page_keeps_its_heading(): void
    {
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $topic = $this->makeThread($board, $member);
        $this->actingAs($member);
        foreach (['/' => 'Board index panes', '/c/' . $board['slug'] => 'Breadcrumb',
            '/t/' . $topic['thread_id'] . '-' . $topic['slug'] => 'Breadcrumb'] as $path => $label) {
            $response = $this->get($path);
            self::assertNotSame('', $response->body(), $path . ' status ' . $response->status());
            $dom = $this->dom($response->body());
            self::assertSame(1, $dom->query('//nav[@aria-label="' . $label . '"]')->length);
            self::assertSame(1, $dom->query('//*[@data-subheader]//nav[@aria-label="' . $label . '"]')->length);
            self::assertSame(1, $dom->query('//main//h1')->length);
            self::assertSame(0, $dom->query('//*[@data-subheader]//h1')->length);
        }
    }

    public function test_dm_rollback_leaves_a_direct_board_aware_topic_link(): void
    {
        (new SettingRepository($this->db))->set('features', ['dms' => false]);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'topic-only']);
        $this->actingAs($this->makeUser());
        foreach (['/', '/c/topic-only', '/compose', '/messages', '/missing-create-page'] as $path) {
            $response = $this->get($path);
            self::assertNotSame('', $response->body(), $path . ' status ' . $response->status());
            $dom = $this->dom($response->body());
            self::assertSame(0, $dom->query('//*[@data-create-menu] | //*[@data-subheader]//a[@href="/messages/new"]')->length, $path);
            $link = $dom->query('//*[@data-subheader]//a[@data-create-trigger]');
            self::assertSame(1, $link->length, $path);
            self::assertSame($path === '/c/topic-only' ? '/compose?board=topic-only' : '/compose', $link->item(0)->getAttribute('href'));
        }
        self::assertGreaterThan(0, $board['id']);
    }

    public function test_account_write_restrictions_leave_creation_available(): void
    {
        foreach (['active', 'suspended', 'banned'] as $status) {
            $member = $this->makeUser(['username' => 'create' . $status, 'status' => $status,
                'suspended_until' => $status === 'suspended' ? '2999-01-01 00:00:00' : null]);
            $this->actingAs($member);
            $dom = $this->dom($this->get('/missing-create-page')->body());
            self::assertSame(1, $dom->query('//*[@data-create-trigger]')->length, $status);
            self::assertSame(1, $dom->query('//*[@data-subheader]//a[@href="/messages/new"]')->length, $status);
        }
    }

    public function test_shared_message_panel_stays_outside_responsive_room_panes(): void
    {
        $member = $this->makeUser(['username' => 'dialogmember', 'display_name' => 'Dialog member']);
        $other = $this->makeUser();
        $conversationId = (new ConversationRepository($this->db))->findOrCreateBetween((int) $member['id'], (int) $other['id']);
        $this->actingAs($member);

        foreach ([true, false] as $groupsEnabled) {
            (new SettingRepository($this->db))->set('features', ['group_dms' => $groupsEnabled]);
            foreach (['/messages', '/messages/new', '/messages/' . $conversationId] as $path) {
                $response = $this->get($path);
                $this->assertStatus(200, $response);
                $dom = $this->dom($response->body());
                self::assertSame(1, $dom->query('//main[@id="main"]/*[@data-dm-compose]')->length, $path . ': hidden list/thread panes must not hide shared creation');
                self::assertSame(1, $dom->query('//*[@data-dm-compose]')->length, $path);
                self::assertSame(1, $dom->query('//*[@data-dm-compose][@hidden]')->length, $path . ': ordinary visits keep the dialog closed');
                self::assertSame(1, $dom->query('//*[@data-dm-compose]//form[@action="/messages"]//input[@name="origin"][@value="dialog"]')->length, $path);
                self::assertSame(1, $dom->query('//*[@id="dm-to-dm-new-dialog"]')->length, $path . ': recipient IDs stay distinct from the dedicated composer');
                self::assertSame($groupsEnabled ? 1 : 0, $dom->query('//*[@data-dm-compose]//input[@name="title"]')->length, $path);
                self::assertSame($groupsEnabled ? '1' : '0', $dom->query('//*[@data-dm-compose]//*[@data-dm-picker]')->item(0)->getAttribute('data-dm-allow-groups'), $path);
                self::assertStringContainsString('Dialog member', $dom->query('//*[@data-dm-compose]')->item(0)->textContent, $path . ': the dialog keeps the current identity');
            }
        }
    }

    public function test_dialog_errors_keep_the_form_visible_without_an_implicit_details_summary(): void
    {
        $this->actingAs($this->makeUser(['username' => 'dialogcreator']));
        $response = $this->post('/messages', ['origin' => 'dialog', 'to' => 'unknown_member', 'title' => 'Keep my group title', 'body' => 'Keep my typed letter']);
        $this->assertStatus(422, $response);
        $dom = $this->dom($response->body());
        self::assertSame(1, $dom->query('//main[@id="main"]/*[@data-dm-compose][not(@hidden)]')->length);
        self::assertSame(1, $dom->query('//*[@data-dm-compose][not(@hidden)]')->length);
        self::assertSame(0, $dom->query('//summary[contains(@class,"dm-new-btn")] | //details[not(summary)]')->length);
        self::assertSame('Keep my typed letter', $dom->query('//*[@data-dm-compose]//textarea[@name="body"]')->item(0)->textContent);
        self::assertSame('unknown_member', $dom->query('//*[@data-dm-compose]//input[@name="to"]')->item(0)->getAttribute('value'));
        self::assertSame('Keep my group title', $dom->query('//*[@data-dm-compose]//input[@name="title"]')->item(0)->getAttribute('value'));
        self::assertSame(2, $dom->query('//*[@data-dm-compose]//a[@data-close-compose][@href="/messages"]')->length);
    }
}
