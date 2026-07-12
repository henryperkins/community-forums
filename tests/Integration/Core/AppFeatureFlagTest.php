<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use App\Worker\RelatedTopicRefreshWorker;
use Tests\Support\TestCase;

/**
 * Feature-flag rollback safety (PHASE_2_PLAN §12). Every Phase 2 subsystem is
 * gated so an operator can "deploy dark" or roll a feature back via the `features`
 * setting without a data change. Disabling a flag must take its routes offline
 * (404) while the core forum keeps serving — and re-enabling restores it.
 */
final class AppFeatureFlagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_invalidate_reloads_live_operator_overrides(): void
    {
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertTrue($flags->enabled('community_memory'));

        $this->setFlags(['community_memory' => false]);
        self::assertTrue($flags->enabled('community_memory'), 'the per-request cache remains stable until a boundary asks to refresh');

        $flags->invalidate();
        self::assertFalse($flags->enabled('community_memory'));
    }

    public function test_phase4_gate_a_flags_have_expected_default_posture(): void
    {
        $flags = new FeatureFlags(new SettingRepository($this->db));
        $defaults = $flags->all();
        foreach (['tags', 'expanded_feeds', 'reputation_ledger', 'badge_rules', 'community_memory', 'content_references'] as $flag) {
            self::assertArrayHasKey($flag, $defaults, "$flag should be declared in FeatureFlags::DEFAULTS");
            self::assertTrue($flags->enabled($flag), "$flag should be default-on after graduation");
        }
        foreach (['group_dms'] as $flag) {
            self::assertArrayHasKey($flag, $defaults, "$flag should be declared in FeatureFlags::DEFAULTS");
            self::assertFalse($flags->enabled($flag), "$flag should deploy dark by default");
        }

        $this->setFlags(['tags' => false]);
        $overridden = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($overridden->enabled('tags'));
        self::assertTrue($overridden->enabled('community'));
    }

    public function test_community_memory_is_available_without_an_override(): void
    {
        $author = $this->makeUser(['username' => 'memory_default_author']);
        $curator = $this->makeAdmin(['username' => 'memory_default_curator']);
        $board = $this->makeBoard($this->makeCategory('Memory Default'), ['slug' => 'memory-default']);
        $thread = $this->makeThread($board, $author, 'Memory default topic', 'Opening post.');
        $sourcePostId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$thread['thread_id']],
        );
        $this->actingAs($curator);

        $response = $this->post('/t/' . $thread['thread_id'] . '/summary', [
            'body' => 'Manual memory remains available by default.',
            'source_post_ids' => (string) $sourcePostId,
        ]);

        $this->assertRedirectContains($response, '/t/' . $thread['thread_id']);
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ? AND status = ?',
            [$thread['thread_id'], 'published'],
        ));
    }

    public function test_community_memory_explicit_false_rolls_back_routes_and_mutations(): void
    {
        $this->setFlags(['community_memory' => false]);
        $author = $this->makeUser(['username' => 'memory_rollback_author']);
        $board = $this->makeBoard($this->makeCategory('Memory Rollback'), ['slug' => 'memory-rollback']);
        $thread = $this->makeThread($board, $author, 'Memory rollback topic', 'Opening post.');
        $sourcePostId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$thread['thread_id']],
        );
        $this->actingAs($author);

        $response = $this->post('/t/' . $thread['thread_id'] . '/summary', [
            'body' => 'This summary must not be published while memory is rolled back.',
            'source_post_ids' => (string) $sourcePostId,
        ]);

        $this->assertStatus(404, $response);
        self::assertSame(0, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ?',
            [$thread['thread_id']],
        ));
    }

    public function test_automated_context_explicit_false_rolls_back_context_and_worker(): void
    {
        $this->setFlags(['community_memory' => true, 'automated_context' => false]);
        $author = $this->makeUser(['username' => 'context_rollback_author']);
        $viewer = $this->makeUser(['username' => 'context_rollback_viewer']);
        $board = $this->makeBoard($this->makeCategory('Context Rollback'), ['slug' => 'context-rollback']);
        $thread = $this->makeThread($board, $author, 'Context rollback topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$thread['thread_id']],
        );
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], [
            'body' => 'Unread context that must stay hidden while automation is rolled back.',
        ]);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertSame(
            ['linked' => 0, 'skipped' => 1],
            (new RelatedTopicRefreshWorker($this->db, $flags))->run(),
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringNotContainsString('Since you last read', $page->body());
        self::assertSame(0, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM since_last_read_context WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        ));
    }

    public function test_wysiwyg_composer_is_available_by_default_and_can_be_disabled(): void
    {
        // wysiwyg_composer graduated to default-on (GA 2026-07-02): with no
        // features override, the Milkdown layer loads wherever the composer
        // renders (bundle tags + the body data attribute). An operator can
        // still roll the layer back via the features setting, and
        // rich_composer stays the broad kill switch (ADR 0013).
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('wysiwyg_composer', $flags->all());
        self::assertTrue($flags->enabled('wysiwyg_composer'));
        self::assertTrue($flags->enabled('rich_composer'));

        // Isolation: graduating wysiwyg_composer must not enable a dark neighbour.
        self::assertFalse($flags->enabled('group_dms'));

        // Available by default on a real page for a signed-in member.
        $this->makeBoard($this->makeCategory(), ['slug' => 'wysiwyg-default']);
        $this->actingAs($this->makeUser(['username' => 'wysiwyg_default_user']));
        $page = $this->get('/c/wysiwyg-default');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('data-wysiwyg-composer="1"', $page->body());

        // Operator rollback: disabling the narrow flag removes the layer.
        $this->setFlags(['wysiwyg_composer' => false]);
        $disabled = $this->get('/c/wysiwyg-default');
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $disabled->body());

        // Kill-switch interplay: rich_composer=false keeps assets dark while
        // the narrow flag remains true by default (no wysiwyg key in the override).
        $this->setFlags(['rich_composer' => false]);
        $killed = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($killed->enabled('rich_composer'));
        self::assertTrue($killed->enabled('wysiwyg_composer'), 'the narrow flag stays true while the broad kill switch keeps assets dark');
        $killedPage = $this->get('/c/wysiwyg-default');
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $killedPage->body());
    }

    public function test_topic_workflow_is_available_by_default_and_can_be_disabled(): void
    {
        // topic_workflow graduated to default-on (GA 2026-07-01): with no
        // features override, the status route is live for a permitted author. An
        // operator can still take the whole surface offline via the features
        // setting (the rollback path), mirroring the polls graduation.
        $author = $this->makeUser(['username' => 'wf_default_author']);
        $board = $this->makeBoard($this->makeCategory('Workflow Default'));
        $thread = $this->makeThread($board, $author, 'Workflow default', 'body');
        $this->actingAs($author);

        // Available by default (no override): the OP may set open/needs_answer/
        // solved, so the status write redirects back to the thread, not 404.
        $this->assertRedirectContains($this->post('/t/' . $thread['thread_id'] . '/status', [
            'status' => 'needs_answer',
            'reason' => 'triage',
        ]), '/t/' . $thread['thread_id']);
        self::assertTrue((new FeatureFlags(new SettingRepository($this->db)))->enabled('topic_workflow'));

        // Isolation: graduating topic_workflow must not enable a Gate A neighbour.
        self::assertFalse((new FeatureFlags(new SettingRepository($this->db)))->enabled('group_dms'));

        // Operator rollback: disabling the flag takes the route offline (404).
        $this->setFlags(['topic_workflow' => false]);
        $this->assertStatus(404, $this->post('/t/' . $thread['thread_id'] . '/status', [
            'status' => 'solved',
            'reason' => 'x',
        ]));
    }

    public function test_phase5_gate_a_defaults_on_and_gate_b_stays_dark(): void
    {
        $flags = new FeatureFlags(new SettingRepository($this->db));
        $phase5DefaultOn = [
            'package_registry',
            'package_themes',
            'capabilities',
            'passkeys',
            'provider_registry',
            'invitations',
            'service_secrets',
            'api_tokens',
            'webhooks',
            'first_party_hooks',
        ];
        $phase5DefaultDark = [
            'server_extensions',
            'governance',
            'service_principals',
            'verified_links',
        ];

        foreach ($phase5DefaultOn as $flag) {
            self::assertArrayHasKey($flag, $flags->all(), "$flag should be declared in FeatureFlags::DEFAULTS");
            self::assertTrue($flags->enabled($flag), "$flag should be default-on after Phase 5 Gate A acceptance");
        }
        foreach ($phase5DefaultDark as $flag) {
            self::assertArrayHasKey($flag, $flags->all(), "$flag should be declared in FeatureFlags::DEFAULTS");
            self::assertFalse($flags->enabled($flag), "$flag should stay default-dark for Gate B");
        }

        $defaults = $flags->all();
        self::assertCount(57, $defaults, 'the declared flag inventory must remain stable during graduation');
        self::assertSame(49, count(array_filter($defaults)), 'fresh and upgraded installs should have 49 default-on flags');
        self::assertSame(8, count($defaults) - count(array_filter($defaults)), 'fresh and upgraded installs should keep 8 default-dark flags');
        self::assertSame([
            'custom_css',
            'group_dms',
            'link_previews',
            'expanded_files',
            'server_extensions',
            'governance',
            'service_principals',
            'verified_links',
        ], array_keys(array_filter($defaults, static fn (bool $enabled): bool => !$enabled)), 'only the approved eight flags stay default-dark');

        $this->setFlags(['capabilities' => false, 'passkeys' => false]);
        $overridden = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($overridden->enabled('capabilities'), 'operator rollback should disable capabilities');
        self::assertFalse($overridden->enabled('passkeys'), 'operator rollback should disable passkeys');
        self::assertTrue($overridden->enabled('provider_registry'), 'rolling back one Phase 5 flag must not disable its neighbours');
        self::assertFalse($overridden->enabled('server_extensions'), 'Gate B stays dark without an override');

        // Enable-direction isolation (kept from the pre-graduation test): lighting
        // one reserved flag via override must not light its siblings.
        $this->setFlags(['server_extensions' => true]);
        $gateB = new FeatureFlags(new SettingRepository($this->db));
        self::assertTrue($gateB->enabled('server_extensions'), 'an explicit override can light a reserved flag');
        self::assertFalse($gateB->enabled('governance'), 'enabling one reserved flag must not enable its siblings');
    }

    public function test_phase5_gate_a_surfaces_are_live_with_no_features_override(): void
    {
        // The zero-override pin every prior graduation carried: the surfaces must
        // actually ANSWER on a pristine install, not merely have a TRUE default —
        // a gate reading the raw setting instead of FeatureFlags::enabled() would
        // pass the posture test while shipping dark. Deliberately no setFlags().
        $this->actingAs($this->makeAdmin(['username' => 'p5_default_admin']));
        foreach ([
            'package_registry' => '/admin/packages',
            'package_themes' => '/admin/themes',
            'capabilities' => '/admin/roles',
            'provider_registry' => '/admin/providers',
            'invitations' => '/admin/invitations',
            'api_tokens' => '/admin/api-tokens',
            'webhooks' => '/admin/webhooks',
        ] as $flag => $path) {
            self::assertNotSame(404, $this->get($path)->status(), "$path must be live by default ($flag)");
        }
        self::assertNotSame(404, $this->get('/admin/registries')->status(), '/admin/registries must be live by default (package_registry)');

        // passkeys: the ceremony route answers (not 404) for a member, and guests
        // see the sign-in affordance. service_secrets/first_party_hooks have no
        // GET surface; their flag-level defaults are pinned by the posture test.
        $this->actingAs($this->makeUser(['username' => 'p5_default_member']));
        self::assertNotSame(404, $this->post('/settings/security/passkeys/challenge', ['current_password' => 'x'])->status());
        $this->logoutClient();
        self::assertStringContainsString('data-passkey-signin', $this->get('/login')->body());

        // api_tokens public seam: anonymous /api/v1 is an auth failure, not absent.
        self::assertSame(401, $this->get('/api/v1/me')->status());
    }

    public function test_capabilities_rollback_keeps_legacy_authorization_writes_live(): void
    {
        // The documented emergency rollback (features.capabilities=false) swaps in
        // AuthorityGate::legacy() with a null resolver; drive real authorization
        // WRITES through the kernel on that posture so a legacy-branch regression
        // (dropped ?->-guard, reordered state-then-capability check) cannot ship
        // green. Complements the route-404 pins, which never exercise a write.
        $this->setFlags(['capabilities' => false]);
        $admin = $this->makeAdmin(['username' => 'legacy_ops_admin']);
        $mod = $this->makeUser(['username' => 'legacy_board_mod']);
        $member = $this->makeUser(['username' => 'legacy_member']);
        $board = $this->makeBoard($this->makeCategory('Legacy Authz'));
        $thread = $this->makeThread($board, $member, 'Legacy authz thread', 'Opening post.');

        // Roster command (AdminService legacy authority) succeeds for the admin…
        $this->actingAs($admin);
        $this->assertRedirect($this->post('/admin/boards/' . $board['id'] . '/moderators', ['username' => 'legacy_board_mod']));

        // …and keeps the V1 no-existence-oracle ordering for a non-admin: 403
        // (authorize first), never 404-for-missing.
        $this->actingAs($member);
        $this->assertStatus(403, $this->post('/admin/boards/999999/moderators', ['username' => 'x']));

        // Moderation write via the legacy closure: the just-assigned board mod may
        // lock (also proves the roster write landed); a plain member may not.
        $this->actingAs($mod);
        $this->assertRedirect($this->post('/mod/t/' . $thread['thread_id'] . '/lock'));
        $this->actingAs($member);
        $this->assertStatus(403, $this->post('/mod/t/' . $thread['thread_id'] . '/lock'));
    }

    public function test_override_values_parse_strictly_and_garbage_fails_dark(): void
    {
        // Rollback is the primary safety lever now that Gate A defaults on, and the
        // only production write path is a hand-edited JSON object — so the merge
        // must honor operator intent for string shapes ("false" must not read ON)
        // and fail DARK on garbage instead of (bool)-truthy failing open.
        $this->setFlags([
            'passkeys' => 'false',
            'webhooks' => 'off',
            'invitations' => '0',
            'api_tokens' => 'true',
            'capabilities' => '1',
            'provider_registry' => ['unexpected'],
            'package_registry' => 'garbage',
        ]);
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($flags->enabled('passkeys'), 'string "false" must roll the flag back, not read ON');
        self::assertFalse($flags->enabled('webhooks'), 'string "off" must roll the flag back');
        self::assertFalse($flags->enabled('invitations'), 'string "0" must roll the flag back');
        self::assertTrue($flags->enabled('api_tokens'), 'string "true" keeps the flag on');
        self::assertTrue($flags->enabled('capabilities'), 'string "1" keeps the flag on');
        self::assertFalse($flags->enabled('provider_registry'), 'a non-scalar override value must fail dark');
        self::assertFalse($flags->enabled('package_registry'), 'an unrecognizable override value must fail dark');
    }

    public function test_non_object_features_setting_is_ignored_and_defaults_apply(): void
    {
        // A double-encoded write leaves a JSON *string* in settings.features (it
        // passes MariaDB's json_valid CHECK, unlike malformed JSON). The loader
        // must ignore it, apply code defaults, and report the corruption.
        (new SettingRepository($this->db))->set('features', 'not-an-object');
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertTrue($flags->enabled('capabilities'), 'defaults apply when the override blob is corrupt');
        self::assertFalse($flags->enabled('server_extensions'), 'Gate B stays dark when the override blob is corrupt');
        self::assertTrue($flags->overridesCorrupt(), 'a non-object features value must be reported as corrupt');

        // Replacing the row with a real JSON object clears the condition.
        $this->setFlags(['passkeys' => false]);
        $repaired = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($repaired->overridesCorrupt(), 'a JSON-object features value is not corrupt');
        self::assertFalse($repaired->enabled('passkeys'), 'the repaired override applies');
    }

    public function test_provider_registry_flag_gates_generic_oidc_routes(): void
    {
        $this->setFlags(['provider_registry' => false]);
        // An ENABLED registry row must stay invisible when an operator rolls back the
        // P5-12 flag: no /auth routes, no sign-in button. Full-flow coverage lives
        // in AppOidcProviderTest; this is the canonical rollback pin.
        $id = (new \App\Repository\IdentityProviderRepository($this->db))->create([
            'provider_key' => 'darkidp',
            'display_name' => 'Dark IdP',
            'issuer' => 'https://dark.idp.test',
            'client_id' => 'client-x',
            'client_secret_ref' => 'svcsec_dark',
        ]);
        (new \App\Repository\IdentityProviderRepository($this->db))->setEnabled($id, true);

        $this->assertStatus(404, $this->get('/auth/darkidp/redirect'));
        $this->assertStatus(404, $this->get('/auth/darkidp/callback', ['code' => 'x', 'state' => 'y']));
        self::assertStringNotContainsString('/auth/darkidp/redirect', $this->get('/login')->body());

        $this->setFlags(['provider_registry' => true]);
        self::assertStringContainsString('/auth/darkidp/redirect', $this->get('/login')->body(), 'flag on: the enabled row surfaces');

        $this->setFlags(['provider_registry' => false]);
        $this->assertStatus(404, $this->get('/auth/darkidp/redirect'));
    }

    public function test_invitations_flag_gates_invitation_routes_and_redemption(): void
    {
        $this->setFlags(['invitations' => false]);
        // Canonical rollback pin for P5-13: routes 404 and a planted VALID invitation
        // stays inert while features.invitations=false.
        $adminRow = $this->makeAdmin();
        $admin = (new \App\Repository\UserRepository($this->db))->findEntity((int) $adminRow['id']);
        self::assertNotNull($admin);
        $board = $this->makeBoard($this->makeCategory(), []);
        $service = new \App\Service\InvitationService(
            $this->db,
            new \App\Repository\InvitationRepository($this->db),
            new \App\Service\AuthService(new \App\Repository\UserRepository($this->db), new \App\Security\PasswordHasher(), $this->config),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new \App\Repository\ModerationLogRepository($this->db),
        );
        $invite = $service->create($admin, ['onboarding_board_id' => (string) $board['id']]);

        $this->actingAs($adminRow);
        $this->assertStatus(404, $this->get('/admin/invitations'));
        $this->logoutClient();
        $this->assertStatus(404, $this->get('/invite/' . $invite['token']));

        $this->get('/register');
        $res = $this->post('/register', [
            'username' => 'darkordinary',
            'email' => 'darkordinary@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
            'invite' => $invite['token'],
        ]);
        $this->assertRedirect($res, '/');
        $user = (new \App\Repository\UserRepository($this->db))->findByUsername('darkordinary');
        self::assertNotNull($user, 'open-mode signup still works while the flag is dark');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT used_count FROM invitations WHERE id = ?', [$invite['id']]), 'the token must stay unconsumed while dark');
        self::assertFalse((new BoardMemberRepository($this->db))->isMember((int) $board['id'], (int) $user['id']), 'no grant while dark');

        $this->setFlags(['invitations' => true]);
        $this->logoutClient();
        $this->assertRedirect($this->get('/invite/' . $invite['token']), '/register?invite=' . $invite['token']);
    }

    public function test_capabilities_flag_gates_role_routes(): void
    {
        $this->setFlags(['capabilities' => false]);
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/roles'));
        // P5-09 scoped-assignment mutation routes (IDs are synthetic/nonexistent
        // here — the gate() flag-check runs before any row lookup, so this
        // proves the flag darkness, not a not-found).
        $this->assertStatus(404, $this->post('/admin/roles/1/assignments', ['username' => 'x', 'current_password' => 'password123']));
        $this->assertStatus(404, $this->post('/admin/role-assignments/1/revoke', []));
        $this->assertStatus(404, $this->post('/admin/role-assignments/1/renew', ['ends_at' => '2030-01-01 00:00', 'current_password' => 'password123']));

        $this->setFlags(['capabilities' => true]);
        self::assertNotSame(404, $this->get('/admin/roles')->status());

        $this->setFlags(['capabilities' => false]);
        $this->assertStatus(404, $this->get('/admin/roles'));
        $this->assertStatus(404, $this->post('/admin/roles/1/assignments', ['username' => 'x', 'current_password' => 'password123']));
        $this->assertStatus(404, $this->post('/admin/role-assignments/1/revoke', []));
        $this->assertStatus(404, $this->post('/admin/role-assignments/1/renew', ['ends_at' => '2030-01-01 00:00', 'current_password' => 'password123']));
    }

    public function test_passkeys_flag_gates_ceremony_routes(): void
    {
        $this->setFlags(['passkeys' => false]);
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->assertStatus(404, $this->post('/settings/security/passkeys/challenge', ['current_password' => 'x']));
        $this->assertStatus(404, $this->post('/settings/security/passkeys', ['credential' => '{}']));
        $this->assertStatus(404, $this->post('/settings/security/passkeys/step-up-challenge', []));
        $this->assertStatus(404, $this->post('/settings/security/passkeys/1/rename', ['nickname' => 'x']));
        $this->assertStatus(404, $this->post('/settings/security/passkeys/1/revoke', []));
        $this->assertStatus(404, $this->post('/login/passkey/challenge', ['email' => 'x@example.test']));
        $this->assertStatus(404, $this->post('/login/passkey', ['email' => 'x@example.test', 'credential' => '{}']));
        $securityPage = $this->get('/settings/security');
        $this->assertStatus(200, $securityPage);
        self::assertStringNotContainsString('data-passkey-panel', $securityPage->body());
        self::assertStringNotContainsString('/assets/passkeys.js', $this->get('/')->body());
        $this->logoutClient();
        $login = $this->get('/login');
        $this->assertStatus(200, $login);
        self::assertStringNotContainsString('data-passkey-signin', $login->body());
        $this->actingAs($user);

        $this->setFlags(['passkeys' => true]);
        self::assertNotSame(404, $this->post('/settings/security/passkeys/challenge', [])->status());
        self::assertStringContainsString('/assets/passkeys.js', $this->get('/')->body());
        $this->logoutClient();
        $enabledLogin = $this->get('/login');
        $this->assertStatus(200, $enabledLogin);
        self::assertStringContainsString('data-passkey-signin', $enabledLogin->body());

        $this->setFlags(['passkeys' => false]);
        $this->actingAs($user);
        $this->assertStatus(404, $this->post('/settings/security/passkeys/challenge', ['current_password' => 'x']));
    }

    public function test_package_registry_flag_gates_catalog_and_registry_routes(): void
    {
        $this->setFlags(['package_registry' => false]);
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/packages'));
        $this->assertStatus(404, $this->get('/admin/registries'));

        $this->setFlags(['package_registry' => true]);
        self::assertNotSame(404, $this->get('/admin/packages')->status());
        self::assertNotSame(404, $this->get('/admin/registries')->status());

        $this->setFlags(['package_registry' => false]);
        $this->assertStatus(404, $this->get('/admin/packages'));
        $this->assertStatus(404, $this->get('/admin/registries'));
        // A mutation route must also be dark (404, not 403/405) when the flag is off.
        $this->assertStatus(404, $this->post('/admin/blocklist', ['digest' => str_repeat('a', 64)]));
        foreach ([
            ['POST', '/admin/packages/1/plan', []],
            ['POST', '/admin/packages/1/install', ['current_password' => 'password123']],
            ['GET', '/admin/packages/1/consent', []],
            ['POST', '/admin/packages/1/consent', ['current_password' => 'password123']],
            ['POST', '/admin/packages/1/enable', ['current_password' => 'password123']],
            ['POST', '/admin/packages/1/disable', []],
            ['POST', '/admin/packages/1/pin', ['pinned' => '1']],
            ['POST', '/admin/packages/1/update-policy', ['policy' => 'notify']],
            ['POST', '/admin/packages/1/update', ['current_password' => 'password123']],
            ['POST', '/admin/packages/1/update/cancel', []],
            ['POST', '/admin/packages/1/rollback', ['current_password' => 'password123', 'release_id' => '1']],
            ['POST', '/admin/packages/1/uninstall', ['current_password' => 'password123']],
            ['POST', '/admin/packages/1/export', []],
            ['POST', '/admin/packages/1/reverify', []],
            ['GET', '/admin/packages/security', []],
            ['POST', '/admin/packages/security/execution', ['disabled' => '1', 'current_password' => 'password123']],
            ['POST', '/admin/packages/1/integration/settings', []],
            ['POST', '/admin/packages/1/integration/provision', ['current_password' => 'password123']],
            ['POST', '/admin/packages/1/integration/credentials/1/rotate', ['current_password' => 'password123']],
            ['POST', '/admin/packages/1/integration/credentials/1/revoke', []],
            ['POST', '/admin/packages/1/integration/disable', []],
            ['POST', '/admin/packages/1/integration/export', []],
            ['POST', '/admin/packages/1/review', ['decision' => 'approved', 'release_id' => '1', 'current_password' => 'password123']],
        ] as [$method, $path, $body]) {
            $response = $method === 'GET' ? $this->get($path) : $this->post($path, $body);
            $this->assertStatus(404, $response);
        }
    }

    public function test_package_registry_gates_publisher_console_routes(): void
    {
        $this->setFlags(['package_registry' => false]);
        $this->actingAs($this->makeAdmin());
        // Rollback: gate() -> NotFoundException (404, never 403/405) before and after requireAdmin().
        $this->assertStatus(404, $this->get('/admin/packages/publishers/1'));
        foreach ([
            ['/admin/packages/publishers/1/verify', ['current_password' => 'password123']],
            ['/admin/packages/publishers/1/suspend', ['current_password' => 'password123', 'reason' => 'x']],
            ['/admin/packages/publishers/1/reinstate', ['current_password' => 'password123']],
            ['/admin/packages/publishers/1/keys', ['current_password' => 'password123', 'key_id' => 'k', 'public_key' => 'x']],
            ['/admin/packages/publishers/1/rotate', ['current_password' => 'password123', 'envelope' => '{}']],
            ['/admin/publisher-keys/1/revoke', ['current_password' => 'password123', 'reason' => 'x']],
        ] as [$path, $body]) {
            $this->assertStatus(404, $this->post($path, $body));
        }

        // Flag on → the gate opens (a seeded publisher renders 200, proving it was the gate, not a not-found).
        $this->setFlags(['package_registry' => true]);
        $ids = \Tests\Support\Phase5\RegistryFixtures::seed($this->db, \Tests\Support\Phase5\SigningHarness::generate('pub-gate'));
        self::assertNotSame(404, $this->get('/admin/packages/publishers/' . $ids['publisher_id'])->status());
    }

    public function test_package_themes_flag_gates_public_theme_routes(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->setFlags(['package_registry' => true, 'package_themes' => false]);

        $this->assertStatus(404, $this->get('/admin/themes'));
        $this->assertStatus(404, $this->get('/theme/' . str_repeat('a', 64) . '.css'));
        $this->assertStatus(404, $this->get('/theme/preview.css'));
        $this->assertStatus(404, $this->get('/theme/asset/' . str_repeat('a', 64)));
        $this->assertStatus(200, $this->get('/admin/themes/safe-mode'));
    }

    public function test_appeals_carryover_defaults_on_and_is_operator_reversible(): void
    {
        // ADR 0007 appeals graduated to default-on (GA 2026-07-02) with
        // browser/a11y/runbook acceptance evidence. The member appeal surface and
        // the board-scoped staff queue are live out of the box, but every route
        // stays operator-reversible via the features override (the deploy-dark
        // rollback path), mirroring the account_lifecycle co-carryover below.
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertTrue($flags->enabled('appeals'), 'appeals graduated to default-on');
        self::assertArrayHasKey('appeals', $flags->all(), 'appeals must be a declared flag, not an unknown-key false');

        $member = $this->makeUser(['username' => 'appealsdefaultmember']);
        $this->actingAs($member);

        // Default-on: the member appeal surface answers without any override.
        self::assertNotSame(404, $this->get('/appeals')->status());

        // Operator rollback: disabling re-gates every appeals route to 404 (the
        // in-controller gate fires before the auth check, so the member and staff
        // faces both go dark)…
        $this->setFlags(['appeals' => false]);
        $this->assertStatus(404, $this->get('/appeals'));
        $this->assertStatus(404, $this->post('/appeals/posts/1', ['reason' => 'x']));
        $this->assertStatus(404, $this->post('/appeals/modlog/1', ['reason' => 'x']));
        $this->assertStatus(404, $this->get('/mod/appeals'));

        // …but the core forum stays up, and rolling appeals back must not enable a
        // still-dark neighbour.
        $this->assertStatus(200, $this->get('/'));
        self::assertFalse((new FeatureFlags(new SettingRepository($this->db)))->enabled('group_dms'));
    }

    public function test_account_lifecycle_carryover_defaults_on_and_is_operator_reversible(): void
    {
        // ADR 0006 account lifecycle/export/delete graduated to default-on
        // (GA 2026-07-02) with browser/a11y/runbook acceptance evidence. The
        // self-serve surface is live out of the box but stays operator-reversible
        // via the features override (the deploy-dark rollback path).
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertTrue($flags->enabled('account_lifecycle'), 'account_lifecycle graduated to default-on');
        self::assertArrayHasKey('account_lifecycle', $flags->all(), 'account_lifecycle must be a declared flag');

        $member = $this->makeUser(['username' => 'lifecycledefaultmember']);
        $this->actingAs($member);

        // Default-on: the self-serve lifecycle surface answers without any override.
        self::assertNotSame(404, $this->get('/settings/account/lifecycle')->status());

        // Operator rollback: disabling re-gates every lifecycle route to 404…
        $this->setFlags(['account_lifecycle' => false]);
        $this->assertStatus(404, $this->get('/settings/account/lifecycle'));
        $this->assertStatus(404, $this->post('/settings/account/export'));
        $this->assertStatus(404, $this->post('/settings/account/deactivate', ['current_password' => 'x']));
        $this->assertStatus(404, $this->post('/settings/account/reactivate'));
        $this->assertStatus(404, $this->post('/settings/account/delete/request', ['current_password' => 'x']));
        $this->assertStatus(404, $this->post('/settings/account/delete/cancel'));

        // …but core profile editing is NOT part of the lifecycle slice and stays
        // up, and rolling account_lifecycle back must not enable a still-dark
        // neighbour (appeals graduated 2026-07-02, so it is no longer a valid
        // dark cross-check; group_dms is).
        self::assertNotSame(404, $this->get('/settings/account')->status(), 'core profile editing must stay available');
        self::assertFalse((new FeatureFlags(new SettingRepository($this->db)))->enabled('group_dms'), 'disabling account_lifecycle must not surface a still-dark flag');
    }

    public function test_profile_media_carryover_defaults_on_and_is_operator_reversible(): void
    {
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertTrue($flags->enabled('profile_media'), 'profile_media graduated to default-on');
        self::assertArrayHasKey('profile_media', $flags->all(), 'profile_media must be a declared flag');

        $admin = $this->makeAdmin(['username' => 'profilemediaadmin']);
        $member = $this->makeUser(['username' => 'profilemediamember']);
        $this->actingAs($member);

        $settings = $this->get('/settings/account');
        $this->assertStatus(200, $settings);
        self::assertStringContainsString('action="/settings/avatar"', $settings->body());

        $this->actingAs($admin);
        $record = $this->get('/admin/users/' . (int) $member['id']);
        $this->assertStatus(200, $record);
        self::assertStringContainsString('Profile media', $record->body());

        $this->setFlags(['profile_media' => false]);

        $this->actingAs($member);
        $rolledBackSettings = $this->get('/settings/account');
        $this->assertStatus(200, $rolledBackSettings);
        self::assertStringNotContainsString('action="/settings/avatar"', $rolledBackSettings->body());
        $this->assertStatus(404, $this->post('/settings/avatar/remove'));
        self::assertNotSame(404, $this->post('/settings/account', [
            'display_name' => 'Profile Media Member',
            'signature' => 'still editable',
        ])->status(), 'core profile editing must stay available when profile_media is rolled back');

        $this->actingAs($admin);
        $rolledBackRecord = $this->get('/admin/users/' . (int) $member['id']);
        $this->assertStatus(200, $rolledBackRecord);
        self::assertStringNotContainsString('Profile media', $rolledBackRecord->body());
        $this->assertStatus(404, $this->post('/admin/users/' . (int) $member['id'] . '/signature/remove'));
        $this->assertStatus(404, $this->post('/admin/users/' . (int) $member['id'] . '/avatar/remove'));

        self::assertFalse((new FeatureFlags(new SettingRepository($this->db)))->enabled('group_dms'), 'disabling profile_media must not surface a still-dark flag');
    }

    public function test_group_dms_flag_gates_group_creation_and_management(): void
    {
        // group_dms defaults dark; the legacy `dms` flag defaults on. Group DMs
        // must NOT ship live just because dms is on (PR #17 regression guard).
        $owner = $this->makeUser(['username' => 'gdmowner']);
        $this->makeUser(['username' => 'gdmbob']);
        $this->makeUser(['username' => 'gdmcarol']);
        // Give the owner a post so they clear the new-account DM anti-spam throttle.
        $this->makeThread($this->makeBoard($this->makeCategory()), $owner, 'Hi', 'establishing a post.');
        $this->actingAs($owner);

        // A 1:1 direct message still works while group_dms is dark.
        $direct = $this->post('/messages', ['to' => 'gdmbob', 'body' => 'hello there']);
        self::assertLessThan(400, $direct->status(), '1:1 DM must stay available while group_dms is dark');

        // A group create (extra recipient + title) is refused server-side and
        // creates no group conversation.
        $this->assertStatus(422, $this->post('/messages', ['to' => 'gdmbob, gdmcarol', 'title' => 'Room', 'body' => 'hi all']));
        self::assertSame(
            0,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM conversations WHERE kind = 'group'"),
            'no group conversation may be created while group_dms is dark',
        );

        // Group-management routes 404 (the flag gate fires before any lookup).
        $this->assertStatus(404, $this->post('/messages/1/members', ['username' => 'gdmcarol']));
        $this->assertStatus(404, $this->post('/messages/1/members/remove', ['user_id' => 1]));
        $this->assertStatus(404, $this->post('/messages/1/rename', ['title' => 'x']));
        $this->assertStatus(404, $this->post('/messages/1/transfer', ['user_id' => 1]));

        // Enabling the flag lets a group be created.
        $this->setFlags(['group_dms' => true]);
        $ok = $this->post('/messages', ['to' => 'gdmbob, gdmcarol', 'title' => 'Room', 'body' => 'hi again']);
        self::assertLessThan(400, $ok->status(), 'group creation should succeed once group_dms is on');
        self::assertSame(
            1,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM conversations WHERE kind = 'group'"),
            'enabling group_dms permits group creation',
        );
    }

    public function test_content_references_are_available_by_default_and_can_be_disabled(): void
    {
        // content_references graduated to default-on (GA 2026-07-02): persisted
        // internal links render as read-gated cards without any override, and an
        // operator can roll the card rendering back via the features setting.
        $author = $this->makeUser(['username' => 'ref_default_author']);
        $member = $this->makeUser(['username' => 'ref_default_member']);
        $category = $this->makeCategory('Reference Default');
        $publicBoard = $this->makeBoard($category, ['slug' => 'ref-public-board', 'name' => 'Public refs']);
        $privateBoard = $this->makeBoard($category, ['slug' => 'ref-private-board', 'name' => 'Private refs', 'visibility' => 'private']);
        (new BoardMemberRepository($this->db))->add((int) $privateBoard['id'], (int) $member['id'], null);

        $publicTarget = $this->makeThread($publicBoard, $author, 'Public Reference Target', 'public body');
        $privateTarget = $this->makeThread($privateBoard, $member, 'Private Reference Target', 'private body');

        $this->actingAs($author);
        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $publicBoard['id'],
            'title' => 'Source with references',
            'body' => 'See [public](/t/' . $publicTarget['thread_id'] . '-' . $publicTarget['slug'] . ') and [private](/t/' . $privateTarget['thread_id'] . '-' . $privateTarget['slug'] . ').',
        ]));
        $source = $this->db->fetch("SELECT id, slug FROM threads WHERE title = 'Source with references' ORDER BY id DESC LIMIT 1");
        self::assertIsArray($source);
        $sourcePath = '/t/' . (int) $source['id'] . '-' . (string) $source['slug'];

        // Default-on: a guest sees the public target card and no private leakage.
        $this->logoutClient();
        $guestPage = $this->get($sourcePath);
        $this->assertStatus(200, $guestPage);
        self::assertStringContainsString('Public Reference Target', $guestPage->body());
        self::assertStringNotContainsString('Private Reference Target', $guestPage->body());

        // Operator rollback: disabling the flag suppresses all reference cards.
        $this->setFlags(['content_references' => false]);
        $rolledBack = $this->get($sourcePath);
        $this->assertStatus(200, $rolledBack);
        self::assertStringNotContainsString('Public Reference Target', $rolledBack->body());
        self::assertStringNotContainsString('Private Reference Target', $rolledBack->body());

        // Core thread rendering survives the rollback.
        self::assertStringContainsString('Source with references', $rolledBack->body());
    }

    public function test_tags_flag_gates_public_and_admin_tag_routes(): void
    {
        $admin = $this->makeAdmin(['username' => 'flagtagsadmin']);
        $this->setFlags(['tags' => false]);

        $this->assertStatus(404, $this->get('/tags'));
        $this->assertStatus(404, $this->get('/tags/anything'));

        $this->actingAs($admin);
        $this->assertStatus(404, $this->get('/admin/tags'));
        $this->assertStatus(404, $this->post('/admin/tags', ['name' => 'Hidden']));
        $this->assertStatus(404, $this->post('/admin/tags/1', ['name' => 'Hidden', 'slug' => 'hidden']));
        $this->assertStatus(404, $this->post('/admin/tags/1/merge', ['target_id' => 2]));
    }

    public function test_tags_are_available_by_default_and_can_be_disabled(): void
    {
        $admin = $this->makeAdmin(['username' => 'tagsdefaultadmin']);

        $this->assertStatus(200, $this->get('/tags'));

        $this->actingAs($admin);
        $this->assertStatus(200, $this->get('/admin/tags'));
        $this->assertRedirect($this->post('/admin/tags', ['name' => 'Default Tags']));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM tags WHERE slug = ?', ['default-tags']));

        $this->setFlags(['tags' => false]);
        $this->assertStatus(404, $this->get('/tags'));
        $this->assertStatus(404, $this->get('/admin/tags'));
        $this->assertStatus(404, $this->post('/admin/tags', ['name' => 'Hidden']));
    }

    public function test_expanded_feeds_are_available_by_default_and_can_be_disabled(): void
    {
        $viewer = $this->makeUser(['username' => 'expandedviewer']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'expanded-feed-board']);
        $tagId = (new TagRepository($this->db))->create('expanded-feed-tag', 'Expanded Feed Tag', null, (int) $viewer['id']);
        self::assertGreaterThan(0, $tagId);

        $this->actingAs($viewer);

        $latest = $this->get('/feed', ['view' => 'latest']);
        $this->assertStatus(200, $latest);
        self::assertStringContainsString('href="/feed?view=latest"', $latest->body());
        $this->assertRedirect($this->post('/b/' . (int) $board['id'] . '/follow'));
        $this->assertRedirect($this->post('/tags/expanded-feed-tag/follow'));

        $this->setFlags(['tags' => true, 'expanded_feeds' => false]);
        $following = $this->get('/feed', ['view' => 'latest']);
        $this->assertStatus(200, $following);
        $this->assertDontSeeText($following, 'Recent visible community activity.');
        $this->assertStatus(404, $this->post('/b/' . (int) $board['id'] . '/follow'));
        $this->assertStatus(404, $this->post('/tags/expanded-feed-tag/follow'));
    }

    public function test_reputation_ledger_is_available_by_default_and_can_be_disabled(): void
    {
        $windowed = $this->get('/leaderboard', ['window' => 'week']);
        $this->assertStatus(200, $windowed);
        $this->assertSeeText($windowed, 'Week');
        $this->assertSeeText($windowed, 'Month');

        $this->setFlags(['reputation_ledger' => false]);
        $allTimeOnly = $this->get('/leaderboard', ['window' => 'week']);
        $this->assertStatus(200, $allTimeOnly);
        $this->assertDontSeeText($allTimeOnly, 'Week');
        $this->assertDontSeeText($allTimeOnly, 'Month');
    }

    public function test_disabling_a_flag_takes_its_get_routes_offline_but_keeps_core_up(): void
    {
        $cases = [
            'notifications' => '/notifications',
            'search' => '/search',
            'dms' => '/messages',
            'community' => '/feed',
            'presence' => '/presence',
            'drafts' => '/drafts',
            'moderation_queue' => '/mod/reports',
            // /settings/connections is gated by the flag itself (the /auth/*
            // routes additionally require a configured provider, absent in tests).
            'oauth' => '/settings/connections',
        ];

        foreach ($cases as $flag => $path) {
            // Default (flag on): the route is NOT a 404 (200, redirect, or 405 —
            // anything but "feature absent").
            $on = $this->get($path);
            self::assertNotSame(404, $on->status(), "$path should be reachable while '$flag' is on");

            // Flag off: the route 404s, and the home page still works.
            $this->setFlags([$flag => false]);
            self::assertStatus(404, $this->get($path));
            $this->assertStatus(200, $this->get('/'));

            // Re-enable for the next case.
            $this->setFlags([$flag => true]);
        }
    }

    public function test_community_flag_gates_all_community_routes(): void
    {
        $this->setFlags(['community' => false]);
        $this->assertStatus(404, $this->get('/feed'));
        $this->assertStatus(404, $this->get('/leaderboard'));

        // A follow POST is also gated (404 before any write).
        $target = $this->makeUser(['username' => 'flagtarget']);
        $actor = $this->makeUser(['username' => 'flagactor']);
        $this->actingAs($actor);
        $this->assertStatus(404, $this->post('/u/flagtarget/follow'));
    }

    public function test_engagement_flag_gates_reaction_and_star_writes(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $user = $this->makeUser(['username' => 'flaguser']);
        $t = $this->makeThread($board, $user, 'Flagged');

        $this->actingAs($user);
        $this->setFlags(['engagement' => false]);
        $this->assertStatus(404, $this->post('/t/' . $t['thread_id'] . '/star'));
    }

    public function test_oauth_off_hides_connections(): void
    {
        // /settings/connections is gated purely by the oauth flag. (The /auth/*
        // provider routes 404 in tests regardless of the flag because no provider
        // is configured, so they can't prove flag gating here.)
        $user = $this->makeUser(['username' => 'flagoauth']);
        $this->actingAs($user);

        // Reachable (redirect to login or 200) while oauth is on…
        self::assertNotSame(404, $this->get('/settings/connections')->status());
        // …and 404 once the flag is off.
        $this->setFlags(['oauth' => false]);
        $this->assertStatus(404, $this->get('/settings/connections'));
    }

    public function test_announcements_flag_and_rate_limit_are_declared(): void
    {
        // The announcements subsystem is a Phase-2 surface: declared + default ON.
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('announcements', $flags->all(), 'announcements must be a declared flag, not an unknown-key false');
        self::assertTrue($flags->enabled('announcements'), 'announcements defaults on (Phase-2 convention)');

        // The broadcast cap needs a real policy (RateLimitService no-ops on unknown names).
        $limits = (array) $this->config->get('rate_limits', []);
        self::assertArrayHasKey('announce', $limits);
        self::assertCount(2, (array) $limits['announce']);
    }

    public function test_announcements_flag_takes_admin_routes_dark(): void
    {
        $admin = $this->makeAdmin(['username' => 'annflagroutes']);
        $this->actingAs($admin);

        // Reachable while the flag is on (default).
        self::assertNotSame(404, $this->get('/admin/announcements')->status());

        // 404 once the flag is off — the GET form and the POST both go dark.
        $this->setFlags(['announcements' => false]);
        $this->assertStatus(404, $this->get('/admin/announcements'));
        $this->assertStatus(404, $this->post('/admin/announcements', ['message' => 'Hidden']));

        // The home page still serves while the flag is off.
        $this->assertStatus(200, $this->get('/'));
    }

    public function test_email_flag_gates_admin_email_routes(): void
    {
        // The email-ops dashboard is gated by the `email` flag (declared, default ON).
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('email', $flags->all(), 'email must be a declared flag, not an unknown-key false');

        $this->actingAs($this->makeAdmin(['username' => 'flagemailadmin']));

        // Flag on (default): the dashboard is reachable.
        self::assertNotSame(404, $this->get('/admin/email')->status());

        // Flag off: every route 404s (the gate fires right after requireAdmin).
        $this->setFlags(['email' => false]);
        $this->assertStatus(404, $this->get('/admin/email'));
        $this->assertStatus(404, $this->get('/admin/email/export'));
        $this->assertStatus(404, $this->post('/admin/email/test', []));
        $this->assertStatus(404, $this->post('/admin/email/suppressions', ['email' => 'x@example.test']));
        $this->assertStatus(404, $this->post('/admin/email/suppressions/remove', ['email' => 'x@example.test']));

        $dashboard = $this->get('/admin');
        $this->assertStatus(200, $dashboard);
        self::assertStringNotContainsString('href="/admin/email"', $dashboard->body());
        self::assertStringContainsString('aria-disabled="true"', $dashboard->body());
    }

    public function test_core_forum_survives_with_every_feature_flag_disabled(): void
    {
        // Foundation F10: the emergency posture "all flags off" must leave the
        // Phase 1 core forum operable: anonymous reading, authenticated posting,
        // and the admin dashboard. If this fails, fix the offending flag guard.
        $allOff = array_map(
            static fn () => false,
            (new FeatureFlags(new SettingRepository($this->db)))->all(),
        );
        $this->setFlags($allOff);

        $author = $this->makeUser(['username' => 'allflagsoff']);
        $board = $this->makeBoard($this->makeCategory('Dark Ops'));
        $thread = $this->makeThread($board, $author, 'Core survives dark', 'Opening post.');
        $threadPath = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];

        $this->assertStatus(200, $this->get('/'));
        $this->assertStatus(200, $this->get($threadPath));

        $this->actingAs($author);
        $this->assertRedirectContains(
            $this->post('/t/' . $thread['thread_id'] . '/reply', [
                'body' => 'Still posting with every flag dark.',
                'idempotency_key' => 'allflagsoff-' . bin2hex(random_bytes(6)),
            ]),
            '/t/' . $thread['thread_id'],
        );

        $admin = $this->makeAdmin(['username' => 'darkadmin']);
        $this->actingAs($admin);
        $this->assertStatus(200, $this->get('/admin'));
    }

    public function test_custom_profile_fields_is_available_by_default_and_can_be_disabled(): void
    {
        // custom_profile_fields graduated to default-on (GA 2026-07-03): the
        // bounded "Custom profile fields" panel renders on /settings/account with
        // no features override, and an operator can still roll it back via the
        // features setting. Render-gated (no route), so this asserts the panel
        // markers rather than a route 404 (the wysiwyg_composer variant).
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('custom_profile_fields', $flags->all());
        self::assertTrue($flags->enabled('custom_profile_fields'), 'custom_profile_fields graduated to default-on');

        // Isolation: graduating this flag must not enable a dark neighbour.
        self::assertFalse($flags->enabled('group_dms'));

        $member = $this->makeUser(['username' => 'cpf_default_member']);
        $this->actingAs($member);

        // Available by default: the settings panel renders its bounded field rows.
        $settings = $this->get('/settings/account');
        $this->assertStatus(200, $settings);
        self::assertStringContainsString('Custom profile fields', $settings->body());
        self::assertStringContainsString('name="custom_label_1"', $settings->body());

        // Operator rollback: disabling hides the panel; core profile editing stays.
        $this->setFlags(['custom_profile_fields' => false]);
        $rolledBack = $this->get('/settings/account');
        $this->assertStatus(200, $rolledBack);
        self::assertStringNotContainsString('name="custom_label_1"', $rolledBack->body());
        self::assertStringContainsString('name="signature"', $rolledBack->body());
    }

    public function test_split_merge_is_available_by_default_and_can_be_disabled(): void
    {
        // split_merge graduated to default-on (GA 2026-07-03): the moderator
        // split/merge routes are live for an in-scope moderator with no features
        // override, and an operator can still take the surface offline (404).
        $author = $this->makeUser(['username' => 'sm_default_author']);
        $board = $this->makeBoard($this->makeCategory('Split Merge Default'), ['slug' => 'sm-default']);
        $thread = $this->makeThread($board, $author, 'Split merge default', 'Opening post');
        $this->actingAs($this->makeAdmin(['username' => 'sm_default_admin']));

        // Available by default: the split route is live. An empty selection fails
        // validation and redirects back to the thread (proving it is not 404-dark).
        $this->assertRedirectContains(
            $this->post('/mod/t/' . $thread['thread_id'] . '/split', ['title' => 'Attempted split']),
            '/t/' . $thread['thread_id'],
        );
        self::assertTrue((new FeatureFlags(new SettingRepository($this->db)))->enabled('split_merge'));

        // Isolation: graduating split_merge must not enable a dark neighbour.
        self::assertFalse((new FeatureFlags(new SettingRepository($this->db)))->enabled('group_dms'));

        // Operator rollback: disabling the flag takes both routes offline (404).
        $this->setFlags(['split_merge' => false]);
        $this->assertStatus(404, $this->post('/mod/t/' . $thread['thread_id'] . '/split', ['title' => 'x']));
        $this->assertStatus(404, $this->post('/mod/t/' . $thread['thread_id'] . '/merge', ['target_thread_id' => 1]));
    }
}
