<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Support\Icons;
use Tests\Support\TestCase;

/**
 * ADR 0060: Fluent System Icons and the member's icon size (USER §4.1).
 * Appearance → Icon size stamps the document root and picks the artwork the
 * icon partial draws; the settings rail draws the current section Filled; and
 * forum jobs use their Fluent marks.
 */
final class AppIconSizePreferenceTest extends TestCase
{
    private const APPEARANCE = ['theme' => 'system', 'density' => 'comfortable', 'font_size' => 'medium'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_guests_and_new_members_draw_standard_icons(): void
    {
        $guest = $this->get('/login');
        $this->assertStatus(200, $guest);
        self::assertStringContainsString('data-icon-size="standard"', $guest->body());

        $this->actingAs($this->makeUser(['username' => 'icon_size_newcomer']));
        $page = $this->get('/settings/appearance');
        $this->assertStatus(200, $page);
        $html = $page->body();
        self::assertStringContainsString('data-icon-size="standard"', $html);
        self::assertStringContainsString('<input type="radio" name="icon_size" value="standard" checked>', $html);
        self::assertStringContainsString('<input type="radio" name="icon_size" value="large">', $html);
        // Each card previews its own size, whatever the member draws today.
        self::assertStringContainsString('<span class="icon-size-prev" aria-hidden="true"><svg class="icon icon-bell" viewBox="0 0 16 16" ', $html);
        self::assertStringContainsString('<span class="icon-size-prev is-large" aria-hidden="true"><svg class="icon icon-bell" viewBox="0 0 20 20" ', $html);
        self::assertStringContainsString('<span class="choice-card-title">Larger</span>', $html);
    }

    public function test_larger_icons_stamp_the_root_and_draw_the_20px_artwork(): void
    {
        $this->actingAs($this->makeUser(['username' => 'icon_size_larger']));
        $saved = $this->post('/settings/appearance', self::APPEARANCE + ['icon_size' => 'large']);
        $this->assertRedirectContains($saved, '/settings/appearance');

        $html = $this->get('/settings/appearance')->body();
        self::assertStringContainsString('data-icon-size="large"', $html);
        self::assertStringContainsString('<input type="radio" name="icon_size" value="large" checked>', $html);
        // The rail draws the current section Filled, at the member's size...
        self::assertStringContainsString(
            'aria-current="page"><svg class="icon icon-sun" viewBox="0 0 20 20" width="16" height="16" fill="currentColor" aria-hidden="true">'
                . self::paths(Icons::glyph('sun', 'large', 'filled')),
            $html,
        );
        // ...and every other section Regular.
        self::assertStringContainsString('<svg class="icon icon-eye" viewBox="0 0 20 20" width="16" height="16" fill="currentColor" aria-hidden="true">'
            . self::paths(Icons::glyph('eye', 'large')), $html);

        $this->post('/settings/appearance', self::APPEARANCE + ['icon_size' => 'standard']);
        $html = $this->get('/settings/appearance')->body();
        self::assertStringContainsString('data-icon-size="standard"', $html);
        self::assertStringContainsString(
            'aria-current="page"><svg class="icon icon-sun" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true">'
                . self::paths(Icons::glyph('sun', 'standard', 'filled')),
            $html,
        );
    }

    public function test_an_unknown_icon_size_falls_back_to_standard(): void
    {
        $this->actingAs($this->makeUser(['username' => 'icon_size_tamper']));
        $this->post('/settings/appearance', self::APPEARANCE + ['icon_size' => 'enormous']);

        self::assertStringContainsString('data-icon-size="standard"', $this->get('/settings/appearance')->body());
    }

    public function test_export_and_reset_cover_the_icon_size(): void
    {
        $this->actingAs($this->makeUser(['username' => 'icon_size_export']));
        $this->post('/settings/appearance', self::APPEARANCE + ['icon_size' => 'large']);

        $export = json_decode($this->get('/settings/preferences/export')->body(), true);
        self::assertIsArray($export);
        self::assertSame('large', $export['preferences']['appearance']['icon_size']);

        $this->post('/settings/preferences/reset');
        self::assertStringContainsString('data-icon-size="standard"', $this->get('/settings/appearance')->body());
    }

    public function test_the_unset_star_is_star_regular_and_the_set_star_star_filled(): void
    {
        $member = $this->makeUser(['username' => 'icon_star_member']);
        $board = $this->makeBoard($this->makeCategory('Icon stars'), ['slug' => 'icon-stars', 'name' => 'Icon Stars']);
        $this->actingAs($member);

        $before = $this->get('/settings/boards')->body();
        self::assertStringContainsString('<svg class="icon icon-commend-star board-pref-star is-outline" viewBox="0 0 20 20" width="16" height="16" fill="currentColor" aria-hidden="true">'
            . self::paths(Icons::glyph('commend-star', 'standard', 'regular')), $before);

        $this->post('/settings/boards/toggle', ['board_id' => (int) $board['id'], 'pref' => 'favorite']);
        $after = $this->get('/settings/boards')->body();
        self::assertStringContainsString('<svg class="icon icon-commend-star board-pref-star" viewBox="0 0 20 20" width="16" height="16" fill="currentColor" aria-hidden="true">'
            . self::paths(Icons::glyph('commend-star')), $after);
    }

    public function test_forum_jobs_use_their_fluent_marks(): void
    {
        $member = $this->makeUser(['username' => 'icon_jobs_member']);
        $category = $this->makeCategory('Icon jobs');
        $empty = $this->makeBoard($category, ['slug' => 'icon-jobs-empty', 'name' => 'Icon Jobs Empty']);
        $busy = $this->makeBoard($category, ['slug' => 'icon-jobs-busy', 'name' => 'Icon Jobs Busy']);
        $thread = $this->makeThread($busy, $member, 'Fluent marks for forum jobs', 'Opening post.');
        $this->actingAs($member);

        $board = $this->get('/c/' . $empty['slug'])->body();
        // Starting a topic and an empty board use the Comment family.
        self::assertStringContainsString('icon icon-new-topic', $board);
        self::assertStringContainsString('<svg class="icon icon-board-empty board-empty-mark" viewBox="0 0 28 28" ', $board);
        self::assertStringNotContainsString('icon-message-square', $board);

        $topic = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug'])->body();
        self::assertStringContainsString('aria-label="Add a reaction"><svg class="icon icon-emoji-add"', $topic);
        self::assertStringContainsString('data-copy-post><svg class="icon icon-post-link"', $topic);
        self::assertMatchesRegularExpression('~<button class="danger" type="submit"><svg class="icon icon-delete"[^>]*>.*?</svg>\s*<span>Delete(?: topic)?</span>~s', $topic);

        $settings = $this->get('/settings/appearance')->body();
        self::assertStringContainsString('data-settings-key="boards" href="/settings/boards"><svg class="icon icon-boards"', $settings);
    }

    /** @param array{size: int, paths: list<array{d: string, rule?: string}>}|null $glyph */
    private static function paths(?array $glyph): string
    {
        self::assertNotNull($glyph);
        $out = '';
        foreach ($glyph['paths'] as $path) {
            $out .= '<path d="' . $path['d'] . '"' . (isset($path['rule']) ? ' fill-rule="' . $path['rule'] . '"' : '') . '/>';
        }
        return $out;
    }
}
