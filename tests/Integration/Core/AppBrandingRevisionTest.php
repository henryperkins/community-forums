<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\AttachmentRepository;
use App\Repository\SettingRepository;
use App\Service\AttachmentService;
use App\Worker\OrphanAttachmentCleaner;
use Tests\Support\TestCase;

/**
 * ADR 0054 increment 2: versioned branding. Every published change is a
 * restorable revision (with its logos), a stale editor gets a conflict instead
 * of silently overwriting newer work, group resets leave other groups alone,
 * and the recovery pages never take the saved overrides themselves.
 */
final class AppBrandingRevisionTest extends TestCase
{
    public function test_the_first_change_records_the_configuration_it_replaced(): void
    {
        $this->actingAs($admin = $this->makeAdmin(['username' => 'brand_owner']));
        $settings = $this->settings();
        $settings->set('site_name', 'Original Commons');
        $settings->set('brand_color_primary', '#123456');

        $this->assertRedirect($this->publish(['site_name' => 'Renamed Commons', 'color_primary' => '#123456']), '/admin/branding');

        $rows = $this->db->fetchAll('SELECT id, action, actor_id, changed_keys_json, document_json FROM branding_revisions ORDER BY id');
        self::assertCount(2, $rows);
        self::assertSame('baseline', $rows[0]['action']);
        self::assertNull($rows[0]['actor_id']);
        $baseline = json_decode((string) $rows[0]['document_json'], true);
        self::assertSame('Original Commons', $baseline['site_name']);
        self::assertSame('#123456', $baseline['brand_color_primary']);
        self::assertSame('publish', $rows[1]['action']);
        self::assertSame((int) $admin['id'], (int) $rows[1]['actor_id']);
        self::assertSame(['site_name'], json_decode((string) $rows[1]['changed_keys_json'], true));
        self::assertSame((int) $rows[1]['id'], $settings->get('brand_revision'));

        $audit = $this->lastAudit('branding_update');
        self::assertSame((int) $rows[1]['id'], $audit['revision']);
        self::assertSame(['site_name'], $audit['changed_keys']);

        // Saving without a change records no revision.
        $this->assertRedirect($this->publish(['site_name' => 'Renamed Commons', 'color_primary' => '#123456']), '/admin/branding');
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM branding_revisions'));
    }

    public function test_a_stale_form_is_refused_with_its_draft_until_the_editor_confirms_replacing_newer_work(): void
    {
        $settings = $this->settings();
        $first = $this->makeAdmin(['username' => 'first_editor']);
        $second = $this->makeAdmin(['username' => 'second_editor']);
        $this->actingAs($first);
        $this->assertRedirect($this->publish(['site_name' => 'Starting point']), '/admin/branding');
        $staleBase = (int) $settings->get('brand_revision');

        // Another administrator publishes while the first form is still open.
        $this->actingAs($second);
        $this->assertRedirect($this->publish(['site_name' => 'Starting point', 'color_accent' => '#b0611c']), '/admin/branding');
        $newer = (int) $settings->get('brand_revision');
        self::assertGreaterThan($staleBase, $newer);

        $this->actingAs($first);
        $draft = ['site_name' => 'Stale draft', 'theme_default' => 'dark', 'site_tagline' => 'Kept in the draft', 'base_revision' => (string) $staleBase];
        $conflict = $this->post('/admin/branding', $draft);
        $this->assertStatus(409, $conflict);
        $body = $conflict->body();
        self::assertStringContainsString('Branding changed while you were editing', $body);
        self::assertStringContainsString('Revision ' . $newer . '</a>', $body);
        self::assertStringContainsString('by @second_editor: Accent colour', $body);
        self::assertStringContainsString('value="Stale draft"', $body);
        self::assertStringContainsString('value="Kept in the draft"', $body);
        self::assertStringContainsString('name="base_revision" value="' . $staleBase . '"', $body);
        self::assertStringContainsString('name="overwrite_revision" value="' . $newer . '"', $body);
        self::assertSame('Starting point', $settings->getString('site_name'));
        self::assertSame('#b0611c', $settings->getString('brand_color_accent'));

        // Confirming an older revision than the live one is still a conflict.
        $this->assertStatus(409, $this->post('/admin/branding', $draft + ['overwrite_revision' => (string) $staleBase]));
        // Confirming the named newer revision publishes the draft.
        $this->assertRedirect($this->post('/admin/branding', $draft + ['overwrite_revision' => (string) $newer]), '/admin/branding');
        self::assertSame('Stale draft', $settings->getString('site_name'));
        self::assertSame('', $settings->getString('brand_color_accent'));
        // The replaced configuration remains restorable.
        $this->assertStatus(200, $this->get('/admin/branding/history/' . $newer));
    }

