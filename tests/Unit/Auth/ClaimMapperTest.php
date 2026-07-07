<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Core\OidcVerificationException;
use App\Service\OAuth\Oidc\ClaimMapper;
use PHPUnit\Framework\TestCase;

/**
 * Inc 8 (P5-12) — verified claims → NormalizedIdentity. The subject claim is
 * fixed (`sub`, never remappable — the identity key must not be operator-
 * configurable), email_verified is strict-boolean (a "true" string is NOT
 * verification — TM-ID-04's never-auto-merge posture starts here), and
 * claim_map_json can only rename the cosmetic claims.
 */
final class ClaimMapperTest extends TestCase
{
    public function test_maps_the_standard_claim_set(): void
    {
        $id = (new ClaimMapper())->map([
            'sub' => '4213', 'email' => 'ada@example.test', 'email_verified' => true,
            'name' => 'Ada Lovelace', 'preferred_username' => 'ada', 'picture' => 'https://cdn.idp.test/a.png',
        ], $this->row());

        self::assertSame('gitlab', $id->provider);
        self::assertSame('4213', $id->providerUserId);
        self::assertSame('ada@example.test', $id->email);
        self::assertTrue($id->emailVerified);
        self::assertSame('Ada Lovelace', $id->displayName);
        self::assertSame('https://cdn.idp.test/a.png', $id->avatarUrl);
        self::assertSame(7, $id->providerConfigId);
    }

    public function test_numeric_sub_is_treated_as_an_opaque_string(): void
    {
        // GitLab subjects are numeric user ids; they must round-trip as strings.
        $id = (new ClaimMapper())->map(['sub' => 4213] + $this->claims(), $this->row());
        self::assertSame('4213', $id->providerUserId);
    }

    public function test_email_verified_is_strict_boolean(): void
    {
        $mapper = new ClaimMapper();
        self::assertFalse($mapper->map($this->claims(['email_verified' => 'true']), $this->row())->emailVerified);
        self::assertFalse($mapper->map($this->claims(['email_verified' => 1]), $this->row())->emailVerified);
        self::assertFalse($mapper->map($this->claims(['email_verified' => null]), $this->row())->emailVerified);
        $noClaim = $this->claims();
        unset($noClaim['email_verified']);
        self::assertFalse($mapper->map($noClaim, $this->row())->emailVerified);
        self::assertTrue($mapper->map($this->claims(['email_verified' => true]), $this->row())->emailVerified);
    }

    public function test_missing_email_yields_null_and_never_a_verified_flag(): void
    {
        // GitLab's private-email setting: no email claim at all (§9 collision arm).
        $claims = $this->claims(['email_verified' => true]);
        unset($claims['email']);
        $id = (new ClaimMapper())->map($claims, $this->row());

        self::assertNull($id->email);
        self::assertFalse($id->emailVerified, 'verification cannot exist without an email');
    }

    public function test_display_name_falls_back_to_preferred_username(): void
    {
        $claims = $this->claims(['preferred_username' => 'ada']);
        unset($claims['name']);
        self::assertSame('ada', (new ClaimMapper())->map($claims, $this->row())->displayName);
    }

    public function test_non_https_avatar_is_dropped(): void
    {
        $mapper = new ClaimMapper();
        self::assertNull($mapper->map($this->claims(['picture' => 'http://cdn.idp.test/a.png']), $this->row())->avatarUrl);
        self::assertNull($mapper->map($this->claims(['picture' => 'javascript:alert(1)']), $this->row())->avatarUrl);
    }

    public function test_claim_map_json_renames_cosmetic_claims_only(): void
    {
        $row = $this->row(['claim_map_json' => (string) json_encode([
            'email' => 'upn', 'email_verified' => 'upn_verified', 'name' => 'displayName', 'username' => 'handle', 'picture' => 'photo',
        ])]);
        $claims = [
            'sub' => 'x-1', 'upn' => 'ada@corp.test', 'upn_verified' => true,
            'displayName' => 'Ada L', 'handle' => 'ada', 'photo' => 'https://corp.test/a.png',
            // A hostile map cannot repoint the subject:
            'other_sub' => 'victim',
        ];

        $id = (new ClaimMapper())->map($claims, $row);
        self::assertSame('ada@corp.test', $id->email);
        self::assertTrue($id->emailVerified);
        self::assertSame('Ada L', $id->displayName);
        self::assertSame('https://corp.test/a.png', $id->avatarUrl);
        self::assertSame('x-1', $id->providerUserId, 'sub is never remappable');
    }

    public function test_invalid_claim_map_json_falls_back_to_defaults(): void
    {
        $row = $this->row(['claim_map_json' => '{not json']);
        $id = (new ClaimMapper())->map($this->claims(), $row);
        self::assertSame('ada@example.test', $id->email);
    }

    public function test_empty_subject_is_refused(): void
    {
        $this->expectExceptionObject(new OidcVerificationException('subject_missing'));
        (new ClaimMapper())->map($this->claims(['sub' => '']), $this->row());
    }

    // ---- helpers ------------------------------------------------------------

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function claims(array $overrides = []): array
    {
        return $overrides + [
            'sub' => 'sub-1',
            'email' => 'ada@example.test',
            'email_verified' => true,
            'name' => 'Ada Lovelace',
        ];
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'id' => 7,
            'provider_key' => 'gitlab',
            'claim_map_json' => null,
        ];
    }
}
