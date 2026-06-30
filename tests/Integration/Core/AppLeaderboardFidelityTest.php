<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Top-contributors (leaderboard) visual-fidelity pass against the Imladris UI kit
 * (ui_kits/retroboards Leaderboard). Asserts observable render behaviour:
 *  - regard uses the brand commend star (✦), never a generic ★;
 *  - the header carries the "The council" eyebrow and a sentence-case title;
 *  - the top three render as identity cards with a "@handle · title" sub-line;
 *  - lower ranks render as compact rows with the smaller mono regard.
 */
final class AppLeaderboardFidelityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    /** Seed four ranked members (rep desc) so there are 3 top cards + ≥1 compact row. */
    private function seedRankedCouncil(): void
    {
        foreach ([
            ['galadriel', 'Galadriel', 5000],
            ['elrond', 'Elrond', 4000],
            ['cirdan', 'Círdan', 3000],
            ['glorfindel', 'Glorfindel', 2000],
        ] as [$username, $name, $rep]) {
            $u = $this->makeUser(['username' => $username, 'display_name' => $name]);
            $this->db->run('UPDATE users SET reputation = ? WHERE id = ?', [$rep, (int) $u['id']]);
        }
    }

    public function test_regard_uses_the_brand_commend_star_not_a_generic_star(): void
    {
        $this->seedRankedCouncil();
        $res = $this->get('/leaderboard');
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'star-marker" aria-hidden="true">✦');
        $this->assertDontSeeText($res, 'star-marker" aria-hidden="true">★');
    }

    public function test_header_has_the_council_eyebrow_and_a_sentence_case_title(): void
    {
        $this->seedRankedCouncil();
        $res = $this->get('/leaderboard');
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'eyebrow');
        $this->assertSeeText($res, 'The council');
        $this->assertSeeText($res, 'Top contributors');
        $this->assertDontSeeText($res, 'Top Contributors');
    }

    public function test_top_three_render_as_identity_cards_with_a_handle_subline(): void
    {
        $this->seedRankedCouncil();
        $res = $this->get('/leaderboard');
        $this->assertStatus(200, $res);
        // Galadriel (highest regard) is a top-3 card with the @handle · title sub-line.
        $this->assertSeeText($res, 'lb-handle');
        $this->assertSeeText($res, '@galadriel');
    }

    public function test_lower_ranks_render_as_compact_rows(): void
    {
        $this->seedRankedCouncil();
        $res = $this->get('/leaderboard');
        $this->assertStatus(200, $res);
        // The fourth-ranked member (Glorfindel) uses the compact-row regard class.
        $this->assertSeeText($res, 'lb-row-rep');
    }
}
