<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppProfileMediaTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_profile_media_routes_are_dark_by_default(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'avatardark']);
        $this->actingAs($user);

        $file = $this->fakeUpload($this->pngBytes(), 'avatar.png', 'image/png');
        $this->assertStatus(404, $this->postFile('/settings/avatar', 'avatar', $file));
        $this->assertStatus(404, $this->post('/settings/avatar/remove'));
    }

    public function test_user_uploads_and_removes_avatar_when_profile_media_enabled(): void
    {
        $this->makeAdmin();
        $this->setFlags(['profile_media' => true]);
        $user = $this->makeUser(['username' => 'avataruser']);
        $this->actingAs($user);

        $file = $this->fakeUpload($this->pngBytes(), 'avatar.png', 'image/png');
        $this->assertRedirect($this->postFile('/settings/avatar', 'avatar', $file), '/settings/account');

        $row = $this->users()->find((int) $user['id']);
        self::assertSame('upload', $row['avatar_source']);
        self::assertMatchesRegularExpression('~^/media/\d+$~', (string) $row['avatar_path']);
        $attachmentId = (int) substr((string) $row['avatar_path'], strlen('/media/'));
        self::assertSame('finalized', (string) $this->db->fetchValue('SELECT status FROM attachments WHERE id = ?', [$attachmentId]));
        self::assertSame('avatar', (string) $this->db->fetchValue('SELECT purpose FROM attachments WHERE id = ?', [$attachmentId]));

        $profile = $this->get('/u/avataruser');
        $this->assertStatus(200, $profile);
        self::assertStringContainsString('src="/media/' . $attachmentId . '"', $profile->body());

        $this->assertRedirect($this->post('/settings/avatar/remove'), '/settings/account');
        $removed = $this->users()->find((int) $user['id']);
        self::assertSame('monogram', $removed['avatar_source']);
        self::assertNull($removed['avatar_path']);
        self::assertSame((int) $user['id'], (int) $removed['avatar_removed_by']);
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
        $this->setFlags(['profile_media' => true]);
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
        $this->setFlags(['profile_media' => true]);
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
