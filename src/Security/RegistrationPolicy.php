<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\FeatureFlags;
use App\Repository\SettingRepository;

/**
 * The single interpretation seam for `registration_mode` (P3-05 setting,
 * P5-13 `invite` value). Both the password form (AuthController) and the
 * OAuth provisioning channel (OAuthService) consume THIS class so the two
 * account-creation paths can never disagree.
 *
 * `invite` requires the `invitations` feature: while the flag is dark the
 * effective mode degrades to `closed` (fail closed — a paused invitation
 * subsystem must not silently reopen public registration, and a configured
 * invite-only site must not admit uninvited members). Owner decision
 * 2026-07-08, docs/phase5/invitation-defaults.md.
 */
final class RegistrationPolicy
{
    public const MODES = ['open', 'invite', 'closed'];

    public function __construct(
        private SettingRepository $settings,
        private FeatureFlags $flags,
    ) {
    }

    /** The stored operator choice, preserved for diagnostics/UI even if unknown. */
    public function configuredMode(): string
    {
        return $this->settings->getString('registration_mode', 'open');
    }

    /** What enforcement actually applies right now. */
    public function effectiveMode(): string
    {
        $mode = $this->configuredMode();
        if (!in_array($mode, self::MODES, true)) {
            return 'closed';
        }
        if ($mode === 'invite' && !$this->flags->enabled('invitations')) {
            return 'closed';
        }
        return $mode;
    }
}
