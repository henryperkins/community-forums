<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\SinceLastReadContextService;
use Tests\Support\TestCase;

final class AppAutomatedContextTest extends TestCase
{
    private const PURGE_MIGRATION = __DIR__ . '/../../../database/migrations/0078_purge_since_last_read_context.php';

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    /**
     * @return array{viewer:array<string,mixed>,thread:array<string,mixed>,reply_ids:list<int>}
     */
    private function seedPagedUnreadThread(
        string $suffix,
        bool $engagement = true,
        bool $automatedContext = true,
    ): array
    {
        $this->makeAdmin();
        $this->setFlags([
            'engagement' => $engagement,
            'automated_context' => $automatedContext,
        ]);
        $author = $this->makeUser(['username' => 'unreadpageauthor' . $suffix]);
        $viewer = $this->makeUser(['username' => 'unreadpageviewer' . $suffix]);
        (new UserPreferenceRepository($this->db))->merge((int) $viewer['id'], ['posts_per_page' => 10]);
        $board = $this->makeBoard(
            $this->makeCategory('Unread Landing ' . $suffix),
            ['slug' => 'unread-landing-' . $suffix],
        );
        $thread = $this->makeThread($board, $author, 'Unread landing topic ' . $suffix, 'Opening post.');

        $replyIds = [];
        for ($i = 1; $i <= 15; $i++) {
            $replyIds[] = $this->posting()->reply(
                $this->userEntity($author),
                $thread['thread_id'],
                ['body' => 'Landing update ' . $i . '.'],
            );
        }
        // OP + replies 1-9 fill page 1; replyIds[9] is the first post on page 2.
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $replyIds[8]],
        );

        return ['viewer' => $viewer, 'thread' => $thread, 'reply_ids' => $replyIds];
    }

    public function test_since_last_read_context_is_available_without_an_override(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'contextauthor']);
        $viewer = $this->makeUser(['username' => 'contextviewer']);
        $board = $this->makeBoard($this->makeCategory('Context Default'), ['slug' => 'context-default']);
        $thread = $this->makeThread($board, $author, 'Context default topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Unread default-on reply.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        // ADR 0030: the panel became the one-line "Catch me up" strip; the
        // excerpts it used to print unconditionally are inside its disclosure.
        self::assertStringContainsString('class="catch-up"', $page->body());
        self::assertStringContainsString('Catch me up', $page->body());
        self::assertStringContainsString('Unread default-on reply.', $page->body());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM since_last_read_context'));
    }

    public function test_since_last_read_context_can_be_rolled_back_with_explicit_false(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => false]);
        $author = $this->makeUser(['username' => 'contextrollbackauthor']);
        $viewer = $this->makeUser(['username' => 'contextrollbackviewer']);
        $board = $this->makeBoard($this->makeCategory('Context Rollback'), ['slug' => 'context-rollback-explicit']);
        $thread = $this->makeThread($board, $author, 'Context rollback topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Unread rollback reply.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringNotContainsString('class="catch-up"', $page->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM since_last_read_context'));
    }

    public function test_since_last_read_context_uses_previous_read_marker_before_marking_read(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => true]);
        $author = $this->makeUser(['username' => 'contextauthor2']);
        $viewer = $this->makeUser(['username' => 'contextviewer2']);
        $board = $this->makeBoard($this->makeCategory('Context'), ['slug' => 'context-board']);
        $thread = $this->makeThread($board, $author, 'Context topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $firstReply = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'First unread update with useful context.']);
        $secondReply = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Second unread update with more detail.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('class="catch-up"', $page->body());
        self::assertStringContainsString('First unread update with useful context.', $page->body());
        self::assertStringContainsString('Second unread update with more detail.', $page->body());

        $row = $this->db->fetch('SELECT * FROM since_last_read_context WHERE user_id = ? AND thread_id = ?', [(int) $viewer['id'], $thread['thread_id']]);
        self::assertIsArray($row);
        self::assertSame($opId, (int) $row['from_post_id']);
        self::assertSame($secondReply, (int) $row['to_post_id']);
        self::assertSame(2, (int) $row['post_count']);
        self::assertStringContainsString('First unread update with useful context.', (string) $row['context_text']);

        self::assertSame($secondReply, (int) $this->db->fetchValue(
            'SELECT last_read_post_id FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        ));
        self::assertGreaterThan($firstReply, $secondReply);
    }

    public function test_since_last_read_context_never_exposes_or_persists_anonymous_author_identity(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => true]);
        $author = $this->makeUser([
            'username' => 'context-secret-identity',
            'display_name' => 'Context Secret Name',
        ]);
        $starter = $this->makeUser(['username' => 'context-anonymous-starter']);
        $viewer = $this->makeUser(['username' => 'context-anonymous-viewer']);
        $board = $this->makeBoard(
            $this->makeCategory('Anonymous Context'),
            ['slug' => 'anonymous-context', 'allow_anonymous' => 1],
        );
        $thread = $this->makeThread($board, $starter, 'Anonymous context topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$thread['thread_id']],
        );

        $this->posting()->reply(
            $this->userEntity($author),
            $thread['thread_id'],
            ['body' => 'Anonymous unread update.', 'is_anonymous' => '1'],
        );
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        // The strip names the author the way mask_author() does — the constant
        // identity, never the real one, in the summary line and in the point.
        self::assertMatchesRegularExpression(
            '/<a href="#p\d+">Anonymous<\/a>/',
            $page->body(),
        );
        self::assertStringContainsString('1 reply — Anonymous', $page->body());
        self::assertStringNotContainsString('@Anonymous', $page->body());
        self::assertStringNotContainsString('context-secret-identity', $page->body());
        self::assertStringNotContainsString('Context Secret Name', $page->body());

        $contextText = (string) $this->db->fetchValue(
            'SELECT context_text FROM since_last_read_context WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        );
        self::assertStringContainsString('Anonymous: Anonymous unread update.', $contextText);
        self::assertStringNotContainsString('@Anonymous', $contextText);
        self::assertStringNotContainsString('context-secret-identity', $contextText);
        self::assertStringNotContainsString('Context Secret Name', $contextText);
    }

    public function test_privacy_migration_purges_regenerable_since_last_read_context(): void
    {
        $author = $this->makeUser(['username' => 'context-purge-author']);
        $viewer = $this->makeUser(['username' => 'context-purge-viewer']);
        $board = $this->makeBoard($this->makeCategory('Context Purge'), ['slug' => 'context-purge']);
        $thread = $this->makeThread($board, $author, 'Context purge topic', 'Opening post.');
        $postId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$thread['thread_id']],
        );
        $this->db->run(
            'INSERT INTO since_last_read_context
                (user_id, thread_id, from_post_id, to_post_id, post_count, context_text, generated_at, expires_at)
             VALUES (?, ?, ?, ?, 1, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 14 DAY))',
            [(int) $viewer['id'], $thread['thread_id'], $postId, $postId, '@Context Secret Name: cached identity'],
        );

        self::assertFileExists(self::PURGE_MIGRATION);
        $migration = require self::PURGE_MIGRATION;
        $migration->up($this->db->pdo());

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM since_last_read_context'));

        $migration->down($this->db->pdo());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM since_last_read_context'));
    }

    public function test_since_last_read_context_counts_full_window_with_bounded_items(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'contextauthor3']);
        $viewer = $this->makeUser(['username' => 'contextviewer3']);
        $board = $this->makeBoard($this->makeCategory('Context Window'), ['slug' => 'context-window']);
        $thread = $this->makeThread($board, $author, 'Context window topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $first = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'First sampled update.']);
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Second sampled update.']);
        $third = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Third counted update.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $context = (new SinceLastReadContextService($this->db))->forThread((int) $viewer['id'], $thread['thread_id'], 2);

        self::assertIsArray($context);
        self::assertSame(3, $context['post_count']);
        self::assertSame($third, $context['to_post_id']);
        self::assertCount(2, $context['items']);
        self::assertSame($first, $context['items'][0]['post_id']);
        self::assertSame(3, (int) $this->db->fetchValue(
            'SELECT post_count FROM since_last_read_context WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        ));
    }

    public function test_since_last_read_context_advances_read_marker_when_engagement_is_disabled(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => true, 'engagement' => false]);
        $author = $this->makeUser(['username' => 'contextauthor4']);
        $viewer = $this->makeUser(['username' => 'contextviewer4']);
        $board = $this->makeBoard($this->makeCategory('Context Read Marker'), ['slug' => 'context-read-marker']);
        $thread = $this->makeThread($board, $author, 'Context read marker topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Unread follow-up one.']);
        $latestReply = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Unread follow-up two.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('class="catch-up"', $page->body());
        self::assertSame($latestReply, (int) $this->db->fetchValue(
            'SELECT last_read_post_id FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        ));
    }

    public function test_since_last_read_context_links_to_the_post_page_when_items_are_off_screen(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => true]);
        $author = $this->makeUser(['username' => 'contextauthor5']);
        $viewer = $this->makeUser(['username' => 'contextviewer5']);
        (new UserPreferenceRepository($this->db))->merge((int) $viewer['id'], ['posts_per_page' => 10]);
        $board = $this->makeBoard($this->makeCategory('Context Paging'), ['slug' => 'context-paging']);
        $thread = $this->makeThread($board, $author, 'Context paging topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $replyIds = [];
        for ($i = 1; $i <= 15; $i++) {
            $replyIds[] = $this->posting()->reply(
                $this->userEntity($author),
                $thread['thread_id'],
                ['body' => 'Unread paging update ' . $i . '.'],
            );
        }
        // Read position straddles the page boundary on purpose: the topic now opens
        // on the page holding the FIRST unread reply, so the read position has to
        // leave part of the catch-me-up window off that page for this test to still
        // be about cross-page links. replyIds[7] and [8] land on page 1 with the
        // viewer; [9] onwards are on page 2.
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $replyIds[6]],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        // On this page: a bare fragment, and the post itself is actually rendered.
        self::assertStringContainsString('id="p' . $replyIds[7] . '"', $page->body());
        self::assertStringContainsString('href="#p' . $replyIds[7] . '"', $page->body());
        // Off this page: an absolute link carrying the page number, never a bare
        // fragment that would scroll to nothing.
        self::assertStringContainsString(
            '/t/' . $thread['thread_id'] . '-' . $thread['slug'] . '?page=2#p' . $replyIds[9],
            $page->body(),
        );
        self::assertStringNotContainsString(
            'href="#p' . $replyIds[9] . '"',
            $page->body(),
        );
    }

    public function test_page_less_topic_permalink_stays_on_page_one_for_a_returning_reader(): void
    {
        ['viewer' => $viewer, 'thread' => $thread, 'reply_ids' => $replyIds] = $this->seedPagedUnreadThread('permalink');

        $this->actingAs($viewer);
        $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];
        $landing = $this->get($url);

        $this->assertStatus(200, $landing);
        self::assertStringContainsString('id="p' . $replyIds[0] . '"', $landing->body());
        self::assertStringNotContainsString('id="p' . $replyIds[9] . '"', $landing->body());
        self::assertSame($replyIds[8], (int) $this->db->fetchValue(
            'SELECT last_read_post_id FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        ));
    }

    public function test_explicit_unread_intent_redirects_to_a_real_fragment_target(): void
    {
        ['viewer' => $viewer, 'thread' => $thread, 'reply_ids' => $replyIds] = $this->seedPagedUnreadThread('explicit');

        $this->actingAs($viewer);
        $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];
        $landing = $this->get($url, ['unread' => '1']);

        $this->assertRedirect($landing, $url . '?page=2#p' . $replyIds[9]);
        self::assertSame($replyIds[8], (int) $this->db->fetchValue(
            'SELECT last_read_post_id FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        ));

        $second = $this->get($url, ['page' => '2']);
        $this->assertStatus(200, $second);
        self::assertStringContainsString('id="p' . $replyIds[9] . '"', $second->body());
        self::assertMatchesRegularExpression(
            '#data-first-unread="1"[^>]*id="p' . $replyIds[9] . '"|id="p' . $replyIds[9] . '"[^>]*data-first-unread="1"#',
            $second->body(),
        );
        self::assertStringNotContainsString('id="p' . $replyIds[0] . '"', $second->body());

        $reload = $this->get($url, ['page' => '2']);
        $this->assertStatus(200, $reload);
        self::assertStringContainsString('id="p' . $replyIds[9] . '"', $reload->body());
        self::assertStringNotContainsString('id="p' . $replyIds[0] . '"', $reload->body());

        // An explicit ?page always wins — including ?page=1, which is the only way
        // back to the top of a topic once the unread rule exists.
        $this->db->run(
            'UPDATE thread_user SET last_read_post_id = ? WHERE user_id = ? AND thread_id = ?',
            [$replyIds[8], (int) $viewer['id'], $thread['thread_id']],
        );
        $first = $this->get($url, ['page' => '1']);
        $this->assertStatus(200, $first);
        self::assertStringContainsString('id="p' . $replyIds[0] . '"', $first->body());
        self::assertStringNotContainsString('id="p' . $replyIds[9] . '"', $first->body());
    }

    public function test_explicit_unread_intent_obeys_all_flag_combinations(): void
    {
        $cases = [
            'both-on' => [true, true, true],
            'engagement-only' => [true, false, true],
            'context-only' => [false, true, true],
            'both-off' => [false, false, false],
        ];

        foreach ($cases as $suffix => [$engagement, $automatedContext, $shouldJump]) {
            ['viewer' => $viewer, 'thread' => $thread, 'reply_ids' => $replyIds] = $this->seedPagedUnreadThread(
                $suffix,
                $engagement,
                $automatedContext,
            );
            $this->actingAs($viewer);
            $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];

            $response = $this->get($url, ['unread' => '1']);

            if ($shouldJump) {
                $this->assertRedirect($response, $url . '?page=2#p' . $replyIds[9]);
            } else {
                $this->assertStatus(200, $response);
                self::assertStringContainsString('id="p' . $replyIds[0] . '"', $response->body());
                self::assertStringNotContainsString('data-first-unread="1"', $response->body());
            }
        }
    }

    public function test_unread_thread_rows_use_explicit_unread_intent(): void
    {
        ['viewer' => $viewer, 'thread' => $thread] = $this->seedPagedUnreadThread('row-link');
        $this->actingAs($viewer);

        $board = $this->get('/c/unread-landing-row-link');

        $this->assertStatus(200, $board);
        self::assertStringContainsString(
            'href="/t/' . $thread['thread_id'] . '-' . $thread['slug'] . '?unread=1"',
            $board->body(),
        );
    }

    public function test_no_js_star_returns_to_the_exact_thread_page(): void
    {
        ['viewer' => $viewer, 'thread' => $thread] = $this->seedPagedUnreadThread('star');

        $this->actingAs($viewer);
        $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];
        $first = $this->get($url, ['page' => '1']);

        $this->assertStatus(200, $first);
        self::assertSame(1, preg_match(
            '#<form class="inline star-form"[^>]*>.*?<input type="hidden" name="return" value="([^"]+)"#s',
            $first->body(),
            $matches,
        ));
        $return = html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        self::assertSame($url . '?page=1', $return);

        $starred = $this->post('/t/' . $thread['thread_id'] . '/star', ['return' => $return]);

        $this->assertRedirect($starred, $url . '?page=1');
    }

    public function test_a_caught_up_reader_opens_a_thread_on_page_one(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => true]);
        $author = $this->makeUser(['username' => 'caughtupauthor']);
        $viewer = $this->makeUser(['username' => 'caughtupviewer']);
        (new UserPreferenceRepository($this->db))->merge((int) $viewer['id'], ['posts_per_page' => 10]);
        $board = $this->makeBoard($this->makeCategory('Caught Up'), ['slug' => 'caught-up']);
        $thread = $this->makeThread($board, $author, 'Caught up topic', 'Opening post.');

        $replyIds = [];
        for ($i = 1; $i <= 15; $i++) {
            $replyIds[] = $this->posting()->reply(
                $this->userEntity($author),
                $thread['thread_id'],
                ['body' => 'Caught up update ' . $i . '.'],
            );
        }
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $replyIds[14]],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('id="p' . $replyIds[0] . '"', $page->body());
        self::assertStringNotContainsString('id="p' . $replyIds[14] . '"', $page->body());
    }

    public function test_a_first_time_reader_opens_a_thread_on_page_one(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => true]);
        $author = $this->makeUser(['username' => 'firsttimeauthor']);
        $viewer = $this->makeUser(['username' => 'firsttimeviewer']);
        (new UserPreferenceRepository($this->db))->merge((int) $viewer['id'], ['posts_per_page' => 10]);
        $board = $this->makeBoard($this->makeCategory('First Time'), ['slug' => 'first-time']);
        $thread = $this->makeThread($board, $author, 'First time topic', 'Opening post.');

        $replyIds = [];
        for ($i = 1; $i <= 15; $i++) {
            $replyIds[] = $this->posting()->reply(
                $this->userEntity($author),
                $thread['thread_id'],
                ['body' => 'First time update ' . $i . '.'],
            );
        }

        // No thread_user row at all — nothing to steer from, so page 1.
        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('id="p' . $replyIds[0] . '"', $page->body());
        self::assertStringNotContainsString('id="p' . $replyIds[14] . '"', $page->body());
    }

    public function test_staff_context_links_rank_posts_against_deleted_stub_pages(): void
    {
        $this->setFlags(['automated_context' => true]);
        $viewer = $this->makeAdmin(['username' => 'contextstaff']);
        $author = $this->makeUser(['username' => 'contextstubauthor']);
        (new UserPreferenceRepository($this->db))->merge((int) $viewer['id'], ['posts_per_page' => 10]);
        $board = $this->makeBoard($this->makeCategory('Context Staff Paging'), ['slug' => 'context-staff-paging']);
        $thread = $this->makeThread($board, $author, 'Context staff paging topic', 'Opening post.');

        $deleted = $this->posting()->reply(
            $this->userEntity($author),
            $thread['thread_id'],
            ['body' => 'Deleted row that still occupies a staff-stream slot.'],
        );
        $this->db->run('UPDATE posts SET is_deleted = 1, deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$deleted]);

        $replyIds = [];
        for ($i = 1; $i <= 9; $i++) {
            $replyIds[] = $this->posting()->reply(
                $this->userEntity($author),
                $thread['thread_id'],
                ['body' => 'Staff paging update ' . $i . '.'],
            );
        }
        // Same reasoning as the test above: the read position leaves part of the
        // catch-me-up window off the landing page, so this stays a test about
        // cross-page ranking. It is also the whole point here — page 2 exists at
        // all ONLY because the deleted stub occupies a slot in the staff stream.
        // Without it, OP + nine replies is exactly one page.
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $replyIds[5]],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('href="#p' . $replyIds[6] . '"', $page->body());
        self::assertStringContainsString(
            '/t/' . $thread['thread_id'] . '-' . $thread['slug'] . '?page=2#p' . $replyIds[8],
            $page->body(),
        );
        self::assertStringNotContainsString('href="#p' . $replyIds[8] . '"', $page->body());
    }
}
