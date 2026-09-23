<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use Tests\Support\TestCase;

/**
 * Server-rendered contracts the Messages enhancement layer (app.js) builds on:
 * the details toggle's no-JS anchor and the UTC fallback in each <time>.
 */
final class AppMessagesInteractionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // past the setup gate
    }

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>, 2: int} */
    private function pair(): array
    {
        $alice = $this->makeUser(['username' => 'alice', 'post_count' => 1]);
        $bob = $this->makeUser(['username' => 'bob', 'post_count' => 1]);
        $id = (new ConversationRepository($this->db))->findOrCreateBetween((int) $alice['id'], (int) $bob['id']);
        return [$alice, $bob, $id];
    }

    public function testDetailsToggleIsAPlainAnchorUntilScriptMakesItAButton(): void
    {
        [$alice, $bob, $id] = $this->pair();
        (new DmMessageRepository($this->db))->create($id, (int) $bob['id'], 'Hello', '<p>Hello</p>');
        $this->actingAs($alice);

        $body = $this->get('/messages/' . $id)->body();

        self::assertSame(1, preg_match('/<a\b[^>]*\bdata-rail-toggle\b[^>]*>/', $body, $match));
        $toggle = $match[0];
        // Without JS the rail opens by :target, so the anchor names its target
        // and claims no disclosure state it cannot keep true.
        self::assertStringContainsString('href="#dm-rail"', $toggle);
        self::assertStringContainsString('aria-controls="dm-rail"', $toggle);
        self::assertStringNotContainsString('aria-expanded', $toggle);
        self::assertStringNotContainsString('aria-pressed', $toggle);
        self::assertStringNotContainsString('role=', $toggle);
        self::assertStringContainsString('id="dm-rail"', $body);
    }

    public function testTimeTitlesNameTheirZoneOnceAndClockLabelsStayTwentyFourHourUtc(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $messageId = (new DmMessageRepository($this->db))->create($id, (int) $bob['id'], 'Evening', '<p>Evening</p>');
        $this->db->run('UPDATE dm_messages SET created_at = ? WHERE id = ?', ['2026-09-21 17:40:00', $messageId]);
        (new ConversationRepository($this->db))->touch($id, '2026-09-21 17:40:00');
        $this->actingAs($alice);

        foreach (['/messages/' . $id, '/messages'] as $route) {
            $body = $this->get($route)->body();
            self::assertGreaterThan(0, preg_match_all('/<time\b[^>]*\bdata-dm-time="[a-z]+"[^>]*>/', $body, $times), $route);
            foreach ($times[0] as $time) {
                self::assertMatchesRegularExpression('/\bdatetime="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z"/', $time, $route);
                self::assertMatchesRegularExpression('/\btitle="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC"/', $time, $route);
                self::assertStringNotContainsString('Z UTC', $time, $route);
            }
        }

        // The no-JS clock is the mock's 24-hour UTC form; app.js relabels it in
        // the reader's zone (e.g. "12:40 PM" in Chicago) from the datetime.
        self::assertStringContainsString(
            'datetime="2026-09-21T17:40:00Z" title="2026-09-21 17:40:00 UTC" data-dm-time="clock">17:40</time>',
            $this->get('/messages/' . $id)->body(),
        );
    }
}
