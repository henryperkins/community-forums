<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Core\FeatureFlags;
use App\Repository\SettingRepository;
use App\Security\RegistrationPolicy;
use Tests\Support\TestCase;

/**
 * The single interpretation seam for `registration_mode` (P3-05 setting,
 * P5-13 `invite` value). Both the password form (AuthController) and the
 * OAuth provisioning channel (OAuthService) read THIS class, so the two
 * account-creation paths can never disagree.
 *
 * `invite` requires the `invitations` feature: while the flag is dark the
 * effective mode degrades to `closed` — fail closed (owner decision
 * 2026-07-08, docs/phase5/invitation-defaults.md).
 */
final class RegistrationPolicyTest extends TestCase
{
    private function policy(): RegistrationPolicy
    {
        // Fresh instances per call: FeatureFlags memoizes its settings read.
        return new RegistrationPolicy(new SettingRepository($this->db), new FeatureFlags(new SettingRepository($this->db)));
    }

    public function test_open_and_closed_pass_through_regardless_of_flag(): void
    {
        (new SettingRepository($this->db))->set('registration_mode', 'open');
        self::assertSame('open', $this->policy()->configuredMode());
        self::assertSame('open', $this->policy()->effectiveMode());

        (new SettingRepository($this->db))->set('registration_mode', 'closed');
        self::assertSame('closed', $this->policy()->configuredMode());
        self::assertSame('closed', $this->policy()->effectiveMode());
    }

    public function test_invite_is_effective_only_while_the_invitations_flag_is_on(): void
    {
        (new SettingRepository($this->db))->set('registration_mode', 'invite');
        (new SettingRepository($this->db))->set('features', ['invitations' => false]);

        // Explicit rollback: fail closed, but the configured value survives so the
        // console keeps showing what the operator chose.
        self::assertSame('invite', $this->policy()->configuredMode());
        self::assertSame('closed', $this->policy()->effectiveMode());

        (new SettingRepository($this->db))->set('features', ['invitations' => true]);
        self::assertSame('invite', $this->policy()->effectiveMode());
    }

    public function test_unknown_stored_mode_is_preserved_but_enforces_closed(): void
    {
        (new SettingRepository($this->db))->set('registration_mode', 'banana');
        self::assertSame('banana', $this->policy()->configuredMode());
        self::assertSame('closed', $this->policy()->effectiveMode());
    }
}
