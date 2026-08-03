<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BadgeRepository;
use Tests\Support\TestCase;

/**
 * Member-profile visual-fidelity contract against the Imladris user-profile
 * template and supplied Commend Star component.
 *
 * (The cover regard stat keeps the noun "Regard" per DESIGN §5.4, which outranks
 * the kit's "Commends earned" label in the precedence chain — so it is not changed.)
 */
final class AppProfileFidelityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'siteadmin']);
    }

    public function test_marks_of_esteem_use_the_supplied_commend_star_not_a_symbol_or_emoji(): void
    {
        $user = $this->makeUser(['username' => 'erestor', 'display_name' => 'Erestor']);
        // The seeded 'welcome' badge ships with a 🎉 emoji icon; awarding it must not
        // surface that emoji on the profile.
        (new BadgeRepository($this->db))->awardBySlug((int) $user['id'], 'welcome');

        $res = $this->get('/u/erestor');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Marks of esteem');     // sentence case
        $this->assertDontSeeText($res, 'Marks of Esteem');  // not title case
        $this->assertSeeText($res, 'icon-commend-star badge-star');
        $this->assertSeeText($res, 'M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z');
        $this->assertSeeText($res, 'Welcome');              // the badge is still shown
        $this->assertDontSeeText($res, 'b-dot');
        $this->assertDontSeeText($res, '✦');
        $this->assertDontSeeText($res, '🎉');               // the emoji never reaches render
    }

    public function test_member_actions_keep_a_safe_copy_link_and_omit_profile_reporting(): void
    {
        $profile = $this->makeUser(['username' => 'celeborn', 'display_name' => 'Celeborn']);
        $this->actingAs($this->makeUser(['username' => 'profile-viewer']));

        $res = $this->get('/u/celeborn');

        $this->assertStatus(200, $res);
        self::assertMatchesRegularExpression('/<a[^>]+href="\/u\/celeborn"[^>]+data-copy-link/', $res->body());
        $this->assertSeeText($res, 'Copy link');
        $this->assertSeeText($res, 'Block');
        $this->assertDontSeeText($res, 'Report');
        unset($profile);
    }

    public function test_gated_profile_uses_the_private_card_without_invented_actions(): void
    {
        $profile = $this->makeUser(['username' => 'private-seat']);
        $this->db->run("UPDATE users SET profile_visibility = 'members' WHERE id = ?", [(int) $profile['id']]);

        $res = $this->get('/u/private-seat');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'This seat is kept private');
        $this->assertSeeText($res, 'Log in to view');
        $this->assertDontSeeText($res, 'Request access');
        $this->assertDontSeeText($res, 'Message');
    }
}
