<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Packages\PackageReviewConsoleService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** GA-DOD-09: local operator review decisions are provenance-bound, digest-exact, and tightening-only. */
final class PackageReviewConsoleServiceTest extends TestCase
{
    private SigningHarness $root;
    private User $admin;

    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->admin = User::fromRow($this->makeAdmin(['password' => 'password123']));
    }

    private function service(): PackageReviewConsoleService
    {
        return new PackageReviewConsoleService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageReviewDecisionRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    private function reviewCount(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'package_review'");
    }

    public function test_records_a_local_decision_with_provenance_and_transparency(): void
    {
        $svc = $this->service();
        $pkg = (new PackageRepository($this->db))->find($this->ids['package_id']);
        $digest = $this->ids['release_digest'];

        $id = $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $this->ids['release_id'], 'approved', 'audited by hand');
        self::assertGreaterThan(0, $id);

        $decision = (new PackageReviewDecisionRepository($this->db))->latestForDigest($this->ids['package_id'], $digest);
        self::assertSame('local', $decision['source']);
        self::assertSame('approved', $decision['decision']);
        self::assertStringContainsString('"reviewer_id":' . $this->admin->id(), (string) $decision['evidence_json']);

        self::assertSame('approved', (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['review_status']);

        $log = (new PackageTransparencyLogRepository($this->db))->forPackageUid((string) $pkg['package_uid']);
        $local = array_values(array_filter($log, static fn (array $r): bool => $r['source'] === 'local'));
        self::assertNotSame([], $local, 'a local transparency row is written');
        self::assertSame('release_verified', $local[0]['event']);
        self::assertSame(1, $this->reviewCount());
    }

    public function test_local_approval_is_refused_over_a_signed_reject_but_a_local_reject_tightens(): void
    {
        $svc = $this->service();
        $release = (new PackageReleaseRepository($this->db))->find($this->ids['release_id']);

        // A signed (non-local) rejection already covers this exact digest.
        (new PackageReviewDecisionRepository($this->db))->record([
            'package_id' => $this->ids['package_id'],
            'release_id' => $this->ids['release_id'],
            'version' => (string) $release['version'],
            'digest' => (string) $release['digest'],
            'decision' => 'rejected',
            'decided_at' => gmdate('Y-m-d H:i:s'),
            'source' => 'advisory',
            'evidence_json' => null,
        ]);

        try {
            $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $this->ids['release_id'], 'approved', null);
            self::fail('expected review_conflict');
        } catch (PackagePolicyException $e) {
            self::assertSame('review_conflict', $e->code);
        }
        self::assertSame(0, $this->reviewCount(), 'the refused approval writes no audit row');

        // Tightening the other way is always allowed.
        $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $this->ids['release_id'], 'revoked', 'kill it');
        self::assertSame('revoked', (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['review_status']);
        self::assertSame(1, $this->reviewCount());
    }

    public function test_a_decision_must_name_a_release_of_the_same_package(): void
    {
        $svc = $this->service();
        $other = RegistryFixtures::seed($this->db, $this->root, null, [
            'source_id' => 'rb-two',
            'publisher_uid' => 'beta',
            'publisher_name' => 'Beta Co',
            'package_uid' => 'beta/other',
            'name' => 'Other',
        ]);

        try {
            $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $other['release_id'], 'approved', null);
            self::fail('expected release-binding ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('release_id', $e->errors);
        }
        self::assertSame(0, $this->reviewCount());
    }

    public function test_reauth_and_decision_value_are_validated(): void
    {
        $svc = $this->service();

        try {
            $svc->recordDecision($this->admin, 'wrong', $this->ids['package_id'], $this->ids['release_id'], 'approved', null);
            self::fail('expected reauth ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }

        try {
            $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $this->ids['release_id'], 'maybe', null);
            self::fail('expected decision-value ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('decision', $e->errors);
        }
        self::assertSame(0, $this->reviewCount());
    }
}
