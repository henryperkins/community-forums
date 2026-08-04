<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Service\AccountRecoveryService;
use InvalidArgumentException;
use Tests\Support\TestCase;

/**
 * Out-of-band recovery (`bin/console user:password`) — the way back in when mail
 * is unconfigured and /forgot therefore cannot deliver a reset link.
 */
final class AccountRecoveryServiceTest extends TestCase
{
    private function service(): AccountRecoveryService
    {
        return new AccountRecoveryService(
            new UserRepository($this->db),
            new SessionRepository($this->db),
            new PasswordHasher(),
            $this->config,
        );
    }

    private function storedHash(int $userId): string
    {
        return (string) $this->db->fetchValue('SELECT password_hash FROM users WHERE id = ?', [$userId]);
    }

    public function test_resets_by_email_and_the_new_password_verifies(): void
    {
        $admin = $this->makeAdmin(['email' => 'founder@example.test', 'password' => 'original-password']);

        $result = $this->service()->resetPassword('founder@example.test', 'brand-new-password');

        self::assertFalse($result['generated']);
        self::assertSame('brand-new-password', $result['password']);
        self::assertSame((int) $admin['id'], (int) $result['user']['id']);
        self::assertTrue(password_verify('brand-new-password', $this->storedHash((int) $admin['id'])));
        self::assertFalse(password_verify('original-password', $this->storedHash((int) $admin['id'])));
    }

    public function test_resets_by_username(): void
    {
        $admin = $this->makeAdmin(['username' => 'founder', 'password' => 'original-password']);

        $this->service()->resetPassword('founder', 'another-password');

        self::assertTrue(password_verify('another-password', $this->storedHash((int) $admin['id'])));
    }

    public function test_generates_a_usable_password_when_none_is_supplied(): void
    {
        $admin = $this->makeAdmin(['email' => 'founder@example.test']);

        $result = $this->service()->resetPassword('founder@example.test');

        self::assertTrue($result['generated']);
        self::assertSame(20, strlen($result['password']));
        // No ambiguous glyphs to mistype off a terminal.
        self::assertSame(0, preg_match('/[0O1lI]/', $result['password']));
        self::assertTrue(password_verify($result['password'], $this->storedHash((int) $admin['id'])));
    }

    public function test_revokes_every_existing_session_for_the_account(): void
    {
        $admin = $this->makeAdmin(['email' => 'founder@example.test']);
        $sessions = new SessionRepository($this->db);
        $sessions->create([
            'id' => str_repeat('a', 64),
            'user_id' => (int) $admin['id'],
            'csrf_secret' => str_repeat('b', 64),
            'user_agent' => 'test',
            'ip' => '127.0.0.1',
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        ]);
        self::assertNotNull($sessions->findActive(str_repeat('a', 64)));

        $this->service()->resetPassword('founder@example.test', 'brand-new-password');

        self::assertNull($sessions->findActive(str_repeat('a', 64)));
    }

    /** A suspended or banned admin still needs a working password to be reinstated. */
    public function test_recovers_a_suspended_account(): void
    {
        $admin = $this->makeAdmin(['email' => 'founder@example.test', 'status' => 'suspended']);

        $this->service()->resetPassword('founder@example.test', 'brand-new-password');

        self::assertTrue(password_verify('brand-new-password', $this->storedHash((int) $admin['id'])));
    }

    public function test_rejects_an_unknown_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No account matches');

        $this->service()->resetPassword('nobody@example.invalid', 'brand-new-password');
    }

    public function test_rejects_a_password_under_the_configured_minimum(): void
    {
        $this->makeAdmin(['email' => 'founder@example.test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least');

        $this->service()->resetPassword('founder@example.test', 'short');
    }
}
