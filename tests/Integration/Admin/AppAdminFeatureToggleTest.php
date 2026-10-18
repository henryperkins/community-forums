<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\WriteGate;
use App\Service\FeatureFlagAdminService;
use Tests\Support\TestCase;

/**
 * The guarded /admin/features toggles (ADR 0049): one inline confirm per row
 * that names the consequence, an explicit target state, the runbook's
 * read-modify-write of settings.features, an audit entry in the same
 * transaction, and the refusals the inventory renders as locked.
 */
final class AppAdminFeatureToggleTest extends TestCase
{
    private const BLOCKED = ['custom_css', 'expanded_files', 'server_extensions', 'governance', 'service_principals', 'verified_links'];

    public function test_each_row_offers_one_closed_confirm_naming_its_consequence(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'toggle-render-admin']));

        $body = $this->get('/admin/features')->body();

        self::assertStringNotContainsString('intentionally no toggles', $body);
        self::assertStringContainsString('you confirm each change', $body);
        self::assertStringContainsString('<a href="/admin/audit?action=feature_flag.">audit log</a>', $body);
        // Every declared flag still parses as an inventory row, now anchored.
        preg_match_all('/<tr id="flag-([a-z0-9_]+)">\s*<td class="features-flag-cell">\s*<code>\1<\/code>/', $body, $rows);
        self::assertCount(57, $rows[1]);
        // A pristine install: every shipped flag is on and offers Turn off; the
        // six that have not shipped are off and locked.
        self::assertSame(51, substr_count($body, '<details class="features-flag-toggle" name="features-flag-confirm">'));
        self::assertSame(51, preg_match_all('~<form method="post" action="/admin/features/[a-z0-9_]+" class="inline-form">\s*<input type="hidden" name="_token" value="[0-9a-f]{64}">~', $body));
        self::assertSame(6, substr_count($body, 'features-flag-locked'));
        self::assertStringNotContainsString('<details class="features-flag-toggle" name="features-flag-confirm" open', $body);

        $uploads = $this->rowHtml($body, 'uploads');
        self::assertStringContainsString('Turn off<span class="sr-only"> uploads</span><span aria-hidden="true">…</span>', $uploads);
        self::assertStringContainsString('Cancel<span class="sr-only"> turning off uploads</span>', $uploads);
        self::assertStringContainsString('<strong>Image uploads.</strong> New image uploads stop', $uploads);
        self::assertStringContainsString('id="flag-uploads-warning"', $uploads);
        self::assertStringContainsString('Saves <code>features.uploads=false</code> and records an audit entry.', $uploads);
        self::assertStringContainsString('action="/admin/features/uploads"', $uploads);
        self::assertStringContainsString('<input type="hidden" name="enabled" value="0">', $uploads);
        self::assertStringContainsString('<input type="hidden" name="return" value="/admin/features">', $uploads);
        self::assertStringContainsString('class="btn btn-small features-flag-commit danger" type="submit" aria-describedby="flag-uploads-warning flag-uploads-write">Turn off<span class="sr-only"> uploads</span> now</button>', $uploads);

        $css = $this->rowHtml($body, 'custom_css');
        self::assertStringNotContainsString('<details', $css);
        self::assertStringNotContainsString('<form', $css);
        self::assertStringContainsString('<span class="features-toggle-btn features-flag-locked" aria-disabled="true">Turn on<span class="sr-only"> custom_css</span></span>', $css);
        self::assertStringContainsString('Cannot be turned on here. Safety-blocked:', $css);
    }

    public function test_a_rolled_back_flag_offers_to_remove_its_override(): void
    {
        $this->setFeatures(['wysiwyg_composer' => false]);
        $this->actingAs($this->makeAdmin(['username' => 'toggle-restore-admin']));

        $row = $this->rowHtml($this->get('/admin/features')->body(), 'wysiwyg_composer');

        self::assertStringContainsString('Turn on<span class="sr-only"> wysiwyg_composer</span><span aria-hidden="true">…</span>', $row);
        self::assertStringContainsString('<strong>Visual editor.</strong> Composers open in a visual editor', $row);
        self::assertStringContainsString('Removes the <code>features.wysiwyg_composer</code> override so the code default applies, and records an audit entry.', $row);
        self::assertStringContainsString('<input type="hidden" name="enabled" value="1">', $row);
        self::assertStringContainsString('class="btn btn-small features-flag-commit" type="submit"', $row);
    }

    public function test_turning_off_a_hand_enabled_blocked_flag_says_it_is_one_way(): void
    {
        $this->setFeatures(['server_extensions' => true]);
        $this->actingAs($this->makeAdmin(['username' => 'toggle-oneway-admin']));

        $row = $this->rowHtml($this->get('/admin/features')->body(), 'server_extensions');

        self::assertStringContainsString('Turn off<span class="sr-only"> server_extensions</span><span aria-hidden="true">…</span>', $row);
        self::assertStringContainsString('Removes the <code>features.server_extensions</code> override so the code default applies', $row);
        self::assertStringContainsString('id="flag-server_extensions-one-way">This console cannot turn it back on. Reserved (ADR 0018):', $row);
        self::assertStringContainsString('aria-describedby="flag-server_extensions-write flag-server_extensions-one-way"', $row);
    }

    public function test_turning_off_a_default_on_flag_stores_false_and_audits_it(): void
    {
        $admin = $this->makeAdmin(['username' => 'toggle-off-admin']);
        $this->actingAs($admin);

        $response = $this->post('/admin/features/uploads', ['enabled' => '0', 'return' => '/admin/features']);

        $this->assertRedirect($response, '/admin/features#flag-uploads');
        self::assertSame(['uploads' => false], $this->storedFeatures());
        $entry = $this->lastAudit();
        self::assertSame('feature_flag.disable', $entry['action']);
        self::assertSame('setting', $entry['target_type']);
        self::assertSame(0, (int) $entry['target_id']);
        self::assertSame((int) $admin['id'], (int) $entry['actor_id']);
        self::assertSame('features.uploads', $entry['reason']);
        self::assertSame(['flag' => 'uploads', 'override' => null, 'effective' => true], json_decode((string) $entry['before_json'], true));
        self::assertSame(['flag' => 'uploads', 'override' => false, 'effective' => false], json_decode((string) $entry['after_json'], true));

        $page = $this->get('/admin/features')->body();
        self::assertStringContainsString('uploads turned off. Saved features.uploads=false. The change is recorded in the audit log.', $page);
        $row = $this->rowHtml($page, 'uploads');
        self::assertStringContainsString('Effective off', $row);
        self::assertStringContainsString('Override off', $row);
        // The row now offers the reverse, and says it just changed — once.
        self::assertStringContainsString('Turn on<span class="sr-only"> uploads</span><span aria-hidden="true">…</span>', $row);
        self::assertStringContainsString('<p class="features-flag-outcome">Turned off just now. <a href="/admin/audit?action=feature_flag.">See the audit log</a></p>', $row);
        self::assertStringNotContainsString('features-flag-outcome', $this->get('/admin/features')->body());
    }

    public function test_turning_a_rolled_back_flag_on_removes_only_its_override(): void
    {
        // Hand-written shapes and undeclared keys belong to the operator; the
        // console rewrites the one key it was asked about and nothing else.
        $this->setFeatures([
            'uploads' => false,
            'passkeys' => 'false',
            'unknown_flag' => ['nested' => [1, 2]],
        ]);
        $this->actingAs($this->makeAdmin(['username' => 'toggle-on-admin']));

        $response = $this->post('/admin/features/uploads', ['enabled' => '1']);

        $this->assertRedirect($response, '/admin/features#flag-uploads');
        self::assertSame(['passkeys' => 'false', 'unknown_flag' => ['nested' => [1, 2]]], $this->storedFeatures());
        $entry = $this->lastAudit();
        self::assertSame('feature_flag.enable', $entry['action']);
        self::assertSame(['flag' => 'uploads', 'override' => false, 'effective' => false], json_decode((string) $entry['before_json'], true));
        self::assertSame(['flag' => 'uploads', 'override' => null, 'effective' => true], json_decode((string) $entry['after_json'], true));
        $this->assertSeeText(
            $this->get('/admin/features'),
            'uploads turned on. Removed the features.uploads override, so the code default applies. The change is recorded in the audit log.',
        );
    }

    public function test_the_last_override_leaves_an_empty_json_object(): void
    {
        $this->setFeatures(['email' => false]);
        $this->actingAs($this->makeAdmin(['username' => 'toggle-empty-admin']));

        $this->post('/admin/features/email', ['enabled' => '1']);

        self::assertSame('{}', (string) $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'"));
    }

    public function test_a_missing_features_row_is_created_by_the_first_write(): void
    {
        $this->db->run("DELETE FROM settings WHERE `key` = 'features'");
        $this->actingAs($this->makeAdmin(['username' => 'toggle-first-admin']));

        $this->post('/admin/features/search', ['enabled' => '0']);

        self::assertSame(['search' => false], $this->storedFeatures());
    }

    public function test_a_repeated_submit_changes_nothing_and_names_the_earlier_change(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'toggle-twice-admin']));

        $this->post('/admin/features/uploads', ['enabled' => '0']);
        $second = $this->post('/admin/features/uploads', ['enabled' => '0']);

        $this->assertRedirect($second, '/admin/features#flag-uploads');
        self::assertSame(1, $this->auditCount('uploads'));
        self::assertSame(['uploads' => false], $this->storedFeatures());
        $page = $this->get('/admin/features')->body();
        self::assertMatchesRegularExpression('/uploads is already off\. It was turned off by you on [A-Z][a-z]{2} \d{1,2}, \d{4} at \d{2}:\d{2} UTC\. Nothing changed\./', $page);
        self::assertStringContainsString('<p class="features-flag-outcome">Already off. Nothing changed.</p>', $this->rowHtml($page, 'uploads'));

        // A second admin's stale page names who got there first.
        $this->actingAs($this->makeAdmin(['username' => 'toggle-late-admin']));
        $this->post('/admin/features/uploads', ['enabled' => '0']);
        $this->assertSeeText($this->get('/admin/features'), 'uploads is already off. It was turned off by @toggle-twice-admin on ');
        self::assertSame(1, $this->auditCount('uploads'));
    }

    public function test_the_write_takes_effect_on_the_next_request(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'toggle-effect-admin']));
        $this->assertStatus(200, $this->get('/search'));

        $this->post('/admin/features/search', ['enabled' => '0']);
        $this->assertStatus(404, $this->get('/search'));

        $this->post('/admin/features/search', ['enabled' => '1']);
        $this->assertStatus(200, $this->get('/search'));
        self::assertSame([], $this->storedFeatures());
        self::assertSame(2, $this->auditCount('search'));
    }

    public function test_blocked_flags_can_be_turned_off_but_never_on(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'toggle-blocked-admin']));

        foreach (self::BLOCKED as $flag) {
            $refused = $this->post('/admin/features/' . $flag, ['enabled' => '1']);
            $this->assertStatus(422, $refused);
            self::assertStringContainsString(
                '<div class="flash flash-error features-toggle-error" role="alert" tabindex="-1" autofocus>' . $flag . ' was not changed. The console cannot turn on ' . $flag . '.',
                $refused->body(),
            );
            self::assertSame(1, substr_count($refused->body(), ' autofocus'), $flag);
            self::assertSame(0, $this->auditCount($flag), $flag);
        }
        self::assertSame([], $this->storedFeatures());

        // An operator who enabled one by hand can still roll it back here.
        $this->setFeatures(['server_extensions' => true, 'email' => false]);
        $this->assertRedirect($this->post('/admin/features/server_extensions', ['enabled' => '0']), '/admin/features#flag-server_extensions');
        self::assertSame(['email' => false], $this->storedFeatures());
        self::assertSame('feature_flag.disable', $this->lastAudit()['action']);
    }

    public function test_the_blocked_list_is_the_inventorys_unshipped_readiness_rows(): void
    {
        // Two maps hold one policy: FeatureFlags refuses to enable exactly the
        // rows the inventory classifies as not shipped. Pin them equal.
        $this->actingAs($this->makeAdmin(['username' => 'toggle-policy-admin']));
        $body = $this->get('/admin/features')->body();

        $unshipped = [];
        foreach (array_keys(FeatureFlags::defaults()) as $flag) {
            if (preg_match('/<span class="state [^"]*">(Missing user UI|Safety-blocked|Reserved \(ADR 0018\))<\/span>/', $this->rowHtml($body, $flag)) === 1) {
                $unshipped[] = $flag;
            }
        }
        $blocked = array_values(array_filter(
            array_keys(FeatureFlags::defaults()),
            static fn (string $flag): bool => FeatureFlags::consoleEnableBlocker($flag) !== null,
        ));
        sort($unshipped);
        sort($blocked);
        self::assertSame($blocked, $unshipped);
        $expected = self::BLOCKED;
        sort($expected);
        self::assertSame($expected, $blocked);
    }

    public function test_a_corrupt_features_blob_locks_every_row_and_refuses_writes(): void
    {
        (new SettingRepository($this->db))->set('features', 'not-an-object');
        $this->actingAs($this->makeAdmin(['username' => 'toggle-corrupt-admin']));

        $page = $this->get('/admin/features')->body();
        self::assertStringContainsString('Flag changes are locked here until then.', $page);
        self::assertStringNotContainsString('<details class="features-flag-toggle"', $page);
        self::assertStringNotContainsString('action="/admin/features/', $page);
        self::assertSame(57, substr_count($page, 'Locked until settings.features is repaired.'));

        $response = $this->post('/admin/features/uploads', ['enabled' => '0']);

        $this->assertStatus(422, $response);
        self::assertStringContainsString('uploads was not changed. settings.features is not a JSON object, so no flag can change here until it is repaired', $response->body());
        self::assertSame('"not-an-object"', (string) $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'"));
        self::assertSame(0, $this->auditCount('uploads'));

        // A JSON list or JSON null is not an override map either: the loader
        // ignores it whole, so the console locks and refuses rather than
        // overwrite what the operator stored.
        foreach (['[false, true]', 'null'] as $raw) {
            $this->db->run(
                "INSERT INTO settings (`key`, `value`, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                [$raw],
            );
            $corruptGet = $this->get('/admin/features')->body();
            self::assertStringContainsString('Flag changes are locked here until then.', $corruptGet, $raw);
            self::assertStringNotContainsString('action="/admin/features/', $corruptGet, $raw);

            $corruptPost = $this->post('/admin/features/uploads', ['enabled' => '0']);
            $this->assertStatus(422, $corruptPost);
            self::assertSame($raw, (string) $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'"), $raw);
        }
    }

    public function test_a_number_php_cannot_write_back_keeps_its_overrides_applied_and_refuses_only_the_write(): void
    {
        // A number outside the float range decodes to INF, which json_encode
        // refuses. The loader still applies the rest of that map, so a rollback
        // in it must not silently come back on and the console must not claim
        // the overrides are ignored. The write is what cannot happen: it has to
        // refuse with a 422, never a 500, and leave the stored text alone.
        try {
            $this->db->run(
                "INSERT INTO settings (`key`, `value`, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                ['{"community": false, "legacy_counter": 1e999}'],
            );
        } catch (\PDOException $e) {
            self::markTestSkipped('This database server rejects out-of-range JSON numbers: ' . $e->getMessage());
        }
        $stored = (string) $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'");
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($flags->enabled('community'), 'the rollback beside the unwritable number keeps applying');
        self::assertFalse($flags->overridesCorrupt());
        $this->actingAs($this->makeAdmin(['username' => 'toggle-inf-admin']));

        $page = $this->get('/admin/features')->body();
        self::assertStringNotContainsString('features-corrupt-alert', $page);
        self::assertStringContainsString('Override off', $this->rowHtml($page, 'community'));
        self::assertStringContainsString('action="/admin/features/uploads"', $page);

        $refused = $this->post('/admin/features/uploads', ['enabled' => '0']);

        $this->assertStatus(422, $refused);
        self::assertStringContainsString('uploads was not changed. settings.features holds a number outside the range PHP can write back', $refused->body());
        self::assertSame($stored, (string) $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'"));
        self::assertSame(0, $this->auditCount('uploads'));

        // A request that would change nothing has no write to refuse.
        $this->assertRedirect($this->post('/admin/features/community', ['enabled' => '0']), '/admin/features#flag-community');
        self::assertSame($stored, (string) $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'"));
        self::assertSame(0, $this->auditCount('community'));
    }

    public function test_malformed_target_state_changes_nothing(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'toggle-malformed-admin']));

        foreach ([[], ['enabled' => 'off'], ['enabled' => ['0']], ['enabled' => '']] as $body) {
            $response = $this->post('/admin/features/uploads', $body);
            $this->assertStatus(422, $response);
            self::assertStringContainsString('uploads was not changed: the request did not say whether to turn it on or off.', $response->body());
        }

        self::assertSame(0, $this->auditCount('uploads'));
        self::assertNull($this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'") ?: null);
    }

    public function test_only_an_active_admin_with_a_token_can_write_a_declared_flag(): void
    {
        $this->makeAdmin();
        $this->actingAs($this->makeUser(['username' => 'toggle-member']));
        $this->assertStatus(403, $this->post('/admin/features/uploads', ['enabled' => '0']));
        $this->assertStatus(403, $this->post('/admin/features/uploads', []));

        $this->actingAs($this->makeUser(['username' => 'toggle-moderator', 'role' => 'moderator']));
        $this->assertStatus(403, $this->post('/admin/features/uploads', ['enabled' => '0']));

        // State beats role: a suspended admin can read the inventory, sees every
        // toggle locked with the reason, and cannot write.
        $this->actingAs($this->makeAdmin([
            'username' => 'toggle-suspended-admin',
            'status' => 'suspended',
            'suspended_until' => gmdate('Y-m-d H:i:s', time() + 3600),
        ]));
        $page = $this->get('/admin/features');
        $this->assertStatus(200, $page);
        self::assertStringNotContainsString('<details class="features-flag-toggle"', $page->body());
        self::assertSame(57, substr_count($page->body(), 'Locked: your account cannot make changes right now.'));
        $this->assertStatus(403, $this->post('/admin/features/uploads', ['enabled' => '0']));

        $this->actingAs($this->makeAdmin(['username' => 'toggle-token-admin']));
        $this->assertStatus(403, $this->post('/admin/features/uploads', ['enabled' => '0'], false));
        $this->assertStatus(404, $this->post('/admin/features/typo', ['enabled' => '0']));
        $this->assertStatus(404, $this->post('/admin/features/typo', []));
        $this->assertStatus(404, $this->post('/admin/features/' . rawurlencode('../uploads'), ['enabled' => '0']));
        $this->assertStatus(405, $this->get('/admin/features/uploads'));

        self::assertSame(0, $this->auditCount('uploads'));
        self::assertNull($this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'") ?: null);
    }

    public function test_return_state_keeps_the_inventory_filters_but_drops_the_inverted_state(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'toggle-return-admin']));

        // The rendered form carries the active filter query in its hidden return field.
        $filteredRow = $this->rowHtml($this->get('/admin/features', ['q' => 'upl', 'readiness' => 'clear'])->body(), 'uploads');
        self::assertStringContainsString('<input type="hidden" name="return" value="/admin/features?q=upl&amp;readiness=clear">', $filteredRow);

        // The write inverts the effective state, so a kept state filter would
        // hide the one row whose cells prove it.
        $this->assertRedirect(
            $this->post('/admin/features/uploads', [
                'enabled' => '0',
                'return' => '/admin/features?q=upl&state=on&readiness=clear&evil=1',
            ]),
            '/admin/features?q=upl&readiness=clear#flag-uploads',
        );

        foreach (['//evil.example/admin/features?q=upl', 'https://evil.example/admin/features?q=upl', '/admin/users?q=x', "/admin/features\t?q=x", '/admin/features?state=maybe'] as $return) {
            $this->assertRedirect(
                $this->post('/admin/features/uploads', ['enabled' => '1', 'return' => $return]),
                '/admin/features#flag-uploads',
            );
        }

        // Live-computed readiness rows lose their readiness badge when turned off
        // (and gain it when turned back on), so a readiness filter that would now
        // hide the changed row is dropped too.
        $this->assertRedirect(
            $this->post('/admin/features/link_previews', [
                'enabled' => '0',
                'return' => '/admin/features?readiness=attention',
            ]),
            '/admin/features#flag-link_previews',
        );
        $afterOff = $this->get('/admin/features')->body();
        self::assertStringContainsString('<tr id="flag-link_previews">', $afterOff);
        self::assertStringContainsString('Turned off just now.', $this->rowHtml($afterOff, 'link_previews'));

        $this->assertRedirect(
            $this->post('/admin/features/link_previews', [
                'enabled' => '1',
                'return' => '/admin/features?readiness=clear',
            ]),
            '/admin/features#flag-link_previews',
        );

        // A refusal re-renders the same filtered view, filters and all.
        $refused = $this->post('/admin/features/custom_css', [
            'enabled' => '1',
            'return' => '/admin/features?q=css&readiness=safety_blocked',
        ]);
        $this->assertStatus(422, $refused);
        self::assertStringContainsString('Showing 1 of 57 declared flags.', $refused->body());
        self::assertStringContainsString('value="safety_blocked" selected', $refused->body());
    }

    public function test_a_flash_that_disagrees_with_the_live_state_marks_no_row(): void
    {
        // The flash cookie is client-held: a row may only claim "just changed"
        // while its live Effective cell agrees with the claim.
        $this->actingAs($this->makeAdmin(['username' => 'toggle-forged-admin']));
        $this->cookies['rb_flash'] = 'uploads turned off. Saved features.uploads=false. The change is recorded in the audit log.';

        $body = $this->get('/admin/features')->body();

        self::assertStringContainsString('uploads turned off.', $body);
        self::assertStringNotContainsString('features-flag-outcome', $body);
    }

    public function test_a_hand_shaped_override_on_the_target_flag_is_read_like_the_inventory(): void
    {
        $this->setFeatures(['uploads' => 'false']);
        $this->actingAs($this->makeAdmin(['username' => 'toggle-shape-admin']));
        self::assertStringContainsString('<input type="hidden" name="enabled" value="1">', $this->rowHtml($this->get('/admin/features')->body(), 'uploads'));

        $this->assertRedirect($this->post('/admin/features/uploads', ['enabled' => '1']), '/admin/features#flag-uploads');

        self::assertSame([], $this->storedFeatures());
        self::assertSame(['flag' => 'uploads', 'override' => 'false', 'effective' => false], json_decode((string) $this->lastAudit()['before_json'], true));
    }

    public function test_attribution_names_the_newest_matching_change_of_the_named_flag(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'toggle-first-admin']));
        $this->post('/admin/features/search', ['enabled' => '0']);
        $this->post('/admin/features/search', ['enabled' => '1']);
        $this->actingAs($this->makeAdmin(['username' => 'toggle-second-admin']));
        $this->post('/admin/features/search', ['enabled' => '0']);
        $this->actingAs($this->makeAdmin(['username' => 'toggle-third-admin']));
        $this->post('/admin/features/uploads', ['enabled' => '0']);
        $this->actingAs($this->makeAdmin(['username' => 'toggle-fourth-admin']));

        $this->post('/admin/features/search', ['enabled' => '0']);
        $this->assertSeeText($this->get('/admin/features'), 'search is already off. It was turned off by @toggle-second-admin on ');

        // A hand edit that changed the state or override after the last console
        // write must not be attributed to that console write.
        $this->setFeatures([]);
        $this->post('/admin/features/uploads', ['enabled' => '1']);
        $page = $this->get('/admin/features')->body();
        self::assertStringContainsString('uploads is already on. Nothing changed.', $page);
        self::assertStringNotContainsString('It was turned on', $page);

        // The same holds when a hand edit leaves the state alone but rewrites
        // the stored override: what is stored is no longer what the console
        // recorded, so the console does not claim it.
        $this->actingAs($this->makeAdmin(['username' => 'toggle-fifth-admin']));
        $this->post('/admin/features/polls', ['enabled' => '0']);
        $this->setFeatures(['polls' => 'off']);
        $this->actingAs($this->makeAdmin(['username' => 'toggle-sixth-admin']));
        $this->post('/admin/features/polls', ['enabled' => '0']);
        $page = $this->get('/admin/features')->body();
        self::assertStringContainsString('polls is already off. Nothing changed.', $page);
        self::assertStringNotContainsString('It was turned off', $page);
    }

    public function test_service_enforces_admin_and_invalidates_feature_flags_cache(): void
    {
        $settings = new SettingRepository($this->db);
        $flags = new FeatureFlags($settings);
        $service = new FeatureFlagAdminService($this->db, $settings, new ModerationLogRepository($this->db), new WriteGate(), $flags);

        $member = new User($this->makeUser(['username' => 'svc-member']));
        try {
            $service->setEnabled($member, 'search', false);
            self::fail('Non-admin must be rejected by FeatureFlagAdminService.');
        } catch (ForbiddenException) {
            self::assertTrue($flags->enabled('search'));
        }

        $admin = new User($this->makeAdmin(['username' => 'svc-admin']));
        try {
            $service->setEnabled($admin, 'not_a_flag', false);
            self::fail('Undeclared flag must throw NotFoundException.');
        } catch (NotFoundException) {
            self::assertTrue($flags->enabled('search'));
        }

        // Prime the cache before setEnabled() so only invalidate() makes the next read observe false.
        self::assertTrue($flags->enabled('search'));
        $service->setEnabled($admin, 'search', false);
        self::assertFalse($flags->enabled('search'));
    }

    /** Everything from the flag's row anchor to the end of its row. */
    private function rowHtml(string $body, string $flag): string
    {
        $start = strpos($body, '<tr id="flag-' . $flag . '">');
        self::assertNotFalse($start, "row for $flag");
        $end = strpos($body, '</tr>', $start);
        self::assertNotFalse($end);

        return substr($body, $start, $end - $start);
    }

    /** @param array<string,mixed> $flags */
    private function setFeatures(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    private function storedFeatures(): mixed
    {
        $raw = $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'");
        return is_string($raw) ? json_decode($raw, true) : null;
    }

    /** @return array<string,mixed> */
    private function lastAudit(): array
    {
        $row = $this->db->fetch("SELECT * FROM moderation_log WHERE action LIKE 'feature\\_flag.%' ORDER BY id DESC LIMIT 1");
        self::assertNotNull($row, 'a feature_flag audit entry should exist');
        return $row;
    }

    private function auditCount(string $flag): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action LIKE 'feature\\_flag.%' AND reason = ?",
            ['features.' . $flag],
        );
    }
}
