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

    public function test_server_draft_endpoints_are_dark_by_default(): void
    {
        $this->actingAs($this->makeUser(['username' => 'darkdrafts']));

        $this->assertStatus(404, $this->get('/api/drafts/thread-1'));
        $this->assertStatus(404, $this->post('/api/drafts/thread-1', ['revision' => '0', 'body' => 'Hidden']));
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
}