    public function test_a_form_from_before_versioning_cannot_overwrite_a_published_revision(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertRedirect($this->publish(['site_name' => 'Versioned']), '/admin/branding');
        // An older page has no base_revision field at all.
        $this->assertStatus(409, $this->post('/admin/branding', ['site_name' => 'Old page', 'theme_default' => 'system']));
        self::assertSame('Versioned', $this->settings()->getString('site_name'));
    }

    public function test_restore_republishes_a_revision_with_its_logo_and_can_itself_be_undone(): void
    {
        $this->actingAs($admin = $this->makeAdmin(['username' => 'restorer']));
        $settings = $this->settings();
        $logo = $this->brandAsset((int) $admin['id']);
        $settings->set('brand_logo_path', $logo);
        $this->assertRedirect($this->publish(['site_name' => 'With logo', 'color_primary' => '#2e4a3a']), '/admin/branding');
        $withLogo = (int) $settings->get('brand_revision');

        $this->assertRedirect($this->publish(['site_name' => 'Without logo', 'color_primary' => '#123456', 'remove_logo' => '1']), '/admin/branding');
        self::assertSame('', $settings->getString('brand_logo_path'));
        $withoutLogo = (int) $settings->get('brand_revision');

        $history = $this->get('/admin/branding/history');
        $this->assertStatus(200, $history);
        self::assertStringContainsString('href="/admin/branding/history/' . $withLogo . '"', $history->body());
        self::assertStringContainsString('Community name, Logo, Primary colour', $history->body());

        $review = $this->get('/admin/branding/history/' . $withLogo);
        $this->assertStatus(200, $review);
        self::assertStringContainsString('<img class="branding-revision-thumb" src="' . $logo . '"', $review->body());
        self::assertStringContainsString('Restore revision #' . $withLogo, $review->body());
        self::assertStringContainsString('fill="#2e4a3a"', $review->body());

        $this->assertRedirect($this->restore($withLogo), '/admin/branding');
        self::assertSame($logo, $settings->getString('brand_logo_path'));
        self::assertSame('With logo', $settings->getString('site_name'));
        self::assertSame('#2e4a3a', $settings->getString('brand_color_primary'));
        $restored = $this->db->fetch('SELECT * FROM branding_revisions ORDER BY id DESC LIMIT 1');
        self::assertSame('restore', $restored['action']);
        self::assertSame($withLogo, (int) $restored['restored_from_id']);
        $audit = $this->lastAudit('branding_restore');
        self::assertSame($withLogo, $audit['restored_from']);
        self::assertSame((int) $restored['id'], $audit['revision']);
        $this->assertSeeText($this->get('/admin/branding'), 'Restored revision ' . $withLogo . ' as revision ' . $restored['id']);
        // The retained image is still served publicly.
        $this->logoutClient();
        $this->assertStatus(200, $this->get($logo));
        self::assertStringContainsString('src="' . $logo . '"', $this->get('/')->body());

        // A restore is undone the same way.
        $this->actingAs($admin);
        $this->assertRedirect($this->restore($withoutLogo), '/admin/branding');
        self::assertSame('Without logo', $settings->getString('site_name'));
        self::assertSame('', $settings->getString('brand_logo_path'));
    }

    public function test_restore_names_missing_media_and_uses_the_fallback(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = $this->settings();
        $settings->set('brand_logo_dark_path', '/media/987654');
        $this->assertRedirect($this->publish(['site_name' => 'Missing media']), '/admin/branding');
        $revision = (int) $this->db->fetchValue("SELECT id FROM branding_revisions WHERE action = 'baseline'");
        $settings->set('brand_logo_dark_path', '');
        $this->assertRedirect($this->publish(['site_name' => 'Missing media 2']), '/admin/branding');

        $review = $this->get('/admin/branding/history/' . $revision);
        $this->assertSeeText($review, 'Dark theme logo');
        $this->assertSeeText($review, 'is no longer available, so restoring uses the built-in fallback for it.');
        $this->assertRedirect($this->post('/admin/branding/history/' . $revision . '/restore', ['base_revision' => (string) $settings->get('brand_revision')]), '/admin/branding');
        self::assertSame('', $settings->getString('brand_logo_dark_path'));
        self::assertSame(['brand_logo_dark_path'], $this->lastAudit('branding_restore')['missing_assets']);
    }

