<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Core\Config;
use App\Core\ForbiddenException;
use App\Core\Telemetry;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Security\AuthorityGate;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use App\Service\ResolverShadow;
use Tests\Support\TestCase;

final class AuthorityGateTest extends TestCase
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

    /** @return list<string> */
    private function eventNames(): array
    {
        $names = [];
        foreach ($this->lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded) && isset($decoded['event'])) {
                $names[] = (string) $decoded['event'];
            }
        }

        return $names;
    }

    private function resolver(): CapabilityResolver
    {
        return new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            new BoardRepository($this->db),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
    }

    public function test_legacy_mode_returns_legacy_verbatim_and_never_consults_a_resolver(): void
    {
        $gate = AuthorityGate::legacy(); // null resolver: consulting it would fatal
        $user = $this->userEntity($this->makeUser());

        self::assertTrue($gate->allows(fn (): bool => true, $user, 'core.thread.lock', ['board_id' => 1], 'test'));
        self::assertFalse($gate->allows(fn (): bool => false, $user, 'core.thread.lock', ['board_id' => 1], 'test'));
        self::assertSame(AuthorityGate::MODE_LEGACY, $gate->mode());
    }

    public function test_shadow_mode_returns_legacy_and_emits_mismatch_telemetry(): void
    {
        $telemetry = $this->telemetry();
        $gate = new AuthorityGate($this->resolver(), new ResolverShadow($this->resolver(), $telemetry), $telemetry, AuthorityGate::MODE_SHADOW);
        $admin = $this->userEntity($this->makeAdmin());
        $board = $this->makeBoard($this->makeCategory());

        // Legacy=false vs resolver=true (admin holds delete_any) -> legacy wins, one mismatch event.
        self::assertFalse($gate->allows(fn (): bool => false, $admin, 'core.post.delete_any', ['board_id' => (int) $board['id']], 'test'));
        self::assertContains('resolver.shadow_mismatch', $this->eventNames());
    }

    public function test_enforce_mode_returns_resolver_decision_and_flags_reverse_mismatch(): void
    {
        $telemetry = $this->telemetry();
        $gate = new AuthorityGate($this->resolver(), null, $telemetry, AuthorityGate::MODE_ENFORCE);
        $admin = $this->userEntity($this->makeAdmin());
        $member = $this->userEntity($this->makeUser());
        $board = $this->makeBoard($this->makeCategory());
        $target = ['board_id' => (int) $board['id']];

        self::assertTrue($gate->allows(fn (): bool => true, $admin, 'core.post.delete_any', $target, 'test'));
        self::assertFalse($gate->allows(fn (): bool => false, $member, 'core.post.delete_any', $target, 'test'));

        // Resolver denies a plain member even when legacy says yes -> enforce wins + reverse mismatch.
        self::assertFalse($gate->allows(fn (): bool => true, $member, 'core.post.delete_any', $target, 'test'));
        self::assertContains('resolver.enforce_mismatch', $this->eventNames());
        self::assertContains('authority.enforce_denied', $this->eventNames());
    }

    public function test_enforce_mode_fails_closed_when_the_resolver_is_missing(): void
    {
        $telemetry = $this->telemetry();
        $gate = new AuthorityGate(null, null, $telemetry, AuthorityGate::MODE_ENFORCE);
        $admin = $this->userEntity($this->makeAdmin());

        self::assertFalse($gate->allows(fn (): bool => true, $admin, 'core.post.delete_any', [], 'test'));
        self::assertContains('authority.enforce_error', $this->eventNames());
    }

    public function test_assert_throws_forbidden_with_caller_message_on_deny(): void
    {
        $gate = new AuthorityGate($this->resolver(), null, $this->telemetry(), AuthorityGate::MODE_ENFORCE);
        $member = $this->userEntity($this->makeUser());
        $board = $this->makeBoard($this->makeCategory());

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('You cannot moderate this board.');
        $gate->assert(fn (): bool => false, $member, 'core.post.delete_any', ['board_id' => (int) $board['id']], 'test', 'You cannot moderate this board.');
    }

    public function test_from_config_normalizes_case_and_whitespace(): void
    {
        $telemetry = $this->telemetry();
        $resolver = $this->resolver();
        $shadow = new ResolverShadow($resolver, $telemetry);

        $gate = AuthorityGate::fromConfig(' ENFORCE ', $resolver, $shadow, $telemetry);
        self::assertSame(AuthorityGate::MODE_ENFORCE, $gate->mode());

        $gate = AuthorityGate::fromConfig('Shadow', $resolver, $shadow, $telemetry);
        self::assertSame(AuthorityGate::MODE_SHADOW, $gate->mode());

        self::assertSame([], $this->eventNames(), 'known modes must not emit telemetry');
    }

    public function test_from_config_unknown_mode_runs_shadow_and_emits_telemetry(): void
    {
        $telemetry = $this->telemetry();
        $resolver = $this->resolver();
        $gate = AuthorityGate::fromConfig('enfroce', $resolver, new ResolverShadow($resolver, $telemetry), $telemetry);

        self::assertSame(AuthorityGate::MODE_SHADOW, $gate->mode());
        self::assertContains('capabilities.mode_invalid', $this->eventNames());

        // The fallback gate still behaves as shadow: legacy decides.
        $member = $this->userEntity($this->makeUser());
        $board = $this->makeBoard($this->makeCategory());
        self::assertTrue($gate->allows(fn (): bool => true, $member, 'core.post.delete_any', ['board_id' => (int) $board['id']], 'test'));
    }
}
