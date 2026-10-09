<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Database;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
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
        self::assertArrayHasKey('initials', $json['items'][0]);
        self::assertSame('AE', $json['items'][0]['initials']);
        self::assertSame('mono-0', $json['items'][0]['mono']);
        self::assertNull($json['items'][0]['avatar']);
        self::assertFalse($json['items'][0]['participant']);
        self::assertArrayNotHasKey('email', $json['items'][0]);
    }

    public function test_bare_mention_lists_only_active_named_visible_participants(): void
    {
        $this->enableSuggestions();
        (new SettingRepository($this->db))->set('features', ['rich_composer' => true, 'mentions' => false]);
        $viewer = $this->makeUser(['username' => 'mentionviewer']);
        $author = $this->makeUser(['username' => 'namedauthor', 'display_name' => 'Named Author']);
        $this->db->run('UPDATE users SET avatar_path = ? WHERE id = ?', ['/media/42', $author['id']]);
        $board = $this->makeBoard($this->makeCategory('Mention identities'), ['allow_anonymous' => 1]);
        $thread = $this->makeThread($board, $author);
        foreach (['anonymous', 'deleted', 'pending', 'banned', 'suspended'] as $kind) {
            $user = $this->makeUser(['username' => 'mention' . $kind]);
            $postId = $this->posting()->reply($this->userEntity($user), $thread['thread_id'], [
                'body' => 'A reply', 'is_anonymous' => $kind === 'anonymous',
            ]);
            if (in_array($kind, ['deleted', 'pending'], true)) {
                $this->db->run('UPDATE posts SET is_' . $kind . ' = 1 WHERE id = ?', [$postId]);
            } elseif (in_array($kind, ['banned', 'suspended'], true)) {
                $this->users()->setStatus((int) $user['id'], $kind);
            }
        }
        $this->actingAs($viewer);
        foreach (['thread', 'reply'] as $context) {
            $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => '', 'context' => $context, 'target_id' => $thread['thread_id']]);
            $this->assertStatus(200, $response);
            $items = json_decode($response->body(), true)['items'];
            self::assertSame(['@namedauthor'], array_column($items, 'token'));
            self::assertSame('Named Author · in this topic', $items[0]['meta']);
            self::assertSame('NA', $items[0]['initials']);
            self::assertSame('mono-2', $items[0]['mono']);
            self::assertSame('/media/42', $items[0]['avatar']);
            self::assertTrue($items[0]['participant']);
        }
    }

    public function test_bare_mention_cannot_disclose_forged_or_unavailable_contexts(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'bareviewer']);
        $author = $this->makeUser(['username' => 'bareauthor']);
        $category = $this->makeCategory('Bare privacy');
        $private = $this->makeBoard($category, ['visibility' => 'private']);
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $author['id'], null);
        $hidden = $this->makeThread($private, $author);
        $public = $this->makeBoard($category);
        $deleted = $this->makeThread($public, $author, 'Deleted thread');
        $pending = $this->makeThread($public, $author, 'Pending thread');
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [$deleted['thread_id']]);
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$pending['thread_id']]);
        $this->actingAs($viewer);
        foreach ([$hidden['thread_id'], $deleted['thread_id'], $pending['thread_id'], 999999] as $targetId) {
            $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => '', 'context' => 'reply', 'target_id' => $targetId]);
            $this->assertStatus(200, $response);
            self::assertSame([], json_decode($response->body(), true)['items']);
        }
        foreach (['', 'compose', 'dm', 'dm-recipient', 'edit'] as $context) {
            $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => '', 'context' => $context, 'target_id' => $hidden['thread_id']]);
            self::assertSame([], json_decode($response->body(), true)['items']);
        }
    }

    public function test_participants_are_prioritized_before_the_global_candidate_cap(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'rankviewer']);
        for ($i = 0; $i < 30; $i++) {
            $this->makeUser(['username' => sprintf('candidate%02d', $i)]);
        }
        $first = $this->makeUser(['username' => 'candidatezulu']);
        $second = $this->makeUser(['username' => 'candidateyankee']);
        $thread = $this->makeThread($this->makeBoard($this->makeCategory('Cap')), $first);
        $this->posting()->reply($this->userEntity($second), $thread['thread_id'], ['body' => 'Later participant']);
        $this->actingAs($viewer);
        $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'candidate', 'context' => 'thread', 'target_id' => $thread['thread_id']]);
        $this->assertStatus(200, $response);
        $items = json_decode($response->body(), true)['items'];
        self::assertCount(20, $items);
        self::assertSame(['@candidateyankee', '@candidatezulu', '@candidate00'], array_slice(array_column($items, 'token'), 0, 3));
    }

    public function test_mention_query_matches_each_display_name_word_prefix(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'wordviewer']);
        $this->makeUser(['username' => 'wordalice', 'display_name' => 'Alice Example']);
        $this->makeUser(['username' => 'wordbob', 'display_name' => 'Bob Exemplar']);
        $this->actingAs($viewer);
        $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'examp']);
        self::assertSame(['@wordalice'], array_column(json_decode($response->body(), true)['items'], 'token'));
    }

    public function test_display_name_prefixes_keep_accent_insensitive_matching(): void
    {
        $this->enableSuggestions();
        $this->makeUser(['username' => 'accentfirst', 'display_name' => 'Élodie Martin']);
        $this->makeUser(['username' => 'accentsecond', 'display_name' => 'Alice Éxample']);
        $this->makeUser(['username' => 'accentpunctuation', 'display_name' => 'Alice Ann-Márie']);
        $this->actingAs($this->makeUser(['username' => 'accentviewer']));
        foreach (['elo' => '@accentfirst', 'examp' => '@accentsecond', 'ann-ma' => '@accentpunctuation', 'marie' => '@accentpunctuation'] as $query => $token) {
            $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => $query]);
            $this->assertStatus(200, $response);
            self::assertSame([$token], array_column(json_decode($response->body(), true)['items'], 'token'));
        }
    }

    public function test_private_context_keeps_assigned_non_member_moderators_eligible(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'boardviewer']);
        $author = $this->makeUser(['username' => 'boardauthor']);
        $moderator = $this->makeUser(['username' => 'boardmoderator', 'role' => 'moderator']);
        $this->makeUser(['username' => 'boardunassigned', 'role' => 'moderator']);
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $members = new BoardMemberRepository($this->db);
        foreach ([$viewer, $author] as $user) { $members->add((int) $board['id'], (int) $user['id'], null); }
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $moderator['id']);
        $thread = $this->makeThread($board, $author);
        $this->actingAs($viewer);
        $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'boardmod', 'context' => 'reply', 'target_id' => $thread['thread_id']]);
        $this->assertStatus(200, $response);
        self::assertSame(['@boardmoderator'], array_column(json_decode($response->body(), true)['items'], 'token'));

        $this->actingAs($moderator);
        $this->assertStatus(200, $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']));
        $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => '', 'context' => 'reply', 'target_id' => $thread['thread_id']]);
        self::assertSame(['@boardauthor'], array_column(json_decode($response->body(), true)['items'], 'token'));
    }

    public function test_mention_query_treats_wildcards_as_literal_text(): void
    {
        $this->enableSuggestions();
        $this->makeUser(['username' => 'wordliteral', 'display_name' => 'A_B']);
        $this->makeUser(['username' => 'wordlookalike', 'display_name' => 'ACB']);
        $this->actingAs($this->makeUser(['username' => 'wordviewer']));
        $literal = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'A_']);
        self::assertSame(['@wordliteral'], array_column(json_decode($literal->body(), true)['items'], 'token'));
        $wildcard = $this->get('/composer/suggest', ['trigger' => '@', 'q' => '%']);
        self::assertSame([], json_decode($wildcard->body(), true)['items']);
    }

    public function test_private_context_suggests_only_current_readers_and_admins(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'privacyviewer']);
        $author = $this->makeUser(['username' => 'eligibilityauthor']);
        $former = $this->makeUser(['username' => 'eligibilityformer']);
        $this->makeUser(['username' => 'eligibilityoutsider']);
        $this->makeAdmin(['username' => 'eligibilityadmin']);
        $board = $this->makeBoard($this->makeCategory('Current readers'), ['visibility' => 'private']);
        $members = new BoardMemberRepository($this->db);
        foreach ([$viewer, $author, $former] as $user) {
            $members->add((int) $board['id'], (int) $user['id'], null);
        }
        $thread = $this->makeThread($board, $author);
        $this->posting()->reply($this->userEntity($former), $thread['thread_id'], ['body' => 'Historical participation']);
        $members->remove((int) $board['id'], (int) $former['id']);
        $this->actingAs($viewer);

        $query = ['trigger' => '@', 'q' => 'eligibility', 'context' => 'thread', 'target_id' => $thread['thread_id']];
        $response = $this->get('/composer/suggest', $query);
        $this->assertStatus(200, $response);
        self::assertSame(['@eligibilityauthor', '@eligibilityadmin'], array_column(json_decode($response->body(), true)['items'], 'token'));
        $query['q'] = '';
        self::assertSame(['@eligibilityauthor'], array_column(json_decode($this->get('/composer/suggest', $query)->body(), true)['items'], 'token'));
        // Context-free suggestions retain their existing public identity lookup.
        self::assertContains('@eligibilityformer', array_column(json_decode($this->get('/composer/suggest', ['trigger' => '@', 'q' => 'eligibility'])->body(), true)['items'], 'token'));
    }

    public function test_private_context_filters_former_participants_before_each_candidate_cap(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'capprivacyviewer']);
        $author = $this->makeUser(['username' => 'privacyrankzulu']);
        $board = $this->makeBoard($this->makeCategory('Eligible cap'), ['visibility' => 'private']);
        $members = new BoardMemberRepository($this->db);
        $members->add((int) $board['id'], (int) $viewer['id'], null);
        $members->add((int) $board['id'], (int) $author['id'], null);
        $thread = $this->makeThread($board, $author);
        for ($i = 0; $i < 25; $i++) {
            $former = $this->makeUser(['username' => sprintf('privacyrank%02d', $i)]);
            $members->add((int) $board['id'], (int) $former['id'], null);
            $this->posting()->reply($this->userEntity($former), $thread['thread_id'], ['body' => 'Before leaving']);
            $members->remove((int) $board['id'], (int) $former['id']);
        }
        $this->actingAs($viewer);
        foreach (['', 'privacyrank'] as $query) {
            $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => $query, 'context' => 'reply', 'target_id' => $thread['thread_id']]);
            $this->assertStatus(200, $response);
            self::assertSame(['@privacyrankzulu'], array_column(json_decode($response->body(), true)['items'], 'token'));
        }
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
