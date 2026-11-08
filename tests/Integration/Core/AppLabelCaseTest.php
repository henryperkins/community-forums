<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * ADR 0057: the Forum theme renders labels as written, with no CSS capitals,
 * so a label built from a stored value is sentence-cased where it is rendered.
 * Before the change these chips relied on `text-transform: uppercase` and
 * would otherwise have shown raw values such as "private" or "off topic".
 */
final class AppLabelCaseTest extends TestCase
{
    public function test_label_text_sentence_cases_stored_values(): void
    {
        self::assertSame('Off topic', label_text('off_topic'));
        self::assertSame('Private', label_text('private'));
        self::assertSame('Élan vital', label_text('élan_vital'));
        self::assertSame('Opened just now', label_text('opened just now'));
        self::assertSame('3 unanswered', label_text('3 unanswered'));
        self::assertSame('', label_text('  '));
    }

    public function test_moderation_labels_built_from_stored_values_are_sentence_case(): void
    {
        $admin = $this->makeAdmin(['username' => 'label-admin']);
        $member = $this->makeUser(['username' => 'label-member']);
        $reporter = $this->makeUser(['username' => 'label-reporter']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'label-case']);
        $thread = $this->makeThread($board, $member, 'Label case topic');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id LIMIT 1', [$thread['thread_id']]);
        $this->db->run(
            "INSERT INTO reports (reporter_id, post_id, reason_code, reason, status, created_at)
             VALUES (?, ?, 'off_topic', 'Wrong board', 'open', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY))",
            [(int) $reporter['id'], $postId],
        );
        foreach ([
            ['DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)', 'NULL'],
            ['NULL', 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 DAY)'],
            ['NULL', 'NULL'],
        ] as [$liftedAt, $expiresAt]) {
            $this->db->run(
                "INSERT INTO bans (user_id, scope, type, reason, created_by, created_at, expires_at, lifted_at, lifted_by)
                 VALUES (?, 'site', 'post', 'Label case', ?, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 DAY), {$expiresAt}, {$liftedAt}, ?)",
                [(int) $member['id'], (int) $admin['id'], (int) $admin['id']],
            );
        }
        $this->actingAs($admin);

        $reports = $this->get('/mod/reports');
        $this->assertStatus(200, $reports);
        self::assertStringContainsString('badge-muted">Open</span>', $reports->body());
        self::assertStringContainsString('<span class="tag">Off topic</span>', $reports->body());
        self::assertStringContainsString('<span class="tag tag-stale">Waiting over a day</span>', $reports->body());

        $panel = $this->get('/mod/u/' . (int) $member['id']);
        $this->assertStatus(200, $panel);
        self::assertStringContainsString('<span class="role-pill role-user">User</span>', $panel->body());
        self::assertStringContainsString('<span class="pill">Lifted ', $panel->body());
        self::assertStringContainsString('<span class="pill">Until ', $panel->body());
        self::assertStringContainsString('<span class="pill">Indefinite</span>', $panel->body());
        self::assertDoesNotMatchRegularExpression('/<span class="(?:pill|role-pill[^"]*|state[^"]*)">[a-z]/', $panel->body());

        $record = $this->get('/admin/users/' . (int) $member['id']);
        $this->assertStatus(200, $record);
        self::assertStringContainsString('<span class="pill">Lifted ', $record->body());
    }

    public function test_appeals_name_moderation_actions_in_words(): void
    {
        (new SettingRepository($this->db))->set('features', ['appeals' => true]);
        $admin = $this->makeAdmin(['username' => 'label-appeal-admin']);
        $member = $this->makeUser(['username' => 'label-appeal-member']);
        $this->actingAs($admin);
        $this->post('/mod/u/' . (int) $member['id'] . '/warn', ['reason' => 'Mind the local rules.']);
        $logId = (int) $this->db->fetchValue(
            "SELECT id FROM moderation_log WHERE action = 'warn' AND target_type = 'user' AND target_id = ? ORDER BY id DESC LIMIT 1",
            [(int) $member['id']],
        );

        $this->actingAs($member);
        $eligible = $this->get('/appeals');
        self::assertStringContainsString('<span class="badge">Warning</span>', $eligible->body());
        $this->assertRedirect($this->post('/appeals/modlog/' . $logId, ['reason' => 'Please review this warning.']), '/appeals');

        $mine = $this->get('/appeals');
        self::assertStringContainsString('<p>Warning</p>', $mine->body());
        self::assertStringContainsString('<span class="badge">Open</span>', $mine->body());
        self::assertStringNotContainsString('<p>warn</p>', $mine->body());

        $this->actingAs($admin);
        $queue = $this->get('/mod/appeals');
        self::assertStringContainsString('<blockquote class="report-excerpt">Warning</blockquote>', $queue->body());
        self::assertStringContainsString('#' . (int) $member['id'] . ' · Warning</p>', $queue->body());
    }

    public function test_directory_rail_and_console_labels_are_sentence_case(): void
    {
        $admin = $this->makeAdmin(['username' => 'label-console-admin']);
        $moderator = $this->makeUser(['username' => 'label-board-mod']);
        $categoryId = $this->makeCategory('Label case');
        $public = $this->makeBoard($categoryId, ['slug' => 'label-public', 'name' => 'Label public']);
        $this->makeBoard($categoryId, ['slug' => 'label-private', 'name' => 'Label private', 'visibility' => 'private']);
        $this->db->run('INSERT INTO board_moderators (board_id, user_id) VALUES (?, ?)', [(int) $public['id'], (int) $moderator['id']]);
        $this->makeThread($public, $moderator, 'A fresh topic');
        $this->actingAs($admin);

        $home = $this->get('/', ['sort' => 'newest']);
        $this->assertStatus(200, $home);
        self::assertStringContainsString('<span class="board-rail-tag">Private</span>', $home->body());
        self::assertStringContainsString('<span class="forum-directory__board-signal">Opened ', $home->body());
        self::assertStringNotContainsString('<span class="board-rail-tag">private</span>', $home->body());

        $users = $this->get('/admin/users');
        $this->assertStatus(200, $users);
        self::assertMatchesRegularExpression('/member-directory-role is-admin">Admin<\/span>/', $users->body());
        self::assertStringContainsString('member-directory-board-mod" title="Moderates 1 board">Board mod</span>', $users->body());

        $email = $this->get('/admin/email');
        $this->assertStatus(200, $email);
        foreach (['Queued', 'Sent', 'Failed', 'Suppressed', 'Bounced', 'Complained'] as $label) {
            self::assertStringContainsString('<span class="stat-label">' . $label . '</span>', $email->body());
        }
    }
}
