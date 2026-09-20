<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppProfileMediaTest extends TestCase
{
    /** @var list<string> */
    private array $uploadFiles = [];

    protected function fakeUpload(string $bytes, string $name = 'image.png', string $type = 'image/png'): array
    {
        $file = parent::fakeUpload($bytes, $name, $type);
        $this->uploadFiles[] = $file['tmp_name'];
        return $file;
    }

    protected function tearDown(): void
    {
        foreach ($this->db->fetchAll('SELECT storage_key FROM attachments') as $row) {
            $this->uploadFiles[] = $this->config->get('uploads.storage_path') . '/' . $row['storage_key'];
        }
        foreach ($this->uploadFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    /** @return array<string,string> */
    private function profileDraft(): array
    {
        return [
            'display_name' => 'Unsaved <name>', 'bio' => 'Unsaved biography',
            'pronouns' => 'they/them', 'location' => 'Unsaved place',
            'website' => 'https://draft.example.test', 'signature' => 'Unsaved signature',
            'custom_label_1' => 'First label', 'custom_value_1' => 'First value',
            'custom_label_2' => 'Second label', 'custom_value_2' => 'Second value',
            'custom_label_3' => 'Third label', 'custom_value_3' => 'Third value',
        ];
    }

    private function assertDraft(\App\Core\Response $response): void
    {
        $document = new \DOMDocument();
        @$document->loadHTML($response->body());
        $xpath = new \DOMXPath($document);
        foreach ($this->profileDraft() as $name => $value) {
            $node = $xpath->query('//*[@name="' . $name . '"]')->item(0);
            self::assertNotNull($node, $name);
            self::assertSame($value, $node->nodeName === 'textarea' ? $node->textContent : $node->getAttribute('value'), $name);
        }
        self::assertStringNotContainsString('Unsaved <name>', $response->body());
    }

    public function test_invalid_avatar_preserves_the_unsaved_profile_draft(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['display_name' => 'Stored name']);
        $this->actingAs($user);
        $this->db->run("UPDATE users SET avatar_path = '/media/321', avatar_source = 'upload' WHERE id = ?", [$user['id']]);
        $file = $this->fakeUpload('not an image', 'bad.txt', 'text/plain');
        $response = $this->postFile('/settings/avatar', 'avatar', $file, $this->profileDraft() + [
            'avatar_path' => '/media/999', 'email' => 'forged@example.test', 'role' => 'admin',
        ]);
        $this->assertStatus(422, $response);
        $this->assertDraft($response);
        self::assertStringContainsString('src="/media/321"', $response->body());
        self::assertStringNotContainsString('/media/999', $response->body());
        self::assertStringNotContainsString('forged@example.test', $response->body());
        self::assertStringContainsString('aria-invalid="true" aria-describedby="err-avatar"', $response->body());
        self::assertStringContainsString('Choose the file again', $response->body());
        self::assertSame('Stored name', $this->users()->find((int) $user['id'])['display_name']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM attachments'));
    }

    public function test_avatar_upload_and_removal_preserve_all_fields_until_profile_save(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['display_name' => 'Stored name']);
        $other = $this->makeUser(['display_name' => 'Other member']);
        $this->actingAs($user);
        $draft = $this->profileDraft() + ['user_id' => (string) $other['id'], 'avatar_path' => '/media/999', 'role' => 'admin'];
        $response = $this->postFile('/settings/avatar', 'avatar', $this->fakeUpload($this->pngBytes()), $draft);
        $this->assertStatus(200, $response);
        $this->assertDraft($response);
        self::assertStringContainsString('Avatar updated. Other profile edits are not saved.', $response->body());
        $row = $this->users()->find((int) $user['id']);
        self::assertStringContainsString('src="' . $row['avatar_path'] . '"', $response->body());
        self::assertSame('Stored name', $row['display_name']);
        self::assertSame('user', $row['role']);
        self::assertEmpty($row['bio']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_profile_fields WHERE user_id = ?', [$user['id']]));
        self::assertNull($this->users()->find((int) $other['id'])['avatar_path']);

        $response = $this->post('/settings/avatar/remove', $draft);
        $this->assertStatus(200, $response);
        $this->assertDraft($response);
        self::assertStringContainsString('Avatar removed. Other profile edits are not saved.', $response->body());
        self::assertStringNotContainsString('src="' . $row['avatar_path'] . '"', $response->body());
        self::assertNull($this->users()->find((int) $user['id'])['avatar_path']);
        self::assertSame('Stored name', $this->users()->find((int) $user['id'])['display_name']);
        $this->assertRedirect($this->post('/settings/account', $draft), '/settings/account');
        self::assertSame('Unsaved <name>', $this->users()->find((int) $user['id'])['display_name']);
        self::assertSame(3, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_profile_fields WHERE user_id = ?', [$user['id']]));
    }

    public function test_missing_and_oversized_avatars_preserve_drafts_without_creating_attachments(): void
    {
        $this->makeAdmin();
        $this->actingAs($this->makeUser());
        $response = $this->post('/settings/avatar', $this->profileDraft());
        $this->assertStatus(422, $response);
        $this->assertDraft($response);
        self::assertStringContainsString('Choose an avatar image.', $response->body());
        $file = $this->fakeUpload($this->pngBytes());
        $file['size'] = 6_000_000;
        $response = $this->postFile('/settings/avatar', 'avatar', $file, $this->profileDraft());
        $this->assertStatus(422, $response);
        $this->assertDraft($response);
        self::assertStringContainsString('That image is too large.', $response->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM attachments'));
    }

    public function test_profile_and_avatar_controls_share_one_multipart_form(): void
    {
        $this->makeAdmin();
        $this->actingAs($this->makeUser());
        $response = $this->get('/settings/account');
        $document = new \DOMDocument();
        @$document->loadHTML($response->body());
        $xpath = new \DOMXPath($document);
        self::assertSame(1, $xpath->query('//form[@action="/settings/account" and @enctype="multipart/form-data"]//input[@name="avatar" and not(@required)]')->length);
        self::assertSame(1, $xpath->query('//form[@action="/settings/account"]//button[@formaction="/settings/avatar" and @formnovalidate]')->length);
        self::assertSame(1, $xpath->query('//form[@action="/settings/account"]//button[not(@formaction) and text()="Save profile"]')->length);
        // Native Enter submission uses the first submit button, so it must save the profile.
        $defaultSubmit = $xpath->query('//form[@action="/settings/account"]//button[@type="submit"]')->item(0);
        self::assertSame('/settings/account', $defaultSubmit->getAttribute('formaction'));
    }

    public function test_avatar_actions_do_not_validate_or_truncate_unsaved_profile_fields(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['display_name' => 'Stored name']);
        $this->actingAs($user);
        $draft = ['display_name' => str_repeat('x', 70), 'website' => 'unfinished URL', 'custom_label_1' => 'Label without value'];
        $response = $this->postFile('/settings/avatar', 'avatar', $this->fakeUpload($this->pngBytes()), $draft);
        $this->assertStatus(200, $response);
        self::assertStringContainsString(str_repeat('x', 70), $response->body());
        self::assertStringContainsString('unfinished URL', $response->body());
        self::assertStringContainsString('Label without value', $response->body());
        $row = $this->users()->find((int) $user['id']);
        self::assertSame('Stored name', $row['display_name']);
        $response = $this->post('/settings/account', $draft + ['avatar_path' => '/media/999']);
        $this->assertStatus(422, $response);
        self::assertStringContainsString('src="' . $row['avatar_path'] . '"', $response->body());
        self::assertStringNotContainsString('/media/999', $response->body());
        self::assertStringContainsString(str_repeat('x', 70), $response->body());
    }

    public function test_avatar_actions_reject_restricted_accounts_and_missing_csrf(): void
    {
        $this->makeAdmin();
        foreach (['banned', 'suspended', 'deactivated', 'pending_deletion'] as $status) {
            $user = $this->makeUser(['status' => $status, 'suspended_until' => '2099-01-01 00:00:00']);
            $this->db->run("UPDATE users SET avatar_path = '/media/321', avatar_source = 'upload' WHERE id = ?", [$user['id']]);
            $this->actingAs($user);
            $this->assertStatus(403, $this->postFile('/settings/avatar', 'avatar', $this->fakeUpload($this->pngBytes()), $this->profileDraft()));
            $this->assertStatus(403, $this->post('/settings/avatar/remove', $this->profileDraft()));
            self::assertSame('/media/321', $this->users()->find((int) $user['id'])['avatar_path']);
        }
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->assertStatus(403, $this->postFile('/settings/avatar', 'avatar', $this->fakeUpload($this->pngBytes()), [], false));
        $this->assertStatus(403, $this->post('/settings/avatar/remove', [], false));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM attachments'));
    }

    public function test_disabled_custom_fields_are_not_reflected_or_saved_by_avatar_actions(): void
    {
        $this->makeAdmin();
        $this->setFlags(['custom_profile_fields' => false]);
        $user = $this->makeUser();
        $this->actingAs($user);
        $response = $this->post('/settings/avatar/remove', $this->profileDraft());
        $this->assertStatus(200, $response);
        self::assertStringContainsString('Unsaved biography', $response->body());
        self::assertStringNotContainsString('First label', $response->body());
        self::assertStringNotContainsString('name="custom_label_1"', $response->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_profile_fields WHERE user_id = ?', [$user['id']]));
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_profile_media_defaults_on_and_operator_rollback_regates_mutations(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser(['username' => 'avatarrollback']);
        $this->actingAs($user);

        $settings = $this->get('/settings/account');
        $this->assertStatus(200, $settings);
        self::assertStringContainsString('action="/settings/avatar"', $settings->body());

        $file = $this->fakeUpload($this->pngBytes(), 'avatar.png', 'image/png');
        $uploaded = $this->postFile('/settings/avatar', 'avatar', $file);
        $this->assertStatus(200, $uploaded);
        self::assertStringContainsString('Avatar updated. Other profile edits are not saved.', $uploaded->body());

        $this->setFlags(['profile_media' => false]);
        $rolledBack = $this->get('/settings/account');
        $this->assertStatus(200, $rolledBack);
        self::assertStringNotContainsString('action="/settings/avatar"', $rolledBack->body());

        $file = $this->fakeUpload($this->pngBytes(), 'avatar.png', 'image/png');
        $this->assertStatus(404, $this->postFile('/settings/avatar', 'avatar', $file));
        $this->assertStatus(404, $this->post('/settings/avatar/remove'));

        $this->actingAs($admin);
        $adminRecord = $this->get('/admin/users/' . (int) $user['id']);
        $this->assertStatus(200, $adminRecord);
        self::assertStringNotContainsString('/avatar/remove', $adminRecord->body());
        $this->assertStatus(404, $this->post('/admin/users/' . (int) $user['id'] . '/avatar/remove'));
    }

    public function test_user_uploads_and_removes_avatar_when_profile_media_enabled(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'avataruser']);
        $this->actingAs($user);

        $file = $this->fakeUpload($this->pngBytes(), 'avatar.png', 'image/png');
        $uploaded = $this->postFile('/settings/avatar', 'avatar', $file);
        $this->assertStatus(200, $uploaded);
        self::assertStringContainsString('Avatar updated. Other profile edits are not saved.', $uploaded->body());

        $row = $this->users()->find((int) $user['id']);
        self::assertSame('upload', $row['avatar_source']);
        self::assertMatchesRegularExpression('~^/media/\d+$~', (string) $row['avatar_path']);
        $attachmentId = (int) substr((string) $row['avatar_path'], strlen('/media/'));
        self::assertSame('finalized', (string) $this->db->fetchValue('SELECT status FROM attachments WHERE id = ?', [$attachmentId]));
        self::assertSame('avatar', (string) $this->db->fetchValue('SELECT purpose FROM attachments WHERE id = ?', [$attachmentId]));

        $profile = $this->get('/u/avataruser');
        $this->assertStatus(200, $profile);
        self::assertStringContainsString('src="/media/' . $attachmentId . '"', $profile->body());
        $this->assertStatus(200, $this->get('/media/' . $attachmentId));

        $removedResponse = $this->post('/settings/avatar/remove');
        $this->assertStatus(200, $removedResponse);
        self::assertStringContainsString('Avatar removed. Other profile edits are not saved.', $removedResponse->body());
        $removed = $this->users()->find((int) $user['id']);
        self::assertSame('monogram', $removed['avatar_source']);
        self::assertNull($removed['avatar_path']);
        self::assertSame((int) $user['id'], (int) $removed['avatar_removed_by']);
        self::assertSame('deleted', (string) $this->db->fetchValue('SELECT status FROM attachments WHERE id = ?', [$attachmentId]));
        $this->assertStatus(404, $this->get('/media/' . $attachmentId));
    }

    public function test_admin_removes_uploaded_avatar_and_audits_profile_media_action(): void
    {
        $admin = $this->makeAdmin(['username' => 'avataradmin']);
        $user = $this->makeUser(['username' => 'avatarsubject']);
        $this->actingAs($user);

        $file = $this->fakeUpload($this->pngBytes(), 'avatar.png', 'image/png');
        $uploaded = $this->postFile('/settings/avatar', 'avatar', $file);
        $this->assertStatus(200, $uploaded);
        self::assertStringContainsString('Avatar updated. Other profile edits are not saved.', $uploaded->body());

        $row = $this->users()->find((int) $user['id']);
        $attachmentId = (int) substr((string) $row['avatar_path'], strlen('/media/'));
        $this->assertStatus(200, $this->get('/media/' . $attachmentId));

        $this->actingAs($admin);
        $record = $this->get('/admin/users/' . (int) $user['id']);
        $this->assertStatus(200, $record);
        self::assertStringContainsString('/admin/users/' . (int) $user['id'] . '/avatar/remove', $record->body());

        $this->assertRedirect(
            $this->post('/admin/users/' . (int) $user['id'] . '/avatar/remove'),
            '/admin/users/' . (int) $user['id'],
        );

        $removed = $this->users()->find((int) $user['id']);
        self::assertSame('monogram', $removed['avatar_source']);
        self::assertNull($removed['avatar_path']);
        self::assertSame((int) $admin['id'], (int) $removed['avatar_removed_by']);
        self::assertSame('deleted', (string) $this->db->fetchValue('SELECT status FROM attachments WHERE id = ?', [$attachmentId]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'clear_avatar'"));
        $this->assertStatus(404, $this->get('/media/' . $attachmentId));

        $profile = $this->get('/u/avatarsubject');
        $this->assertStatus(200, $profile);
        self::assertStringNotContainsString('src="/media/' . $attachmentId . '"', $profile->body());
    }

    public function test_signature_height_is_enforced(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'sigheight']);
        $this->actingAs($user);

        $res = $this->post('/settings/account', [
            'display_name' => 'Sig Height',
            'signature' => "one\ntwo\nthree\nfour",
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Signature is too tall');
    }

    public function test_admin_removes_signature_with_profile_media_flag(): void
    {
        $admin = $this->makeAdmin(['username' => 'sigadmin']);
        $user = $this->makeUser(['username' => 'sigsubject']);
        $this->db->run('UPDATE users SET signature = ? WHERE id = ?', ['spam signature', (int) $user['id']]);

        $this->actingAs($admin);
        $this->assertRedirect($this->post('/admin/users/' . (int) $user['id'] . '/signature/remove'), '/admin/users/' . (int) $user['id']);

        $row = $this->users()->find((int) $user['id']);
        self::assertNull($row['signature']);
        self::assertSame((int) $admin['id'], (int) $row['signature_removed_by']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'clear_signature'"));
    }

    public function test_account_validation_error_preserves_existing_avatar_preview(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'avatarvalidation']);
        $this->db->run(
            'UPDATE users SET avatar_path = ?, avatar_source = ? WHERE id = ?',
            ['/media/321', 'upload', (int) $user['id']],
        );

        $this->actingAs($user);
        $res = $this->post('/settings/account', [
            'display_name' => 'Avatar Validation',
            'signature' => "one\ntwo\nthree\nfour",
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('src="/media/321"', $res->body());
        self::assertStringContainsString('Remove avatar', $res->body());
    }
}
