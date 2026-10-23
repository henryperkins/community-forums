<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppSettingsLayoutTest extends TestCase
{
    private array $member;
    private array $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        $this->member = $this->makeUser();
        $this->board = $this->makeBoard($this->makeCategory());
        $this->actingAs($this->member);
    }

    private function dom(string $body): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($body);
        return new \DOMXPath($document);
    }

    public function test_profile_pairs_keep_labels_and_individual_fields_without_nested_pane_or_decoration(): void
    {
        $response = $this->get('/settings/account');
        $this->assertStatus(200, $response);
        $dom = $this->dom($response->body());
        self::assertSame(1, $dom->query('//form[@action="/settings/account" and @class="account-profile-form"]')->length);
        self::assertSame(0, $dom->query('//form[@action="/settings/account" and contains(@class,"settings-pane")]')->length);
        self::assertSame(3, $dom->query('//fieldset[contains(@class,"custom-profile-fields")]/div[contains(@class,"custom-profile-pair")]')->length);
        for ($i = 1; $i <= 3; $i++) {
            foreach (['label' => 'Label', 'value' => 'Value'] as $name => $label) {
                self::assertSame(1, $dom->query('//label[span="' . $label . ' ' . $i . '"]/input[@name="custom_' . $name . '_' . $i . '" and contains(@class,"input-engraved")]')->length);
            }
        }
        self::assertStringNotContainsString('row-bullet', $response->body());
        self::assertStringNotContainsString('row-mark', $response->body());
    }

    public function test_folder_management_is_native_closed_by_default_and_reopens_only_the_failed_editor(): void
    {
        foreach (['Morning reading', 'Reference'] as $name) {
            $this->assertRedirect($this->post('/settings/board-folders', ['name' => $name]));
        }
        $id = (int) $this->db->fetchValue('SELECT id FROM board_folders WHERE user_id = ? AND name = ?', [(int) $this->member['id'], 'Morning reading']);
        $this->assertRedirect($this->post('/settings/board-folders/' . $id . '/boards', ['board_id' => $this->board['id']]));
        $dom = $this->dom($this->get('/settings/boards')->body());
        self::assertSame(2, $dom->query('//details[@class="org-manage" and not(@open)]')->length);
        self::assertSame(1, $dom->query('//a[@href="/c/' . $this->board['slug'] . '" and not(ancestor::details)]/ancestor::span[@class="org-items"]')->length);

        $response = $this->post('/settings/board-folders/' . $id . '/rename', ['name' => ' ']);
        $this->assertStatus(422, $response);
        $dom = $this->dom($response->body());
        self::assertSame(1, $dom->query('//details[@class="org-manage" and @open]')->length);
        self::assertSame(1, $dom->query('//details[@open]/summary[@aria-label="Manage folder Morning reading"]')->length);
        self::assertSame(1, $dom->query('//details[@open]//*[@role="alert" and @autofocus]')->length);
        self::assertSame(1, $dom->query('//details[@open]//input[@name="name" and @aria-invalid="true" and @value=""]')->length);
        self::assertSame(1, $dom->query('//*[@autofocus]')->length);
    }

    public function test_feed_errors_reopen_management_while_digest_opt_out_remains_outside(): void
    {
        $this->assertRedirect($this->post('/settings/saved-feeds', [
            'name' => 'Reading feed', 'board_id' => $this->board['id'], 'digest_enabled' => '1',
        ]));
        $id = (int) $this->db->fetchValue('SELECT id FROM saved_feed_filters WHERE user_id = ?', [(int) $this->member['id']]);
        $dom = $this->dom($this->get('/settings/boards')->body());
        self::assertSame(1, $dom->query('//details[not(@open)]/summary[@aria-label="Manage feed Reading feed"]')->length);
        self::assertSame(1, $dom->query('//button[normalize-space(.)="Turn off digest" and not(ancestor::details)]')->length);
        self::assertSame(1, $dom->query('//details//form[@action="/settings/saved-feeds/' . $id . '/delete"]')->length);

        $response = $this->post('/settings/saved-feeds/' . $id, [
            'name' => ' ', 'board_id' => $this->board['id'], 'digest_enabled' => '1',
        ]);
        $this->assertStatus(422, $response);
        $dom = $this->dom($response->body());
        self::assertSame(1, $dom->query('//details[@open]/summary[@aria-label="Manage feed Reading feed"]')->length);
        self::assertSame(1, $dom->query('//details[@open]//option[@value="' . $this->board['id'] . '" and @selected]')->length);
        self::assertSame(1, $dom->query('//details[@open]//input[@name="digest_enabled" and @checked]')->length);
        self::assertSame(1, $dom->query('//details[@open]//*[@role="alert" and @autofocus]')->length);
    }

    public function test_organization_sections_keep_creation_without_decorative_icon_tiles_or_duplicate_empty_hint(): void
    {
        $response = $this->get('/settings/boards');
        $this->assertStatus(200, $response);
        $dom = $this->dom($response->body());
        foreach (['board-folders', 'saved-feeds', 'bookmark-folders'] as $route) {
            self::assertSame(1, $dom->query('//form[@action="/settings/' . $route . '"]')->length);
        }
        self::assertStringNotContainsString('org-icon', $response->body());
        self::assertStringNotContainsString('Create a folder, then add boards to it.', $response->body());
        self::assertSame(0, $dom->query('//details[@class="org-manage"]')->length);
    }
}
