<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BadgeRepository;
use App\Repository\BlockRepository;
use App\Repository\FollowRepository;
use App\Repository\UsernameHistoryRepository;
use Tests\Support\TestCase;

/**
 * Community profile surface (P2-09): cosmetic title, badges, follow/message/block
 * actions, follower/following counts, members-only visibility, and renamed-handle
 * redirects.
 */
final class AppCommunityProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_profile_shows_title_badges_and_counts(): void
    {
        $owner = $this->makeUser(['username' => 'star', 'display_name' => 'Star']);
        $follower = $this->makeUser(['username' => 'fan']);
        $this->db->run('UPDATE users SET reputation = 60 WHERE id = ?', [(int) $owner['id']]);
        (new BadgeRepository($this->db))->awardBySlug((int) $owner['id'], 'welcome');
        (new FollowRepository($this->db))->follow((int) $follower['id'], (int) $owner['id']);

        $res = $this->get('/u/star');
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Regular');   // derived title at rep 60
        $this->assertSeeText($res, 'Welcome');   // badge name
        $this->assertSeeText($res, 'Followers');
    }

    public function test_admin_title_override_wins(): void
    {
        $user = $this->makeUser(['username' => 'vip']);
        $this->db->run('UPDATE users SET reputation = 5, title = ? WHERE id = ?', ['Legend', (int) $user['id']]);
        $res = $this->get('/u/vip');
        $this->assertSeeText($res, 'Legend');
    }

    public function test_follow_button_shown_to_other_members_not_self(): void
    {
        $owner = $this->makeUser(['username' => 'owner']);
        $viewer = $this->makeUser(['username' => 'viewer2']);

        // Another member sees a Follow button (match the form action exactly so
        // it isn't confused with the "/u/owner/following" stat link).
        $this->actingAs($viewer);
        $other = $this->get('/u/owner');
        $this->assertSeeText($other, 'action="/u/owner/follow"');

        // The owner does not see a follow button on their own profile.
        $this->actingAs($owner);
        $self = $this->get('/u/owner');
        $this->assertDontSeeText($self, 'action="/u/owner/follow"');

        // Guests see no follow form.
        $this->logoutClient();
        $guest = $this->get('/u/owner');
        $this->assertDontSeeText($guest, 'action="/u/owner/follow"');
    }

    public function test_members_only_profile_is_gated_from_guests(): void
    {
        $user = $this->makeUser(['username' => 'private1']);
        $this->db->run("UPDATE users SET profile_visibility = 'members' WHERE id = ?", [(int) $user['id']]);

        $guest = $this->get('/u/private1');
        $this->assertStatus(200, $guest);
        $this->assertSeeText($guest, 'signed-in members');

        // A signed-in member sees the full profile.
        $this->actingAs($this->makeUser(['username' => 'member1']));
        $full = $this->get('/u/private1');
        $this->assertSeeText($full, '@private1');
        $this->assertDontSeeText($full, 'signed-in members');
    }

    public function test_block_button_toggles_and_severs_follow(): void
    {
        $me = $this->makeUser(['username' => 'me']);
        $them = $this->makeUser(['username' => 'them']);
        (new FollowRepository($this->db))->follow((int) $me['id'], (int) $them['id']);

        $this->actingAs($me);
        $this->post('/u/them/block');

        $blocks = new BlockRepository($this->db);
        self::assertTrue($blocks->blocks((int) $me['id'], (int) $them['id']));
        self::assertFalse((new FollowRepository($this->db))->isFollowing((int) $me['id'], (int) $them['id']));

        // Unblock toggles back off.
        $this->post('/u/them/block');
        self::assertFalse($blocks->blocks((int) $me['id'], (int) $them['id']));
    }

    public function test_renamed_username_redirects_from_old_handle(): void
    {
        $user = $this->makeUser(['username' => 'newname']);
        (new UsernameHistoryRepository($this->db))->record((int) $user['id'], 'oldname');

        $res = $this->get('/u/oldname');
        $this->assertRedirect($res, '/u/newname');
    }
}
