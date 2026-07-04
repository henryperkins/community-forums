<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Config;
use Tests\Support\TestCase;

final class AppEnforcementCutoverTest extends TestCase
{
    public function test_enforce_mode_keeps_admin_moderation_working_end_to_end(): void
    {
        $admin = $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $t = $this->makeThread($board, $author);
        $this->withCapabilitiesEnforced();

        $this->actingAs($admin);
        $response = $this->post('/mod/t/' . $t['thread_id'] . '/lock');
        $this->assertRedirect($response); // admin still locks under enforcement
    }

    public function test_enforce_mode_denies_plain_member_moderation(): void
    {
        // A live admin must already exist or every request (including this
        // POST) is bounced to /setup by App::process()'s first-run gate
        // (SetupService::isInitialized() === adminCount() > 0), independent of
        // the moderation check this test targets. The sibling test above
        // satisfies this incidentally via makeAdmin(); this one must too.
        $this->makeAdmin();
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $t = $this->makeThread($board, $author);
        $this->withCapabilitiesEnforced();

        $this->actingAs($member);
        $response = $this->post('/mod/t/' . $t['thread_id'] . '/lock');
        $this->assertStatus(403, $response);
    }
}
