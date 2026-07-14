<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use App\Repository\UserBoardPrefRepository;
use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

/**
 * Member controls (P2-10): privacy, reading preferences (server-enforced),
 * board organization, blocks list, and digest settings.
 */
final class AppUserPreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_privacy_settings_persist(): void
    {
        $user = $this->makeUser(['username' => 'priv']);
        $this->actingAs($user);

        $this->assertStatus(200, $this->get('/settings/privacy'));
        $res = $this->post('/settings/privacy', [
            'profile_visibility' => 'members',
            'allow_dms' => 'none',
            // show_presence unchecked → off
            'hide_from_leaderboard' => '1',
        ]);
        $this->assertRedirect($res, '/settings/privacy');

        $row = $this->users()->find((int) $user['id']);
        self::assertSame('members', $row['profile_visibility']);
        self::assertSame('none', $row['allow_dms']);
        self::assertSame(0, (int) $row['show_presence']);

        $prefs = (new UserPreferenceRepository($this->db))->get((int) $user['id']);
        self::assertTrue($prefs['hide_from_leaderboard']);
    }

    public function test_reading_preference_is_server_enforced(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'pref-board']);
        $user = $this->makeUser(['username' => 'reader']);

        $t = $this->makeThread($board, $user, 'Long thread');
        for ($i = 0; $i < 10; $i++) {
            $this->posting()->reply($this->userEntity($user), $t['thread_id'], ['body' => "reply $i"]);
        }

        $url = '/t/' . $t['thread_id'] . '-' . $t['slug'];
        $this->actingAs($user);
        // Default (20/page): 11 posts fit on one page.
        $this->assertDontSeeText($this->get($url), 'page=2');

        // Set 10/page: now there is a second page.
        $this->post('/settings/preferences', ['posts_per_page' => '10']);
        $this->assertSeeText($this->get($url), 'page=2');
    }

    public function test_board_mute_hides_from_sidebar(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'muteme', 'name' => 'MuteMe']);
        // A second board gives us a page whose MAIN content doesn't list 'muteme',
        // so the only place '/c/muteme' can appear is the sidebar nav.
        $this->makeBoard($cat, ['slug' => 'elsewhere', 'name' => 'Elsewhere']);
        $user = $this->makeUser(['username' => 'muter']);
        $this->actingAs($user);

        $this->assertSeeText($this->get('/c/elsewhere'), '/c/muteme');

        $this->post('/settings/boards/toggle', ['board_id' => (int) $board['id'], 'pref' => 'mute']);
        self::assertSame(1, (int) (new UserBoardPrefRepository($this->db))->forUser((int) $user['id'])[(int) $board['id']]['is_muted']);

        $this->assertDontSeeText($this->get('/c/elsewhere'), '/c/muteme');
    }

    public function test_board_favorite_persists(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'favme']);
        $user = $this->makeUser(['username' => 'faver']);
        $this->actingAs($user);

        $this->post('/settings/boards/toggle', ['board_id' => (int) $board['id'], 'pref' => 'favorite']);
        self::assertSame(1, (int) (new UserBoardPrefRepository($this->db))->forUser((int) $user['id'])[(int) $board['id']]['is_favorite']);
    }

    public function test_stale_board_preference_toggle_redirects_instead_of_500(): void
    {
        $user = $this->makeUser(['username' => 'stale_board_pref']);
        $this->actingAs($user);

        $res = $this->post('/settings/boards/toggle', ['board_id' => 999999, 'pref' => 'favorite']);

        $this->assertRedirect($res, '/settings/boards');
    }

    public function test_malformed_board_preference_toggle_redirects_instead_of_500(): void
    {
        $user = $this->makeUser(['username' => 'malformed_board_pref']);
        $this->actingAs($user);

        $res = $this->post('/settings/boards/toggle', ['board_id' => 'abc', 'pref' => 'favorite']);

        $this->assertRedirect($res, '/settings/boards');
    }

    public function test_invalid_board_preference_name_redirects_instead_of_500(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'invalid-pref-board']);
        $user = $this->makeUser(['username' => 'invalid_board_pref']);
        $this->actingAs($user);

        $res = $this->post('/settings/boards/toggle', ['board_id' => (int) $board['id'], 'pref' => 'zzz']);

        $this->assertRedirect($res, '/settings/boards');
    }

    public function test_blocks_page_lists_blocked_users(): void
    {
        $me = $this->makeUser(['username' => 'blocker']);
        $foe = $this->makeUser(['username' => 'foe']);
        (new BlockRepository($this->db))->block((int) $me['id'], (int) $foe['id']);

        $this->actingAs($me);
        $res = $this->get('/settings/blocks');
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, '@foe');
    }

    public function test_digest_settings_persist(): void
    {
        $user = $this->makeUser(['username' => 'digester']);
        $this->actingAs($user);

        $res = $this->post('/settings/notifications', ['timezone' => 'America/New_York', 'digest_hour' => '8']);
        $this->assertRedirect($res, '/settings/notifications');

        $row = $this->users()->find((int) $user['id']);
        self::assertSame('America/New_York', $row['timezone']);
        self::assertSame(8, (int) $row['digest_hour']);
    }

    public function test_digest_hour_label_names_effective_timezone(): void
    {
        $user = $this->makeUser(['username' => 'digest_label']);
        $this->actingAs($user);

        $res = $this->get('/settings/notifications');

        $this->assertStatus(200, $res);
        self::assertStringContainsString('Digest hour (selected timezone; UTC if unset)', $res->body());
    }

    public function test_pause_all_email_preference_persists_and_renders(): void
    {
        $user = $this->makeUser(['username' => 'email_pauser']);
        $this->actingAs($user);

        $paused = $this->post('/settings/notifications', [
            'timezone' => 'UTC',
            'digest_hour' => '9',
            'pause_all_email' => '1',
        ]);
        $this->assertRedirect($paused, '/settings/notifications');

        $prefs = (new UserPreferenceRepository($this->db))->get((int) $user['id']);
        self::assertTrue($prefs['pause_all_email']);
        self::assertStringContainsString('name="pause_all_email" value="1" checked', $this->get('/settings/notifications')->body());

        $resumed = $this->post('/settings/notifications', [
            'timezone' => 'UTC',
            'digest_hour' => '9',
        ]);
        $this->assertRedirect($resumed, '/settings/notifications');

        $prefs = (new UserPreferenceRepository($this->db))->get((int) $user['id']);
        self::assertFalse($prefs['pause_all_email']);
    }

    public function test_guest_cannot_reach_member_controls(): void
    {
        $this->assertRedirectContains($this->get('/settings/privacy'), '/login');
        $this->assertRedirectContains($this->get('/settings/preferences'), '/login');
        $this->assertRedirectContains($this->get('/settings/preferences/export'), '/login');
        $this->assertRedirectContains($this->get('/settings/sessions'), '/login');
        $this->assertRedirectContains($this->get('/settings/blocks'), '/login');
        $this->assertRedirectContains($this->get('/settings/boards'), '/login');
    }

    public function test_preferences_export_returns_a_self_describing_json_download(): void
    {
        $user = $this->makeUser(['username' => 'exporter']);
        $this->actingAs($user);
        // Persist non-default appearance prefs so the export reflects them.
        $this->post('/settings/appearance', [
            'theme' => 'dark', 'density' => 'compact', 'font_size' => 'large', 'reduced_motion' => '1',
        ]);

        $res = $this->get('/settings/preferences/export');
        $this->assertStatus(200, $res);
        self::assertStringContainsString('application/json', (string) $res->getHeader('content-type'));
        $disposition = (string) $res->getHeader('content-disposition');
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString('retroboards-preferences.json', $disposition);

        $data = json_decode($res->body(), true);
        self::assertIsArray($data);
        self::assertSame(\App\Support\PreferenceSchema::VERSION, $data['schema_version']);
        self::assertSame('exporter', $data['username']);
        // Grouped by section, reflecting the saved overrides + schema defaults.
        self::assertSame('dark', $data['preferences']['appearance']['theme']);
        self::assertSame('compact', $data['preferences']['appearance']['density']);
        self::assertTrue($data['preferences']['appearance']['reduced_motion']);
        self::assertSame('last_post', $data['preferences']['reading']['thread_sort']);
        self::assertArrayHasKey('composing', $data['preferences']);
        // Non-schema blob keys are not leaked into the export.
        self::assertArrayNotHasKey('hide_from_leaderboard', $data['preferences']);
    }

    public function test_corrupt_preference_blob_recovers_to_defaults(): void
    {
        $user = $this->makeUser(['username' => 'corrupt']);
        // The prefs column has CHECK (json_valid), so truly malformed text can't
        // be stored; the realistic "corrupt blob" is a valid-JSON value that is
        // not the expected object (here a scalar). get() must recover to [] so
        // rendering falls back to defaults instead of 500-ing.
        $this->db->run(
            'INSERT INTO user_preferences (user_id, prefs, updated_at) VALUES (?, ?, UTC_TIMESTAMP())',
            [(int) $user['id'], '"corrupt-not-an-object"'],
        );

        $prefs = (new UserPreferenceRepository($this->db))->get((int) $user['id']);
        self::assertSame([], $prefs, 'A non-object prefs blob must decode to an empty array.');

        // The settings + thread render paths still serve 200 with defaults.
        $this->actingAs($user);
        $appearance = $this->get('/settings/appearance');
        $this->assertStatus(200, $appearance);
        $this->assertSeeText($appearance, 'choice-card-title">System</span>'); // default theme option rendered
        $this->assertSeeText($appearance, 'choice-card-desc">Match your device.</span>');
        $reading = $this->get('/settings/preferences');
        $this->assertStatus(200, $reading);
        self::assertStringContainsString('<option value="20" selected>20</option>', $reading->body());
    }

    public function test_fresh_user_gets_the_slack_like_composing_defaults(): void
    {
        // composer.js reads these <body> data attributes to gate enter-to-send,
        // the live preview, and smart list continuation (P3-01).
        $user = $this->makeUser(['username' => 'freshcompose']);
        $this->actingAs($user);

        $home = $this->get('/')->body();
        self::assertStringContainsString('data-enter-to-send="1"', $home);
        self::assertStringContainsString('data-show-preview="1"', $home);
        self::assertStringContainsString('data-smart-lists="1"', $home);
    }

    public function test_composing_settings_explain_the_send_and_preview_contract(): void
    {
        $user = $this->makeUser(['username' => 'composecopy']);
        $this->actingAs($user);
        $settings = $this->get('/settings/composing')->body();
        self::assertStringContainsString('outside lists, quotes, and code', $settings);
        self::assertStringContainsString('always sends', $settings);
        self::assertStringContainsString('Start with the preview pane open (source mode)', $settings);
    }

    public function test_explicit_saved_off_enter_to_send_round_trips_unchanged(): void
    {
        $user = $this->makeUser(['username' => 'composeoff']);
        $this->actingAs($user);

        $response = $this->post('/settings/composing', [
            'show_preview' => '1',
            'smart_lists' => '1',
            // enter_to_send deliberately omitted: unchecked persists false.
        ]);
        $this->assertRedirect($response);

        $home = $this->get('/')->body();
        self::assertStringContainsString('data-enter-to-send="0"', $home);
        self::assertStringContainsString('data-show-preview="1"', $home);
        self::assertStringContainsString('data-smart-lists="1"', $home);
    }

    public function test_guest_body_has_no_composing_attributes(): void
    {
        // Guests never compose; the attributes are only stamped when signed in.
        $body = $this->get('/')->body();
        self::assertStringNotContainsString('data-enter-to-send', $body);
    }
}
