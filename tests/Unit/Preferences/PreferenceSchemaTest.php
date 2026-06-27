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
}
