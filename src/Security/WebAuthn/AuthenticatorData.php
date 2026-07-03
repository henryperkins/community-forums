<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * WebAuthn authenticator-data structure parser.
 */
final class AuthenticatorData
{
    private const FLAG_UP = 0x01;
    private const FLAG_UV = 0x04;
    private const FLAG_BE = 0x08;
    private const FLAG_BS = 0x10;
    private const FLAG_AT = 0x40;
    private const FLAG_ED = 0x80;

    private function __construct(
        public readonly string $rpIdHash,
        public readonly int $flags,
        public readonly int $signCount,
        public readonly ?string $aaguid,
        public readonly ?string $credentialId,
        public readonly ?string $credentialPublicKey,
    ) {
    }

    public static function parse(string $bytes): self
    {
        if (strlen($bytes) < 37) {
            throw new WebAuthnException('malformed_authenticator_data', 'Authenticator data shorter than 37 bytes.');
        }

        $rpIdHash = substr($bytes, 0, 32);
        $flags = ord($bytes[32]);
        $signCount = unpack('N', substr($bytes, 33, 4))[1];
        $rest = substr($bytes, 37);

        $aaguid = null;
        $credentialId = null;
        $credentialPublicKey = null;

        if (($flags & self::FLAG_AT) !== 0) {
            if (strlen($rest) < 18) {
                throw new WebAuthnException('malformed_authenticator_data', 'Attested credential data truncated.');
            }

            $aaguid = substr($rest, 0, 16);
            $idLen = unpack('n', substr($rest, 16, 2))[1];
            if ($idLen < 1 || $idLen > 1023 || strlen($rest) < 18 + $idLen) {
                throw new WebAuthnException('malformed_authenticator_data', 'Credential id length out of range.');
            }

            $credentialId = substr($rest, 18, $idLen);
            $coseStart = 18 + $idLen;
            [$coseMap, $after] = CborDecoder::decodeFirst(substr($rest, $coseStart));
            if (!is_array($coseMap)) {
                throw new WebAuthnException('malformed_authenticator_data', 'Credential public key is not a COSE map.');
            }

            $coseLen = strlen($rest) - $coseStart - strlen($after);
            $credentialPublicKey = substr($rest, $coseStart, $coseLen);
            $rest = $after;
        }

        if (($flags & self::FLAG_ED) !== 0) {
            [, $rest] = CborDecoder::decodeFirst($rest);
        }

        if ($rest !== '') {
            throw new WebAuthnException('malformed_authenticator_data', 'Trailing bytes after authenticator data.');
        }

        return new self($rpIdHash, $flags, $signCount, $aaguid, $credentialId, $credentialPublicKey);
    }

    public function userPresent(): bool
    {
        return ($this->flags & self::FLAG_UP) !== 0;
    }

    public function userVerified(): bool
    {
        return ($this->flags & self::FLAG_UV) !== 0;
    }

    public function backupEligible(): bool
    {
        return ($this->flags & self::FLAG_BE) !== 0;
    }

    public function backedUp(): bool
    {
        return ($this->flags & self::FLAG_BS) !== 0;
    }
}
