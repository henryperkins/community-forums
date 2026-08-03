<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * P3-01: the reading-display preferences are server-enforced, not write-only.
 * Board order is fixed to pinned then last activity; show_avatars /
 * show_signatures / show_reactions hide their elements in the rendered thread +
 * listing.
 * Closes the Gate A "reading toggles do nothing" finding (docs/history/PHASE_1-4_HISTORY.md#phase-3-status §11).
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
        $this->setReading(['show_signatures' => '1', 'show_reactions' => '1']);
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

        $this->setReading(['show_signatures' => '1', 'show_avatars' => '1']);
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

        $this->setReading(['show_avatars' => '1', 'show_reactions' => '1']);
        $this->assertDontSeeText($this->get($url), 'SIGMARKER_XYZ');
    }

    public function test_board_order_is_pinned_then_last_post_for_every_legacy_sort_value(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'fixed-order-board']);
        $user = $this->makeUser(['username' => 'fixed_order_reader']);
        $pinned = $this->makeThread($board, $user, 'ZZPINNED');
        $active = $this->makeThread($board, $user, 'ZZACTIVE');
        $tieHigh = $this->makeThread($board, $user, 'ZZTIEHIGH');
        $tieLow = $this->makeThread($board, $user, 'ZZTIELOW');

        $this->db->run('UPDATE threads SET is_pinned = 1, created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-01-01 00:00:00', '2024-01-01 00:00:00', 0, $pinned['thread_id']]);
        $this->db->run('UPDATE threads SET created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-01-01 00:00:00', '2024-04-01 00:00:00', 1, $active['thread_id']]);
        $this->db->run('UPDATE threads SET created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-04-01 00:00:00', '2024-03-01 00:00:00', 2, $tieHigh['thread_id']]);
        $this->db->run('UPDATE threads SET created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-03-01 00:00:00', '2024-03-01 00:00:00', 99, $tieLow['thread_id']]);
        $this->actingAs($user);

        foreach (['last_post', 'newest', 'replies'] as $legacySort) {
            $this->db->run(
                'INSERT INTO user_preferences (user_id, prefs, updated_at) VALUES (?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE prefs = VALUES(prefs), updated_at = UTC_TIMESTAMP()',
                [$user['id'], json_encode(['__v' => 2, 'thread_sort' => $legacySort], JSON_THROW_ON_ERROR)],
            );
            $this->assertOrder($this->get('/c/fixed-order-board')->body(), ['ZZPINNED', 'ZZACTIVE', 'ZZTIELOW', 'ZZTIEHIGH']);
        }
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
