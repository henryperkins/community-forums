<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use App\Repository\ThreadUserRepository;
use App\Repository\UserBoardPrefRepository;
use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

final class AppMemberShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_shared_shell_puts_routes_in_the_topbar_and_only_boards_in_the_rail(): void
    {
        $category = $this->makeCategory('Council places');
        $board = $this->makeBoard($category, ['slug' => 'council-fire', 'name' => 'Council fire']);
        $user = $this->makeUser(['username' => 'shell_routes']);
        $this->actingAs($user);

        foreach (['/', '/inbox', '/search', '/c/' . $board['slug']] as $path) {
            $response = $this->get($path);
            $this->assertStatus(200, $response);
            $html = $response->body();
            self::assertStringContainsString('<nav class="topbar-primary" aria-label="Primary">', $html);
            self::assertStringContainsString('data-primary-route="boards"', $html);
            self::assertStringContainsString('data-primary-route="inbox"', $html);
            self::assertStringContainsString('data-primary-route="messages"', $html);

            $rail = $this->boardRail($html);
            self::assertStringContainsString('/c/council-fire', $rail);
            foreach (['/inbox', '/messages', '/feed', '/drafts', '/leaderboard', '/search'] as $route) {
                self::assertStringNotContainsString('href="' . $route . '"', $rail);
            }
        }

        $home = $this->get('/')->body();
        self::assertMatchesRegularExpression('/data-primary-route="boards"[^>]*aria-current="page"/', $home);
        self::assertMatchesRegularExpression('/data-primary-route="inbox"[^>]*aria-current="page"/', $this->get('/inbox')->body());
        self::assertMatchesRegularExpression('/data-primary-route="messages"[^>]*aria-current="page"/', $this->get('/messages')->body());
        self::assertStringContainsString('href="/search"', $home);
        self::assertStringNotContainsString('href="/search"', $this->topbar($this->get('/search')->body()));
    }

    public function test_identity_menu_owns_secondary_routes_and_topbar_exposes_new_topic(): void
    {
        $user = $this->makeUser(['username' => 'shell_identity']);
        $this->actingAs($user);

        $topbar = $this->topbar($this->get('/')->body());
        self::assertStringContainsString('<details class="identity-menu"', $topbar);
        foreach (['/u/shell_identity', '/notifications', '/drafts', '/feed', '/leaderboard', '/settings/account'] as $route) {
            self::assertStringContainsString('href="' . $route . '"', $topbar);
        }
        self::assertStringContainsString('href="/compose"', $topbar);
    }

    public function test_rail_unread_pills_sum_to_inbox_and_muted_places_remain_without_attention(): void
    {
        $category = $this->makeCategory('Attention places');
        $visible = $this->makeBoard($category, ['slug' => 'visible-attention', 'name' => 'Visible attention']);
        $muted = $this->makeBoard($category, ['slug' => 'muted-attention', 'name' => 'Muted attention']);
        $private = $this->makeBoard($category, ['slug' => 'private-attention', 'name' => 'Private attention', 'visibility' => 'private']);
        $author = $this->makeUser(['username' => 'attention_author']);
        $viewer = $this->makeUser(['username' => 'attention_viewer']);
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $author['id'], null);
        $this->makeThread($visible, $author, 'Visible unread');
        $this->makeThread($muted, $author, 'Muted unread');
        $this->makeThread($private, $author, 'Private unread');
        (new UserBoardPrefRepository($this->db))->setMuted((int) $viewer['id'], (int) $muted['id'], true);
        (new SettingRepository($this->db))->set('engagement_cutover_at', '2000-01-01 00:00:00');
        $this->actingAs($viewer);

        $html = $this->get('/')->body();
        $rail = $this->boardRail($html);
        self::assertStringContainsString('data-board-slug="visible-attention"', $rail);
        self::assertStringContainsString('data-board-unread-count="1"', $rail);
        self::assertStringContainsString('data-board-slug="muted-attention"', $rail);
        self::assertStringNotContainsString('private-attention', $rail);
        self::assertStringContainsString('data-inbox-unread-count="1"', $this->topbar($html));
    }

    public function test_contextual_surface_preference_persists_without_overwriting_siblings(): void
    {
        $user = $this->makeUser(['username' => 'surface_reader']);
        $repo = new UserPreferenceRepository($this->db);
        $repo->merge((int) $user['id'], [
            'directory_sort' => 'top',
            'directory_peek' => 5,
            'inbox_reading_open' => true,
        ]);
        $this->actingAs($user);

        $response = $this->post('/settings/member-surfaces', [
            'rail_open' => '0',
            'return' => '/inbox?scope=unread&order=newest',
        ]);

        $this->assertRedirect($response, '/inbox?scope=unread&order=newest');
        $stored = $repo->get((int) $user['id']);
        self::assertFalse($stored['rail_open']);
        self::assertTrue($stored['inbox_reading_open']);
        self::assertSame('top', $stored['directory_sort']);
        self::assertSame(5, $stored['directory_peek']);

        $home = $this->get('/')->body();
        self::assertStringContainsString('data-rail-open="0"', $home);
        self::assertStringContainsString('data-inbox-reading-open="1"', $home);
        self::assertStringContainsString('class="variant-app is-rail-closed is-reading-open"', $home);
    }

    public function test_surface_preference_rejects_unknown_values_and_external_returns(): void
    {
        $user = $this->makeUser(['username' => 'surface_guard']);
        $this->actingAs($user);

        $response = $this->post('/settings/member-surfaces', [
            'directory_sort' => 'DROP TABLE',
            'return' => '//outside.example/path',
        ]);

        $this->assertRedirect($response, '/');
        $stored = (new UserPreferenceRepository($this->db))->get((int) $user['id']);
        self::assertArrayNotHasKey('directory_sort', $stored);
    }

    public function test_reading_settings_exposes_server_owned_member_surface_controls(): void
    {
        $user = $this->makeUser(['username' => 'surface_settings']);
        (new UserPreferenceRepository($this->db))->merge((int) $user['id'], [
            'rail_open' => false,
            'inbox_reading_open' => true,
        ]);
        $this->actingAs($user);

        $html = $this->get('/settings/preferences')->body();
        self::assertStringContainsString('action="/settings/member-surfaces"', $html);
        self::assertStringContainsString('name="rail_open" value="1"', $html);
        self::assertStringContainsString('name="inbox_reading_open" value="1" checked', $html);
        self::assertStringContainsString('name="return" value="/settings/preferences"', $html);
    }

    public function test_guest_cannot_write_member_surface_preferences(): void
    {
        $this->get('/'); // establish the guest session/CSRF secret
        $this->assertRedirectContains(
            $this->post('/settings/member-surfaces', ['rail_open' => '0', 'return' => '/']),
            '/login',
        );
    }

    public function test_board_unread_aggregate_is_read_gated_and_treats_mute_as_attention_only(): void
    {
        $category = $this->makeCategory('Places');
        $visible = $this->makeBoard($category, ['slug' => 'visible-place']);
        $muted = $this->makeBoard($category, ['slug' => 'muted-place']);
        $private = $this->makeBoard($category, ['slug' => 'private-place', 'visibility' => 'private']);
        $author = $this->makeUser(['username' => 'shell_author']);
        $viewer = $this->makeUser(['username' => 'shell_viewer']);
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $author['id'], null);

        $this->makeThread($visible, $author, 'Visible attention');
        $this->makeThread($muted, $author, 'Muted attention');
        $this->makeThread($private, $author, 'Private attention');
        (new UserBoardPrefRepository($this->db))->setMuted((int) $viewer['id'], (int) $muted['id'], true);
        $cutover = '2000-01-01 00:00:00';
        (new SettingRepository($this->db))->set('engagement_cutover_at', $cutover);

        $repo = new ThreadUserRepository($this->db);
        $counts = $repo->unreadCountsByBoard((int) $viewer['id'], false, $cutover);

        self::assertSame([(int) $visible['id'] => 1], $counts);
        self::assertSame(1, $repo->unreadCount((int) $viewer['id'], false, $cutover));
    }

    private function topbar(string $html): string
    {
        self::assertSame(1, preg_match('#<header class="topbar">.*?</header>#s', $html, $match));
        return $match[0];
    }

    private function boardRail(string $html): string
    {
        self::assertSame(1, preg_match('#<aside class="sidebar".*?</aside>#s', $html, $match));
        return $match[0];
    }
}
