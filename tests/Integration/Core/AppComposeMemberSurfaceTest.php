<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use Tests\Support\TestCase;

final class AppComposeMemberSurfaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'composebootstrap']);
    }

    public function test_get_is_authenticated_and_lists_policy_visible_boards_with_server_owned_postability(): void
    {
        $member = $this->makeUser(['username' => 'composermember']);
        $category = $this->makeCategory('Council rooms');
        $public = $this->makeBoard($category, ['slug' => 'public-room', 'name' => 'Public Room']);
        $wardens = $this->makeBoard($category, [
            'slug' => 'warden-room',
            'name' => 'Warden Room',
            'post_min_role' => 'admin',
        ]);
        $archived = $this->makeBoard($category, ['slug' => 'old-room', 'name' => 'Old Room']);
        $this->boards()->setArchived((int) $archived['id'], true);
        $privateMember = $this->makeBoard($category, [
            'slug' => 'member-room',
            'name' => 'Member Room',
            'visibility' => 'private',
        ]);
        $privateOther = $this->makeBoard($category, [
            'slug' => 'other-room',
            'name' => 'Other Room',
            'visibility' => 'private',
        ]);
        $hidden = $this->makeBoard($category, [
            'slug' => 'hidden-room',
            'name' => 'Hidden Room',
            'visibility' => 'hidden',
        ]);
        (new BoardMemberRepository($this->db))->add((int) $privateMember['id'], (int) $member['id'], null);

        $guest = $this->get('/compose');
        $this->assertRedirectContains($guest, '/login?next=');

        $this->actingAs($member);
        $page = $this->get('/compose', ['board' => 'member-room']);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Posting to Member Room');
        $this->assertSeeText($page, 'Open a topic');
        $this->assertSeeText($page, 'Say what you want the council to consider, and what would change your mind.');
        self::assertStringContainsString('data-compose-selected-board="member-room"', $page->body());
        self::assertStringContainsString('data-compose-board-picker="public-room"', $page->body());
        self::assertStringContainsString('data-compose-board-picker="member-room"', $page->body());
        self::assertStringContainsString('data-compose-board-disabled="warden-room"', $page->body());
        self::assertStringContainsString('data-compose-board-disabled="old-room"', $page->body());
        self::assertStringContainsString('title="Only wardens may open a topic here"', $page->body());
        self::assertStringNotContainsString('Other Room', $page->body());
        self::assertStringNotContainsString('Hidden Room', $page->body());
        self::assertMatchesRegularExpression('/<option value="' . (int) $privateMember['id'] . '"[^>]*selected[^>]*>Member Room<\/option>/', $page->body());
        self::assertMatchesRegularExpression('/<option value="' . (int) $wardens['id'] . '"[^>]*disabled[^>]*>Warden Room<\/option>/', $page->body());
        self::assertMatchesRegularExpression('/<option value="' . (int) $archived['id'] . '"[^>]*disabled[^>]*>Old Room<\/option>/', $page->body());

        $byId = $this->get('/compose', ['board' => (string) $public['id']]);
        self::assertStringContainsString('data-compose-selected-board="public-room"', $byId->body());
        $fallback = $this->get('/compose', ['board' => 'not-a-board']);
        self::assertStringContainsString('data-compose-selected-board="public-room"', $fallback->body());

        $legacyBody = $page->body();
        $this->withCapabilitiesEnforced();
        $enforced = $this->get('/compose', ['board' => 'member-room']);
        $this->assertStatus(200, $enforced);
        foreach (['public-room', 'member-room'] as $slug) {
            self::assertSame(
                str_contains($legacyBody, 'data-compose-board-picker="' . $slug . '"'),
                str_contains($enforced->body(), 'data-compose-board-picker="' . $slug . '"'),
                $slug,
            );
        }
        foreach (['warden-room', 'old-room'] as $slug) {
            self::assertSame(
                str_contains($legacyBody, 'data-compose-board-disabled="' . $slug . '"'),
                str_contains($enforced->body(), 'data-compose-board-disabled="' . $slug . '"'),
                $slug,
            );
        }

        // Keep these locals live so fixture intent is explicit when this test is edited.
        self::assertGreaterThan(0, (int) $privateOther['id']);
        self::assertGreaterThan(0, (int) $hidden['id']);
    }

    public function test_non_writable_account_states_cannot_open_the_compose_surface(): void
    {
        $this->makeBoard($this->makeCategory(), ['slug' => 'state-room']);
        foreach (['suspended', 'banned', 'deactivated', 'pending_deletion'] as $status) {
            $user = $this->makeUser([
                'username' => 'compose' . str_replace('_', '', $status),
                'status' => $status,
                'suspended_until' => $status === 'suspended' ? '2999-01-01 00:00:00' : null,
            ]);
            $this->actingAs($user);
            $this->assertStatus(403, $this->get('/compose'));
        }
    }

    public function test_anonymity_control_is_rendered_dormant_when_another_postable_board_allows_it(): void
    {
        $member = $this->makeUser(['username' => 'composeanonymousswitch']);
        $category = $this->makeCategory('Anonymity destinations');
        $plain = $this->makeBoard($category, [
            'slug' => 'plain-destination',
            'name' => 'Plain Destination',
            'allow_anonymous' => 0,
        ]);
        $this->makeBoard($category, [
            'slug' => 'anonymous-destination',
            'name' => 'Anonymous Destination',
            'allow_anonymous' => 1,
        ]);
        $this->actingAs($member);

        $page = $this->get('/compose', ['board' => (string) $plain['id']]);

        $this->assertStatus(200, $page);
        self::assertMatchesRegularExpression('/<span class="composer-anonymous-chip"[^>]*data-compose-anonymous[^>]*hidden>/', $page->body());
        self::assertMatchesRegularExpression('/<input\b[^>]*name="is_anonymous"[^>]*disabled/', $page->body());
        self::assertMatchesRegularExpression('/<span class="composer-anonymous-disclosure"[^>]*data-compose-anonymous[^>]*hidden>/', $page->body());
    }

    public function test_validation_preserves_the_draft_board_anonymity_and_shared_composer_contract(): void
    {
        $member = $this->makeUser(['username' => 'draftkeeper']);
        $board = $this->makeBoard($this->makeCategory(), [
            'slug' => 'draft-room',
            'name' => 'Draft Room',
            'allow_anonymous' => 1,
        ]);
        $this->actingAs($member);

        $csrf = $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'A valid title',
            'body' => 'A valid body.',
        ], false);
        $this->assertStatus(403, $csrf);

        $failed = $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Hi',
            'body' => 'Draft body that should survive.',
            'is_anonymous' => '1',
            'idempotency_key' => '0123456789abcdef0123456789abcdef',
        ]);
        $this->assertStatus(422, $failed);
        $this->assertSeeText($failed, 'Give the topic a title before you open it.');
        self::assertStringContainsString('name="title"', $failed->body());
        self::assertStringContainsString('value="Hi"', $failed->body());
        self::assertStringContainsString('Draft body that should survive.', $failed->body());
        self::assertMatchesRegularExpression('/<option value="' . (int) $board['id'] . '"[^>]*selected/', $failed->body());
        self::assertMatchesRegularExpression('/<input\b[^>]*name="is_anonymous"[^>]*\bchecked\b/', $failed->body());
        self::assertMatchesRegularExpression('/<input\b[^>]*name="title"[^>]*aria-invalid="true"[^>]*autofocus/', $failed->body());
        self::assertSame(1, substr_count($failed->body(), 'data-composer-instance="new-thread-page"'));
        self::assertSame(1, substr_count($failed->body(), 'name="idempotency_key"'));

        $invalidBoard = $this->post('/threads', [
            'board_id' => 999999,
            'title' => 'Preserved title',
            'body' => 'Preserved invalid-board body.',
            'idempotency_key' => 'fedcba9876543210fedcba9876543210',
        ]);
        $this->assertStatus(422, $invalidBoard);
        $this->assertSeeText($invalidBoard, 'Choose a board to post in.');
        self::assertStringContainsString('value="Preserved title"', $invalidBoard->body());
        self::assertStringContainsString('Preserved invalid-board body.', $invalidBoard->body());

        $get = $this->get('/compose', ['board' => 'draft-room']);
        $this->assertStatus(200, $get);
        self::assertStringContainsString('data-compose-board-select', $get->body());
        self::assertStringContainsString('data-compose-board-picker="draft-room"', $get->body());
        self::assertStringContainsString('href="/" class="compose-cancel"', $get->body());
        self::assertStringContainsString('data-composer-draft-slot', $get->body());
        self::assertStringContainsString('Draft kept on this device.', $get->body());
        self::assertStringNotContainsString('topbar-new-topic', $get->body());
        self::assertStringNotContainsString('Topic opened in', $get->body());

        $success = $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'A properly titled topic',
            'body' => 'A canonical successful body.',
            'idempotency_key' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ]);
        $this->assertRedirectContains($success, '/t/');
    }
}
