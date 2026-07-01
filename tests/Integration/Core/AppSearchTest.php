<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Database;
use App\Search\MysqlSearchService;
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
        // provider_aliases -> 0052, capabilities / role_capabilities -> 0066).
        $preserve = [
            'schema_migrations', 'badges', 'roles', 'identity_providers', 'provider_aliases',
            'capabilities', 'role_capabilities',
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

    public function testGuestSeesPublicButNotPrivateContent(): void
    {
        $author = $this->makeUser();
        $admin = $this->makeAdmin();
        $public = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $private = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $this->makeThread($public, $author, 'Galápagos tortoise sightings', 'A public thread about tortoises.');
        $this->makeThread($private, $admin, 'Galápagos secret expedition', 'Private planning notes.');

        $results = $this->service()->search('Galápagos', null, 20);
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

        $asMember = $this->service()->search('Antikythera', $this->userEntity($member), 20);
        self::assertNotEmpty($asMember, 'a board member can find private content');

        $stranger = $this->makeUser();
        $asStranger = $this->service()->search('Antikythera', $this->userEntity($stranger), 20);
        self::assertEmpty($asStranger, 'a non-member cannot find private content');
    }

    public function testDeletedContentIsExcluded(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Brontosaurus discovery', 'Original visible post.');
        $replyId = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Brontosaurus reply that will be deleted.']);
        $this->db->run('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$replyId]);

        $results = $this->service()->search('Brontosaurus', null, 20);
        foreach ($results as $r) {
            self::assertStringNotContainsString('will be deleted', (string) $r['snippet'], 'deleted posts never appear in results');
        }
    }

    public function testSnippetIsHtmlEscaped(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $author, 'Quarknado warning', 'Beware the <script>alert(1)</script> Quarknado event.');

        $results = $this->service()->search('Quarknado', null, 20);
        $post = array_values(array_filter($results, static fn (array $r): bool => $r['type'] === 'post'));
        self::assertNotEmpty($post);
        self::assertStringNotContainsString('<script>', (string) $post[0]['snippet'], 'snippet must be HTML-escaped');
    }

    public function testSearchRouteRendersResults(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $author, 'Hyperloop velocity tests', 'Public thread.');

        $r = $this->get('/search', ['q' => 'Hyperloop']);
        $this->assertStatus(200, $r);
        $this->assertSeeText($r, 'Hyperloop velocity tests');
    }

    public function testArchivedBoardContentStaysSearchable(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $author, 'Stegosaurus retrospective', 'Public thread before archive.');
        $this->boards()->setArchived((int) $board['id'], true); // archive AFTER seeding

        $results = $this->service()->search('Stegosaurus', null, 20);
        self::assertContains(
            'Stegosaurus retrospective',
            array_column($results, 'title'),
            'archived boards remain searchable — read-only is not hidden',
        );
    }
}
