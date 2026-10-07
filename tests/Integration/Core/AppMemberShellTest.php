<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\BoardFolderRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
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
            self::assertStringContainsString('<nav class="forum-bar-surfaces" aria-label="Primary">', $html);
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

    /**
     * The header's New topic opens the composer on the board being read, not on
     * the first board listed: a member who meant to post in Harbour watch was
     * handed whichever board sorted first. Plain error pages carry no board.
     */
    public function test_new_topic_opens_the_composer_on_the_board_being_read(): void
    {
        $category = $this->makeCategory('Compose places');
        $this->makeBoard($category, ['slug' => 'first-listed', 'name' => 'First listed']);
        $board = $this->makeBoard($category, ['slug' => 'harbour-watch', 'name' => 'Harbour watch']);
        $private = $this->makeBoard($category, ['slug' => 'sealed-room', 'visibility' => 'private']);
        $user = $this->makeUser(['username' => 'compose_context']);
        $thread = $this->makeThread($board, $user, 'A topic on the harbour');
        $this->actingAs($user);

        self::assertStringContainsString('href="/compose"', $this->topbar($this->get('/')->body()));
        foreach (['/c/harbour-watch', '/t/' . $thread['thread_id'] . '-' . $thread['slug']] as $path) {
            $response = $this->get($path);
            $this->assertStatus(200, $response);
            self::assertStringContainsString('href="/compose?board=harbour-watch"', $this->topbar($response->body()), $path);
        }

        $denied = $this->get('/c/' . $private['slug']);
        $this->assertStatus(404, $denied);
        self::assertStringContainsString('href="/compose"', $this->topbar($denied->body()));
        self::assertStringNotContainsString('sealed-room', $this->topbar($denied->body()));

        $compose = $this->get('/compose', ['board' => 'harbour-watch']);
        $this->assertStatus(200, $compose);
        self::assertMatchesRegularExpression(
            '~<option value="' . (int) $board['id'] . '"\s+data-board-slug="harbour-watch"[^>]*\bselected\b~',
            $compose->body(),
        );
    }

    /**
     * The pane toggles are toggle buttons: a fixed name, with aria-pressed alone
     * carrying the state. They had announced it three times over (a Hide/Show
     * name, aria-pressed and aria-expanded).
     */
    public function test_pane_toggles_carry_a_fixed_name_and_announce_their_state_once(): void
    {
        $user = $this->makeUser(['username' => 'pane_names']);
        (new UserPreferenceRepository($this->db))->merge((int) $user['id'], ['rail_open' => true, 'inbox_reading_open' => false]);
        $this->actingAs($user);

        $topbar = $this->topbar($this->get('/inbox')->body());
        self::assertStringContainsString(
            '<button class="forum-bar-railtoggle is-on" type="submit" aria-controls="sidebar-nav" aria-pressed="true" aria-label="Board rail" title="Board rail" data-shortcut="b">',
            $topbar,
        );
        self::assertStringContainsString(
            '<button class="forum-bar-railtoggle" type="submit" aria-controls="inbox-reading-pane" aria-pressed="false" aria-label="Reading pane" title="Reading pane" data-shortcut="j">',
            $topbar,
        );
        self::assertSame(2, preg_match_all('~<button class="forum-bar-railtoggle[^>]*>~', $topbar, $toggles));
        foreach ($toggles[0] as $toggle) {
            self::assertStringNotContainsString('aria-expanded', $toggle);
            self::assertDoesNotMatchRegularExpression('~Hide|Show~', $toggle);
        }
    }

    public function test_thread_rail_marks_its_authorized_parent_board(): void
    {
        $category = $this->makeCategory('Thread places');
        $board = $this->makeBoard($category, ['slug' => 'thread-home', 'name' => 'Thread home']);
        $this->makeBoard($category, ['slug' => 'other-place']);
        $author = $this->makeUser(['username' => 'rail_author']);
        $thread = $this->makeThread($board, $author, 'A place to return to');

        $response = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $response);
        $rail = $this->boardRail($response->body());
        self::assertMatchesRegularExpression(
            '/class="board-rail-item is-active"[^>]*href="\/c\/thread-home"[^>]*aria-current="page"/',
            $rail,
        );
        self::assertSame(1, substr_count($rail, 'aria-current="page"'));
        self::assertStringNotContainsString('aria-current="page"', $this->boardRail($this->get('/')->body()));
    }

    public function test_boards_primary_route_covers_tag_pages_and_authorized_topics(): void
    {
        $user = $this->makeUser(['username' => 'primary_families']);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $user);
        (new TagRepository($this->db))->create('shell-tag', 'Shell tag', null, (int) $user['id']);
        $this->actingAs($user);

        foreach (['/tags', '/tags/shell-tag', '/t/' . $thread['thread_id'] . '-' . $thread['slug']] as $path) {
            $response = $this->get($path);
            $this->assertStatus(200, $response);
            self::assertMatchesRegularExpression(
                '/data-primary-route="boards"[^>]*href="\/"[^>]*aria-current="page"/',
                $this->topbar($response->body()),
                $path,
            );
        }
    }

    public function test_plain_error_header_omits_controls_for_panes_that_do_not_exist(): void
    {
        $user = $this->makeUser(['username' => 'error_shell']);
        $private = $this->makeBoard($this->makeCategory(), ['slug' => 'private-error-shell', 'visibility' => 'private']);
        $this->actingAs($user);
        (new UserPreferenceRepository($this->db))->merge((int) $user['id'], ['rail_open' => true]);

        foreach (['/missing-shell' => 404, '/admin/settings' => 403, '/c/' . $private['slug'] => 404] as $path => $status) {
            $response = $this->get($path);
            $this->assertStatus($status, $response);
            $topbar = $this->topbar($response->body());
            self::assertStringContainsString('data-primary-route="boards"', $topbar);
            self::assertStringNotContainsString('data-panel-form=', $topbar);
            self::assertStringNotContainsString('data-nav-toggle', $topbar);
            self::assertStringNotContainsString('data-nav-fallback', $topbar);
            self::assertStringNotContainsString('aria-current="page"', $topbar);
            // The divider sets the pane toggles apart, so it goes with them.
            self::assertStringNotContainsString('forum-bar-divider', $topbar);
        }

        self::assertTrue((new UserPreferenceRepository($this->db))->get((int) $user['id'])['rail_open']);
        $home = $this->topbar($this->get('/')->body());
        self::assertStringContainsString('data-panel-form="rail"', $home);
        self::assertMatchesRegularExpression('~data-panel-form="rail".*?</form>\s*<span class="forum-bar-divider" aria-hidden="true"></span>~s', $home);
    }

    public function test_drawer_close_control_is_a_named_cross_at_the_top_of_the_rail(): void
    {
        $this->actingAs($this->makeUser(['username' => 'drawer_close']));

        $rail = $this->boardRail($this->get('/inbox')->body());
        // The first stop in the drawer, before and after the enhancement: a fragment
        // link back to the content, named for what it does and drawn as the icon set's cross.
        self::assertMatchesRegularExpression(
            '~^<nav class="board-rail"[^>]*>\s*<a class="nav-close" data-nav-close href="#main" aria-label="Close board rail"><svg class="icon icon-x"[^>]*aria-hidden="true">~',
            $rail,
        );
        self::assertSame(1, substr_count($rail, 'data-nav-close'));
    }

    public function test_folder_shortcuts_share_board_state_without_bypassing_read_gates(): void
    {
        $category = $this->makeCategory('Folder places');
        $user = $this->makeUser(['username' => 'folder_reader']);
        $author = $this->makeUser(['username' => 'folder_author']);
        $public = $this->makeBoard($category, ['slug' => 'folder-public']);
        $private = $this->makeBoard($category, ['slug' => 'folder-private', 'visibility' => 'private']);
        $hidden = $this->makeBoard($category, ['slug' => 'folder-hidden', 'visibility' => 'hidden']);
        $muted = $this->makeBoard($category, ['slug' => 'folder-muted']);
        $denied = $this->makeBoard($category, ['slug' => 'folder-denied', 'visibility' => 'private']);
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $user['id'], null);
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $author['id'], null);
        $folders = new BoardFolderRepository($this->db);
        $folder = $folders->create((int) $user['id'], 'My places');
        foreach ([$public, $private, $hidden, $muted, $denied] as $board) {
            $folders->addBoard($folder, (int) $board['id']);
        }
        $thread = $this->makeThread($public, $author, 'Folder unread');
        $this->makeThread($private, $author, 'Private unread');
        $this->makeThread($hidden, $author, 'Hidden unread');
        $this->makeThread($muted, $author, 'Muted unread');
        (new UserBoardPrefRepository($this->db))->setMuted((int) $user['id'], (int) $muted['id'], true);
        (new SettingRepository($this->db))->set('engagement_cutover_at', '2000-01-01 00:00:00');
        $this->actingAs($user);

        $html = $this->get('/')->body();
        $home = $this->boardRail($html);
        self::assertStringContainsString('data-inbox-unread-count="2"', $this->topbar($html));
        self::assertStringNotContainsString('folder-denied', $home);
        foreach (['folder-public', 'folder-private'] as $slug) {
            self::assertSame(2, preg_match_all('#<a class="board-rail-item[^>]*href="/c/' . $slug . '"[^>]*>.*?</a>#s', $home, $rows));
            self::assertSame($rows[0][0], $rows[0][1], 'Both copies must carry the same board state.');
            self::assertStringContainsString('data-board-unread-count="1"', $rows[0][0]);
        }
        self::assertSame(1, preg_match('#<a class="board-rail-item[^>]*href="/c/folder-hidden"[^>]*>.*?</a>#s', $home, $hiddenRow));
        self::assertStringContainsString('board-rail-tag">hidden</span>', $hiddenRow[0]);
        self::assertStringNotContainsString('data-board-unread-count', $hiddenRow[0]);
        self::assertSame(2, preg_match_all('#<a class="board-rail-item[^>]*href="/c/folder-muted"[^>]*>.*?</a>#s', $home, $mutedRows));
        foreach ($mutedRows[0] as $row) {
            self::assertStringNotContainsString('data-board-unread-count', $row);
        }

        foreach (['/c/folder-public', '/t/' . $thread['thread_id'] . '-' . $thread['slug']] as $path) {
            $rail = $this->boardRail($this->get($path)->body());
            self::assertSame(2, preg_match_all('#<a class="board-rail-item is-active"[^>]*href="/c/folder-public"[^>]*aria-current="page"[^>]*>.*?</a>#s', $rail, $rows));
            self::assertSame($rows[0][0], $rows[0][1]);
        }
        // Losing access must remove a stored private shortcut as well as its category row.
        (new BoardMemberRepository($this->db))->remove((int) $private['id'], (int) $user['id']);
        self::assertStringNotContainsString('folder-private', $this->boardRail($this->get('/')->body()));
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
        // The words live on the Inbox link, singular for one; the digits are hidden.
        self::assertStringContainsString(
            'data-count-name="Inbox" aria-label="Inbox, 1 unread topic">Inbox<span class="forum-bar-count" data-inbox-unread-count="1" aria-hidden="true">1</span>',
            $this->topbar($html),
        );
    }

    public function test_bar_names_its_counts_on_their_links_claims_no_shortcut_and_marks_the_bell_current_on_its_page(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'counted-words']);
        $sender = $this->makeUser(['username' => 'count_sender']);
        $reader = $this->makeUser(['username' => 'count_reader', 'display_name' => 'Count Reader']);
        // The sender posts once, so the new-account throttle lets the letter through.
        $this->makeThread($board, $sender, 'Warm-up', 'hello');
        $this->actingAs($sender);
        $this->post('/messages', ['to' => 'count_reader', 'body' => 'One letter.']);

        $this->actingAs($reader);
        $topbar = $this->topbar($this->get('/inbox')->body());
        // One unread conversation: the link reads it, in the singular; the digits are hidden.
        self::assertStringContainsString(
            'data-count-name="Messages" aria-label="Messages, 1 unread conversation">Messages<span class="forum-bar-count" data-dm-unread-count aria-hidden="true">1</span>',
            $topbar,
        );
        self::assertStringNotContainsString('unread conversations"', $topbar);
        // Without the script nothing answers ⌘K, so the server claims no shortcut.
        self::assertStringContainsString('<a class="forum-bar-search" href="/search" aria-label="Search the council" data-shortcut="k">', $topbar);
        self::assertStringContainsString('<span class="forum-bar-kbd" data-shortcut-hint aria-hidden="true" hidden></span>', $topbar);
        self::assertStringNotContainsString('⌘', $topbar);
        // The summary's expanded state says open or closed; its name carries no verb.
        self::assertStringContainsString('<summary class="forum-bar-user" aria-label="Account menu for Count Reader">', $topbar);
        // The menu's groups, and Settings no longer wearing Profile's person.
        self::assertMatchesRegularExpression('~<a class="identity-menu-break" href="/settings/account"><svg class="icon icon-settings"~', $topbar);
        self::assertStringContainsString('<form class="identity-menu-break" method="post" action="/logout">', $topbar);
        // The bell is current only on its own page.
        self::assertStringContainsString('<a class="forum-bar-bell bell" href="/notifications" data-bell data-notification-link aria-label=', $topbar);
        $notices = $this->topbar($this->get('/notifications')->body());
        self::assertStringContainsString('<a class="forum-bar-bell bell is-active" href="/notifications" data-bell data-notification-link aria-current="page" aria-label=', $notices);
        self::assertSame(0, substr_count($notices, 'forum-bar-surface is-active'));
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
        $this->get('/login'); // Establish the guest CSRF secret through a form.
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
        self::assertSame(1, preg_match('#<header class="forum-bar">.*?</header>#s', $html, $match));
        return $match[0];
    }

    private function boardRail(string $html): string
    {
        self::assertSame(1, preg_match('#<nav class="board-rail".*?</nav>#s', $html, $match));
        return $match[0];
    }
}
