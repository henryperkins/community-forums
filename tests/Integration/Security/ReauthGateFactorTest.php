<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Core\ValidationException;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use Tests\Support\TestCase;

final class ReauthGateFactorTest extends TestCase
{
    public function test_password_factor_verifies_and_wrong_password_throws(): void
    {
        $gate = new ReauthGate(new PasswordHasher());
        $user = $this->userEntity($this->makeUser(['password' => 'secret-pass-1']));

        self::assertSame(ReauthGate::FACTOR_PASSWORD, $gate->requireFactor($user, 'secret-pass-1'));

        $this->expectException(ValidationException::class);
        $gate->requireFactor($user, 'wrong');
    }

    public function test_passkey_probe_wins_when_it_returns_true(): void
    {
        $gate = new ReauthGate(new PasswordHasher());
        $user = $this->userEntity($this->makeUser());
        self::assertSame(ReauthGate::FACTOR_PASSKEY, $gate->requireFactor($user, null, static fn (): bool => true));
    }

    public function test_false_passkey_probe_falls_back_to_password_factor(): void
    {
        $gate = new ReauthGate(new PasswordHasher());
        $user = $this->userEntity($this->makeUser(['password' => 'secret-pass-1']));

        self::assertSame(ReauthGate::FACTOR_PASSWORD, $gate->requireFactor($user, 'secret-pass-1', static fn (): bool => false));
    }

    public function test_no_factor_at_all_throws_on_the_named_field(): void
    {
        $gate = new ReauthGate(new PasswordHasher());
        $user = $this->userEntity($this->makeUser());
        try {
            $gate->requireFactor($user, null, null);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
    }
}
