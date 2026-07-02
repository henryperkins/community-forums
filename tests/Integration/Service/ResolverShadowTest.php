<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\Telemetry;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\SettingRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use App\Service\ResolverShadow;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08): shadow comparison records mismatches to telemetry and
 * never changes or breaks the caller's decision.
 */
final class ResolverShadowTest extends TestCase
{
    /** @var list<string> */
    private array $lines = [];

    private function telemetry(): Telemetry
    {
        $this->lines = [];

        return new Telemetry(
            new Config(['telemetry' => ['enabled' => true]]),
            function (string $line): void {
                $this->lines[] = $line;
            },
        );
    }

    private function resolver(?Database $db = null): CapabilityResolver
    {
        $db ??= $this->db;

        return new CapabilityResolver(
            new RoleCapabilityRepository($db),
            new RoleAssignmentRepository($db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($db)),
            new ProtectedOwnerRepository($db),
            $this->boards(),
            new BoardMemberRepository($db),
            new BoardPolicy(),
            new WriteGate(),
        );
    }

    /** @return list<array<string,mixed>> */
    private function events(string $event): array
    {
        $out = [];
        foreach ($this->lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded) && ($decoded['event'] ?? null) === $event) {
                $out[] = $decoded;
            }
        }

        return $out;
    }

    public function test_agreement_emits_nothing_and_mismatch_emits_one_event(): void
    {
        $board = $this->makeBoard($this->makeCategory('Shadow'));
        $admin = User::fromRow($this->makeAdmin());
        $shadow = new ResolverShadow($this->resolver(), $this->telemetry());

        $shadow->compare(true, $admin, 'core.post.delete_any', ['board_id' => (int) $board['id']], 'test');
        self::assertSame([], $this->events('resolver.shadow_mismatch'));

        $shadow->compare(false, $admin, 'core.post.delete_any', ['board_id' => (int) $board['id']], 'test');
        $events = $this->events('resolver.shadow_mismatch');
        self::assertCount(1, $events);
        self::assertSame('core.post.delete_any', $events[0]['capability']);
        self::assertFalse($events[0]['legacy']);
        self::assertTrue($events[0]['resolver']);
        self::assertSame('test', $events[0]['site']);
    }

    public function test_resolver_exception_fails_open_with_error_event(): void
    {
        $telemetry = $this->telemetry();
        $badDb = new Database([
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'missing',
            'charset' => 'utf8mb4',
            'username' => 'missing',
            'password' => 'missing',
        ]);

        $shadow = new ResolverShadow($this->resolver($badDb), $telemetry);
        $shadow->compare(true, null, 'core.board.read', [], 'test');

        self::assertCount(1, $this->events('resolver.shadow_error'));
        self::assertSame([], $this->events('resolver.shadow_mismatch'));
    }

    public function test_moderation_and_posting_shadow_paths_agree_on_the_fixture(): void
    {
        (new SettingRepository($this->db))->set('features', ['capabilities' => true]);
        $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory('ShadowHttp'));
        $user = $this->makeUser();
        $this->actingAs($user);

        $resp = $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Shadow soak',
            'body' => 'A body long enough.',
        ]);
        $this->assertRedirectContains($resp, '/t/');

        $mod = $this->makeUser();
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $this->actingAs($mod);
        $thread = $this->db->fetch('SELECT id FROM threads WHERE title = ?', ['Shadow soak']);
        self::assertNotNull($thread);
        $resp = $this->post('/mod/t/' . (int) $thread['id'] . '/lock');
        self::assertContains($resp->status(), [302, 303]);
    }
}
