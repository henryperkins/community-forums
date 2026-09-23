<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\ConversationRepository;
use Tests\Support\TestCase;

/**
 * The Messages conversation dock rests as one row on phones, like the thread
 * reply dock (app.css keys the resting geometry off `.dm-composer` without
 * `.is-expanded`; composer.js wires the same expansion controller). The server
 * owns the first paint of a rejected reply: it must arrive open on the
 * preserved draft and the error, never folded away.
 */
final class AppMessagesShortScreenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>, 2: int} */
    private function pair(): array
    {
        $alice = $this->makeUser(['username' => 'alice', 'post_count' => 1]);
        $bob = $this->makeUser(['username' => 'bob', 'post_count' => 1]);
        $this->db->run('UPDATE users SET post_count = 1 WHERE id IN (?, ?)', [$alice['id'], $bob['id']]);
        $alice = $this->users()->find((int) $alice['id']);
        $bob = $this->users()->find((int) $bob['id']);
        $id = (new ConversationRepository($this->db))->findOrCreateBetween((int) $alice['id'], (int) $bob['id']);

        return [$alice, $bob, $id];
    }

    private function composerForm(string $html, string $instance): string
    {
        $pattern = '/<form\b(?=[^>]*data-composer-instance="'
            . preg_quote($instance, '/')
            . '")[^>]*>.*?<\/form>/s';
        self::assertSame(1, preg_match($pattern, $html, $matches), 'missing composer instance ' . $instance);

        return $matches[0];
    }

    private function formClass(string $form): string
    {
        self::assertSame(1, preg_match('/^<form\b[^>]*\bclass="([^"]*)"/', $form, $matches));

        return $matches[1];
    }

    public function testConversationDockRestsOnAPlainVisitAndOnlyTheRoomCarriesTheDockClass(): void
    {
        [$alice, , $id] = $this->pair();
        $this->actingAs($alice);

        $room = $this->get('/messages/' . $id);
        self::assertSame(200, $room->status());
        // The short-screen layout rules key off the room shell's attribute.
        self::assertMatchesRegularExpression('/<div class="dm-shell[^"]*" data-dm-conversation="' . $id . '"/', $room->body());
        $dock = $this->composerForm($room->body(), 'dm-conversation-' . $id);
        self::assertSame('composer composer-shell dm-composer', $this->formClass($dock));

        // The standalone page and the compose dialog are never the dock.
        $newPage = $this->get('/messages/new');
        self::assertSame(200, $newPage->status());
        self::assertStringNotContainsString('dm-composer', $this->formClass($this->composerForm($newPage->body(), 'dm-new-page')));
        self::assertStringNotContainsString('dm-composer', $this->formClass($this->composerForm($room->body(), 'dm-new-dialog')));
    }

    public function testRejectedReplyArrivesOpenOnThePreservedDraft(): void
    {
        [$alice, , $id] = $this->pair();
        $this->actingAs($alice);

        $tooLong = str_repeat('Counsel kept. ', 400);
        $response = $this->post('/messages/' . $id, ['body' => $tooLong]);
        self::assertSame(422, $response->status());
        $dock = $this->composerForm($response->body(), 'dm-conversation-' . $id);
        self::assertSame('composer composer-shell dm-composer is-expanded', $this->formClass($dock));
        self::assertStringContainsString('Your message is too long.', $dock);
        self::assertStringContainsString(htmlspecialchars(trim($tooLong), ENT_QUOTES), $dock);

        // A blank body leaves nothing to preserve, but the error still has to show.
        $response = $this->post('/messages/' . $id, ['body' => '   ']);
        self::assertSame(422, $response->status());
        $dock = $this->composerForm($response->body(), 'dm-conversation-' . $id);
        self::assertStringContainsString('is-expanded', $this->formClass($dock));
        self::assertStringContainsString('Write a message before sending.', $dock);
        self::assertMatchesRegularExpression('/<textarea[^>]*aria-invalid="true"[^>]*autofocus/', $dock);
    }
}
