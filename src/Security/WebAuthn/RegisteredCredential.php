<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/** Verified registration output matching the storable credential columns. */
final class RegisteredCredential
{
    public function __construct(
        public readonly string $credentialId,
        public readonly string $publicKey,
        public readonly int $signCount,
        public readonly ?string $aaguid,
        public readonly string $transports,
        public readonly bool $userVerified,
        public readonly bool $backupEligible,
        public readonly bool $backedUp,
    ) {
    }
}