    public function test_restore_reviews_contrast_against_the_current_theme(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = $this->settings();
        $settings->set('brand_color_primary', '#ffffff');
        $this->assertRedirect($this->publish(['site_name' => 'Contrast', 'color_primary' => '']), '/admin/branding');
        $white = (int) $this->db->fetchValue("SELECT id FROM branding_revisions WHERE action = 'baseline'");
        $base = (string) $settings->get('brand_revision');

        $review = $this->get('/admin/branding/history/' . $white);
        $this->assertSeeText($review, 'Review colour contrast');
        self::assertSame(1, preg_match('/name="contrast_review" value="([a-f0-9]{64})"/', $review->body(), $token));

        $refused = $this->post('/admin/branding/history/' . $white . '/restore', ['base_revision' => $base]);
        $this->assertStatus(422, $refused);
        $this->assertSeeText($refused, 'acknowledge them before restoring');
        self::assertSame('', $settings->getString('brand_color_primary'));
        // A forged or stale review identity is not an acknowledgement.
        $this->assertStatus(422, $this->post('/admin/branding/history/' . $white . '/restore', [
            'base_revision' => $base, 'contrast_ack' => '1', 'contrast_review' => str_repeat('0', 64),
        ]));

        $this->assertRedirect($this->post('/admin/branding/history/' . $white . '/restore', [
            'base_revision' => $base, 'contrast_ack' => '1', 'contrast_review' => $token[1],
        ]), '/admin/branding');
        self::assertSame('#ffffff', $settings->getString('brand_color_primary'));
        self::assertSame('#ffffff', $this->lastAudit('branding_contrast_override')['color_primary']);
    }

    public function test_each_group_reset_changes_only_its_group_and_is_restorable(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = $this->settings();
        $configured = [
            'site_name' => 'Grouped', 'brand_tagline' => 'Kept tagline',
            'brand_color_primary' => '#123456', 'brand_color_accent' => '#abcdef',
            'brand_logo_path' => '/media/1', 'brand_logo_light_path' => '/media/2', 'brand_logo_dark_path' => '/media/3', 'brand_favicon_path' => '/media/4',
            'brand_theme_default' => 'dark', 'brand_theme_preset' => 'retro',
        ];
        $groups = [
            'colours' => ['brand_color_primary' => '', 'brand_color_accent' => ''],
            'assets' => ['brand_logo_path' => '', 'brand_logo_light_path' => '', 'brand_logo_dark_path' => '', 'brand_favicon_path' => ''],
            'appearance' => ['brand_theme_default' => 'system', 'brand_theme_preset' => 'classic'],
        ];
        foreach ($groups as $group => $cleared) {
            foreach ($configured as $key => $value) {
                $settings->set($key, $value);
            }
            $this->assertRedirect($this->post('/admin/branding/reset/' . $group), '/admin/branding');
            foreach ($configured as $key => $value) {
                self::assertSame($cleared[$key] ?? $value, $settings->getString($key), $group . ' ' . $key);
            }
            $audit = $this->lastAudit('branding_reset_group');
            self::assertSame($group, $audit['group']);
            self::assertSame(array_keys($cleared), $audit['changed_keys']);
            $revision = $this->db->fetch('SELECT * FROM branding_revisions ORDER BY id DESC LIMIT 1');
            self::assertSame('reset', $revision['action']);
        }
        $this->assertSeeText($this->get('/admin/branding'), 'Reset the appearance defaults. Everything else was kept.');

        // A second reset of an already-default group changes nothing.
        $this->assertRedirect($this->post('/admin/branding/reset/appearance'), '/admin/branding');
        $this->assertSeeText($this->get('/admin/branding'), 'The appearance defaults already use their defaults; nothing changed.');
        $this->assertStatus(404, $this->post('/admin/branding/reset/everything'));
    }

