<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BadgeRepository;
use Tests\Support\TestCase;

/**
 * Member-profile visual-fidelity pass against the Imladris UI kit (retroboards
 * Profile). The profile cover, gilt avatar, tier pill and regard value already
 * match; these assert the remaining gaps:
 *  - "Marks of esteem" carry the brand dot, never an emoji (the design forbids
 *    emoji in UI — the seed badges ship with 🎉/💬/… which must not reach render);
 *  - the section label is sentence-case and uses the kit's class name.
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

    public function test_marks_of_esteem_use_the_brand_dot_not_an_emoji(): void
    {
        $user = $this->makeUser(['username' => 'erestor', 'display_name' => 'Erestor']);
        // The seeded 'welcome' badge ships with a 🎉 emoji icon; awarding it must not
        // surface that emoji on the profile.
        (new BadgeRepository($this->db))->awardBySlug((int) $user['id'], 'welcome');

        $res = $this->get('/u/erestor');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Marks of esteem');     // sentence case
        $this->assertDontSeeText($res, 'Marks of Esteem');  // not title case
        $this->assertSeeText($res, 'b-dot');                // the brand dot replaces the icon
        $this->assertSeeText($res, 'Welcome');              // the badge is still shown
        $this->assertDontSeeText($res, '🎉');               // the emoji never reaches render
    }
}
