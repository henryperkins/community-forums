<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\PackageReleaseRepository;
use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** The no-JS local-review form: reauth-gated, digest-tightening, 422 on refusal. */
final class AppPackageReviewTest extends TestCase
{
    private SigningHarness $root;

    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        (new SettingRepository($this->db))->set('features', ['package_registry' => true]);
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
    }

    private function reviewStatus(): string
    {
        return (string) (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['review_status'];
    }

    public function test_form_renders_on_the_package_detail_and_records_a_decision(): void
    {
        $show = $this->get('/admin/packages/' . $this->ids['package_id']);
        $this->assertStatus(200, $show);
        self::assertStringContainsString('name="decision"', $show->body(), 'no-JS review form is server-rendered');

        $ok = $this->post('/admin/packages/' . $this->ids['package_id'] . '/review', [
            'release_id' => (string) $this->ids['release_id'],
            'decision' => 'revoked',
            'note' => 'local kill decision',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(303, $ok);
        self::assertSame('noindex', $ok->getHeader('x-robots-tag'));
        self::assertSame('revoked', $this->reviewStatus());
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'package_review'"));
    }

    public function test_wrong_password_re_renders_422_and_does_not_change_status(): void
    {
        $resp = $this->post('/admin/packages/' . $this->ids['package_id'] . '/review', [
            'release_id' => (string) $this->ids['release_id'],
            'decision' => 'revoked',
            'current_password' => 'wrong',
        ]);
        $this->assertStatus(422, $resp);
        self::assertStringContainsString('password', strtolower($resp->body()));
        self::assertSame('approved', $this->reviewStatus(), 'seed status is untouched on a refused write');
    }

    public function test_local_approval_over_a_signed_reject_is_refused_422(): void
    {
        $release = (new PackageReleaseRepository($this->db))->find($this->ids['release_id']);
        $this->db->insert(
            'INSERT INTO package_review_decisions (package_id, release_id, version, digest, decision, decided_at, source)
             VALUES (?, ?, ?, ?, \'rejected\', UTC_TIMESTAMP(), \'advisory\')',
            [$this->ids['package_id'], $this->ids['release_id'], (string) $release['version'], (string) $release['digest']],
        );

        $resp = $this->post('/admin/packages/' . $this->ids['package_id'] . '/review', [
            'release_id' => (string) $this->ids['release_id'],
            'decision' => 'approved',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $resp);
        self::assertStringContainsString('review_conflict', $resp->body());
    }
}
