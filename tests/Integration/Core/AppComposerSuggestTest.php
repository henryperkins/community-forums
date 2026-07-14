<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Database;
use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use App\Security\ArrayRateLimiter;
use PDO;
use Tests\Support\TestCase;

final class AppComposerSuggestTest extends TestCase
{
    protected function setUp(): void
    {
        // Deliberately NOT calling parent::setUp(): fixtures must be committed so
        // the FULLTEXT index (used for '#' topic/post suggestions) sees them.
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
        // Preserve migration-seeded reference tables (see AppSearchTest): TRUNCATE
        // auto-commits, so wiping these would leak empty seeds into later tests.
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

    private function enableSuggestions(): void
    {
        (new SettingRepository($this->db))->set('features', [
            'rich_composer' => true,
            'tags' => true,
            'content_references' => true,
            'custom_emoji' => true,
        ]);
    }

    public function test_suggest_requires_auth_and_rich_composer(): void
    {
        // Guests hit requireUser() first, which redirects (302) to /login.
        $this->assertRedirectContains($this->get('/composer/suggest', ['trigger' => '@', 'q' => 'a']), '/login');
        $user = $this->makeUser(['username' => 'suggestauth']);
        $this->actingAs($user);
        (new SettingRepository($this->db))->set('features', ['rich_composer' => false]);
        $this->assertStatus(404, $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'a']));
    }

    public function test_user_suggestions_return_mention_markdown(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'suggestviewer']);
        $this->makeUser(['username' => 'alice', 'display_name' => 'Alice Example']);
        $this->actingAs($viewer);

        $res = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'ali']);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        self::assertSame('user', $json['items'][0]['type']);
        self::assertSame('@alice', $json['items'][0]['token']);
        self::assertSame('@alice', $json['items'][0]['markdown']);
        self::assertSame('/u/alice', $json['items'][0]['url']);
    }

    public function test_hash_suggestions_are_read_gated_and_grouped(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'hashviewer']);
        $author = $this->makeUser(['username' => 'hashauthor']);
        $cat = $this->makeCategory('Hash Suggest');
        $public = $this->makeBoard($cat, ['slug' => 'general-suggest', 'name' => 'General Suggest']);
        $private = $this->makeBoard($cat, ['slug' => 'private-suggest', 'name' => 'Private Suggest', 'visibility' => 'private']);
        $thread = $this->makeThread($public, $author, 'Release planning topic', 'Planning body for release notes');
        (new TagRepository($this->db))->create('release-notes', 'Release Notes', 'Shipping notes', (int) $author['id']);

        $this->actingAs($viewer);
        $res = $this->get('/composer/suggest', ['trigger' => '#', 'q' => 'release']);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        $markdown = array_column($json['items'], 'markdown');
        self::assertContains('[#release-notes](/tags/release-notes)', $markdown);
        self::assertContains('[Release planning topic](/t/' . $thread['thread_id'] . '-' . $thread['slug'] . ')', $markdown);
        self::assertNotContains('[#private-suggest](/c/private-suggest)', $markdown);

        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $viewer['id'], null);
        $memberRes = $this->get('/composer/suggest', ['trigger' => '#', 'q' => 'private']);
        $this->assertStatus(200, $memberRes);
        self::assertStringContainsString('[#private-suggest](/c/private-suggest)', $memberRes->body());
    }

    public function test_forged_unreadable_target_id_matches_context_free_results(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'contextviewer']);
        $other = $this->makeUser(['username' => 'contextother']);
        $cat = $this->makeCategory('Context');
        $private = $this->makeBoard($cat, ['slug' => 'context-private', 'visibility' => 'private']);
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $other['id'], null);
        $thread = $this->makeThread($private, $other, 'Hidden context topic', 'hidden');
        $this->actingAs($viewer);

        $plain = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'context']);
        $forged = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'context', 'context' => 'thread', 'target_id' => (string) $thread['thread_id']]);
        self::assertSame($plain->body(), $forged->body());
    }

    public function test_anonymous_participation_does_not_boost_user_suggestion_rank(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'anonrankviewer']);
        $anon = $this->makeUser(['username' => 'anonrankalice']);
        $normal = $this->makeUser(['username' => 'anonrankbob']);
        $board = $this->makeBoard($this->makeCategory('Anon Rank'), ['slug' => 'anon-rank', 'allow_anonymous' => 1]);
        $thread = $this->makeThread($board, $viewer, 'Anon ranking', 'opening');
        $this->actingAs($anon);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'secret', 'is_anonymous' => '1']);
        $this->actingAs($normal);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'visible']);

        $this->actingAs($viewer);
        $res = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'anonrank', 'context' => 'thread', 'target_id' => (string) $thread['thread_id']]);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        $tokens = array_column($json['items'], 'token');
        self::assertLessThan(array_search('@anonrankalice', $tokens, true), array_search('@anonrankbob', $tokens, true));
    }

    public function test_unicode_emoji_suggestions_support_prefixes_plus_and_full_catalog(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'emojiviewer']);
        $this->actingAs($viewer);

        $smile = $this->get('/composer/suggest', ['trigger' => ':', 'q' => 'smil']);
        $this->assertStatus(200, $smile);
        $smileJson = json_decode($smile->body(), true);
        self::assertTrue($smileJson['ok']);
        self::assertNotEmpty($smileJson['items']);
        $smileItems = array_values(array_filter(
            $smileJson['items'],
            static fn (array $item): bool => $item['token'] === ':smile:',
        ));
        self::assertCount(1, $smileItems);
        self::assertSame('emoji', $smileItems[0]['type']);
        self::assertSame('😄', $smileItems[0]['markdown']);
        self::assertSame('', $smileItems[0]['url']);

        $plus = $this->get('/composer/suggest', ['trigger' => ':', 'q' => '+1']);
        $this->assertStatus(200, $plus);
        $plusJson = json_decode($plus->body(), true);
        self::assertSame('👍', $plusJson['items'][0]['markdown']);
        self::assertSame(':+1:', $plusJson['items'][0]['token']);

        $catalog = $this->get('/composer/suggest', ['trigger' => ':', 'q' => '']);
        $this->assertStatus(200, $catalog);
        $catalogJson = json_decode($catalog->body(), true);
        self::assertGreaterThanOrEqual(280, count($catalogJson['items']));
        self::assertLessThanOrEqual(320, count($catalogJson['items']));
        self::assertContains('Smileys & emotion', array_column($catalogJson['items'], 'group'));

        $emptyMention = $this->get('/composer/suggest', ['trigger' => '@', 'q' => '']);
        $this->assertStatus(200, $emptyMention);
        self::assertSame([], json_decode($emptyMention->body(), true)['items']);

        $unsupported = $this->get('/composer/suggest', ['trigger' => '!', 'q' => 'smil']);
        $this->assertStatus(422, $unsupported);
    }

    public function test_custom_emoji_suggestions_follow_row_and_feature_gates(): void
    {
        $this->enableSuggestions();
        $this->db->run(
            "INSERT INTO custom_emoji
                (shortcode, name, image_path, mime, is_enabled, allow_reactions, created_at)
             VALUES
                ('party_blob', 'Party Blob', '/emoji/party-blob.webp', 'image/webp', 1, 0, UTC_TIMESTAMP()),
                ('sleep_blob', 'Sleep Blob', '/emoji/sleep-blob.webp', 'image/webp', 0, 0, UTC_TIMESTAMP())",
        );
        $viewer = $this->makeUser(['username' => 'customemojiviewer']);
        $this->actingAs($viewer);

        $enabled = $this->get('/composer/suggest', ['trigger' => ':', 'q' => 'party_blob']);
        $this->assertStatus(200, $enabled);
        $enabledJson = json_decode($enabled->body(), true);
        $custom = array_values(array_filter(
            $enabledJson['items'],
            static fn (array $item): bool => $item['type'] === 'custom_emoji',
        ));
        self::assertCount(1, $custom);
        self::assertSame(':party_blob:', $custom[0]['token']);
        self::assertSame(':party_blob:', $custom[0]['markdown']);
        self::assertSame('/emoji/party-blob.webp', $custom[0]['url']);
        self::assertSame('Custom', $custom[0]['group']);

        $disabledRow = $this->get('/composer/suggest', ['trigger' => ':', 'q' => 'sleep_blob']);
        $this->assertStatus(200, $disabledRow);
        self::assertSame([], array_values(array_filter(
            json_decode($disabledRow->body(), true)['items'],
            static fn (array $item): bool => $item['type'] === 'custom_emoji',
        )));

        (new SettingRepository($this->db))->set('features', [
            'rich_composer' => true,
            'custom_emoji' => false,
        ]);
        $featureOff = $this->get('/composer/suggest', ['trigger' => ':', 'q' => 'party_blob']);
        $this->assertStatus(200, $featureOff);
        self::assertSame([], array_values(array_filter(
            json_decode($featureOff->body(), true)['items'],
            static fn (array $item): bool => $item['type'] === 'custom_emoji',
        )));
    }
}
