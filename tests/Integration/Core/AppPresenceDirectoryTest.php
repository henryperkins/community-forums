<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Config;
use App\Core\Response;
use App\Repository\BlockRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * The /users-online directory and the presence rules the three public surfaces
 * must agree on (ADR 0031).
 *
 * Every test here exists because a surface once disagreed with another one: the
 * profile dot ignored the feature flag, blocks and banned status that the roster
 * enforced; the roster published members whose profile is members-only to signed
 * -out readers; and the rail's count meant something different from the badge
 * beside it. Assertions are on observable HTTP behaviour, never on rows — per
 * -test isolation is one transaction rolled back, with no savepoints.
 */
final class AppPresenceDirectoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    /**
     * The rail renders on /users-online too, and the page echoes the search term
     * back into the input, the filter links and the empty-state copy. A bare
     * assertion on the whole body therefore proves nothing about either surface:
     * scope to the directory, and assert on the ROW marker, not the handle.
     */
    private function directory(Response $response): string
    {
        return $this->region($response->body(), 'data-users-online', 'presence-guidance');
    }

    /** The profile's own avatar cell — not the topbar's dot, nor the rail's. */
    private function profileAvatar(Response $response): string
    {
        return $this->region($response->body(), 'profile-avatar', 'profile-id');
    }

    private function region(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        self::assertNotFalse($start, 'Region start not found: ' . $from);
        $end = strpos($html, $to, $start);
        self::assertNotFalse($end, 'Region end not found: ' . $to);
        return substr($html, $start, $end - $start);
    }

    private function row(string $username): string
    {
        return 'data-presence-row="' . $username . '"';
    }

    private function seen(array $user, int $secondsAgo, int $show = 1): void
    {
        $this->db->run(
            'UPDATE users SET last_seen_at = ?, show_presence = ? WHERE id = ?',
            [gmdate('Y-m-d H:i:s', time() - $secondsAgo), $show, (int) $user['id']],
        );
    }

    // ── The profile dot now obeys the same ladder as the roster ──────────────

    public function test_profile_dot_disappears_when_the_presence_feature_is_off(): void
    {
        $member = $this->makeUser(['username' => 'flaggedoff']);
        $this->seen($member, 10);

        $on = $this->get('/u/flaggedoff');
        $this->assertStatus(200, $on);
        self::assertStringContainsString('presence-dot', $this->profileAvatar($on));

        (new SettingRepository($this->db))->set('features', ['presence' => false]);

        $off = $this->get('/u/flaggedoff');
        $this->assertStatus(200, $off);
        // The whole subsystem is rolled back: /presence 404s and the rail is
        // gone, so a green leaf on the profile would be the only thing left
        // claiming the feature still works.
        self::assertStringNotContainsString('presence-dot', $this->profileAvatar($off));
    }

    public function test_profile_dot_respects_a_block_in_either_direction(): void
    {
        $viewer = $this->makeUser(['username' => 'dotblocker']);
        $foe = $this->makeUser(['username' => 'dotfoe']);
        $this->seen($foe, 10);

        $this->actingAs($viewer);
        self::assertStringContainsString('presence-dot', $this->profileAvatar($this->get('/u/dotfoe')));

        (new BlockRepository($this->db))->block((int) $viewer['id'], (int) $foe['id']);

        // The roster has always hidden a blocked member; the profile used not to,
        // so the same person was invisible in the rail and lit up one click away.
        self::assertStringNotContainsString('presence-dot', $this->profileAvatar($this->get('/u/dotfoe')));
    }

    public function test_profile_dot_is_dark_for_a_banned_member(): void
    {
        $banned = $this->makeUser(['username' => 'dotbanned']);
        $this->seen($banned, 10);
        $this->db->run("UPDATE users SET status = 'banned' WHERE id = ?", [(int) $banned['id']]);

        self::assertStringNotContainsString('presence-dot', $this->profileAvatar($this->get('/u/dotbanned')));
    }

    public function test_a_member_who_turned_presence_off_is_not_shown_online_even_to_themselves(): void
    {
        $quiet = $this->makeUser(['username' => 'quietself']);
        $this->seen($quiet, 10, 0);

        $this->actingAs($quiet);
        // "Show when I'm online / A leaf marks your presence beside your name" —
        // painting the leaf anyway makes the control lie to the person who set it.
        self::assertStringNotContainsString('presence-dot', $this->profileAvatar($this->get('/u/quietself')));
    }

    public function test_the_topbar_leaf_follows_the_viewers_own_toggle_and_the_feature_flag(): void
    {
        $member = $this->makeUser(['username' => 'topbarleaf']);
        $this->actingAs($member);

        // The heartbeat has fired by now, so the viewer is here by definition —
        // and their own leaf is resolved from their own row, not from the capped
        // roster slice they might rank below on a busy forum.
        self::assertStringContainsString('presence-dot', $this->topbar($this->get('/')));

        $this->db->run('UPDATE users SET show_presence = 0 WHERE id = ?', [(int) $member['id']]);
        // This leaf was unconditional: it stayed lit for a member who had switched
        // presence off, contradicting the control that set it.
        self::assertStringNotContainsString('presence-dot', $this->topbar($this->get('/')));

        $this->db->run('UPDATE users SET show_presence = 1 WHERE id = ?', [(int) $member['id']]);
        (new SettingRepository($this->db))->set('features', ['presence' => false]);
        self::assertStringNotContainsString('presence-dot', $this->topbar($this->get('/')));
    }

    /** The account-menu avatar cell only — not the rail, not the roll. */
    private function topbar(Response $response): string
    {
        return $this->region($response->body(), 'forum-bar-user', 'forum-bar-username');
    }

    // ── The members-only leak (the defect the review itself missed) ──────────

    public function test_members_only_profiles_are_not_published_to_guests_by_presence(): void
    {
        $restricted = $this->makeUser(['username' => 'onlymembers']);
        $this->seen($restricted, 10);
        $this->db->run("UPDATE users SET profile_visibility = 'members' WHERE id = ?", [(int) $restricted['id']]);

        // A guest cannot open this profile, so presence must not hand them the
        // name, the handle, or a link to it — in the rail, the JSON, or search.
        $this->assertDontSeeText($this->get('/'), $this->row('onlymembers'));
        $this->assertDontSeeText($this->get('/presence'), '"username":"onlymembers"');
        self::assertStringNotContainsString($this->row('onlymembers'), $this->directory($this->get('/users-online')));
        // Searching the exact handle is the sharpest probe there is. The term is
        // echoed back into the input and the empty-state copy, but no ROW may
        // come with it.
        self::assertStringNotContainsString(
            $this->row('onlymembers'),
            $this->directory($this->get('/users-online', ['q' => 'onlymembers'])),
        );

        // A signed-in member may see them: the restriction is about guests.
        $this->actingAs($this->makeUser(['username' => 'signedinreader']));
        $this->assertSeeText($this->get('/presence'), '"username":"onlymembers"');
    }

    // ── Here now vs stepped away ─────────────────────────────────────────────

    public function test_the_roster_splits_the_window_into_here_now_and_away(): void
    {
        $here = $this->makeUser(['username' => 'herenow']);
        $away = $this->makeUser(['username' => 'steppedaway']);
        $gone = $this->makeUser(['username' => 'longgone']);
        $this->seen($here, 30);     // inside the 300s online window
        $this->seen($away, 600);    // outside 300s, inside the 900s away window
        $this->seen($gone, 5000);   // outside both

        $json = $this->get('/presence');
        $this->assertStatus(200, $json);
        $this->assertSeeText($json, '"username":"herenow"');
        $this->assertSeeText($json, '"username":"steppedaway"');
        $this->assertDontSeeText($json, 'longgone');
        $this->assertSeeText($json, '"state":"away"');

        $page = $this->get('/users-online');
        self::assertStringContainsString('data-presence-state="away"', $page->body());
        $this->assertSeeText($page, 'Away');
    }

    public function test_the_count_is_here_now_while_total_carries_the_away_members(): void
    {
        $here = $this->makeUser(['username' => 'countshere']);
        $away = $this->makeUser(['username' => 'countsaway']);
        $this->seen($here, 30);
        $this->seen($away, 600);

        $body = $this->get('/presence')->body();
        $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        // The badge would lie the moment away members entered the roster if
        // `count` meant "everyone listed".
        self::assertSame(1, $payload['here']);
        self::assertSame(1, $payload['away']);
        self::assertSame(2, $payload['total']);
        self::assertSame($payload['here'], $payload['count']);
    }

    public function test_the_json_payload_no_longer_publishes_activity_timestamps(): void
    {
        $member = $this->makeUser(['username' => 'notimestamp']);
        $this->seen($member, 30);

        // Second-precision last_seen_at for every opted-in member, to anonymous
        // clients, read by nothing. "Show when I'm online" is not "publish my
        // activity timeline".
        $this->assertDontSeeText($this->get('/presence'), 'last_seen_at');
    }

    // ── Filters, search and paging ───────────────────────────────────────────

    public function test_filters_narrow_the_roll_without_losing_the_counts(): void
    {
        $here = $this->makeUser(['username' => 'filterhere']);
        $away = $this->makeUser(['username' => 'filteraway']);
        $this->seen($here, 30);
        $this->seen($away, 600);

        $onlyHere = $this->get('/users-online', ['filter' => 'online']);
        $this->assertStatus(200, $onlyHere);
        // Scoped: the rail beside the page is deliberately unfiltered, so an
        // unscoped assertion would pass for the wrong reason and keep passing if
        // the filter stopped working entirely.
        $here = $this->directory($onlyHere);
        self::assertStringContainsString($this->row('filterhere'), $here);
        self::assertStringNotContainsString($this->row('filteraway'), $here);

        $away = $this->directory($this->get('/users-online', ['filter' => 'away']));
        self::assertStringContainsString($this->row('filteraway'), $away);
        self::assertStringNotContainsString($this->row('filterhere'), $away);
    }

    public function test_search_cannot_reach_a_member_who_turned_presence_off(): void
    {
        $hidden = $this->makeUser(['username' => 'searchhidden']);
        $this->seen($hidden, 30, 0);

        // Searching by exact handle is the sharpest possible probe: if the name
        // predicate were applied anywhere but beside the privacy predicate, this
        // is where it would show.
        self::assertStringNotContainsString(
            $this->row('searchhidden'),
            $this->directory($this->get('/users-online', ['q' => 'searchhidden'])),
        );
    }

    public function test_search_treats_wildcards_as_literal_text(): void
    {
        $member = $this->makeUser(['username' => 'wildcardish']);
        $this->seen($member, 30);

        // An unescaped % would match every member on the roll.
        self::assertStringNotContainsString(
            $this->row('wildcardish'),
            $this->directory($this->get('/users-online', ['q' => '%'])),
        );
        self::assertStringContainsString(
            $this->row('wildcardish'),
            $this->directory($this->get('/users-online', ['q' => 'wildcard'])),
        );
    }

    public function test_pagination_clamps_junk_and_out_of_range_pages(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->seen($this->makeUser(['username' => 'pager' . $i]), 30);
        }

        $first = $this->get('/users-online');
        $this->assertStatus(200, $first);
        $this->assertSeeText($first, 'Page 1 of');

        // Beyond the last page, and a non-numeric page, both land somewhere real
        // rather than on an empty roll or a 500.
        $this->assertStatus(200, $this->get('/users-online', ['page' => '9999']));
        $this->assertStatus(200, $this->get('/users-online', ['page' => 'banana']));
        $this->assertStatus(200, $this->get('/users-online', ['filter' => 'nonsense']));
    }

    public function test_the_rail_caps_its_rows_and_says_how_many_it_left_out(): void
    {
        for ($i = 0; $i < 9; $i++) {
            $this->seen($this->makeUser(['username' => 'railer' . $i]), 30);
        }

        $shell = $this->get('/');
        $this->assertStatus(200, $shell);
        // Server and poller share one cap, published on the element so they
        // cannot drift: the rail used to render 6 and then reflow to 20.
        self::assertStringContainsString('data-presence-limit="5"', $shell->body());
        self::assertSame(5, substr_count($shell->body(), 'data-presence-row='));
        // With JavaScript off the badge used to read "9" over six names with no
        // affordance to see the rest. Assert the RENDERED TEXT and the arithmetic:
        // `assertStringContainsString('more', ...)` would be satisfied by the
        // `presence-more` class name the template emits unconditionally, which is
        // a tautology dressed up as a regression guard.
        self::assertMatchesRegularExpression(
            '#<p class="presence-more" data-presence-more>\+(\d+) more</p>#',
            $shell->body(),
        );
        self::assertSame(1, preg_match('#data-presence-more>\+(\d+) more#', $shell->body(), $more));
        // 9 seeded above, minus the 5 the rail shows. The admin from setUp() is
        // not on the roll: this viewer is a guest, so no heartbeat ever wrote a
        // last_seen_at for that account.
        self::assertSame(4, (int) $more[1]);

        // And it is genuinely absent, not merely empty, when nothing is left out.
        $this->db->run("UPDATE users SET last_seen_at = NULL WHERE username LIKE 'railer%'");
        self::assertStringContainsString('data-presence-more hidden', $this->get('/')->body());
    }

    // ── Transport posture ────────────────────────────────────────────────────

    public function test_presence_responses_are_private_and_not_indexable(): void
    {
        $json = $this->get('/presence');
        // Response::header() normalises names to lower case.
        self::assertSame('private, no-store', $json->headers()['cache-control'] ?? null);
        self::assertSame('Cookie', $json->headers()['vary'] ?? null);

        // no-cache, not no-store, on the HTML: the body is viewer-specific so it
        // must revalidate, but no-store would also kill the bfcache and
        // roster -> profile -> Back is this page's primary journey.
        $page = $this->get('/users-online');
        self::assertSame('private, no-cache', $page->headers()['cache-control'] ?? null);

        $robots = $this->get('/robots.txt');
        $this->assertSeeText($robots, 'Disallow: /users-online');
        $this->assertSeeText($robots, 'Disallow: /presence');
    }

    /** Rebuild the kernel with an overridden rate-limit policy (TestCase config-rebuild pattern). */
    private function withRateLimit(string $policy, int $max, int $decay): void
    {
        $items = $this->config->all();
        $items['rate_limits'][$policy] = [$max, $decay];
        $this->app = new App(new Config($items), $this->db, $this->rateLimiter);
    }

    public function test_the_json_poll_is_throttled_and_answers_in_json_a_poller_can_obey(): void
    {
        $this->withRateLimit('presence', 1, 300);

        $this->assertStatus(200, $this->get('/presence'));

        $throttled = $this->get('/presence');
        $this->assertStatus(429, $throttled);
        // The kernel's HTML error page reads as a failed request to a fetch()
        // loop, which then keeps hammering at its normal cadence.
        self::assertStringContainsString('application/json', $throttled->headers()['content-type'] ?? '');
        $payload = json_decode($throttled->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('rate_limited', $payload['error']);
        self::assertGreaterThan(0, $payload['retry_after']);
        self::assertGreaterThan(0, (int) ($throttled->headers()['retry-after'] ?? 0));
    }

    public function test_the_directory_shares_the_same_bucket_as_the_poll(): void
    {
        // Throttling the poll while leaving paginated, searchable enumeration
        // wide open would be theatre, so both routes spend the same policy.
        $this->withRateLimit('presence', 1, 300);

        $this->assertStatus(200, $this->get('/presence'));
        // A human gets the kernel's HTML error page, not JSON.
        $this->assertStatus(429, $this->get('/users-online'));
    }

    public function test_the_presence_rate_limit_policy_is_actually_declared(): void
    {
        // RateLimitService::consume() silently no-ops on an unknown policy name,
        // so a typo here would ship an unthrottled, guest-reachable endpoint and
        // nothing else in the suite would notice.
        $config = require dirname(__DIR__, 3) . '/config/config.php';
        self::assertArrayHasKey('presence', $config['rate_limits']);
        self::assertCount(2, $config['rate_limits']['presence']);
    }
}
