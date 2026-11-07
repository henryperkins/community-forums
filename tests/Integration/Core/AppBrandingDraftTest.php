<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * ADR 0054 increment 2b: private branding drafts. A draft stays with its
 * author — never in public pages, shared caches, another admin's view or media
 * anyone else can load — survives navigation, previews across the community
 * for its author only, and publishes explicitly, checked against newer work.
 */
final class AppBrandingDraftTest extends TestCase
{
    public function test_a_saved_draft_changes_nothing_public_and_reopens_in_the_form(): void
    {
        $this->actingAs($admin = $this->makeAdmin());
        $settings = $this->settings();
        $settings->set('site_name', 'Published Commons');

        $saved = $this->post('/admin/branding', $this->fresh([
            'intent' => 'draft', 'site_name' => 'Draft Commons', 'site_tagline' => 'A drafted tagline',
            'color_primary' => '#ffffff', 'theme_preset' => 'retro',
        ]));
        // A draft is never refused for contrast; publishing it is.
        $this->assertRedirect($saved, '/admin/branding');
        self::assertSame('Published Commons', $settings->getString('site_name'));
        self::assertSame('', $settings->getString('brand_color_primary'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM branding_revisions'));

        $form = $this->get('/admin/branding')->body();
        self::assertStringContainsString('Draft saved. Only you can see it; the published branding is unchanged.', $form);
        self::assertStringContainsString('Unpublished draft', $form);
        self::assertStringContainsString('value="Draft Commons"', $form);
        self::assertStringContainsString('value="A drafted tagline"', $form);
        self::assertStringContainsString('value="#ffffff"', $form);

        // Drafts survive the session.
        $this->logoutClient();
        self::assertStringContainsString('<title>Published Commons</title>', $this->get('/')->body());
        $this->actingAs($admin);
        self::assertStringContainsString('value="Draft Commons"', $this->get('/admin/branding')->body());
    }

    public function test_each_administrator_has_a_private_draft(): void
    {
        $first = $this->makeAdmin();
        $second = $this->makeAdmin();
        $this->actingAs($first);
        $this->assertRedirect($this->post('/admin/branding', $this->fresh(['intent' => 'draft', 'site_name' => 'First draft'])), '/admin/branding');

        $this->actingAs($second);
        $form = $this->get('/admin/branding')->body();
        self::assertStringNotContainsString('First draft', $form);
        self::assertStringNotContainsString('Unpublished draft', $form);
        $this->assertRedirect($this->post('/admin/branding/preview', ['preview' => '1']), '/admin/branding');
        $this->assertSeeText($this->get('/admin/branding'), 'Save a draft before previewing it.');
    }

    public function test_the_preview_shows_the_draft_only_to_its_author_and_only_privately(): void
    {
        $this->actingAs($admin = $this->makeAdmin());
        $this->settings()->set('site_name', 'Published Commons');
        $this->assertRedirect($this->post('/admin/branding', $this->fresh([
            'intent' => 'draft', 'site_name' => 'Previewed Commons', 'color_accent' => '#b0611c',
            'directory_heading' => 'Welcome to the draft',
        ])), '/admin/branding');

        $this->assertRedirect($this->post('/admin/branding/preview', ['preview' => '1', 'return' => '/']), '/');
        $home = $this->get('/');
        $body = $home->body();
        self::assertSame('private, no-store', $home->getHeader('cache-control'));
        self::assertStringContainsString('Previewing your branding draft.', $body);
        self::assertStringContainsString('<h1>Welcome to the draft</h1>', $body);
        self::assertStringContainsString('<title>Previewed Commons</title>', $body);
        self::assertSame(1, preg_match('#href="(/brand\.css\?v=4-draft-[a-f0-9]{16}-[^"]*&amp;draft=1)"#', $body, $link));
        $draftCss = $this->get('/brand.css', ['v' => 'x', 'draft' => '1']);
        self::assertSame('private, no-store', $draftCss->getHeader('cache-control'));
        self::assertStringContainsString('--accent-2:#b0611c', $draftCss->body());
        $this->assertStatus(200, $this->get('/admin/branding/preview/sign-in'));
        self::assertStringContainsString('auth-stage', $this->get('/admin/branding/preview/sign-in')->body());

        // Nobody else sees it, including through the draft stylesheet URL.
        $this->logoutClient();
        $guestHome = $this->get('/')->body();
        self::assertStringNotContainsString('Previewing your branding draft.', $guestHome);
        self::assertStringNotContainsString('Welcome to the draft', $guestHome);
        self::assertStringContainsString('<title>Published Commons</title>', $guestHome);
        $guestCss = $this->get('/brand.css', ['v' => 'x', 'draft' => '1']);
        self::assertSame('private, no-store', $guestCss->getHeader('cache-control'));
        self::assertStringNotContainsString('#b0611c', $guestCss->body());
        $this->assertStatus(302, $this->get('/admin/branding/preview/sign-in'));
        $this->actingAs($this->makeAdmin());
        self::assertStringNotContainsString('Welcome to the draft', $this->get('/')->body());
        self::assertStringNotContainsString('#b0611c', $this->get('/brand.css', ['v' => 'x', 'draft' => '1'])->body());

        // Ending the preview returns the author to the published branding.
        $this->actingAs($admin);
        $this->assertRedirect($this->post('/admin/branding/preview', ['preview' => '0', 'return' => '/']), '/');
        self::assertStringNotContainsString('Welcome to the draft', $this->get('/')->body());
    }

    public function test_draft_images_stay_private_until_published(): void
    {
        $admin = $this->makeAdmin();
        $other = $this->makeAdmin();
        $this->actingAs($admin);
        $response = $this->postFile('/admin/branding', 'logo', $this->fakeUpload($this->pngBytes(), 'draft-logo.png'), $this->fresh([
            'intent' => 'draft', 'site_name' => 'Draft with logo',
        ]));
        $this->assertRedirect($response, '/admin/branding');
        self::assertSame('', $this->settings()->getString('brand_logo_path'));
        $draft = json_decode((string) $this->db->fetchValue('SELECT document_json FROM branding_drafts WHERE admin_id = ?', [(int) $admin['id']]), true);
        $logo = (string) $draft['brand_logo_path'];
        self::assertMatchesRegularExpression('#^/media/\d+$#', $logo);
        $owned = $this->get($logo);
        $this->assertStatus(200, $owned);
        self::assertSame('private, no-store', $owned->getHeader('cache-control'));
        self::assertStringContainsString('src="' . $logo . '"', $this->get('/admin/branding')->body());

        $this->actingAs($other);
        $this->assertStatus(404, $this->get($logo));
        $this->logoutClient();
        $this->assertStatus(404, $this->get($logo));

        $this->actingAs($admin);
        $this->assertRedirect($this->post('/admin/branding', $this->draftBase($admin, ['site_name' => 'Draft with logo'])), '/admin/branding');
        self::assertSame($logo, $this->settings()->getString('brand_logo_path'));
        $this->logoutClient();
        $public = $this->get($logo);
        $this->assertStatus(200, $public);
        self::assertStringContainsString('public', (string) $public->getHeader('cache-control'));
    }

    public function test_publishing_a_draft_writes_all_of_it_once_and_clears_the_draft(): void
    {
        $this->actingAs($admin = $this->makeAdmin());
        $this->assertRedirect($this->post('/admin/branding', $this->fresh([
            'intent' => 'draft', 'site_name' => 'Drafted name', 'directory_intro' => "First line\nSecond line", 'theme_preset' => 'retro',
        ])), '/admin/branding');
        $this->post('/admin/branding/preview', ['preview' => '1']);

        // The form carries the draft, so publishing it is one click.
        $published = $this->post('/admin/branding', $this->draftBase($admin, [
            'site_name' => 'Drafted name', 'directory_intro' => "First line\nSecond line", 'theme_preset' => 'retro',
        ]));
        $this->assertRedirect($published, '/admin/branding');
        $settings = $this->settings();
        self::assertSame('Drafted name', $settings->getString('site_name'));
        self::assertSame("First line\nSecond line", $settings->getString('brand_directory_intro'));
        self::assertSame('retro', $settings->getString('brand_theme_preset'));
        self::assertNull($this->db->fetchValue('SELECT admin_id FROM branding_drafts WHERE admin_id = ?', [(int) $admin['id']]) ?: null);
        $audit = json_decode((string) $this->db->fetchValue("SELECT after_json FROM moderation_log WHERE action = 'branding_update' ORDER BY id DESC LIMIT 1"), true);
        self::assertTrue($audit['from_draft']);
        self::assertSame('publish', $this->db->fetchValue('SELECT action FROM branding_revisions ORDER BY id DESC LIMIT 1'));
        // Publishing also ends the preview.
        self::assertStringNotContainsString('Previewing your branding draft.', $this->get('/')->body());
        self::assertStringNotContainsString('Unpublished draft', $this->get('/admin/branding')->body());
    }

    public function test_publishing_a_draft_still_requires_the_contrast_acknowledgement(): void
    {
        $this->actingAs($admin = $this->makeAdmin());
        $this->assertRedirect($this->post('/admin/branding', $this->fresh(['intent' => 'draft', 'site_name' => 'Low contrast', 'color_primary' => '#ffffff'])), '/admin/branding');
        $refused = $this->post('/admin/branding', $this->draftBase($admin, ['site_name' => 'Low contrast', 'color_primary' => '#ffffff']));
        $this->assertStatus(422, $refused);
        $this->assertSeeText($refused, 'Review colour contrast');
        self::assertSame('', $this->settings()->getString('brand_color_primary'));
        self::assertNotNull($this->db->fetchValue('SELECT admin_id FROM branding_drafts WHERE admin_id = ?', [(int) $admin['id']]) ?: null);
    }

    public function test_a_draft_started_before_newer_work_needs_the_conflict_confirmation(): void
    {
        $author = $this->makeAdmin();
        $publisher = $this->makeAdmin(['username' => 'newer_publisher']);
        $this->actingAs($author);
        $this->assertRedirect($this->post('/admin/branding', $this->fresh(['intent' => 'draft', 'site_name' => 'Old draft'])), '/admin/branding');

        $this->actingAs($publisher);
        $this->assertRedirect($this->post('/admin/branding', $this->fresh(['site_name' => 'Newer publication'])), '/admin/branding');
        $current = (int) $this->settings()->get('brand_revision');

        $this->actingAs($author);
        $form = $this->get('/admin/branding')->body();
        self::assertStringContainsString('Branding was published after this draft was started.', $form);
        $conflict = $this->post('/admin/branding', $this->draftBase($author, ['site_name' => 'Old draft']));
        $this->assertStatus(409, $conflict);
        $this->assertSeeText($conflict, 'by @newer_publisher: Community name');
        self::assertSame('Newer publication', $this->settings()->getString('site_name'));
        $this->assertRedirect($this->post('/admin/branding', $this->draftBase($author, ['site_name' => 'Old draft', 'overwrite_revision' => (string) $current])), '/admin/branding');
        self::assertSame('Old draft', $this->settings()->getString('site_name'));
    }

    public function test_discarding_a_draft_purges_only_its_private_images(): void
    {
        $this->actingAs($admin = $this->makeAdmin());
        $this->assertRedirect($this->postFile('/admin/branding', 'logo_dark', $this->fakeUpload($this->pngBytes(), 'dark.png'), $this->fresh([
            'intent' => 'draft', 'site_name' => 'Draft to discard',
        ])), '/admin/branding');
        $draft = json_decode((string) $this->db->fetchValue('SELECT document_json FROM branding_drafts WHERE admin_id = ?', [(int) $admin['id']]), true);
        $id = (int) substr((string) $draft['brand_logo_dark_path'], strlen('/media/'));
        $this->post('/admin/branding/preview', ['preview' => '1']);

        $this->assertRedirect($this->post('/admin/branding/draft/discard'), '/admin/branding');
        $this->assertSeeText($this->get('/admin/branding'), 'Draft discarded. The published branding is unchanged.');
        self::assertFalse($this->db->fetchValue('SELECT admin_id FROM branding_drafts WHERE admin_id = ?', [(int) $admin['id']]));
        self::assertSame('deleted', $this->db->fetchValue('SELECT status FROM attachments WHERE id = ?', [$id]));
        self::assertStringNotContainsString('Previewing your branding draft.', $this->get('/')->body());
        self::assertNotSame('Draft to discard', $this->settings()->getString('site_name'));
    }

    public function test_draft_routes_are_admin_only_and_write_gated(): void
    {
        $this->makeAdmin();
        $member = $this->makeUser();
        $this->actingAs($member);
        $this->assertStatus(403, $this->post('/admin/branding/preview', ['preview' => '1']));
        $this->assertStatus(403, $this->post('/admin/branding/draft/discard'));
        $this->assertStatus(403, $this->get('/admin/branding/preview/sign-in'));
        // A member's session can never turn a preview on for itself.
        $this->assertStatus(403, $this->post('/admin/branding', ['intent' => 'draft', 'site_name' => 'Member draft']));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM branding_drafts'));
    }

    private function settings(): SettingRepository
    {
        return new SettingRepository($this->db);
    }

    /** A form opened now: the live revision. */
    private function fresh(array $values): array
    {
        $revision = $this->settings()->get('brand_revision', 0);
        return $values + ['theme_default' => 'system', 'base_revision' => (string) (is_int($revision) ? $revision : 0)];
    }

    /** A form showing this administrator's draft: the draft's own base revision. */
    private function draftBase(array $admin, array $values): array
    {
        $base = $this->db->fetchValue('SELECT base_revision_id FROM branding_drafts WHERE admin_id = ?', [(int) $admin['id']]);
        return $values + ['theme_default' => 'system', 'intent' => 'publish', 'base_revision' => (string) (int) $base];
    }
}
