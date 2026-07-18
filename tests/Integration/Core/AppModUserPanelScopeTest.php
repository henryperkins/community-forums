<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

/**
 * PR #44 remediation (spec §2): /mod/u/{id} is board-scoped for non-admin
 * staff. A moderator sees only subjects who participated in a board they
 * moderate (else the panel and its writes 404 exactly like a missing user),
 * a reduced model (identity summary + overlap-scoped warnings + warn form —
 * no notes, bans, audit trail, site-wide warnings, or email), and their warn
 * must carry a validated in-overlap board attribution. Notes are admin-only.
 * Warn submissions are idempotent via the submission_idempotency seam.
 */
final class AppModUserPanelScopeTest extends TestCase
{
    /**
     * @return array{admin:array<string,mixed>,mod:array<string,mixed>,subject:array<string,mixed>,boardA:array<string,mixed>,boardB:array<string,mixed>}
     */
    private function seedScoped(bool $subjectParticipatesInA = true): array
    {
        $admin = $this->makeAdmin(['username' => 'scoperoot']);
        $boardA = $this->makeBoard($this->makeCategory('ScopeA'), ['name' => 'Alpha Lounge']);
        $boardB = $this->makeBoard($this->makeCategory('ScopeB'), ['name' => 'Beta Annex']);
        $mod = $this->makeUser(['username' => 'scopemod', 'password' => 'password123']);
        (new BoardModeratorRepository($this->db))->assign((int) $boardA['id'], (int) $mod['id']);
        $subject = $this->makeUser(['username' => 'scopesubject']);
        // The subject always participates in board B (out of the mod's scope);
        // participation in A is the variable under test.
        $this->makeThread($boardB, $subject, 'Beta topic', 'Beta body.');
        if ($subjectParticipatesInA) {
            $this->makeThread($boardA, $subject, 'Alpha topic', 'Alpha body.');
        }
        return ['admin' => $admin, 'mod' => $mod, 'subject' => $subject, 'boardA' => $boardA, 'boardB' => $boardB];
    }

