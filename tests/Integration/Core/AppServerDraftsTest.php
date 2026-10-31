<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppServerDraftsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        $this->db->run(
            "INSERT INTO settings (`key`, value, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()",
            [json_encode($flags, JSON_THROW_ON_ERROR)],
        );
    }

    public function test_server_draft_endpoints_are_available_by_default_and_can_be_disabled(): void
    {
        // server_drafts graduated to default-on (GA 2026-07-02, ADR 0010): with
        // no features override the JSON sync endpoints are live for an
        // authenticated member, mirroring the polls/topic_workflow graduations.
        $this->actingAs($this->makeUser(['username' => 'defaultdrafts']));

        $saved = $this->post('/api/drafts/thread-default', [
            'revision' => '0',
            'title' => 'Default-on draft',
            'body' => 'Available without an override',
        ]);
        $this->assertStatus(200, $saved);
        self::assertNotSame(404, $this->get('/api/drafts/thread-default')->status());
        self::assertTrue((new \App\Core\FeatureFlags(new \App\Repository\SettingRepository($this->db)))->enabled('server_drafts'));

        // Operator rollback: disabling the flag takes every server-draft route
        // offline (404) — the local-only draft fallback keeps working.
        $this->setFlags(['server_drafts' => false]);
        $this->assertStatus(404, $this->get('/api/drafts/thread-default'));
        $this->assertStatus(404, $this->post('/api/drafts/thread-default', ['revision' => '0', 'body' => 'Hidden']));
        $this->assertStatus(404, $this->post('/drafts/1/discard'));
    }

    public function test_save_load_and_conflict_response(): void
    {
        $this->setFlags(['server_drafts' => true]);
        $this->actingAs($this->makeUser(['username' => 'serverdrafter']));

        $saved = $this->post('/api/drafts/thread-1', [
            'revision' => '0',
            'title' => 'Draft title',
            'body' => 'Server draft body',
            'metadata' => '{"path":"/t/1-topic"}',
        ]);

        $this->assertStatus(200, $saved);
        $payload = json_decode($saved->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, (int) $payload['draft']['revision']);

        $loaded = json_decode($this->get('/api/drafts/thread-1')->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Server draft body', $loaded['draft']['body']);
        self::assertSame('/t/1-topic', $loaded['draft']['metadata']['path']);

        $conflict = $this->post('/api/drafts/thread-1', [
            'revision' => '0',
            'title' => 'Local old title',
            'body' => 'Local old body',
        ]);
        $this->assertStatus(409, $conflict);
        $conflictPayload = json_decode($conflict->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('conflict', $conflictPayload['error']);
        self::assertSame(1, (int) $conflictPayload['server']['revision']);

        $updated = json_decode($this->post('/api/drafts/thread-1', [
            'revision' => '1',
            'title' => 'Updated title',
            'body' => 'Updated body',
        ])->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(2, (int) $updated['draft']['revision']);
        self::assertSame('Updated body', $updated['draft']['body']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('blockedDraftWrites')]
    public function test_restricted_accounts_can_read_drafts_but_cannot_write_them(string $status, string $role, string $operation): void
    {
        $user = $this->makeUser(['role' => $role]);
        $this->actingAs($user);
        $saved = $this->savedDraft('/threads', ['title' => 'Keep this title'], 'Keep this body — café 東京.');
        (new \App\Repository\UserRepository($this->db))->setStatus(
            (int) $user['id'], $status, gmdate('Y-m-d H:i:s', time() + 3600),
        );

        $page = $this->get('/drafts');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('Keep this title', $page->body());
        $loaded = $this->get('/api/drafts/~2Fthreads');
        $this->assertStatus(200, $loaded);
        self::assertSame($saved, json_decode($loaded->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);

        [$url, $input] = match ($operation) {
            'create' => ['/api/drafts/another-context', ['revision' => '0', 'body' => 'Blocked new draft']],
            'update' => ['/api/drafts/~2Fthreads', ['draft_id' => (string) $saved['id'], 'revision' => '1', 'title' => 'Blocked title', 'body' => 'Blocked edit']],
            'discard' => ['/api/drafts/~2Fthreads/discard', []],
            'revision-discard' => ['/api/drafts/~2Fthreads/discard', ['draft_id' => (string) $saved['id'], 'revision' => '1']],
            'page-discard' => ['/drafts/' . $saved['id'] . '/discard', []],
        };
        $this->assertStatus(403, $this->post($url, $input));
        $after = $this->get('/api/drafts/~2Fthreads');
        $this->assertStatus(200, $after);
        self::assertSame($saved, json_decode($after->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
        self::assertNull(json_decode($this->get('/api/drafts/another-context')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
    }

    public static function blockedDraftWrites(): iterable
    {
        foreach (['suspended', 'banned'] as $status) {
            foreach (['user', 'admin'] as $role) {
                foreach (['create', 'update', 'discard', 'revision-discard', 'page-discard'] as $operation) {
                    yield "$status $role $operation" => [$status, $role, $operation];
                }
            }
        }
    }

    public function test_expired_suspension_allows_draft_save_and_revision_discard(): void
    {
        $this->actingAs($this->makeUser([
            'status' => 'suspended', 'suspended_until' => gmdate('Y-m-d H:i:s', time() - 60),
        ]));
        $saved = $this->savedDraft('/threads', ['title' => 'Writable again'], 'The suspension has expired.');
        $this->assertStatus(200, $this->post('/api/drafts/~2Fthreads/discard', [
            'draft_id' => (string) $saved['id'], 'revision' => (string) $saved['revision'],
        ]));
        self::assertNull(json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
    }

    public function test_disabled_server_drafts_stay_unavailable_for_restricted_accounts(): void
    {
        $this->actingAs($this->makeUser(['status' => 'banned']));
        $this->setFlags(['server_drafts' => false]);
        $this->assertStatus(404, $this->get('/api/drafts/~2Fthreads'));
        $this->assertStatus(404, $this->post('/api/drafts/~2Fthreads', ['revision' => '0', 'body' => 'Unavailable']));
        $this->assertStatus(404, $this->post('/api/drafts/~2Fthreads/discard'));
        $this->assertStatus(404, $this->post('/drafts/1/discard'));
    }

    public function test_invalid_discard_context_returns_422_json(): void
    {
        $this->setFlags(['server_drafts' => true]);
        $this->actingAs($this->makeUser(['username' => 'discard-validator']));

        $response = $this->post('/api/drafts/' . str_repeat('a', 192) . '/discard');

        $this->assertStatus(422, $response);
        $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('validation', $payload['error']);
        self::assertSame('Draft context is invalid.', $payload['messages']['context_key']);
    }

    public function test_drafts_page_lists_and_discards_server_drafts_without_js(): void
    {
        $this->setFlags(['server_drafts' => true]);
        $this->actingAs($this->makeUser(['username' => 'listed-drafter']));
        $this->post('/api/drafts/thread-2', [
            'revision' => '0',
            'title' => 'Listed draft',
            'body' => 'No JavaScript needed',
        ]);
        $id = (int) $this->db->fetchValue('SELECT id FROM server_drafts WHERE context_key = ?', ['thread-2']);

        $page = $this->get('/drafts');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('Listed draft', $page->body());
        self::assertStringContainsString('/drafts/' . $id . '/discard', $page->body());
        self::assertStringContainsString('No JavaScript needed', $page->body());

        $this->assertRedirect($this->post('/drafts/' . $id . '/discard'), '/drafts');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM server_drafts WHERE id = ?', [$id]));
    }

    public function test_expired_server_drafts_can_be_purged_by_worker(): void
    {
        $user = $this->makeUser(['username' => 'expired-drafter']);
        $this->db->run(
            "INSERT INTO server_drafts (user_id, context_key, revision, title, body, metadata, updated_at, expires_at)
             VALUES
               (?, 'expired-thread', 1, 'Expired', 'Old body', '{}', UTC_TIMESTAMP(), DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)),
               (?, 'active-thread', 1, 'Active', 'Fresh body', '{}', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 90 DAY))",
            [(int) $user['id'], (int) $user['id']],
        );

        $repo = new \App\Repository\ServerDraftRepository($this->db);
        self::assertSame(1, $repo->purgeExpired());
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM server_drafts WHERE context_key = 'expired-thread'"));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM server_drafts WHERE context_key = 'active-thread'"));
    }

    public function test_owned_topic_draft_has_a_safe_resume_link_and_prefills_without_javascript(): void
    {
        $user = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['allow_anonymous' => 1]);
        $this->actingAs($user);
        $title = 'Café — 東京 <keep literal>';
        $body = "Do not publish yet.\n\nExact Unicode — α.";
        $saved = json_decode($this->post('/api/drafts/~2Fthreads', [
            'revision' => '0', 'title' => $title, 'body' => $body,
            'metadata' => json_encode(['context' => '/threads', 'path' => '//evil.test', 'state' => ['version' => 1, 'fields' => ['title' => $title, 'board_id' => (string) $board['id'], 'is_anonymous' => true]]], JSON_THROW_ON_ERROR),
        ])->body(), true, flags: JSON_THROW_ON_ERROR)['draft'];
        $url = '/compose?draft=' . $saved['id'];
        $list = $this->get('/drafts');
        self::assertStringContainsString('href="' . $url . '"', $list->body());
        self::assertStringNotContainsString('href="//evil.test"', $list->body());
        self::assertStringNotContainsString('>~2Fthreads<', $list->body());
        $resumed = $this->get('/compose', ['draft' => (string) $saved['id']]);
        $this->assertStatus(200, $resumed);
        self::assertStringContainsString('value="Café — 東京 &lt;keep literal&gt;"', $resumed->body());
        self::assertStringContainsString($body, $resumed->body());
        self::assertMatchesRegularExpression('/name="is_anonymous"[^>]*checked/', $resumed->body());
        $this->actingAs($this->makeUser());
        $this->assertStatus(404, $this->get('/compose', ['draft' => (string) $saved['id']]));
    }

    public function test_resume_is_gated_and_does_not_retarget_an_unavailable_board(): void
    {
        $owner = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $this->actingAs($owner);
        $saved = json_decode($this->post('/api/drafts/~2Fthreads', [
            'revision' => '0', 'title' => 'Private draft', 'body' => 'Keep this private body',
            'metadata' => json_encode(['state' => ['version' => 1, 'fields' => ['title' => 'Private draft', 'board_id' => (string) $board['id']]]], JSON_THROW_ON_ERROR),
        ])->body(), true, flags: JSON_THROW_ON_ERROR)['draft'];
        $response = $this->get('/compose', ['draft' => (string) $saved['id']]);
        $this->assertStatus(404, $response);
        self::assertStringNotContainsString('Keep this private body', $response->body());
        $this->setFlags(['server_drafts' => false]);
        $this->assertStatus(404, $this->get('/compose', ['draft' => (string) $saved['id']]));
    }

    public function test_draft_state_rejects_invalid_types_and_excludes_unrelated_fields(): void
    {
        $this->actingAs($this->makeUser());
        $invalid = $this->post('/api/drafts/~2Fthreads', ['revision' => '0', 'body' => 'Body', 'metadata' => '{"state":{"version":1,"fields":{"is_anonymous":"false"}}}']);
        $this->assertStatus(422, $invalid);
        $saved = $this->post('/api/drafts/~2Fthreads', ['revision' => '0', 'body' => 'Body', 'metadata' => '{"state":{"version":1,"fields":{"title":"","is_anonymous":false,"password":"never keep this","_token":"never keep this"}}}']);
        $this->assertStatus(200, $saved);
        $fields = json_decode($saved->body(), true, flags: JSON_THROW_ON_ERROR)['draft']['metadata']['state']['fields'];
        self::assertSame('', $fields['title']);
        self::assertFalse($fields['is_anonymous']);
        self::assertArrayNotHasKey('password', $fields);
        self::assertArrayNotHasKey('_token', $fields);
    }

    public function test_successful_post_preserves_newer_anonymity_only_server_draft(): void
    {
        $user = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['allow_anonymous' => 1]);
        $this->actingAs($user);
        $this->post('/api/drafts/~2Fthreads', [
            'revision' => '0', 'title' => 'Same title', 'body' => 'Same body with a newer anonymity choice.',
            'metadata' => json_encode(['state' => ['version' => 1, 'fields' => ['title' => 'Same title', 'board_id' => (string) $board['id'], 'is_anonymous' => true]]], JSON_THROW_ON_ERROR),
        ]);
        $sent = $this->post('/threads', ['board_id' => (string) $board['id'], 'title' => 'Same title', 'body' => 'Same body with a newer anonymity choice.', 'idempotency_key' => 'older-named-submit']);
        self::assertSame(303, $sent->status());
        $draft = json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft'];
        self::assertIsArray($draft);
        self::assertTrue($draft['metadata']['state']['fields']['is_anonymous']);
    }

    public function test_stale_row_identity_cannot_replace_or_clear_a_recreated_draft(): void
    {
        $this->actingAs($this->makeUser());
        $first = $this->savedDraft('/threads', ['title' => 'First'], 'Old body');
        $this->assertStatus(200, $this->post('/api/drafts/~2Fthreads/discard'));
        $second = $this->savedDraft('/threads', ['title' => 'Second'], 'New body');
        self::assertNotSame($first['id'], $second['id']);
        self::assertSame($first['revision'], $second['revision']);
        foreach (['/api/drafts/~2Fthreads', '/api/drafts/~2Fthreads/discard'] as $url) {
            $response = $this->post($url, ['draft_id' => (string) $first['id'], 'revision' => '1', 'body' => 'Stale replacement']);
            $this->assertStatus(409, $response);
            $server = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR)['server'];
            self::assertSame($second['id'], $server['id']);
            self::assertSame('New body', $server['body']);
        }
        $this->assertStatus(200, $this->post('/api/drafts/~2Fthreads/discard', ['draft_id' => (string) $second['id'], 'revision' => '1']));
        self::assertNull(json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
    }

    public function test_local_resume_rejects_lost_destinations_and_legacy_resume_requires_a_board_choice(): void
    {
        $this->actingAs($this->makeUser());
        $private = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $public = $this->makeBoard($this->makeCategory());
        $this->assertStatus(404, $this->get('/compose', ['resume' => 'local', 'board_id' => (string) $private['id']]));
        $this->assertStatus(404, $this->get('/compose', ['resume' => 'local', 'board_id' => '999999999']));
        $draft = $this->savedDraft('/threads', [], 'A legacy body without a board');
        $page = $this->get('/compose', ['draft' => (string) $draft['id']]);
        $this->assertStatus(200, $page);
        self::assertStringContainsString('A legacy body without a board', $page->body());
        self::assertStringContainsString('<option value="" selected disabled>Choose a board</option>', $page->body());
        self::assertStringContainsString('The original board was not saved.', $page->body());
        self::assertStringNotContainsString('value="' . $public['id'] . '" selected', $page->body());
    }

    public function test_reply_resume_keeps_canonical_query_and_checks_context_expiry_and_merges(): void
    {
        $user = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['allow_anonymous' => 1]);
        $thread = $this->makeThread($board, $user, 'A canonical topic');
        $this->actingAs($user);
        $context = '/t/' . $thread['thread_id'] . '/reply';
        $draft = $this->savedDraft($context, ['is_anonymous' => true], 'An owned reply — α');
        $this->assertRedirect($this->get('/t/' . $thread['thread_id'], ['draft' => (string) $draft['id']]), '/t/' . $thread['thread_id'] . '-' . $thread['slug'] . '?draft=' . $draft['id'], 301);
        $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];
        $page = $this->get($url, ['draft' => (string) $draft['id']]);
        $this->assertStatus(200, $page);
        self::assertStringContainsString('An owned reply — α', $page->body());
        self::assertMatchesRegularExpression('/name="is_anonymous"[^>]*checked/', $page->body());
        $this->assertStatus(404, $this->get('/compose', ['draft' => (string) $draft['id']]));
        $this->db->run('UPDATE threads SET is_locked = 1 WHERE id = ?', [$thread['thread_id']]);
        $this->assertStatus(404, $this->get($url, ['draft' => (string) $draft['id']]));
        $this->db->run('UPDATE threads SET is_locked = 0 WHERE id = ?', [$thread['thread_id']]);
        $this->db->run('UPDATE server_drafts SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE id = ?', [$draft['id']]);
        $this->assertStatus(404, $this->get($url, ['draft' => (string) $draft['id']]));
        $target = $this->makeThread($board, $user, 'Merge destination');
        $this->db->run('INSERT INTO thread_redirects (old_thread_id, canonical_thread_id) VALUES (?, ?)', [$thread['thread_id'], $target['thread_id']]);
        $this->assertStatus(404, $this->get($url, ['draft' => (string) $draft['id']]));
        $this->assertStatus(404, $this->get($url, ['resume' => 'local']));
        self::assertSame(301, $this->get($url)->status());
    }

    public function test_message_resume_restores_only_enabled_wrapper_fields(): void
    {
        $this->actingAs($this->makeUser());
        $draft = $this->savedDraft('/messages', ['to' => 'alice, bob', 'title' => 'A group — 東京'], 'Private body');
        $page = $this->get('/messages/new', ['draft' => (string) $draft['id']]);
        $this->assertStatus(200, $page);
        self::assertStringContainsString('value="alice, bob"', $page->body());
        self::assertStringContainsString('value="A group — 東京"', $page->body());
        self::assertStringContainsString('Private body', $page->body());
        $this->setFlags(['group_dms' => false]);
        $page = $this->get('/messages/new', ['draft' => (string) $draft['id']]);
        self::assertStringNotContainsString('name="title"', $page->body());
        self::assertStringNotContainsString('A group — 東京', $page->body());
        self::assertStringContainsString('Private body', $page->body());
        $this->setFlags(['dms' => false]);
        $this->assertStatus(404, $this->get('/messages/new', ['draft' => (string) $draft['id']]));
    }

    public function test_matching_submission_clears_full_state_but_other_submission_identity_is_preserved(): void
    {
        $user = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->actingAs($user);
        $fields = ['title' => 'Matching title', 'board_id' => (string) $board['id'], 'is_anonymous' => false];
        $this->savedDraft('/threads', $fields, 'Exactly submitted body', 'matching-key');
        $response = $this->post('/threads', $fields + ['body' => 'Exactly submitted body', 'idempotency_key' => 'matching-key']);
        self::assertSame(303, $response->status());
        self::assertNull(json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
        $this->savedDraft('/threads', $fields, 'Exactly submitted body', 'newer-key');
        $response = $this->post('/threads', $fields + ['body' => 'Exactly submitted body', 'idempotency_key' => 'older-key']);
        self::assertSame(303, $response->status());
        self::assertSame('newer-key', json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']['metadata']['submission']);
    }

    public function test_direct_message_resume_does_not_invent_a_group_title_from_its_list_label(): void
    {
        $this->actingAs($this->makeUser());
        $draft = $this->savedDraft('/messages', ['to' => 'alice'], 'One-recipient body');
        $page = $this->get('/messages/new', ['draft' => (string) $draft['id']]);
        $this->assertStatus(200, $page);
        self::assertStringContainsString('value="alice"', $page->body());
        self::assertStringContainsString('One-recipient body', $page->body());
        self::assertStringNotContainsString('value="Context label"', $page->body());
    }

    public function test_legacy_topic_without_metadata_still_clears_on_matching_submission(): void
    {
        $this->actingAs($this->makeUser(['username' => 'legacy-completion']));
        $board = $this->makeBoard($this->makeCategory());
        $fields = ['title' => 'Legacy saved topic', 'body' => 'An older client saved this complete body.'];
        $saved = $this->post('/api/drafts/~2Fthreads', ['revision' => '0'] + $fields);
        $this->assertStatus(200, $saved);
        $sent = $this->post('/threads', $fields + ['board_id' => (string) $board['id'], 'idempotency_key' => 'legacy-topic-completion']);
        $this->assertStatus(303, $sent);
        self::assertNull(json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
    }

    public function test_unchanged_resumed_topic_clears_its_exact_source_without_reusing_submission_identity(): void
    {
        $this->actingAs($this->makeUser());
        $board = $this->makeBoard($this->makeCategory(), ['allow_anonymous' => 1]);
        $fields = ['title' => 'Resumed — 東京', 'board_id' => (string) $board['id'], 'is_anonymous' => true];
        $body = "Unchanged saved text.\n\nCafé — α.";
        $draft = $this->savedDraft('/threads', $fields, $body, 'original-draft-identity');
        $page = $this->get('/compose', ['draft' => (string) $draft['id']]);
        $hidden = $this->submissionFields($page->body());
        self::assertSame($draft['id'] . ':' . $draft['revision'], $hidden['draft_source']);
        self::assertNotSame('original-draft-identity', $hidden['idempotency_key']);
        $sent = $this->post('/threads', $hidden + $fields + ['body' => $body]);
        $this->assertStatus(303, $sent);
        self::assertNull(json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
    }

    /** @return array<string,string> */
    private function submissionFields(string $html): array
    {
        preg_match_all('/name="(idempotency_key|draft_source)" value="([^"]*)"/', $html, $matches, PREG_SET_ORDER);
        $fields = [];
        foreach ($matches as $match) { $fields += [$match[1] => html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')]; }
        self::assertArrayHasKey('idempotency_key', $fields);
        self::assertArrayHasKey('draft_source', $fields);
        return $fields;
    }

    public function test_resumed_reply_and_message_forms_complete_their_exact_saved_copy(): void
    {
        $user = $this->makeUser(['post_count' => 3]);
        $recipient = $this->makeUser(['username' => 'resume-recipient']);
        $board = $this->makeBoard($this->makeCategory(), ['allow_anonymous' => 1]);
        $thread = $this->makeThread($board, $user, 'Reply destination');
        $this->actingAs($user);
        $replyContext = '/t/' . $thread['thread_id'] . '/reply';
        $body = 'Unchanged reply — café 東京.';
        $draft = $this->savedDraft($replyContext, ['is_anonymous' => false], $body, 'reply-origin');
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug'], ['draft' => (string) $draft['id']]);
        $sent = $this->post($replyContext, $this->submissionFields($page->body()) + ['body' => $body]);
        $this->assertStatus(303, $sent);
        self::assertNull(json_decode($this->get('/api/drafts/' . str_replace('%', '~', rawurlencode($replyContext)))->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);

        $fields = ['to' => $recipient['username']];
        $draft = $this->savedDraft('/messages', $fields, $body, 'message-origin');
        $page = $this->get('/messages/new', ['draft' => (string) $draft['id']]);
        $sent = $this->post('/messages', $this->submissionFields($page->body()) + $fields + ['body' => $body, 'title' => '']);
        $this->assertStatus(303, $sent);
        self::assertNull(json_decode($this->get('/api/drafts/~2Fmessages')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
        $location = $sent->headers()['location'];
        self::assertMatchesRegularExpression('#^/messages/([0-9]+)#', $location);
        preg_match('#^(/messages/[0-9]+)#', $location, $matched);
        $context = $matched[1];
        $draft = $this->savedDraft($context, [], $body, 'message-reply-origin');
        $page = $this->get($context, ['draft' => (string) $draft['id']]);
        $sent = $this->post($context, $this->submissionFields($page->body()) + ['body' => $body]);
        $this->assertStatus(303, $sent);
        self::assertNull(json_decode($this->get('/api/drafts/' . str_replace('%', '~', rawurlencode($context)))->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unmatchedResumeSources')]
    public function test_resume_cleanup_preserves_any_copy_outside_its_exact_source(string $scenario): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $board = $this->makeBoard($this->makeCategory(), ['allow_anonymous' => 1]);
        $fields = ['title' => 'A retained copy', 'board_id' => (string) $board['id'], 'is_anonymous' => false];
        $body = 'Same body does not establish completion.';
        $draft = $this->savedDraft('/threads', $fields, $body, 'saved-identity');
        $hidden = $this->submissionFields($this->get('/compose', ['draft' => (string) $draft['id']])->body());
        $input = $hidden + $fields + ['body' => $body];
        if ($scenario === 'newer-revision') {
            $updated = $this->post('/api/drafts/~2Fthreads', [
                'draft_id' => (string) $draft['id'], 'revision' => '1', 'title' => $fields['title'], 'body' => $body,
                'metadata' => json_encode(['submission' => 'newer-identity', 'state' => ['version' => 1, 'fields' => $fields]], JSON_THROW_ON_ERROR),
            ]);
            $this->assertStatus(200, $updated);
            $draft = json_decode($updated->body(), true, flags: JSON_THROW_ON_ERROR)['draft'];
        } elseif ($scenario === 'recreated-row') {
            $this->assertStatus(200, $this->post('/api/drafts/~2Fthreads/discard'));
            $draft = $this->savedDraft('/threads', $fields, $body, 'recreated-identity');
        } elseif ($scenario === 'other-owner') {
            $this->actingAs($this->makeUser());
            $draft = $this->savedDraft('/threads', $fields, $body, 'other-owner-identity');
        } elseif ($scenario === 'other-body') {
            $input['body'] = 'Different body in the submitted form.';
        } elseif ($scenario === 'other-title') {
            $input['title'] = 'Different submitted title';
        } elseif ($scenario === 'other-anonymity') {
            $input['is_anonymous'] = true;
        } else {
            $input['draft_source'] = $scenario === 'malformed-source' ? ['unexpected'] : '';
        }
        $this->assertStatus(303, $this->post('/threads', $input));
        $retained = json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft'];
        self::assertIsArray($retained);
        self::assertSame((int) $draft['id'], (int) $retained['id']);
        self::assertSame((int) $draft['revision'], (int) $retained['revision']);
        self::assertSame($body, $retained['body']);
        self::assertSame($fields, $retained['metadata']['state']['fields']);
    }

    public static function unmatchedResumeSources(): iterable
    {
        foreach (['newer-revision', 'recreated-row', 'other-owner', 'other-body', 'other-title', 'other-anonymity', 'malformed-source', 'missing-source'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_resumed_source_survives_validation_and_retry_does_not_clear_a_new_draft(): void
    {
        $this->actingAs($this->makeUser());
        $board = $this->makeBoard($this->makeCategory());
        $fields = ['title' => 'Recovered title', 'board_id' => (string) $board['id'], 'is_anonymous' => false];
        $body = 'A corrected form can finish its original copy.';
        $draft = $this->savedDraft('/threads', $fields, $body, 'resume-validation');
        $hidden = $this->submissionFields($this->get('/compose', ['draft' => (string) $draft['id']])->body());
        $invalid = $this->post('/threads', ['title' => ''] + $hidden + $fields + ['body' => $body]);
        $this->assertStatus(422, $invalid);
        $retry = $this->submissionFields($invalid->body());
        self::assertSame($hidden['draft_source'], $retry['draft_source']);
        $input = $retry + $fields + ['body' => $body];
        $sent = $this->post('/threads', $input);
        $this->assertStatus(303, $sent);
        self::assertNull(json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']);
        $newer = $this->savedDraft('/threads', $fields, $body, 'new-after-send');
        $replayed = $this->post('/threads', $input);
        self::assertSame($sent->status(), $replayed->status());
        self::assertSame($sent->headers()['location'], $replayed->headers()['location']);
        self::assertSame((int) $newer['id'], (int) json_decode($this->get('/api/drafts/~2Fthreads')->body(), true, flags: JSON_THROW_ON_ERROR)['draft']['id']);
    }

    /** Save through the public endpoint so regression fixtures share its validation. */
    private function savedDraft(string $context, array $fields, string $body, string $submission = ''): array
    {
        $response = $this->post('/api/drafts/' . str_replace('%', '~', rawurlencode($context)), [
            'revision' => '0', 'draft_id' => '0', 'title' => $fields['title'] ?? 'Context label', 'body' => $body,
            'metadata' => json_encode(['submission' => $submission, 'state' => ['version' => 1, 'fields' => $fields]], JSON_THROW_ON_ERROR),
        ]);
        $this->assertStatus(200, $response);
        return json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR)['draft'];
    }
}
