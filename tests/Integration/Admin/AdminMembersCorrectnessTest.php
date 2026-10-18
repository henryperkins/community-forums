<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class AdminMembersCorrectnessTest extends TestCase
{
    public function test_warning_confirmation_excludes_self_but_keeps_other_administrators_actionable(): void
    {
        $admin = $this->makeAdmin(['username' => 'bulk_self_actor']);
        $peer = $this->makeAdmin(['username' => 'bulk_self_peer']);
        $member = $this->makeUser(['username' => 'bulk_self_member']);
        $this->actingAs($admin);

        $response = $this->post('/admin/users/bulk', [
            'bulk_action' => 'warn',
            'selected' => [$admin['id'], $peer['id'], $member['id']],
        ]);

        $this->assertStatus(200, $response);
        self::assertSame(1, substr_count($response->body(), 'skipped — your own account'));
        self::assertStringNotContainsString('skipped — administrator', $response->body());
        self::assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*>Warn 2 members<\/button>/', $response->body());
        self::assertStringContainsString('<h2>Warn 2 members</h2>', $response->body());
    }

    /** @return array<string,array{bool}> */
    public static function selfOrders(): array
    {
        return ['self first' => [true], 'self last' => [false]];
    }

    #[DataProvider('selfOrders')]
    public function test_bulk_warning_skips_self_regardless_of_selection_order(bool $selfFirst): void
    {
        $admin = $this->makeAdmin(['username' => 'bulk_order_actor']);
        $peer = $this->makeAdmin(['username' => 'bulk_order_peer']);
        $member = $this->makeUser(['username' => 'bulk_order_member']);
        $this->actingAs($admin);
        $selected = $selfFirst
            ? [$admin['id'], $peer['id'], $member['id']]
            : [$peer['id'], $member['id'], $admin['id']];

        $response = $this->post('/admin/users/bulk/apply', [
            'bulk_action' => 'warn', 'selected' => $selected, 'reason' => 'One shared warning',
        ]);

        $this->assertRedirectContains($response, '/admin/users');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id = ?', [$admin['id']]));
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id IN (?, ?)', [$peer['id'], $member['id']]));
        self::assertSame(2, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'warn' AND target_id IN (?, ?)", [$peer['id'], $member['id']]));
        $flash = urldecode(implode(' ', $response->cookieHeaders()));
        self::assertStringContainsString('Warned 2 members', $flash);
        self::assertStringContainsString('Skipped: @bulk_order_actor', $flash);
    }

    public function test_shared_validation_still_runs_when_every_selected_target_will_be_skipped(): void
    {
        $admin = $this->makeAdmin(['username' => 'bulk_invalid_actor']);
        $peer = $this->makeAdmin(['username' => 'bulk_invalid_peer']);
        $this->actingAs($admin);

        $response = $this->post('/admin/users/bulk/apply', [
            'bulk_action' => 'suspend', 'selected' => [$admin['id'], $peer['id']],
            'reason' => 'Keep this reason', 'until' => '2026-02-30 12:00:00',
        ]);

        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Keep this reason');
        self::assertStringContainsString('value="2026-02-30 12:00:00"', $response->body());
        self::assertSame(2, substr_count($response->body(), 'name="selected[]"'));
        self::assertSame('active', $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [$peer['id']]));
    }

    public function test_warning_with_invalid_shared_reason_preserves_selection_before_any_warning(): void
    {
        $admin = $this->makeAdmin(['username' => 'bulk_reason_actor']);
        $member = $this->makeUser(['username' => 'bulk_reason_member']);
        $this->actingAs($admin);
        $reason = str_repeat('x', 256);

        $response = $this->post('/admin/users/bulk/apply', [
            'bulk_action' => 'warn', 'selected' => [$admin['id'], $member['id']], 'reason' => $reason,
        ]);

        $this->assertStatus(422, $response);
        self::assertStringContainsString('value="' . $reason . '"', $response->body());
        self::assertSame(2, substr_count($response->body(), 'name="selected[]"'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id IN (?, ?)', [$admin['id'], $member['id']]));
    }

    public function test_shared_reason_is_required_even_for_a_self_only_warning_batch(): void
    {
        $admin = $this->makeAdmin(['username' => 'bulk_empty_self']);
        $this->actingAs($admin);

        $response = $this->post('/admin/users/bulk/apply', [
            'bulk_action' => 'warn', 'selected' => [$admin['id']], 'reason' => '',
        ]);

        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'A shared reason is required');
        self::assertSame(1, substr_count($response->body(), 'name="selected[]"'));
    }

    public function test_invalid_filter_rerender_keeps_the_bulk_error_and_selected_count(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeUser(['username' => 'bulk_filter_recovery']);
        $this->actingAs($admin);

        $response = $this->post('/admin/users/bulk', [
            'q' => 'bulk_filter_recovery', 'joined_to' => '2026-02-30',
            'selected' => [$member['id']], 'bulk_action' => '',
        ]);

        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Choose an action to apply to the selected members.');
        $this->assertSeeText($response, '1 member selected');
        self::assertStringContainsString('value="2026-02-30"', $response->body());
        $dom = new \DOMDocument();
        $dom->loadHTML($response->body(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $selectionNodes = (new \DOMXPath($dom))->query('//form[@action="/admin/users/bulk"]//input[@name="selected[]"]');
        self::assertNotFalse($selectionNodes);
        $preservedIds = [];
        foreach ($selectionNodes as $node) {
            $preservedIds[] = $node->getAttribute('value');
        }
        self::assertSame([(string) $member['id']], $preservedIds);

        $confirmed = $this->post('/admin/users/bulk', ['bulk_action' => 'warn', 'selected' => $preservedIds]);
        $this->assertStatus(200, $confirmed);
        $this->assertSeeText($confirmed, '@bulk_filter_recovery');
    }

    public function test_directory_joined_range_includes_the_whole_final_utc_day(): void
    {
        $this->actingAs($this->makeAdmin());
        foreach (['before' => '2026-10-17 23:59:59', 'start' => '2026-10-18 00:00:00', 'noon' => '2026-10-18 12:00:00', 'end' => '2026-10-18 23:59:59', 'after' => '2026-10-19 00:00:00'] as $name => $createdAt) {
            $member = $this->makeUser(['username' => 'joined_day_' . $name]);
            $this->db->run('UPDATE users SET created_at = ? WHERE id = ?', [$createdAt, $member['id']]);
        }

        $response = $this->get('/admin/users', ['q' => 'joined_day_', 'joined_from' => '2026-10-18', 'joined_to' => '2026-10-18']);

        $this->assertStatus(200, $response);
        foreach (['start', 'noon', 'end'] as $name) {
            $this->assertSeeText($response, 'joined_day_' . $name);
        }
        foreach (['before', 'after'] as $name) {
            $this->assertDontSeeText($response, 'joined_day_' . $name);
        }
        $this->assertSeeText($response, '3 members of 3');
    }

    /** @return array<string,array{array<string,string>,string}> */
    public static function invalidJoinedRanges(): array
    {
        return [
            'impossible date' => [['joined_to' => '2026-02-30'], 'joined_to'],
            'date with time' => [['joined_from' => '2026-10-18 12:00:00'], 'joined_from'],
            'out of range year' => [['joined_to' => '10000-01-01'], 'joined_to'],
            'outside database date range' => [['joined_from' => '0000-01-01'], 'joined_from'],
            'reversed range' => [['joined_from' => '2026-10-19', 'joined_to' => '2026-10-18'], 'joined_to'],
        ];
    }

    #[DataProvider('invalidJoinedRanges')]
    public function test_invalid_joined_range_is_a_recoverable_422(array $range, string $invalidField): void
    {
        $this->actingAs($this->makeAdmin());
        $member = $this->makeUser(['username' => 'invalid_range_member']);

        $response = $this->get('/admin/users', ['q' => 'invalid_range_member'] + $range);

        $this->assertStatus(422, $response);
        self::assertStringContainsString('name="q"', $response->body());
        self::assertStringContainsString('value="invalid_range_member"', $response->body());
        self::assertStringContainsString('name="' . $invalidField . '"', $response->body());
        self::assertStringContainsString('value="' . $range[$invalidField] . '"', $response->body());
        // Invalid bounds must not broaden the result into an unfiltered list.
        self::assertStringNotContainsString('href="/admin/users/' . $member['id'] . '"', $response->body());
    }

    public function test_null_byte_in_date_returns_422_without_a_parser_error(): void
    {
        $this->actingAs($this->makeAdmin());

        $response = $this->get('/admin/users', ['joined_to' => "2026-10\0-18"]);

        $this->assertStatus(422, $response);
    }
}
