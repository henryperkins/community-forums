<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * /mod posture rule (round-2 audit finding 7, recorded in ADR 0023): browsing a
 * staff surface with zero moderation authority → 404 (existence-hiding, the
 * /mod/reports + PR #44 precedent, ADMIN §9.4 "hide what a role can't do");
 * attempting a staff ACTION without authority stays 403. /mod/approvals also
 * gains the previously missing moderation_queue flag gate.
 */
final class AppModPostureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_mod_queue_surfaces_are_uniformly_404_for_non_staff(): void
    {
        $subject = (int) $this->makeUser(['username' => 'posturesubject'])['id'];
        $this->actingAs($this->makeUser(['username' => 'posturemember']));

        $this->assertStatus(404, $this->get('/mod/reports'));
        $this->assertStatus(404, $this->get('/mod/approvals'));
        $this->assertStatus(404, $this->get('/mod/appeals'));
        $this->assertStatus(404, $this->get('/mod/u/' . $subject));
    }

    public function test_mod_actions_without_authority_stay_403(): void
    {
        $board = $this->makeBoard($this->makeCategory('Posture'), ['slug' => 'posture-board']);
        $author = $this->makeUser(['username' => 'postureauthor']);
        $t = $this->makeThread($board, $author, 'Posture topic', 'Body.');
        $reply = $this->posting()->reply($this->userEntity($author), $t['thread_id'], ['body' => 'held reply']);
        $this->db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$reply]);

        $this->actingAs($this->makeUser(['username' => 'postureactor']));
        $this->assertStatus(403, $this->post('/mod/approvals/post/' . $reply . '/approve'));
        $this->assertStatus(403, $this->post('/mod/u/' . (int) $author['id'] . '/warn', ['reason' => 'nope']));
    }

    public function test_mod_approvals_goes_dark_with_moderation_queue_off_even_for_admin(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'postureadmin']));
        self::assertNotSame(404, $this->get('/mod/approvals')->status());

        (new SettingRepository($this->db))->set('features', ['moderation_queue' => false]);
        $this->assertStatus(404, $this->get('/mod/approvals'));
        $this->assertStatus(404, $this->post('/mod/approvals/post/1/approve'));
        $this->assertStatus(200, $this->get('/'));
    }

    public function test_admin_dashboard_approval_pointer_follows_the_flag(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'posturecards']));
        self::assertStringContainsString('href="/mod/approvals"', $this->get('/admin')->body());

        (new SettingRepository($this->db))->set('features', ['moderation_queue' => false]);
        self::assertStringNotContainsString('href="/mod/approvals"', $this->get('/admin')->body());
    }
}
