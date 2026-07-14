<?php

declare(strict_types=1);

namespace Tests\Unit\Preferences;

use App\Support\PreferenceSchema;
use PHPUnit\Framework\TestCase;

/**
 * P3-01: the preference schema is the trust boundary for the per-user JSON blob.
 * Malformed/unknown values must never break rendering or bypass a server rule.
 */
final class PreferenceSchemaTest extends TestCase
{
    public function test_defaults_cover_every_appearance_key_and_stamp_version(): void
    {
        $d = PreferenceSchema::defaults();
        self::assertSame(PreferenceSchema::VERSION, $d['__v']);
        self::assertSame('system', $d['theme']);
        self::assertSame('comfortable', $d['density']);
        self::assertSame('medium', $d['font_size']);
        self::assertFalse($d['reduced_motion']);
        self::assertTrue($d['show_signatures']);
        self::assertTrue($d['enter_to_send']);
        // Per-page keys carry a null default → absent from the flat defaults.
        self::assertArrayNotHasKey('threads_per_page', $d);
    }

    public function test_resolve_merges_defaults_for_empty_blob(): void
    {
        $r = PreferenceSchema::resolve([]);
        self::assertSame('system', $r['theme']);
        self::assertSame('last_post', $r['thread_sort']);
        self::assertTrue($r['show_avatars']);
    }

    public function test_resolve_drops_invalid_enum_and_falls_back_to_default(): void
    {
        $r = PreferenceSchema::resolve(['theme' => 'neon', 'density' => 'compact']);
        self::assertSame('system', $r['theme']);   // invalid → default
        self::assertSame('compact', $r['density']); // valid → kept
    }

    public function test_resolve_drops_out_of_range_per_page(): void
    {
        $r = PreferenceSchema::resolve(['posts_per_page' => 9999, 'threads_per_page' => 50]);
        self::assertArrayNotHasKey('posts_per_page', $r); // invalid → absent (server default applies)
        self::assertSame(50, $r['threads_per_page']);
    }

    public function test_resolve_preserves_unknown_keys(): void
    {
        $r = PreferenceSchema::resolve(['hide_from_leaderboard' => true, 'bogus' => 'x']);
        self::assertTrue($r['hide_from_leaderboard']);
        self::assertSame('x', $r['bogus']);
    }

    public function test_resolve_ignores_malformed_types(): void
    {
        // A tampered blob with wrong types must not throw and must not leak.
        $r = PreferenceSchema::resolve(['theme' => ['array'], 'reduced_motion' => 'yes', 'posts_per_page' => 'DROP TABLE']);
        self::assertSame('system', $r['theme']);
        self::assertTrue($r['reduced_motion']); // 'yes' is truthy → true
        self::assertArrayNotHasKey('posts_per_page', $r);
    }

    public function test_validate_section_writes_every_bool_in_section(): void
    {
        // Composing form with only show_preview checked → the other toggles persist false.
        $changes = PreferenceSchema::validateSection('composing', ['show_preview' => '1']);
        self::assertTrue($changes['show_preview']);
        self::assertFalse($changes['enter_to_send']);
        self::assertFalse($changes['smart_lists']);
    }

    public function test_validate_section_invalid_per_page_becomes_null(): void
    {
        $changes = PreferenceSchema::validateSection('reading', ['posts_per_page' => '7', 'threads_per_page' => '25']);
        self::assertNull($changes['posts_per_page']); // removed → server default
        self::assertSame(25, $changes['threads_per_page']);
    }

    public function test_unknown_section_is_inert(): void
    {
        self::assertSame([], PreferenceSchema::validateSection('nope', ['x' => 1]));
        self::assertFalse(PreferenceSchema::hasSection('nope'));
        self::assertTrue(PreferenceSchema::hasSection('appearance'));
    }

    // ---- Versioned upgrade path (P3-01) ----------------------------------

    public function test_upgrade_ignores_an_empty_blob(): void
    {
        // A never-saved user keeps an empty blob (no stray __v persisted).
        self::assertSame([], PreferenceSchema::upgrade([]));
    }

    public function test_upgrade_stamps_the_current_version_on_a_legacy_blob(): void
    {
        // No __v marker → treated as legacy and brought to the current version.
        $up = PreferenceSchema::upgrade(['theme' => 'dark', 'density' => 'compact']);
        self::assertSame(PreferenceSchema::VERSION, $up['__v']);
        self::assertSame('dark', $up['theme']);     // valid value preserved
        self::assertSame('compact', $up['density']);
    }

    public function test_upgrade_is_idempotent_for_a_current_blob(): void
    {
        $current = ['__v' => PreferenceSchema::VERSION, 'theme' => 'light', 'show_avatars' => false];
        self::assertSame($current, PreferenceSchema::upgrade($current));
    }

    public function test_upgrade_drops_invalid_known_values_but_keeps_unknown_keys(): void
    {
        $up = PreferenceSchema::upgrade([
            '__v' => 1,
            'theme' => 'midnight',           // invalid enum → dropped (falls to default)
            'threads_per_page' => 999,       // out of range → dropped
            'font_size' => 'large',          // valid → kept
            'hide_from_leaderboard' => true, // unknown (other subsystem) → kept
        ]);
        self::assertArrayNotHasKey('theme', $up);
        self::assertArrayNotHasKey('threads_per_page', $up);
        self::assertSame('large', $up['font_size']);
        self::assertTrue($up['hide_from_leaderboard']);
        self::assertSame(PreferenceSchema::VERSION, $up['__v']);
    }

    public function test_upgrade_never_downgrades_a_future_blob(): void
    {
        // A blob written by a newer deploy must be left untouched (rollback-safe).
        $future = ['__v' => PreferenceSchema::VERSION + 5, 'theme' => 'dark'];
        self::assertSame($future, PreferenceSchema::upgrade($future));
    }

    public function test_resolve_upgrades_a_legacy_blob_and_fills_new_defaults(): void
    {
        // A v1-era blob predates the reading-display keys; resolve() upgrades it,
        // keeps the old value, and fills the new keys with their defaults.
        $r = PreferenceSchema::resolve(['__v' => 1, 'theme' => 'dark']);
        self::assertSame(PreferenceSchema::VERSION, $r['__v']);
        self::assertSame('dark', $r['theme']);
        self::assertSame('last_post', $r['thread_sort']); // new-in-v2 default
        self::assertTrue($r['show_reactions']);
    }
}
