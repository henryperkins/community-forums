<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Core\ValidationException;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use Tests\Support\TestCase;

/**
 * Foundation F7 - the unified present-factor reauthentication gate.
 * One window/factor policy: the factor is presented with the request itself
 * (window zero, matching the five call sites it consolidates); Inc 7 adds
 * passkey as a second factor. Messages and field keys stay byte-identical.
 */
final class ReauthGateTest extends TestCase
{
    private function gate(): ReauthGate
    {
        return new ReauthGate(new PasswordHasher());
    }

    public function test_correct_password_passes(): void
    {
        $user = $this->userEntity($this->makeUser(['password' => 'password123']));
        $this->gate()->requirePassword($user, 'password123');
        self::assertTrue($this->gate()->verifyPassword($user, 'password123'));
    }

    public function test_wrong_password_throws_the_exact_legacy_message(): void
    {
        $user = $this->userEntity($this->makeUser(['password' => 'password123']));
        try {
            $this->gate()->requirePassword($user, 'nope');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['current_password' => 'Your current password is incorrect.'], $e->errors);
        }
    }

    public function test_custom_field_key_is_honored(): void
    {
        $user = $this->userEntity($this->makeUser(['password' => 'password123']));
        try {
            $this->gate()->requirePassword($user, 'nope', 'admin_password');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('admin_password', $e->errors);
        }
    }

    public function test_account_without_password_uses_missing_password_message_when_given(): void
    {
        $row = $this->makeUser(['username' => 'oauthonly']);
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$row['id']]);
        $user = $this->users()->findEntity((int) $row['id']);
        self::assertNotNull($user);

        try {
            $this->gate()->requirePassword($user, 'anything', 'current_password', 'Set a password before managing two-factor authentication.');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['current_password' => 'Set a password before managing two-factor authentication.'], $e->errors);
        }
    }

    public function test_account_without_password_falls_through_to_incorrect_when_no_message_given(): void
    {
        $row = $this->makeUser(['username' => 'oauthonly2']);
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$row['id']]);
        $user = $this->users()->findEntity((int) $row['id']);
        self::assertNotNull($user);

        self::assertFalse($this->gate()->verifyPassword($user, 'anything'));
        try {
            $this->gate()->requirePassword($user, 'anything');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['current_password' => 'Your current password is incorrect.'], $e->errors);
        }
    }
}
