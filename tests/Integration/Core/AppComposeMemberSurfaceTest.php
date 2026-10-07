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

    public function test_numeric_board_slug_takes_precedence_over_another_boards_id(): void
    {
        $category = $this->makeCategory('Numeric destinations');
        $idBoard = $this->makeBoard($category, ['slug' => 'id-destination']);
        $slugBoard = $this->makeBoard($category, ['slug' => (string) $idBoard['id']]);
        $member = $this->makeUser(['username' => 'numericslug']);
        $thread = $this->makeThread($slugBoard, $member);
        $this->actingAs($member);

        // The header sends the slug from both board and topic pages.
        foreach (['/c/' . $slugBoard['slug'], '/t/' . $thread['thread_id'] . '-' . $thread['slug']] as $path) {
            $page = $this->get($path);
            $this->assertStatus(200, $page);
            self::assertStringContainsString('href="/compose?board=' . $slugBoard['slug'] . '"', $page->body());
        }

        foreach ([[$idBoard['id'], $slugBoard['id']], [$slugBoard['id'], $idBoard['id']]] as $order) {
            $this->boards()->setPositions($category, $order);
            $page = $this->get('/compose', ['board' => (string) $slugBoard['slug']]);
            $this->assertStatus(200, $page);
            $this->assertSelectedComposeBoard($page->body(), $slugBoard);
        }
    }

    public function test_board_parameter_keeps_id_selection_when_no_slug_matches(): void
    {
        $category = $this->makeCategory('ID destinations');
        $this->makeBoard($category, ['slug' => 'first-id-fallback']);
        $target = $this->makeBoard($category, ['slug' => 'legacy-id-target']);
        $this->actingAs($this->makeUser(['username' => 'legacycomposeid']));

        $page = $this->get('/compose', ['board' => (string) $target['id']]);
        $this->assertStatus(200, $page);
        $this->assertSelectedComposeBoard($page->body(), $target);
    }

    public function test_explicit_board_id_never_matches_another_boards_slug(): void
    {
        $category = $this->makeCategory('Explicit ID destinations');
        $fallback = $this->makeBoard($category, ['slug' => 'explicit-id-fallback']);
        $target = $this->makeBoard($category, ['slug' => 'explicit-id-target']);
        $collision = $this->makeBoard($category, ['slug' => (string) $target['id']]);
        $this->boards()->setPositions($category, [$fallback['id'], $collision['id'], $target['id']]);
        $this->actingAs($this->makeUser(['username' => 'explicitcomposeid']));

        $byId = $this->get('/compose', ['board_id' => (string) $target['id']]);
        $this->assertStatus(200, $byId);
        $this->assertSelectedComposeBoard($byId->body(), $target);

        $bySlugInIdField = $this->get('/compose', ['board_id' => (string) $target['slug']]);
        $this->assertStatus(200, $bySlugInIdField);
        $this->assertSelectedComposeBoard($bySlugInIdField->body(), $fallback);
    }

    public function test_validation_preserves_the_submitted_board_id_despite_a_numeric_slug_collision(): void
    {
        $category = $this->makeCategory('Rejected ID destinations');
        $target = $this->makeBoard($category, ['slug' => 'rejected-id-target', 'allow_anonymous' => 1]);
        $collision = $this->makeBoard($category, ['slug' => (string) $target['id']]);
        $this->boards()->setPositions($category, [$collision['id'], $target['id']]);
        $this->actingAs($this->makeUser(['username' => 'rejectedcomposeid']));

        $failed = $this->post('/threads', [
            'board_id' => (int) $target['id'],
            'board' => (string) $collision['slug'],
            'title' => 'Hi',
            'body' => 'This rejected draft stays in its intended board.',
            'is_anonymous' => '1',
        ]);

        $this->assertStatus(422, $failed);
        $this->assertSelectedComposeBoard($failed->body(), $target);
        self::assertStringContainsString('value="Hi"', $failed->body());
        self::assertStringContainsString('This rejected draft stays in its intended board.', $failed->body());
        self::assertMatchesRegularExpression('/<input\b[^>]*name="is_anonymous"[^>]*\bchecked\b/', $failed->body());
        $this->assertSeeText($failed, 'Give the topic a title before you open it.');
    }

    public function test_unpostable_numeric_slug_falls_back_instead_of_selecting_its_colliding_id(): void
    {
        $category = $this->makeCategory('Guarded numeric destinations');
        $fallback = $this->makeBoard($category, ['slug' => 'guarded-fallback']);
        $idBoard = $this->makeBoard($category, ['slug' => 'guarded-id-destination']);
        $restricted = $this->makeBoard($category, [
            'slug' => (string) $idBoard['id'],
            'post_min_role' => 'admin',
        ]);
        $archived = $this->makeBoard($category, ['slug' => 'archived-destination']);
        $this->boards()->setArchived((int) $archived['id'], true);
        $private = $this->makeBoard($category, ['slug' => 'inaccessible-destination', 'visibility' => 'private']);
        $this->actingAs($this->makeUser(['username' => 'guardedcompose']));

        foreach ([$restricted, $archived, $private] as $destination) {
            $page = $this->get('/compose', ['board' => (string) $destination['slug']]);
            $this->assertStatus(200, $page);
            $this->assertSelectedComposeBoard($page->body(), $fallback);
            self::assertStringNotContainsString('data-board-slug="inaccessible-destination"', $page->body());
            self::assertStringNotContainsString((string) $private['name'], $page->body());
        }

        $this->assertStatus(403, $this->post('/threads', [
            'board_id' => (int) $restricted['id'],
            'title' => 'An unauthorized topic',
            'body' => 'The destination picker must not grant posting rights.',
        ]));
        $this->assertStatus(404, $this->get('/c/' . $private['slug']));
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
        self::assertStringNotContainsString('forum-bar-compose', $get->body());
        self::assertStringNotContainsString('Topic opened in', $get->body());

        $success = $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'A properly titled topic',
            'body' => 'A canonical successful body.',
            'idempotency_key' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ]);
        $this->assertRedirectContains($success, '/t/');
    }

    /** @param array<string,mixed> $board */
    private function assertSelectedComposeBoard(string $html, array $board): void
    {
        self::assertSame(1, preg_match('/data-compose-selected-board="([^"]+)"/', $html, $surface));
        self::assertSame((string) $board['slug'], $surface[1]);
        self::assertSame(1, preg_match('/<option value="(\d+)"[^>]*\bselected\b/', $html, $option));
        self::assertSame((string) $board['id'], $option[1]);
    }
}
