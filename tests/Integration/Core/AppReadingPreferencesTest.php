<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * P3-01: the reading-display preferences are server-enforced, not write-only.
 * `thread_sort` reorders the board listing, and show_avatars / show_signatures /
 * show_reactions actually hide their elements in the rendered thread + listing.
 * Closes the Gate A "reading toggles do nothing" finding (PHASE_3_STATUS §11).
 *
 * The signed-in topbar always renders exactly one monogram, so avatar assertions
 * compare monogram COUNTS rather than mere presence.
 */
final class AppReadingPreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Mark the site initialized so requests aren't redirected to /setup.
        $this->makeAdmin();
    }

    /**
     * Post the reading form. Unlisted checkboxes persist as `false`, so callers
     * pass the toggles they want ON as '1' to isolate the variable under test.
     *
     * @param array<string,mixed> $fields
     */
    private function setReading(array $fields): void
    {
        $this->post('/settings/preferences', $fields);
    }

    public function test_show_avatars_off_hides_post_and_list_avatars(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'av-board']);
        $user = $this->makeUser(['username' => 'avatarist']);
        $t = $this->makeThread($board, $user, 'Avatar thread');
        $url = '/t/' . $t['thread_id'] . '-' . $t['slug'];
        $this->actingAs($user);

        // Default: topbar monogram + the OP post monogram = at least two.
        $on = $this->get($url)->body();
        self::assertGreaterThanOrEqual(2, substr_count($on, 'class="monogram'));

        // Avatars off (other toggles on): only the topbar monogram survives.
        $this->setReading(['show_signatures' => '1', 'show_reactions' => '1', 'thread_sort' => 'last_post']);
        $off = $this->get($url)->body();
        self::assertSame(1, substr_count($off, 'class="monogram'), 'Only the topbar monogram should remain on the thread.');

        // The board listing avatar is hidden too (topbar monogram only).
        $list = $this->get('/c/' . $board['slug'])->body();
        self::assertSame(1, substr_count($list, 'class="monogram'), 'Board list avatar should be hidden.');
    }

    public function test_show_reactions_off_hides_the_reaction_bar(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'rx-board']);
        $user = $this->makeUser(['username' => 'reactor']);
        $t = $this->makeThread($board, $user, 'Reaction thread');
        $url = '/t/' . $t['thread_id'] . '-' . $t['slug'];
        $this->actingAs($user);

        $this->assertSeeText($this->get($url), 'class="reactions"');

        $this->setReading(['show_signatures' => '1', 'show_avatars' => '1', 'thread_sort' => 'last_post']);
        $this->assertDontSeeText($this->get($url), 'class="reactions"');
    }

    public function test_show_signatures_off_hides_the_author_signature(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'sig-board']);
        $user = $this->makeUser(['username' => 'signer']);
        $this->db->run('UPDATE users SET signature = ? WHERE id = ?', ['SIGMARKER_XYZ', (int) $user['id']]);
        $t = $this->makeThread($board, $user, 'Signature thread');
        $url = '/t/' . $t['thread_id'] . '-' . $t['slug'];
        $this->actingAs($user);

        $this->assertSeeText($this->get($url), 'SIGMARKER_XYZ');

        $this->setReading(['show_avatars' => '1', 'show_reactions' => '1', 'thread_sort' => 'last_post']);
        $this->assertDontSeeText($this->get($url), 'SIGMARKER_XYZ');
    }

    public function test_thread_sort_preference_orders_the_board_list(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'sort-board']);
        $user = $this->makeUser(['username' => 'sorter']);

        $a = $this->makeThread($board, $user, 'ZZALPHA');
        $b = $this->makeThread($board, $user, 'ZZBRAVO');
        $c = $this->makeThread($board, $user, 'ZZCHARLIE');
        // Distinct created_at / last_post_at / reply_count so each sort yields a
        // different leader (none pinned, so is_pinned DESC is neutral).
        $this->db->run('UPDATE threads SET created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-01-01 00:00:00', '2024-03-01 00:00:00', 1, $a['thread_id']]);
        $this->db->run('UPDATE threads SET created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-02-01 00:00:00', '2024-01-15 00:00:00', 9, $b['thread_id']]);
        $this->db->run('UPDATE threads SET created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-03-01 00:00:00', '2024-02-01 00:00:00', 3, $c['thread_id']]);

        $this->actingAs($user);
        $boardUrl = '/c/' . $board['slug'];
        $keep = ['show_avatars' => '1', 'show_signatures' => '1', 'show_reactions' => '1'];

        // last_post (default): A (Mar) > C (Feb) > B (Jan).
        $this->setReading(['thread_sort' => 'last_post'] + $keep);
        $this->assertOrder($this->get($boardUrl)->body(), ['ZZALPHA', 'ZZCHARLIE', 'ZZBRAVO']);

        // newest (created_at desc): C (Mar) > B (Feb) > A (Jan).
        $this->setReading(['thread_sort' => 'newest'] + $keep);
        $this->assertOrder($this->get($boardUrl)->body(), ['ZZCHARLIE', 'ZZBRAVO', 'ZZALPHA']);

        // replies (reply_count desc): B (9) > C (3) > A (1).
        $this->setReading(['thread_sort' => 'replies'] + $keep);
        $this->assertOrder($this->get($boardUrl)->body(), ['ZZBRAVO', 'ZZCHARLIE', 'ZZALPHA']);
    }

    public function test_guest_sees_default_reading_surface(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'guest-board']);
        $user = $this->makeUser(['username' => 'host']);
        $this->db->run('UPDATE users SET signature = ? WHERE id = ?', ['GUEST_SIG_MARK', (int) $user['id']]);
        $t = $this->makeThread($board, $user, 'Guest thread');

        // Guests have no stored prefs → readingDefaults(): everything shown, and
        // no topbar monogram (not signed in), so the only monogram is the post's.
        $body = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug'])->body();
        self::assertSame(1, substr_count($body, 'class="monogram'));
        $this->assertSeeText($this->get('/t/' . $t['thread_id'] . '-' . $t['slug']), 'GUEST_SIG_MARK');
    }

    /**
     * Assert the needles appear in this left-to-right order in the body.
     *
     * @param list<string> $needles
     */
    private function assertOrder(string $body, array $needles): void
    {
        $prev = -1;
        foreach ($needles as $needle) {
            $pos = strpos($body, $needle);
            self::assertNotFalse($pos, "Missing from listing: $needle");
            self::assertGreaterThan($prev, $pos, "Out of expected sort order: $needle");
            $prev = $pos;
        }
    }
}
