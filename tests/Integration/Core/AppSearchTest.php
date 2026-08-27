<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Database;
use App\Domain\User;
use App\Search\MysqlSearchService;
use App\Search\SearchQuery;
use App\Security\ArrayRateLimiter;
use PDO;
use Tests\Support\TestCase;

/**
 * FULLTEXT search (P2-06). InnoDB FULLTEXT does not index rows inside an open
 * transaction, so this suite commits its fixtures (no per-test transaction) and
 * truncates everything in tearDown. Covers the read gate (the security-critical
 * part), deleted-content exclusion, and snippet escaping.
 */
final class AppSearchTest extends TestCase
{
    protected function setUp(): void
    {
        // Deliberately NOT calling parent::setUp() — we must commit fixtures so
        // the FULLTEXT index sees them, so we skip the rolling-back transaction.
        $this->pdo = $GLOBALS['__RB_TEST_PDO'];
        $this->config = $GLOBALS['__RB_TEST_CONFIG'];
        $this->db = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $this->db->setPdo($this->pdo);
        $this->resetDatabase();
        $this->rateLimiter = new ArrayRateLimiter();
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
        $this->cookies = [];
        $this->csrfSecret = null;
        $this->makeAdmin();
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();
    }

    private function resetDatabase(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        // Preserve migration-seeded reference tables so the seeded rows other
        // tests depend on survive this destructive reset. TRUNCATE auto-commits,
        // so wiping these would leak an empty seed into every later test in the
        // suite (badges -> 0040, roles -> 0050, identity_providers /
        // provider_aliases -> 0052, capabilities / role_capabilities -> 0066,
        // theme_state -> 0072).
        $preserve = [
            'schema_migrations', 'badges', 'roles', 'identity_providers', 'provider_aliases',
            'capabilities', 'role_capabilities', 'theme_state',
        ];
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (!in_array($t, $preserve, true)) {
                $this->pdo->exec('TRUNCATE TABLE `' . str_replace('`', '', (string) $t) . '`');
            }
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function service(): MysqlSearchService
    {
        return new MysqlSearchService($this->db);
    }

    /** @return list<array<string,mixed>> */
    private function search(
        string $term,
        ?User $viewer = null,
        string $scope = 'everything',
        string $order = 'relevance',
        int $limit = 20,
    ): array {
        return $this->service()->search(new SearchQuery($term, $scope, $order, $limit), $viewer);
    }

    public function testGuestSeesPublicButNotPrivateContent(): void
    {
        $author = $this->makeUser();
        $admin = $this->makeAdmin();
        $public = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $private = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $this->makeThread($public, $author, 'Galápagos tortoise sightings', 'A public thread about tortoises.');
        $this->makeThread($private, $admin, 'Galápagos secret expedition', 'Private planning notes.');

        $results = $this->search('Galápagos');
        $titles = array_column($results, 'title');
        self::assertContains('Galápagos tortoise sightings', $titles);
        self::assertNotContains('Galápagos secret expedition', $titles, 'guest never sees private-board content');
    }

    public function testMemberSeesPrivateBoardTheyBelongTo(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeUser();
        $private = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $this->makeThread($private, $admin, 'Antikythera mechanism notes', 'members only.');
        $this->db->run('INSERT INTO board_members (board_id, user_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())', [(int) $private['id'], (int) $member['id']]);

        $asMember = $this->search('Antikythera', $this->userEntity($member));
        self::assertNotEmpty($asMember, 'a board member can find private content');

        $stranger = $this->makeUser();
        $asStranger = $this->search('Antikythera', $this->userEntity($stranger));
        self::assertEmpty($asStranger, 'a non-member cannot find private content');
    }

    public function testEveryScopeSupportsBothOrdersAndMineIsEmptyForGuests(): void
    {
        $viewer = $this->makeUser();
        $other = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $viewerTopic = $this->makeThread($board, $viewer, 'Scopequasar viewer topic', 'A plain opening.');
        $otherTopic = $this->makeThread($board, $other, 'Scopequasar other topic', 'Another plain opening.');
        $this->posting()->reply($this->userEntity($viewer), (int) $otherTopic['thread_id'], [
            'body' => 'Scopequasar viewer reply.',
        ]);
        $this->posting()->reply($this->userEntity($other), (int) $viewerTopic['thread_id'], [
            'body' => 'Scopequasar other reply.',
        ]);

        foreach (['relevance', 'newest'] as $order) {
            $everything = $this->search('Scopequasar', $this->userEntity($viewer), 'everything', $order);
            $everythingTypes = array_values(array_unique(array_column($everything, 'type')));
            sort($everythingTypes);
            self::assertSame(['post', 'thread'], $everythingTypes);

            $topics = $this->search('Scopequasar', $this->userEntity($viewer), 'topics', $order);
            self::assertCount(2, $topics);
            self::assertSame(['thread'], array_values(array_unique(array_column($topics, 'type'))));

            $replies = $this->search('Scopequasar', $this->userEntity($viewer), 'replies', $order);
            self::assertCount(2, $replies);
            self::assertSame(['post'], array_values(array_unique(array_column($replies, 'type'))));

            $mine = $this->search('Scopequasar', $this->userEntity($viewer), 'mine', $order);
            self::assertCount(2, $mine);
            self::assertSame([(int) $viewer['id']], array_values(array_unique(array_map(
                static fn (array $row): int => (int) $row['author_id'],
                $mine,
            ))));
        }

        self::assertSame([], $this->search('Scopequasar', null, 'mine'));
    }

    public function testUnionIsGloballyLimitedAfterScopeAndNewestOrder(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $oldTopic = $this->makeThread($board, $author, 'Unionmeteor old topic', 'Plain opening.');
        $newTopic = $this->makeThread($board, $author, 'Unionmeteor newest topic', 'Plain opening.');
        $replyHost = $this->makeThread($board, $author, 'Reply host', 'Plain opening.');
        $olderReplyId = $this->posting()->reply($this->userEntity($author), (int) $replyHost['thread_id'], [
            'body' => 'Unionmeteor older reply.',
        ]);
        $newestReplyId = $this->posting()->reply($this->userEntity($author), (int) $replyHost['thread_id'], [
            'body' => 'Unionmeteor newest reply.',
        ]);
        $this->db->run("UPDATE threads SET created_at = '2024-01-01 00:00:00' WHERE id = ?", [(int) $oldTopic['thread_id']]);
        $this->db->run("UPDATE threads SET created_at = '2026-02-01 00:00:00' WHERE id = ?", [(int) $newTopic['thread_id']]);
        $this->db->run("UPDATE posts SET created_at = '2026-01-01 00:00:00' WHERE id = ?", [$olderReplyId]);
        $this->db->run("UPDATE posts SET created_at = '2026-03-01 00:00:00' WHERE id = ?", [$newestReplyId]);

        $results = $this->search('Unionmeteor', null, 'everything', 'newest', 2);
        self::assertCount(2, $results);
        self::assertSame(['post', 'thread'], array_column($results, 'type'));
        self::assertStringEndsWith('#p' . $newestReplyId, (string) $results[0]['url']);
        self::assertSame((int) $newTopic['thread_id'], (int) $results[1]['thread_id']);
    }

    public function testNewestAndRelevanceTiesAreDeterministic(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $first = $this->makeThread($board, $author, 'Tiefalcon alpha', 'Plain opening.');
        $second = $this->makeThread($board, $author, 'Tiefalcon bravo', 'Plain opening.');
        $this->db->run(
            "UPDATE threads SET created_at = '2026-04-01 00:00:00' WHERE id IN (?, ?)",
            [(int) $first['thread_id'], (int) $second['thread_id']],
        );

        foreach (['relevance', 'newest'] as $order) {
            self::assertSame(
                [(int) $second['thread_id'], (int) $first['thread_id']],
                array_map('intval', array_column($this->search('Tiefalcon', null, 'topics', $order), 'thread_id')),
                $order,
            );
        }
    }

    public function testDeletedContentIsExcluded(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Brontosaurus discovery', 'Original visible post.');
        $replyId = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Brontosaurus reply that will be deleted.']);
        $this->db->run('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$replyId]);

        $results = $this->search('Brontosaurus');
        foreach ($results as $r) {
            self::assertStringNotContainsString('will be deleted', (string) $r['snippet'], 'deleted posts never appear in results');
        }
    }

    public function testSnippetIsHtmlEscaped(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Quarknado warning', 'Opening post.');
        $this->posting()->reply($this->userEntity($author), (int) $thread['thread_id'], [
            'body' => 'Beware the <script>alert(1)</script> Quarknado event.',
        ]);

        $results = $this->search('Quarknado', null, 'replies');
        $post = array_values(array_filter($results, static fn (array $r): bool => $r['type'] === 'post'));
        self::assertNotEmpty($post);
        self::assertStringNotContainsString('<script>', (string) $post[0]['snippet'], 'snippet must be HTML-escaped');
        self::assertStringContainsString('&lt;script', (string) $post[0]['snippet']);
        self::assertStringEndsWith('#p' . (int) ($post[0]['post_id'] ?? 0), (string) $post[0]['url']);
    }

    public function testSearchRouteRendersApprovedControlsResultsAndStates(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Orbital Mechanics']);
        $thread = $this->makeThread($board, $author, 'Hyperloop velocity tests', 'Public opening.');
        $this->posting()->reply($this->userEntity($author), (int) $thread['thread_id'], [
            'body' => 'Hyperloop reply evidence.',
        ]);

        $r = $this->get('/search', ['q' => 'Hyperloop', 'scope' => 'everything', 'order' => 'newest']);
        $this->assertStatus(200, $r);
        $this->assertSeeText($r, 'Search the council');
        $this->assertSeeText($r, 'Hyperloop velocity tests');
        $this->assertSeeText($r, '2 results for “Hyperloop” · newest first');
        self::assertStringContainsString('<span>Topic</span>', $r->body());
        self::assertStringContainsString('<span>Reply</span>', $r->body());
        self::assertSame(2, substr_count($r->body(), '>Orbital Mechanics</a>'));
        self::assertStringContainsString('class="search-query-well"', $r->body());
        self::assertStringContainsString('name="q" value="Hyperloop"', $r->body());
        self::assertStringContainsString('minlength="3"', $r->body());
        self::assertStringContainsString('data-search-scope="everything"', $r->body());
        self::assertStringContainsString('data-search-order="newest"', $r->body());
        self::assertStringContainsString('/search?q=Hyperloop&amp;scope=topics&amp;order=newest', $r->body());
        self::assertStringContainsString('/search?q=Hyperloop&amp;scope=everything&amp;order=relevance', $r->body());

        $invalid = $this->get('/search', ['q' => 'ab', 'scope' => 'topics', 'order' => 'relevance']);
        $this->assertStatus(200, $invalid);
        self::assertStringContainsString('aria-invalid="true"', $invalid->body());
        $this->assertSeeText($invalid, 'Search phrases must be at least 3 characters.');

        $empty = $this->get('/search', ['q' => 'Nothingmatchingthisphrase', 'scope' => 'replies']);
        $this->assertSeeText($empty, 'Nothing matches that.');
        $this->assertSeeText($empty, 'Try a shorter phrase, or widen the scope above.');
        self::assertStringContainsString('/assets/commend-star.svg', $empty->body());

        $initial = $this->get('/search');
        $this->assertStatus(200, $initial);
        $this->assertSeeText($initial, 'Search topic titles and replies across every board you can read.');
        $this->assertDontSeeText($initial, 'Nothing matches that.');
    }

    public function testShortQueriesReturnNoServiceResults(): void
    {
        self::assertSame([], $this->search('ab'));
    }

    public function testArchivedBoardContentStaysSearchable(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $author, 'Stegosaurus retrospective', 'Public thread before archive.');
        $this->boards()->setArchived((int) $board['id'], true); // archive AFTER seeding

        $results = $this->search('Stegosaurus');
        self::assertContains(
            'Stegosaurus retrospective',
            array_column($results, 'title'),
            'archived boards remain searchable — read-only is not hidden',
        );
    }
}
