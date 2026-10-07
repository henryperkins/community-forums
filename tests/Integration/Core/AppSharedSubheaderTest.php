<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Repository\SavedFeedRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadUserRepository;
use DOMDocument;
use DOMXPath;
use Tests\Support\TestCase;

final class AppSharedSubheaderTest extends TestCase
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

    private function assertInboxRoomStructure(DOMXPath $dom): void
    {
        self::assertSame(0, $dom->query('//*[@data-subheader]//*[@data-inbox]')->length, 'Unclosed toolbar markup must not absorb the Inbox room.');
        self::assertSame(1, $dom->query('//main[@id="main"]/*[@data-inbox]')->length, 'The Inbox room is a direct child of the main landmark.');
        self::assertSame(1, $dom->query('//main[@id="main"]/*[@data-inbox]/preceding-sibling::*[@data-subheader]')->length, 'The room follows the shared row as its sibling.');
        self::assertSame(0, $dom->query('//main[@id="main"]/*[@data-inbox]/preceding-sibling::*[not(@data-subheader) and not(contains(concat(" ", @class, " "), " flash "))]')->length, 'Only flash messages may appear between the row and room.');
    }

    public function test_inbox_controls_share_the_create_row_and_page_read_submits_only_displayed_topics(): void
    {
        $member = $this->makeUser();
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $state = new ThreadUserRepository($this->db);
        $perPage = (int) $this->config->get('pagination.threads_per_page', 20);
        for ($i = 0; $i < $perPage + 2; $i++) {
            $topic = $this->makeThread($board, $author, 'Header contract topic ' . $i);
            $state->setStar((int) $member['id'], (int) $topic['thread_id'], true);
        }
        $this->actingAs($member);

        foreach ([true, false] as $dmsEnabled) {
            (new SettingRepository($this->db))->set('features', ['dms' => $dmsEnabled]);
            $response = $this->get('/inbox', ['scope' => 'starred', 'order' => 'newest', 'page' => '2']);
            $this->assertStatus(200, $response);
            $dom = $this->dom($response->body());
            $this->assertInboxRoomStructure($dom);

            self::assertSame(1, $dom->query('//main//h1')->length);
            self::assertSame(1, $dom->query('//*[@data-subheader]//h1[normalize-space(.)="Inbox"]')->length);
            foreach (['@data-inbox-scope-menu', 'contains(concat(" ", @class, " "), " inbox-sort-menu ")',
                'contains(concat(" ", @class, " "), " inbox-actions ")', '@data-create-trigger',
                '@data-inbox-current-count', '@data-inbox-count-label'] as $predicate) {
                self::assertSame(1, $dom->query('//*[@data-subheader]//*[' . $predicate . ']')->length, $predicate);
                self::assertSame(1, $dom->query('//*[' . $predicate . ']')->length, $predicate . ' must render once');
                self::assertSame(0, $dom->query('//*[@data-inbox]//*[' . $predicate . ']')->length, $predicate . ' must not remain in the queue');
            }
            self::assertSame(0, $dom->query('//*[@data-inbox]//h1 | //*[@data-inbox]//header[contains(concat(" ", @class, " "), " inbox-list-head ")]')->length);
            self::assertSame((string) ($perPage + 2), $dom->query('//*[@data-subheader]//*[@data-inbox-current-count]')->item(0)->textContent);
            self::assertSame('topics', $dom->query('//*[@data-subheader]//*[@data-inbox-count-label]')->item(0)->textContent);
            self::assertSame('starred', $dom->query('//*[@data-inbox]')->item(0)->getAttribute('data-inbox-scope'));
            self::assertSame('newest', $dom->query('//*[@data-inbox]')->item(0)->getAttribute('data-inbox-order'));
            self::assertSame($dmsEnabled ? 1 : 0, $dom->query('//*[@data-subheader]//*[@data-create-menu]')->length);

            $scopeLink = $dom->query('//*[@data-subheader]//*[@data-inbox-scope-menu]//a[@aria-current="page"]');
            self::assertSame(1, $scopeLink->length);
            self::assertSame('/inbox?scope=starred&order=newest', $scopeLink->item(0)->getAttribute('href'));
            $sortLink = $dom->query('//*[@data-subheader]//*[contains(concat(" ", @class, " "), " inbox-sort-menu ")]//a[@aria-current="page"]');
            self::assertSame(1, $sortLink->length);
            self::assertSame('/inbox?scope=starred&order=newest', $sortLink->item(0)->getAttribute('href'));

            $forms = $dom->query('//*[@data-subheader]//*[contains(concat(" ", @class, " "), " inbox-actions ")]//form[@action="/inbox/bulk"]');
            self::assertSame(1, $forms->length, 'The overflow must retain its native page-read form.');
            $form = $forms->item(0);
            self::assertSame('post', strtolower($form->getAttribute('method')));
            $token = $dom->query('.//input[@name="_token"]', $form);
            self::assertSame(1, $token->length);
            self::assertNotSame('', $token->item(0)->getAttribute('value'));
            foreach (['scope' => 'starred', 'order' => 'newest', 'page' => '2', 'action' => 'read'] as $name => $value) {
                $field = $dom->query('.//input[@name="' . $name . '"]', $form);
                self::assertSame(1, $field->length, $name);
                self::assertSame($value, $field->item(0)->getAttribute('value'), $name);
            }
            $displayedIds = [];
            foreach ($dom->query('//*[@data-inbox-row]') as $row) {
                $displayedIds[] = $row->getAttribute('data-thread-id');
            }
            $submittedIds = [];
            foreach ($dom->query('.//input[@name="thread_ids[]"]', $form) as $field) {
                $submittedIds[] = $field->getAttribute('value');
            }
            self::assertCount(2, $displayedIds, 'The page-two fixture must distinguish this page from the entire queue.');
            sort($displayedIds);
            sort($submittedIds);
            self::assertSame($displayedIds, $submittedIds);
            self::assertSame(1, $dom->query('//*[@data-subheader]//*[contains(concat(" ", @class, " "), " inbox-help ")]')->length);
        }
    }

    public function test_empty_inbox_keeps_its_header_choices_and_omits_the_page_read_action(): void
    {
        $this->actingAs($this->makeUser());
        $response = $this->get('/inbox', ['scope' => 'starred', 'order' => 'commended']);
        $this->assertStatus(200, $response);
        $dom = $this->dom($response->body());
        $this->assertInboxRoomStructure($dom);
        self::assertSame(1, $dom->query('//*[@data-subheader]//h1[normalize-space(.)="Inbox"]')->length);
        self::assertSame('0', $dom->query('//*[@data-subheader]//*[@data-inbox-current-count]')->item(0)->textContent);
        self::assertSame('topics', $dom->query('//*[@data-subheader]//*[@data-inbox-count-label]')->item(0)->textContent);
        self::assertSame(1, $dom->query('//*[@data-subheader]//*[@data-inbox-scope-menu]')->length);
        self::assertSame(1, $dom->query('//*[@data-subheader]//*[contains(concat(" ", @class, " "), " inbox-sort-menu ")]')->length);
        self::assertSame(0, $dom->query('//*[@data-subheader]//form[@action="/inbox/bulk"]')->length);
        self::assertStringContainsString('Nothing in Starred.', $response->body());
        self::assertStringContainsString('/inbox?scope=for_you&amp;order=commended', $response->body());
    }

    public function test_utility_page_headings_share_the_create_row_without_duplicate_titles(): void
    {
        $this->makeBoard($this->makeCategory());
        $this->actingAs($this->makeUser());
        foreach (['/messages', '/search', '/compose', '/notifications', '/settings/account', '/settings/appearance',
            '/settings/security', '/settings/notifications', '/feed', '/tags', '/drafts', '/users-online'] as $path) {
            $response = $this->get($path);
            $this->assertStatus(200, $response);
            $dom = $this->dom($response->body());
            self::assertSame(1, $dom->query('//main//h1')->length, $path);
            $heading = $dom->query('//*[@data-subheader]//h1');
            self::assertSame(1, $heading->length, $path . ' must put its page context beside creation');
            self::assertNotSame('', trim($heading->item(0)->textContent), $path);
            self::assertSame(1, $dom->query('//*[@data-subheader]//*[@data-create-trigger]')->length, $path);
        }
    }

    public function test_feed_choices_and_saved_feed_management_share_the_heading_row(): void
    {
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->actingAs($member);
        foreach ([true, false] as $expanded) {
            (new SettingRepository($this->db))->set('features', ['expanded_feeds' => $expanded]);
            $response = $this->get('/feed', ['view' => 'latest']);
            $this->assertStatus(200, $response);
            $dom = $this->dom($response->body());
            self::assertSame(1, $dom->query('//*[@data-subheader]//nav[@aria-label="Feed views"]')->length);
            self::assertSame(1, $dom->query('//nav[@aria-label="Feed views"]')->length);
            self::assertSame(1, $dom->query('//*[@data-subheader]//a[@href="/feed?view=following"]')->length);
            self::assertSame($expanded ? 1 : 0, $dom->query('//*[@data-subheader]//a[@href="/feed?view=latest"]')->length);
            self::assertSame($expanded ? 'Latest' : 'Following', trim($dom->query('//*[@data-subheader]//h1')->item(0)->textContent));
        }
        (new SettingRepository($this->db))->set('features', ['saved_feeds' => true]);
        $feedId = (new SavedFeedRepository($this->db))->create(
            (int) $member['id'],
            'My saved council',
            json_encode(['board_ids' => [(int) $board['id']], 'sort' => 'latest'], JSON_THROW_ON_ERROR),
            false,
        );
        $response = $this->get('/feeds/saved/' . $feedId);
        $this->assertStatus(200, $response);
        $dom = $this->dom($response->body());
        self::assertSame('My saved council', trim($dom->query('//*[@data-subheader]//h1')->item(0)->textContent));
        self::assertSame(1, $dom->query('//*[@data-subheader]//a[@href="/settings/boards"][normalize-space(.)="Manage saved feeds"]')->length);
        self::assertSame(1, $dom->query('//a[@href="/settings/boards"][normalize-space(.)="Manage saved feeds"]')->length);
        self::assertSame(0, $dom->query('//nav[@aria-label="Feed views"]')->length);
    }

    public function test_leaderboard_windows_share_the_heading_row_only_when_the_ledger_is_enabled(): void
    {
        $this->actingAs($this->makeUser());
        foreach ([true, false] as $ledgerEnabled) {
            (new SettingRepository($this->db))->set('features', ['reputation_ledger' => $ledgerEnabled]);
            $response = $this->get('/leaderboard', ['window' => 'month']);
            $this->assertStatus(200, $response);
            $dom = $this->dom($response->body());
            self::assertSame(1, $dom->query('//*[@data-subheader]//h1')->length);
            self::assertSame($ledgerEnabled ? 1 : 0, $dom->query('//*[@data-subheader]//nav[@aria-label="Leaderboard windows"]')->length);
            self::assertSame($ledgerEnabled ? 1 : 0, $dom->query('//nav[@aria-label="Leaderboard windows"]')->length);
            foreach (['week' => 'Week', 'month' => 'Month', 'all' => 'All time'] as $window => $label) {
                self::assertSame($ledgerEnabled ? 1 : 0, $dom->query('//*[@data-subheader]//a[@href="/leaderboard?window=' . $window . '"][normalize-space(.)="' . $label . '"]')->length);
            }
        }
    }

    public function test_tag_breadcrumb_and_gated_follow_form_share_the_member_heading_row(): void
    {
        $member = $this->makeUser();
        (new TagRepository($this->db))->create('shared-header-tag', 'Shared header tag', 'Tag description stays in the content.', (int) $member['id']);
        $guest = $this->get('/tags/shared-header-tag');
        $this->assertStatus(200, $guest);
        $guestDom = $this->dom($guest->body());
        self::assertSame(0, $guestDom->query('//*[@data-subheader]')->length);
        self::assertSame(1, $guestDom->query('//main//h1[normalize-space(.)="Shared header tag"]')->length);

        $this->actingAs($member);
        foreach ([true, false] as $expanded) {
            (new SettingRepository($this->db))->set('features', ['expanded_feeds' => $expanded]);
            $response = $this->get('/tags/shared-header-tag');
            $this->assertStatus(200, $response);
            $dom = $this->dom($response->body());
            self::assertSame(1, $dom->query('//*[@data-subheader]//a[@href="/tags"][normalize-space(.)="Tags"]')->length);
            self::assertSame(1, $dom->query('//*[@data-subheader]//h1[normalize-space(.)="Shared header tag"]')->length);
            self::assertSame(1, $dom->query('//main//h1')->length);
            $forms = $dom->query('//*[@data-subheader]//form[@action="/tags/shared-header-tag/follow"]');
            self::assertSame($expanded ? 1 : 0, $forms->length);
            self::assertSame($expanded ? 1 : 0, $dom->query('//form[@action="/tags/shared-header-tag/follow"]')->length);
            if ($expanded) {
                self::assertSame('post', strtolower($forms->item(0)->getAttribute('method')));
                self::assertSame(1, $dom->query('.//input[@name="_token"]', $forms->item(0))->length);
                self::assertNotSame('', $dom->query('.//input[@name="_token"]', $forms->item(0))->item(0)->getAttribute('value'));
                self::assertSame(1, $dom->query('.//button[normalize-space(.)="Follow tag"]', $forms->item(0))->length);
            }
            self::assertStringContainsString('Tag description stays in the content.', $response->body());
        }
    }
}