    public function test_full_reset_is_recorded_and_can_be_restored(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = $this->settings();
        $settings->set('brand_color_accent', '#b0611c');
        $settings->set('brand_theme_preset', 'retro');
        $this->assertRedirect($this->post('/admin/branding', ['reset' => '1', 'reset_confirm' => 'RESET']), '/admin/branding');
        self::assertSame('', $settings->getString('brand_color_accent'));
        $before = (int) $this->db->fetchValue("SELECT id FROM branding_revisions WHERE action = 'baseline'");
        $reset = $this->db->fetch("SELECT * FROM branding_revisions WHERE action = 'reset'");
        self::assertNotNull($reset);
        self::assertSame((int) $reset['id'], $this->lastAudit('branding_reset')['revision']);

        $this->assertRedirect($this->restore($before), '/admin/branding');
        self::assertSame('#b0611c', $settings->getString('brand_color_accent'));
        self::assertSame('retro', $settings->getString('brand_theme_preset'));
    }

    public function test_community_name_changes_from_settings_are_versioned_too(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'settings_editor']));
        $this->assertRedirect($this->publish(['site_name' => 'Branding name']), '/admin/branding');
        $base = (int) $this->settings()->get('brand_revision');

        $this->post('/admin/site', ['site_name' => 'Settings name']);
        self::assertSame('Settings name', $this->settings()->getString('site_name'));
        $revision = $this->db->fetch('SELECT * FROM branding_revisions ORDER BY id DESC LIMIT 1');
        self::assertSame(['site_name'], json_decode((string) $revision['changed_keys_json'], true));
        self::assertSame((int) $revision['id'], $this->settings()->get('brand_revision'));

        // A branding form opened before that rename is now stale.
        $this->assertStatus(409, $this->post('/admin/branding', ['site_name' => 'Branding name', 'base_revision' => (string) $base]));
        self::assertSame('Settings name', $this->settings()->getString('site_name'));
    }

    public function test_recovery_pages_never_take_the_saved_overrides(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = $this->settings();
        $settings->set('brand_color_primary', '#ffffff');
        $settings->set('brand_theme_preset', 'retro');
        $settings->set('brand_version', 'recovery');

        // The branding page loads only the scoped sample rules; history has no
        // sample, so it loads no brand stylesheet at all.
        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/brand\.css\?v=[^"]*&amp;scope=sample">#', $this->get('/admin/branding')->body());
        self::assertStringNotContainsString('/brand.css', $this->get('/admin/branding/history')->body());
        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/brand\.css\?v=[^"&]*">#', $this->get('/admin/audit')->body());

        $sample = $this->get('/brand.css', ['v' => 'x', 'scope' => 'sample'])->body();
        self::assertStringStartsWith(":root{}\n.brand-preview", $sample);
        self::assertStringNotContainsString('[data-theme="dark"]{', $sample);
        self::assertStringContainsString('.brand-preview[data-brand-preset="retro"]', $sample);
        self::assertStringContainsString('.theme-thumb [data-thumb-mode="light"]', $sample);
        $full = $this->get('/brand.css', ['v' => 'x'])->body();
        self::assertMatchesRegularExpression('/\A:root\{[^}]*--accent:#ffffff;/', $full);
    }

    public function test_history_is_admin_only_and_lists_newest_first(): void
    {
        $member = $this->makeUser();
        $this->actingAs($this->makeAdmin());
        self::assertStringContainsString('No branding changes yet', $this->get('/admin/branding/history')->body());
        foreach (['One', 'Two', 'Three'] as $name) {
            $this->assertRedirect($this->publish(['site_name' => $name]), '/admin/branding');
        }
        $body = $this->get('/admin/branding/history')->body();
        $ids = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM branding_revisions ORDER BY id DESC'), 'id'));
        self::assertCount(4, $ids);
        self::assertLessThan(strpos($body, 'history/' . $ids[1] . '"'), strpos($body, 'history/' . $ids[0] . '"'));
        self::assertStringContainsString('<span class="state state-active">Live</span>', $body);
        self::assertStringContainsString('Configuration before history began', $body);

        $this->actingAs($member);
        $this->assertStatus(403, $this->get('/admin/branding/history'));
        $this->assertStatus(403, $this->get('/admin/branding/history/' . $ids[0]));
        $this->assertStatus(403, $this->post('/admin/branding/history/' . $ids[1] . '/restore', ['base_revision' => (string) $ids[0]]));
        $this->assertStatus(403, $this->post('/admin/branding/reset/colours'));
        $this->logoutClient();
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/branding/history/999999'));
    }

    public function test_suspended_admins_can_review_history_but_cannot_restore_or_reset(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $this->assertRedirect($this->publish(['site_name' => 'Before suspension']), '/admin/branding');
        $revision = (int) $this->db->fetchValue('SELECT MIN(id) FROM branding_revisions');
        $this->db->run("UPDATE users SET status = 'suspended', suspended_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE id = ?", [(int) $admin['id']]);

        $this->assertStatus(200, $this->get('/admin/branding/history'));
        $this->assertStatus(200, $this->get('/admin/branding/history/' . $revision));
        self::assertNotSame(302, $this->post('/admin/branding/history/' . $revision . '/restore', ['base_revision' => (string) $this->settings()->get('brand_revision')])->status());
        self::assertNotSame(302, $this->post('/admin/branding/reset/colours')->status());
        self::assertSame('Before suspension', $this->settings()->getString('site_name'));
    }

    public function test_brand_media_kept_for_restores_survives_the_orphan_sweep(): void
    {
        $admin = $this->makeAdmin();
        $logo = $this->brandAsset((int) $admin['id']);
        $this->settings()->set('brand_logo_path', $logo);
        $this->actingAs($admin);
        $this->assertRedirect($this->publish(['site_name' => 'Retained', 'remove_logo' => '1']), '/admin/branding');

        $cleaner = new OrphanAttachmentCleaner(new AttachmentRepository($this->db), $this->attachmentService(), 0, 0);
        $cleaner->run(gmdate('Y-m-d H:i:s', time() + 86400 * 400));
        $id = (int) substr($logo, strlen('/media/'));
        self::assertSame('finalized', $this->db->fetchValue('SELECT status FROM attachments WHERE id = ?', [$id]));
        $this->logoutClient();
        $this->assertStatus(200, $this->get($logo));
    }

    /**
     * Publish through the branding form as a freshly opened page would,
     * acknowledging the exact reviewed candidate when colours draw warnings.
     */
    private function publish(array $values): \App\Core\Response
    {
        $revision = $this->settings()->get('brand_revision', 0);
        $values += [
            'theme_default' => 'system',
            'base_revision' => (string) (is_int($revision) ? $revision : 0),
        ];
        $response = $this->post('/admin/branding', $values);
        if ($response->status() === 422
            && preg_match('/name="contrast_review" value="([a-f0-9]{64})"/', $response->body(), $token) === 1) {
            $response = $this->post('/admin/branding', $values + ['contrast_ack' => '1', 'contrast_review' => $token[1]]);
        }
        return $response;
    }

    /** Restore from the review page, acknowledging its exact contrast review if it shows one. */
    private function restore(int $revision): \App\Core\Response
    {
        $review = $this->get('/admin/branding/history/' . $revision)->body();
        self::assertSame(1, preg_match('/name="base_revision" value="([0-9]+)"/', $review, $base));
        $values = ['base_revision' => $base[1]];
        if (preg_match('/name="contrast_review" value="([a-f0-9]{64})"/', $review, $token) === 1) {
            $values += ['contrast_ack' => '1', 'contrast_review' => $token[1]];
        }
        return $this->post('/admin/branding/history/' . $revision . '/restore', $values);
    }

    private function settings(): SettingRepository
    {
        return new SettingRepository($this->db);
    }

    /** @return array<string,mixed> */
    private function lastAudit(string $action): array
    {
        $json = $this->db->fetchValue('SELECT after_json FROM moderation_log WHERE action = ? ORDER BY id DESC LIMIT 1', [$action]);
        self::assertIsString($json, $action . ' was not audited.');
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function attachmentService(): AttachmentService
    {
        return new AttachmentService(new AttachmentRepository($this->db), (string) $this->config->get('uploads.storage_path'));
    }

    /** A real finalized brand image, stored through the production intake. */
    private function brandAsset(int $ownerId): string
    {
        $row = $this->attachmentService()->storeUpload($ownerId, $this->fakeUpload($this->pngBytes(), 'logo.png'), 'brand_logo');
        (new AttachmentRepository($this->db))->finalizeBrandAsset((int) $row['id'], $ownerId);
        return '/media/' . (int) $row['id'];
    }
}
