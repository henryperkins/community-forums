<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * P3-07: operator branding. Name/colours/theme are applied through the shell and
 * the dynamic /brand.css (the strict CSP forbids inline styles), invalid input is
 * rejected, reset restores the safe defaults, and only admins can change it.
 */
final class AppBrandingThemeTest extends TestCase
{
    public function test_a_discarded_oversized_branding_body_reports_size_without_authorizing_a_write(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('site_name', 'Before oversized request');
        $cap = ini_parse_quantity(ini_get('post_max_size'));
        self::assertGreaterThan(0, $cap);
        $response = $this->postWithServer('/admin/branding', [], ['CONTENT_LENGTH' => (string) ($cap + 1)], false);
        $this->assertStatus(413, $response);
        $this->assertSeeText($response, 'Nothing was saved');
        self::assertSame('Before oversized request', $settings->getString('site_name'));
        $this->assertStatus(403, $this->postWithServer('/admin/branding', ['site_name' => 'Untrusted'], ['CONTENT_LENGTH' => '100'], false));
        self::assertSame('Before oversized request', $settings->getString('site_name'));
    }

    public function test_php_upload_errors_preserve_the_draft_and_every_saved_asset(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('site_name', 'Saved community');
        foreach (['logo', 'logo_light', 'logo_dark', 'favicon'] as $field) {
            $settings->set('brand_' . $field . '_path', '/media/saved-' . $field);
        }
        foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL,
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION, 99] as $error) {
            foreach (['logo', 'logo_light', 'logo_dark', 'favicon'] as $field) {
                $response = $this->postFile('/admin/branding', $field, [
                    'name' => 'chosen.png', 'type' => 'image/png', 'tmp_name' => '', 'error' => $error, 'size' => 0,
                ], ['site_name' => 'Preserved upload draft', 'theme_default' => 'dark', 'remove_logo_light' => '1']);
                $this->assertStatus(422, $response);
                $this->assertSeeText($response, 'Preserved upload draft');
                $this->assertSeeText($response, 'Select those files again');
                self::assertSame('Saved community', $settings->getString('site_name'));
                foreach (['logo', 'logo_light', 'logo_dark', 'favicon'] as $saved) {
                    self::assertSame('/media/saved-' . $saved, $settings->getString('brand_' . $saved . '_path'));
                }
                self::assertMatchesRegularExpression('/name="remove_logo_light"[^>]*checked/', $response->body());
                self::assertStringNotContainsString('Saved. The chrome is live', $response->body());
            }
        }
    }

    public function test_a_rejected_upload_prevents_another_file_from_being_saved(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('site_name', 'Before upload');
        $settings->set('brand_logo_path', '/media/previous');
        $response = $this->request('POST', '/admin/branding', [
            '_token' => $this->csrfToken(), 'site_name' => 'Two-file draft', 'theme_preset' => 'retro',
        ], [], [
            'logo' => $this->fakeUpload($this->pngBytes()),
            'favicon' => ['name' => 'favicon.png', 'type' => 'image/png', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0],
        ]);
        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Two-file draft');
        self::assertSame('Before upload', $settings->getString('site_name'));
        self::assertSame('/media/previous', $settings->getString('brand_logo_path'));
        self::assertSame('', $settings->getString('brand_favicon_path'));
        self::assertMatchesRegularExpression('/name="favicon"[^>]*aria-invalid="true"/', $response->body());
    }

    public function test_malformed_upload_metadata_is_not_treated_as_an_absent_selection(): void
    {
        $this->actingAs($this->makeAdmin());
        $response = $this->postFile('/admin/branding', 'logo_dark', [
            'name' => ['unexpected'], 'tmp_name' => '', 'error' => UPLOAD_ERR_OK, 'size' => 0,
        ], ['site_name' => 'Malformed upload draft']);
        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Malformed upload draft');
        self::assertMatchesRegularExpression('/name="logo_dark"[^>]*aria-invalid="true"/', $response->body());
    }

    public function test_all_logo_variants_and_favicon_upload_with_supported_attachment_purposes(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new \App\Repository\SettingRepository($this->db);
        $uploaded = [];
        foreach (['logo', 'logo_light', 'logo_dark', 'favicon'] as $field) {
            $response = $this->postFile('/admin/branding', $field, $this->fakeUpload($this->pngBytes(), $field . '.png'), [
                'site_name' => 'Uploaded marks', 'theme_default' => 'system',
            ]);
            $this->assertRedirect($response, '/admin/branding');
            $uploaded[$field] = $settings->getString('brand_' . $field . '_path');
            self::assertSame(1, preg_match('#^/media/(\d+)$#', $uploaded[$field], $match));
            $asset = $this->db->fetch('SELECT purpose, status FROM attachments WHERE id = ?', [(int) $match[1]]);
            self::assertNotNull($asset);
            self::assertSame($field === 'favicon' ? 'brand_favicon' : 'brand_logo', $asset['purpose']);
            self::assertSame('finalized', $asset['status']);
        }
        self::assertCount(4, array_unique($uploaded));
        foreach ($uploaded as $field => $path) {
            self::assertSame($path, $settings->getString('brand_' . $field . '_path'));
        }
    }

    public function test_each_brand_asset_can_be_removed_without_resetting_other_branding(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $settings = new \App\Repository\SettingRepository($this->db);
        $assets = ['logo' => 'brand_logo_path', 'logo_light' => 'brand_logo_light_path', 'logo_dark' => 'brand_logo_dark_path', 'favicon' => 'brand_favicon_path'];
        $settings->set('brand_custom_css', '.kept{color:inherit}');
        foreach ($assets as $removed => $removedSetting) {
            foreach ($assets as $field => $setting) {
                $settings->set($setting, '/media/' . $field);
            }
            $response = $this->saveAcknowledgedBranding([
                'site_name' => 'Preserved community', 'color_primary' => '#123456', 'color_accent' => '#abcdef',
                'theme_default' => 'dark', 'theme_preset' => 'retro', 'remove_' . $removed => '1',
                // Arbitrary posted suffixes never become setting names.
                'remove_site_name' => '1', 'remove_brand_custom_css' => '1',
            ]);
            $this->assertRedirect($response, '/admin/branding');
            foreach ($assets as $field => $setting) {
                self::assertSame($field === $removed ? '' : '/media/' . $field, $settings->getString($setting));
            }
            self::assertSame('Preserved community', $settings->getString('site_name'));
            self::assertSame('#123456', $settings->getString('brand_color_primary'));
            self::assertSame('#abcdef', $settings->getString('brand_color_accent'));
            self::assertSame('dark', $settings->getString('brand_theme_default'));
            self::assertSame('retro', $settings->getString('brand_theme_preset'));
            self::assertSame('.kept{color:inherit}', $settings->getString('brand_custom_css'));
            $audit = json_decode((string) $this->db->fetchValue("SELECT after_json FROM moderation_log WHERE action = 'branding_update' ORDER BY id DESC LIMIT 1"), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame([$removed], $audit['removed_assets']);
        }
    }

    public function test_pending_asset_removals_survive_validation_without_clearing_saved_assets(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new \App\Repository\SettingRepository($this->db);
        $assets = ['logo', 'logo_light', 'logo_dark', 'favicon'];
        foreach ($assets as $field) {
            $settings->set('brand_' . $field . '_path', '/media/' . $field);
        }
        $values = ['site_name' => 'Pending removals', 'color_primary' => '#ffffff', 'theme_default' => 'light'];
        foreach ($assets as $field) {
            $values['remove_' . $field] = '1';
        }
        foreach ([$values, array_replace($values, ['site_name' => '']), $values + ['reset' => '1', 'reset_confirm' => 'no']] as $invalid) {
            $response = $this->post('/admin/branding', $invalid);
            $this->assertStatus(422, $response);
            foreach ($assets as $field) {
                self::assertSame('/media/' . $field, $settings->getString('brand_' . $field . '_path'));
                self::assertMatchesRegularExpression('/name="remove_' . $field . '"[^>]*checked/', $response->body());
            }
        }
    }

    public function test_removing_and_uploading_the_same_asset_is_rejected_without_partial_save(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('site_name', 'Original community');
        foreach (['logo', 'logo_light', 'logo_dark', 'favicon'] as $field) {
            $settings->set('brand_' . $field . '_path', '/media/' . $field);
            $response = $this->postFile('/admin/branding', $field, $this->fakeUpload($this->pngBytes()), [
                'site_name' => 'Conflict draft', 'theme_default' => 'system', 'remove_' . $field => '1',
            ]);
            $this->assertStatus(422, $response);
            $this->assertSeeText($response, 'uploading a replacement, not both.');
            self::assertMatchesRegularExpression('/name="remove_' . $field . '"[^>]*checked/', $response->body());
            self::assertSame('/media/' . $field, $settings->getString('brand_' . $field . '_path'));
            self::assertSame('Original community', $settings->getString('site_name'));
        }
    }

    public function test_empty_asset_removal_is_a_safe_noop_and_unselected_upload_fields_do_not_conflict(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new \App\Repository\SettingRepository($this->db);
        $form = $this->get('/admin/branding');
        self::assertStringNotContainsString('name="remove_logo"', $form->body());
        $response = $this->post('/admin/branding', [
            'site_name' => 'No saved marks', 'remove_logo' => '1', 'remove_favicon' => '1',
        ]);
        $this->assertRedirect($response, '/admin/branding');
        self::assertSame('', $settings->getString('brand_logo_path'));
        $audit = json_decode((string) $this->db->fetchValue("SELECT after_json FROM moderation_log WHERE action = 'branding_update' ORDER BY id DESC LIMIT 1"), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $audit['removed_assets']);

        $settings->set('brand_logo_path', '/media/logo');
        $response = $this->postFile('/admin/branding', 'logo', [
            'name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0,
        ], ['site_name' => 'No selected file', 'remove_logo' => '1']);
        $this->assertRedirect($response, '/admin/branding');
        self::assertSame('', $settings->getString('brand_logo_path'));
    }

    public function test_regular_members_cannot_remove_brand_assets(): void
    {
        $this->makeAdmin();
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('brand_logo_path', '/media/logo');
        $this->actingAs($this->makeUser());
        $response = $this->post('/admin/branding', ['site_name' => 'Attempted removal', 'remove_logo' => '1']);
        $this->assertStatus(403, $response);
        self::assertSame('/media/logo', $settings->getString('brand_logo_path'));
    }

    public function test_suspended_or_banned_admin_can_read_branding_but_cannot_remove_or_reset_it(): void
    {
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('brand_logo_path', '/media/logo');
        foreach (['suspended', 'banned'] as $status) {
            $admin = $this->makeAdmin(['status' => $status, 'suspended_until' => gmdate('Y-m-d H:i:s', time() + 3600)]);
            $this->actingAs($admin);
            $this->assertStatus(200, $this->get('/admin/branding'));
            $this->assertStatus(403, $this->post('/admin/branding', ['site_name' => 'Restricted', 'remove_logo' => '1']));
            $this->assertStatus(403, $this->post('/admin/branding', ['reset' => '1', 'reset_confirm' => 'RESET']));
            self::assertSame('/media/logo', $settings->getString('brand_logo_path'));
        }
    }

    public function test_white_link_colour_requires_explicit_contrast_acknowledgement(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('brand_color_primary', '#2e4a3a');

        $response = $this->post('/admin/branding', [
            'site_name' => 'Retained colour draft',
            'color_primary' => '#ffffff',
            'theme_default' => 'light',
        ]);

        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Retained colour draft');
        $this->assertSeeText($response, '#ffffff');
        self::assertSame('#2e4a3a', $settings->getString('brand_color_primary'));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'branding_contrast_override'"));
    }

    public function test_confirmed_contrast_override_records_the_colours_and_warnings(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $values = [
            'site_name' => 'Acknowledged colour',
            'color_primary' => '#ffffff',
            'color_accent' => '#ffffff',
            'theme_default' => 'light',
        ];
        $review = $this->post('/admin/branding', $values);
        $this->assertStatus(422, $review);
        self::assertSame(1, preg_match('/name="contrast_review" value="([a-f0-9]{64})"/', $review->body(), $token));
        $response = $this->post('/admin/branding', $values + ['contrast_ack' => '1', 'contrast_review' => $token[1]]);

        $this->assertRedirect($response, '/admin/branding');
        $record = $this->db->fetch("SELECT actor_id, after_json FROM moderation_log WHERE action = 'branding_contrast_override' ORDER BY id DESC LIMIT 1");
        self::assertNotNull($record);
        self::assertSame((int) $admin['id'], (int) $record['actor_id']);
        $after = json_decode($record['after_json'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('#ffffff', $after['color_primary']);
        self::assertSame('#ffffff', $after['color_accent']);
        self::assertNotEmpty($after['warnings']);
        self::assertStringContainsString('light', implode(' ', $after['warnings']));
        self::assertStringContainsString('links', implode(' ', $after['warnings']));
        self::assertStringContainsString('indicators', implode(' ', $after['warnings']));
    }

    public function test_contrast_acknowledgement_cannot_reuse_a_review_after_changing_colours(): void
    {
        $this->actingAs($this->makeAdmin());
        $values = ['site_name' => 'Review must match', 'color_primary' => '#ffffff', 'theme_default' => 'light'];
        $review = $this->post('/admin/branding', $values);
        $this->assertStatus(422, $review);
        self::assertSame(1, preg_match('/name="contrast_review" value="([a-f0-9]{64})"/', $review->body(), $token));

        $values['color_primary'] = '#000000';
        $changed = $this->post('/admin/branding', $values + ['contrast_ack' => '1', 'contrast_review' => $token[1]]);
        $this->assertStatus(422, $changed);
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'branding_contrast_override'"));
    }

    public function test_black_primary_warns_for_member_dark_mode_even_with_light_guest_default(): void
    {
        $this->actingAs($this->makeAdmin());
        $response = $this->post('/admin/branding', [
            'site_name' => 'Member modes matter',
            'color_primary' => '#000000',
            'theme_default' => 'light',
        ]);
        $this->assertStatus(422, $response);
    }

    public function test_contrast_acknowledgement_does_not_allow_invalid_hex_values(): void
    {
        $this->actingAs($this->makeAdmin());
        $response = $this->post('/admin/branding', [
            'site_name' => 'Invalid remains invalid',
            'color_primary' => 'white',
            'contrast_ack' => '1',
        ]);
        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Primary colour must be a hex value');
    }

    public function test_retro_serves_complete_dark_and_system_palettes(): void
    {
        $this->makeAdmin();
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('brand_theme_preset', 'retro');
        $response = $this->get('/brand.css');

        $this->assertStatus(200, $response);
        self::assertSame(1, preg_match('/\[data-theme="dark"\]\{([^}]+)\}/', $response->body(), $dark));
        foreach (['--surface-page:', '--surface-raised:', '--surface-sunken:', '--surface-2:', '--text:', '--accent-contrast:'] as $token) {
            self::assertStringContainsString($token, $dark[1]);
        }
        self::assertStringNotContainsString('--text:#241706;', $dark[1]);
        self::assertStringContainsString('@media (prefers-color-scheme: dark){:root[data-theme="system"]{' . $dark[1] . '}', $response->body());
    }

    public function test_branding_preview_css_is_private_even_without_a_valid_preview(): void
    {
        $this->makeAdmin();
        $response = $this->get('/brand.css', ['preview' => '1']);
        $this->assertStatus(200, $response);
        self::assertSame('private, no-store', $response->getHeader('cache-control'));

        (new \App\Repository\SettingRepository($this->db))->set('features', ['branding' => false]);
        $disabled = $this->get('/brand.css', ['preview' => '1']);
        self::assertSame(':root{}', $disabled->body());
        self::assertSame('private, no-store', $disabled->getHeader('cache-control'));
    }

    public function test_default_shell_declares_a_non_fetching_favicon_fallback(): void
    {
        $this->makeAdmin();

        $home = $this->get('/');

        $this->assertStatus(200, $home);
        self::assertStringContainsString('<link rel="icon" href="data:,">', $home->body());
        self::assertSame(1, substr_count($home->body(), '<link rel="icon"'));
    }

    public function test_admin_branding_recolours_via_brand_css(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin']);
        $this->actingAs($admin);

        $res = $this->saveAcknowledgedBranding([
            'site_name' => 'Lakeside Forum',
            'color_primary' => '#123456',
            'color_accent' => '#abcdef',
            'theme_default' => 'dark',
        ]);
        $this->assertRedirect($res, '/admin/branding');

        // The dynamic stylesheet maps the primary colour onto --accent and the
        // accent colour onto --accent-2.
        $css = $this->get('/brand.css');
        $this->assertStatus(200, $css);
        self::assertStringContainsString('text/css', (string) $css->getHeader('content-type'));
        $this->assertSeeText($css, '--accent:#123456');
        $this->assertSeeText($css, '--accent-contrast:#ffffff');
        $this->assertSeeText($css, '--accent-2:#abcdef');
        $this->assertSeeText($css, '--brand-accent-contrast:#0f1218');

        // Regression guard: both brand tokens must be CONSUMED by the stylesheet,
        // otherwise the colour picker is inert (the accent previously emitted a
        // --brand-accent token that no rule referenced).
        $appCss = (string) file_get_contents(dirname(__DIR__, 3) . '/public/assets/app.css');
        self::assertStringContainsString('var(--accent-2)', $appCss, 'the brand accent colour must drive at least one rule');
        self::assertStringContainsString('var(--accent)', $appCss);

        // The shell now links it and shows the new name; the signed-out default
        // theme is dark.
        $home = $this->get('/');
        $this->assertSeeText($home, '/brand.css?v=');
        $this->assertSeeText($home, 'Lakeside Forum');
    }

    public function test_name_change_retires_the_placeholder(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin2']);
        $this->actingAs($admin);
        $this->post('/admin/branding', ['site_name' => 'CoolCommunity', 'theme_default' => 'system']);

        $home = $this->get('/');
        $this->assertSeeText($home, 'CoolCommunity');
        $this->assertSeeText($home, '<title>CoolCommunity</title>');
    }

    public function test_reset_restores_defaults(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin3']);
        $this->actingAs($admin);
        $this->saveAcknowledgedBranding(['site_name' => 'Temp', 'color_primary' => '#222222', 'theme_default' => 'system']);
        $this->assertSeeText($this->get('/brand.css'), '#222222');

        $this->post('/admin/branding', ['reset' => '1', 'reset_confirm' => 'RESET']);
        // Colours cleared → brand.css carries no overrides and the shell stops linking it.
        $this->assertDontSeeText($this->get('/brand.css'), '#222222');
        $this->assertDontSeeText($this->get('/'), '/brand.css');
    }

    public function test_reset_validation_preserves_the_submitted_branding_draft(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin-reset-draft']);
        $this->actingAs($admin);
        (new \App\Repository\SettingRepository($this->db))->set('features', ['custom_css' => true]);

        $response = $this->post('/admin/branding', [
            'reset' => '1',
            'reset_confirm' => 'RESE',
            'site_name' => 'Unsaved Forum Name',
            'color_primary' => '#123456',
            'color_accent' => '#abcdef',
            'theme_default' => 'dark',
            'theme_preset' => 'retro',
            'custom_css_enabled' => '1',
            'custom_css' => '.draft-rule{letter-spacing:.02em;}',
            'custom_css_ack' => '1',
        ]);

        $this->assertStatus(422, $response);
        self::assertStringContainsString('value="Unsaved Forum Name"', $response->body());
        self::assertStringContainsString('value="#123456"', $response->body());
        self::assertStringContainsString('value="#abcdef"', $response->body());
        self::assertStringContainsString('value="RESE"', $response->body());
        self::assertStringContainsString('.draft-rule{letter-spacing:.02em;}', $response->body());
        self::assertStringContainsString('name="custom_css_ack" value="1" checked', $response->body());
        self::assertStringContainsString(
            'type="submit" name="reset" value="1" formnovalidate>Reset branding</button>',
            $response->body(),
        );
    }

    public function test_branding_success_flashes_use_the_design_register(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin-status']);
        $this->actingAs($admin);

        $this->assertRedirect($this->post('/admin/branding', [
            'site_name' => 'Status Forum',
            'theme_default' => 'system',
        ]), '/admin/branding');
        $this->assertSeeText($this->get('/admin/branding'), 'Saved. The chrome is live for everyone.');

        $this->assertRedirect($this->post('/admin/branding', [
            'reset' => '1',
            'reset_confirm' => 'RESET',
        ]), '/admin/branding');
        $this->assertSeeText($this->get('/admin/branding'), 'Branding reset. The community name and package theme were kept.');
    }

    public function test_rejected_brand_asset_uses_the_review_callout_and_keeps_the_previous_asset(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin-upload-rejection']);
        $this->actingAs($admin);

        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('brand_logo_path', '/media/existing-logo');

        $response = $this->postFile(
            '/admin/branding',
            'logo',
            $this->fakeUpload('not an image', 'invalid-logo.png', 'image/png'),
            ['site_name' => 'Status Forum', 'theme_default' => 'system'],
        );
        $this->assertRedirect($response, '/admin/branding');

        $rendered = $this->get('/admin/branding');
        self::assertStringContainsString(
            '<p class="branding-status callout callout-review" role="status">Branding updated, but Logo upload was rejected:',
            $rendered->body(),
        );
        $this->assertSeeText($rendered, 'The previous asset was kept.');
        self::assertSame('/media/existing-logo', $settings->getString('brand_logo_path'));
    }

    public function test_invalid_colour_is_rejected(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin4']);
        $this->actingAs($admin);
        $res = $this->post('/admin/branding', [
            'site_name' => 'X',
            'color_primary' => 'red',
            'color_accent' => 'blue',
            'theme_default' => 'system',
        ]);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Primary colour must be a hex value, e.g. #2e4a3a.');
        $this->assertSeeText($res, 'Accent colour must be a hex value, e.g. #c29a44.');
    }

    public function test_low_contrast_brand_colour_is_rejected(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin6']);
        $this->actingAs($admin);
        $res = $this->post('/admin/branding', ['site_name' => 'X', 'color_primary' => '#7a7a7a', 'theme_default' => 'system']);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'readable button text');
    }

    public function test_non_admin_cannot_change_branding(): void
    {
        $this->makeAdmin(); // satisfy setup gate
        $user = $this->makeUser(['username' => 'plebeian']);
        $this->actingAs($user);
        $this->assertStatus(403, $this->get('/admin/branding'));
        $this->assertStatus(403, $this->post('/admin/branding', ['site_name' => 'Hijack', 'theme_default' => 'system']));
    }

    public function test_branding_subsystem_can_be_disabled(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin5']);
        $this->actingAs($admin);
        // Set a colour while enabled, then disable the subsystem.
        $this->saveAcknowledgedBranding(['site_name' => 'Soon Off', 'color_primary' => '#654321', 'theme_default' => 'system']);
        (new \App\Repository\SettingRepository($this->db))->set('features', ['branding' => false]);

        // The admin form/update 404 and /brand.css emits no stored colours.
        $this->assertStatus(404, $this->get('/admin/branding'));
        $this->assertStatus(404, $this->post('/admin/branding', ['site_name' => 'Nope', 'theme_default' => 'system']));
        $this->assertDontSeeText($this->get('/brand.css'), '#654321');
    }

    public function test_custom_css_requires_flag_and_confirmation_before_emitting(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin7']);
        $this->actingAs($admin);
        $settings = new \App\Repository\SettingRepository($this->db);

        $settings->set('features', ['custom_css' => true]);
        $res = $this->post('/admin/branding', [
            'site_name' => 'CSS Forum',
            'theme_default' => 'system',
            'theme_preset' => 'retro',
            'custom_css_enabled' => '1',
            'custom_css' => '.brand-name{letter-spacing:.04em;}',
        ]);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Acknowledge that custom CSS applies site-wide before saving it.');

        $draft = $this->post('/admin/branding', [
            'site_name' => '',
            'theme_default' => 'system',
            'theme_preset' => 'retro',
            'custom_css_enabled' => '1',
            'custom_css_ack' => '1',
            'custom_css' => '.draft-rule{letter-spacing:.02em;}',
        ]);
        $this->assertStatus(422, $draft);
        $this->assertSeeText($draft, 'Site name must be 1–80 characters.');
        self::assertStringContainsString('name="custom_css_ack" value="1" checked', $draft->body());
        self::assertStringContainsString('.draft-rule{letter-spacing:.02em;}', $draft->body());

        $res = $this->post('/admin/branding', [
            'site_name' => 'CSS Forum',
            'theme_default' => 'system',
            'theme_preset' => 'retro',
            'custom_css_enabled' => '1',
            'custom_css_ack' => '1',
            'custom_css' => '.brand-name{letter-spacing:.04em;}',
        ]);
        $this->assertRedirect($res, '/admin/branding');

        $css = $this->get('/brand.css');
        $this->assertSeeText($css, '--surface:#fff7dc');
        $this->assertSeeText($css, '.brand-name{letter-spacing:.04em;}');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'branding_update' AND actor_id = ?", [$admin['id']]));

        $settings->set('features', ['custom_css' => false]);
        $css = $this->get('/brand.css');
        $this->assertSeeText($css, '--surface:#fff7dc');
        $this->assertDontSeeText($css, '.brand-name{letter-spacing:.04em;}');
    }

    public function test_custom_css_rejects_unsafe_constructs(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin8']);
        $this->actingAs($admin);
        (new \App\Repository\SettingRepository($this->db))->set('features', ['custom_css' => true]);

        $res = $this->post('/admin/branding', [
            'site_name' => 'Unsafe CSS',
            'theme_default' => 'system',
            'custom_css_enabled' => '1',
            'custom_css_ack' => '1',
            'custom_css' => '@import url("https://evil.example/x.css");',
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Custom CSS cannot import external stylesheets.');
    }

    public function test_disabled_custom_css_ignores_hidden_submission_without_erasing_saved_css(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin-hidden-css']);
        $this->actingAs($admin);
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('features', ['custom_css' => true]);
        $settings->set('brand_custom_css_enabled', true);
        $settings->set('brand_custom_css', '.saved-rule{letter-spacing:.03em;}');

        $response = $this->post('/admin/branding', [
            'site_name' => 'CSS Disabled Forum',
            'theme_default' => 'system',
            // CSS :has() hides rather than removes this textarea. The server
            // must treat its value as absent while the checkbox is off.
            'custom_css' => '@import url("https://evil.example/hidden.css");',
        ]);

        $this->assertRedirect($response, '/admin/branding');
        self::assertFalse($settings->get('brand_custom_css_enabled', true));
        self::assertSame('.saved-rule{letter-spacing:.03em;}', $settings->getString('brand_custom_css', ''));
        $this->assertDontSeeText($this->get('/brand.css'), '.saved-rule{letter-spacing:.03em;}');
    }

    public function test_dark_default_theme_uses_dark_logo_variant(): void
    {
        $this->makeAdmin();
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('site_name', 'Variant Forum');
        $settings->set('brand_logo_path', '/media/base-logo');
        $settings->set('brand_logo_light_path', '/media/light-logo');
        $settings->set('brand_logo_dark_path', '/media/dark-logo');
        $settings->set('brand_theme_default', 'dark');
        $settings->set('brand_version', 'variant1');

        $home = $this->get('/');

        $this->assertSeeText($home, '/media/dark-logo');
        $this->assertDontSeeText($home, '/media/base-logo');
        $this->assertDontSeeText($home, '/media/light-logo');
    }

    /** Exercise the same review-then-confirm flow as an operator selecting an unsafe colour. */
    private function saveAcknowledgedBranding(array $values): \App\Core\Response
    {
        $review = $this->post('/admin/branding', $values);
        $this->assertStatus(422, $review);
        self::assertSame(1, preg_match('/name="contrast_review" value="([a-f0-9]{64})"/', $review->body(), $token));
        return $this->post('/admin/branding', $values + ['contrast_ack' => '1', 'contrast_review' => $token[1]]);
    }
}
