<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Core\FeatureFlags;
use App\Support\FeatureFlagCatalog;
use PHPUnit\Framework\TestCase;

final class FeatureFlagCatalogTest extends TestCase
{
    /**
     * Phrases the inventory owns elsewhere on the page. The confirm panels render
     * (closed) for every row, and AppAdminFeaturesTest asserts several of these
     * are absent from the whole body, so copy must never introduce them.
     */
    private const RESERVED_PHRASES = [
        'Override on', 'Override off', 'Effective on', 'Effective off', 'No override',
        'Resolver posture is', 'giphy_public_key is unset', 'no host is allowlisted',
        'no board has opted in', 'Missing admin operations', 'Ready for acceptance',
        'Operational configuration required', 'unknown override',
    ];

    public function test_every_declared_flag_is_described_and_nothing_else(): void
    {
        $declared = array_keys(FeatureFlags::defaults());
        $described = FeatureFlagCatalog::described();
        sort($declared);
        sort($described);

        self::assertSame($declared, $described);
    }

    public function test_copy_is_plain_sentences_that_leave_the_write_to_the_template(): void
    {
        foreach (FeatureFlagCatalog::described() as $flag) {
            $entry = FeatureFlagCatalog::entry($flag);
            self::assertNotSame('', $entry['label'], $flag);
            self::assertStringEndsNotWith('.', $entry['label'], $flag);
            foreach (['on', 'off', 'warn_on', 'warn_off'] as $key) {
                $text = $entry[$key];
                if ($text === null) {
                    continue;
                }
                self::assertStringEndsWith('.', $text, "$flag.$key");
                self::assertLessThanOrEqual(240, mb_strlen($text), "$flag.$key");
                self::assertDoesNotMatchRegularExpression('/[<>]|settings\.features|\boverride\b|\baudit\b/i', $text, "$flag.$key");
                foreach (self::RESERVED_PHRASES as $phrase) {
                    self::assertStringNotContainsStringIgnoringCase($phrase, $text, "$flag.$key");
                }
            }
        }
    }

    public function test_an_undescribed_flag_gets_the_runbooks_generic_rollback_sentence(): void
    {
        $entry = FeatureFlagCatalog::entry('not_a_declared_flag');

        self::assertSame('not_a_declared_flag', $entry['label']);
        self::assertSame('Its pages and actions go offline until it is turned back on; no data is deleted.', $entry['off']);
        self::assertNull($entry['warn_off']);
    }

    public function test_sign_in_flags_warn_about_lockout_before_they_are_turned_off(): void
    {
        foreach (['oauth', 'passkeys', 'provider_registry'] as $flag) {
            self::assertStringContainsString('reset a password by email', (string) FeatureFlagCatalog::entry($flag)['warn_off'], $flag);
        }
    }
}
