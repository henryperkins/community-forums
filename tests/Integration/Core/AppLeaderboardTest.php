<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

/**
 * All-time Top Contributors (P2-09): ranked by reputation, excluding opted-out
 * and banned members, granting no powers.
 */
final class AppLeaderboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function setRep(array $user, int $rep): void
    {
        $this->db->run('UPDATE users SET reputation = ? WHERE id = ?', [$rep, (int) $user['id']]);
    }

    public function test_leaderboard_ranks_and_excludes_optout_and_banned(): void
    {
        $top = $this->makeUser(['username' => 'topper', 'display_name' => 'Topper']);
        $mid = $this->makeUser(['username' => 'middler', 'display_name' => 'Middler']);
        $hidden = $this->makeUser(['username' => 'shy', 'display_name' => 'ShyOne']);
        $banned = $this->makeUser(['username' => 'gone', 'display_name' => 'Goner']);
        $zero = $this->makeUser(['username' => 'newbie', 'display_name' => 'Newbie']);

        $this->setRep($top, 500);
        $this->setRep($mid, 200);
        $this->setRep($hidden, 999);
        $this->setRep($banned, 999);
        // $zero stays at 0 reputation.

        (new UserPreferenceRepository($this->db))->merge((int) $hidden['id'], ['hide_from_leaderboard' => true]);
        $this->users()->setStatus((int) $banned['id'], 'banned');

        $res = $this->get('/leaderboard');
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Topper');
        $this->assertSeeText($res, 'Middler');
        $this->assertDontSeeText($res, 'ShyOne');   // opted out
        $this->assertDontSeeText($res, 'Goner');     // banned
        $this->assertDontSeeText($res, 'Newbie');    // zero reputation

        // Topper (500) ranks above Middler (200).
        $body = $res->body();
        self::assertLessThan(strpos($body, 'Middler'), strpos($body, 'Topper'));
    }
}