    public function test_out_of_scope_subject_is_404_on_panel_and_warn(): void
    {
        $seed = $this->seedScoped(subjectParticipatesInA: false);
        $this->actingAs($seed['mod']);
        $id = (int) $seed['subject']['id'];

        $this->assertStatus(404, $this->get('/mod/u/' . $id));
        $this->assertStatus(404, $this->post('/mod/u/' . $id . '/warn', [
            'reason' => 'valid reason',
            'board_id' => (string) (int) $seed['boardA']['id'],
        ]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings'));
    }

    public function test_in_scope_panel_is_reduced_to_the_scoped_model(): void
    {
        $seed = $this->seedScoped();
        $id = (int) $seed['subject']['id'];
        // History rows that must NOT reach a scoped moderator:
        $this->db->run(
            'INSERT INTO warnings (user_id, issued_by, board_id, reason, created_at) VALUES (?, ?, NULL, ?, UTC_TIMESTAMP())',
            [$id, (int) $seed['admin']['id'], 'SITEWIDE-MARKER-W1'],
        );
        $this->db->run(
            'INSERT INTO warnings (user_id, issued_by, board_id, reason, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$id, (int) $seed['admin']['id'], (int) $seed['boardB']['id'], 'OUTSCOPE-MARKER-W2'],
        );
        $this->db->run(
            'INSERT INTO warnings (user_id, issued_by, board_id, reason, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$id, (int) $seed['admin']['id'], (int) $seed['boardA']['id'], 'INSCOPE-MARKER-W3'],
        );
        $this->db->run(
            'INSERT INTO user_notes (subject_user_id, author_id, body, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$id, (int) $seed['admin']['id'], 'NOTE-MARKER-N1'],
        );
        $this->db->run(
            "INSERT INTO bans (user_id, scope, type, reason, created_by, created_at, expires_at) VALUES (?, 'site', 'post', ?, ?, UTC_TIMESTAMP(), NULL)",
            [$id, 'BAN-MARKER-B1', (int) $seed['admin']['id']],
        );
        $this->db->run(
            "INSERT INTO moderation_log (actor_id, action, target_type, target_id, reason, created_at) VALUES (?, 'suspend', 'user', ?, ?, UTC_TIMESTAMP())",
            [(int) $seed['admin']['id'], $id, 'AUDIT-MARKER-L1'],
        );

        $this->actingAs($seed['mod']);
        $res = $this->get('/mod/u/' . $id);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, '@scopesubject');
        // The warn form offers the overlap board by name.
        $this->assertSeeText($res, 'Alpha Lounge');
        // Scoped warnings only.
        $this->assertSeeText($res, 'INSCOPE-MARKER-W3');
        $this->assertDontSeeText($res, 'SITEWIDE-MARKER-W1');
        $this->assertDontSeeText($res, 'OUTSCOPE-MARKER-W2');
        // No notes, bans, audit, or PII.
        $this->assertDontSeeText($res, 'Private staff note');
        $this->assertDontSeeText($res, 'NOTE-MARKER-N1');
        $this->assertDontSeeText($res, 'BAN-MARKER-B1');
        $this->assertDontSeeText($res, 'AUDIT-MARKER-L1');
        $this->assertDontSeeText($res, (string) $seed['subject']['email']);
        $this->assertDontSeeText($res, '/admin/users/');
    }

    public function test_deleted_content_still_counts_as_participation(): void
    {
        $seed = $this->seedScoped();
        // The subject's only Alpha thread is soft-deleted — accountability for
        // deleted content is the point of the panel, so it still admits.
        $this->db->run(
            'UPDATE threads SET is_deleted = 1 WHERE user_id = ? AND board_id = ?',
            [(int) $seed['subject']['id'], (int) $seed['boardA']['id']],
        );

        $this->actingAs($seed['mod']);
        $this->assertStatus(200, $this->get('/mod/u/' . (int) $seed['subject']['id']));
    }

    public function test_mod_note_is_403_and_admin_note_flow_is_unchanged(): void
    {
        $seed = $this->seedScoped();
        $id = (int) $seed['subject']['id'];

        $this->actingAs($seed['mod']);
        $this->assertStatus(403, $this->post('/mod/u/' . $id . '/note', ['body' => 'mod note attempt']));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_notes'));

        $this->actingAs($seed['admin']);
        $ok = $this->post('/mod/u/' . $id . '/note', ['body' => 'admin note sticks']);
        $this->assertRedirectContains($ok, '/mod/u/' . $id);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_notes'));
    }

    public function test_mod_warn_bad_board_choices_are_one_uniform_422(): void
    {
        $seed = $this->seedScoped();
        $id = (int) $seed['subject']['id'];
        $this->actingAs($seed['mod']);

        $outScope = $this->post('/mod/u/' . $id . '/warn', [
            'reason' => 'valid reason',
            'board_id' => (string) (int) $seed['boardB']['id'],
        ]);
        $missing = $this->post('/mod/u/' . $id . '/warn', [
            'reason' => 'valid reason',
            'board_id' => '999999',
        ]);

        $this->assertStatus(422, $outScope);
        $this->assertStatus(422, $missing);
        $this->assertSeeText($outScope, 'Choose a board you moderate where this member has participated.');
        $this->assertSeeText($missing, 'Choose a board you moderate where this member has participated.');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings'));
    }

    public function test_mod_warn_in_overlap_records_board_attribution_and_audit(): void
    {
        $seed = $this->seedScoped();
        $id = (int) $seed['subject']['id'];
        $this->actingAs($seed['mod']);

        $res = $this->post('/mod/u/' . $id . '/warn', [
            'reason' => 'Alpha misconduct',
            'board_id' => (string) (int) $seed['boardA']['id'],
        ]);

        $this->assertRedirectContains($res, '/mod/u/' . $id);
        $rows = $this->db->fetchAll('SELECT board_id FROM warnings WHERE user_id = ?', [$id]);
        self::assertCount(1, $rows);
        self::assertSame((int) $seed['boardA']['id'], (int) $rows[0]['board_id']);
        $after = (string) $this->db->fetchValue(
            "SELECT after_json FROM moderation_log WHERE action = 'warn' AND target_id = ? ORDER BY id DESC LIMIT 1",
            [$id],
        );
        self::assertStringContainsString((string) (int) $seed['boardA']['id'], $after);
        self::assertStringContainsString('board_id', $after);
    }

    public function test_self_warning_is_rejected_without_writing_a_warning(): void
    {
        $admin = $this->makeAdmin(['username' => 'selfwarnadmin']);
        $this->actingAs($admin);

        $res = $this->post('/mod/u/' . (int) $admin['id'] . '/warn', [
            'reason' => 'crafted self-warning',
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'You cannot warn your own account.');
        self::assertSame(
            0,
            (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id = ?', [(int) $admin['id']]),
        );
    }

    public function test_admin_warn_site_wide_named_board_and_missing_board(): void
    {
        $seed = $this->seedScoped();
        $id = (int) $seed['subject']['id'];
        $this->actingAs($this->makeAdmin(['username' => 'warnadmin']));

        $siteWide = $this->post('/mod/u/' . $id . '/warn', ['reason' => 'site-wide warning']);
        $this->assertRedirectContains($siteWide, '/mod/u/' . $id);

        $scoped = $this->post('/mod/u/' . $id . '/warn', [
            'reason' => 'board warning',
            'board_id' => (string) (int) $seed['boardB']['id'],
        ]);
        $this->assertRedirectContains($scoped, '/mod/u/' . $id);

        $missing = $this->post('/mod/u/' . $id . '/warn', ['reason' => 'x', 'board_id' => '999999']);
        $this->assertStatus(422, $missing);

        $rows = $this->db->fetchAll('SELECT board_id FROM warnings WHERE user_id = ? ORDER BY id ASC', [$id]);
        self::assertCount(2, $rows);
        self::assertNull($rows[0]['board_id']);
        self::assertSame((int) $seed['boardB']['id'], (int) $rows[1]['board_id']);
    }

    public function test_duplicate_warn_replays_the_success_redirect_with_one_row(): void
    {
        $seed = $this->seedScoped();
        $id = (int) $seed['subject']['id'];
        $this->actingAs($seed['mod']);
        $key = bin2hex(random_bytes(16));
        $body = [
            'reason' => 'double-click warning',
            'board_id' => (string) (int) $seed['boardA']['id'],
            'idempotency_key' => $key,
        ];

        $first = $this->post('/mod/u/' . $id . '/warn', $body);
        $second = $this->post('/mod/u/' . $id . '/warn', $body);

        $this->assertRedirectContains($first, '/mod/u/' . $id);
        $this->assertRedirectContains($second, '/mod/u/' . $id);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id = ?', [$id]));
        self::assertSame(
            1,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'warn' AND target_id = ?", [$id]),
        );
    }

    public function test_warn_422_rerender_preserves_reason_board_and_idempotency_key(): void
    {
        $seed = $this->seedScoped();
        $id = (int) $seed['subject']['id'];
        $this->actingAs($seed['mod']);
        $key = bin2hex(random_bytes(16));

        // Valid board, empty reason → 422 that must carry all typed context.
        $res = $this->post('/mod/u/' . $id . '/warn', [
            'reason' => '   ',
            'board_id' => (string) (int) $seed['boardA']['id'],
            'idempotency_key' => $key,
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'A reason is required.');
        $this->assertSeeText($res, $key);
        self::assertMatchesRegularExpression(
            '/<option value="' . (int) $seed['boardA']['id'] . '"[^>]*selected/',
            $res->body(),
        );
    }

    public function test_admin_panel_keeps_the_full_history(): void
    {
        $seed = $this->seedScoped();
        $id = (int) $seed['subject']['id'];
        $this->db->run(
            'INSERT INTO warnings (user_id, issued_by, board_id, reason, created_at) VALUES (?, ?, NULL, ?, UTC_TIMESTAMP())',
            [$id, (int) $seed['admin']['id'], 'SITEWIDE-MARKER-W1'],
        );
        $this->db->run(
            'INSERT INTO user_notes (subject_user_id, author_id, body, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$id, (int) $seed['admin']['id'], 'NOTE-MARKER-N1'],
        );
        $this->db->run(
            "INSERT INTO bans (user_id, scope, type, reason, created_by, created_at, expires_at) VALUES (?, 'site', 'post', ?, ?, UTC_TIMESTAMP(), NULL)",
            [$id, 'BAN-MARKER-B1', (int) $seed['admin']['id']],
        );

        $this->actingAs($this->makeAdmin(['username' => 'historyadmin']));
        $res = $this->get('/mod/u/' . $id);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'SITEWIDE-MARKER-W1');
        $this->assertSeeText($res, 'NOTE-MARKER-N1');
        $this->assertSeeText($res, 'BAN-MARKER-B1');
        $this->assertSeeText($res, '/admin/users/' . $id);
    }
}
