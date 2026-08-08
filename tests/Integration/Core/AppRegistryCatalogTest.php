<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\PackageRegistryRepository;
use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** Staff catalogue renders signed registry metadata and the dark-gated lifecycle entry point. */
final class AppRegistryCatalogTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    /** @return array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private function seedCatalog(): array
    {
        $this->makeAdmin();
        $ids = RegistryFixtures::seed($this->db, SigningHarness::generate('root-1'));
        $this->setFlags(['package_registry' => true]);

        return $ids;
    }

    public function test_guests_redirect_and_members_are_forbidden(): void
    {
        $this->seedCatalog();
        $this->assertStatus(302, $this->get('/admin/packages'));

        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->get('/admin/packages'));
    }

    public function test_admin_catalogue_lists_packages_with_badges_and_noindex(): void
    {
        $ids = $this->seedCatalog();
        // RegistryFixtures seeds the source disabled. The freshness banner is now
        // scoped to registries the operator actually enabled (FC-23), so enable it
        // here — this test is about the banner rendering, and the suppression case
        // has its own test below.
        (new PackageRegistryRepository($this->db))->setEnabled((int) $ids['registry_id'], true);
        $this->actingAs($this->makeAdmin());

        $resp = $this->get('/admin/packages');
        $this->assertStatus(200, $resp);
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));
        self::assertStringContainsString('acme/midnight-theme', $resp->body());
        self::assertStringContainsString('Midnight Theme', $resp->body());
        self::assertStringContainsString('reviewed_declarative', $resp->body());
        self::assertStringContainsString('Stale snapshot', $resp->body(), 'an enabled registry with no verified snapshot shows the freshness banner');
    }

    public function test_stale_alert_is_suppressed_for_a_disabled_registry(): void
    {
        // A registry added through /admin/registries "starts disabled until you
        // enable it with your password". Painting a red never-fetched alarm for a
        // source the operator deliberately left off reports a fault that does not
        // exist — the freshness fact is still computed, only the alert is scoped.
        $this->seedCatalog();
        $this->actingAs($this->makeAdmin());

        $resp = $this->get('/admin/packages');

        $this->assertStatus(200, $resp);
        self::assertStringContainsString('acme/midnight-theme', $resp->body(), 'the catalogue still lists cached metadata');
        self::assertStringNotContainsString('Stale snapshot', $resp->body());
    }

    public function test_detail_shows_provenance_and_release_rows(): void
    {
        $ids = $this->seedCatalog();
        $this->actingAs($this->makeAdmin());

        $resp = $this->get('/admin/packages/' . $ids['package_id']);
        $this->assertStatus(200, $resp);
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));
        self::assertStringContainsString('1.0.0', $resp->body());
        self::assertStringContainsString(substr($ids['release_digest'], 0, 16), $resp->body(), 'digest (abbreviated) is displayed');
        self::assertStringContainsString('root-1', $resp->body(), 'signing key id is displayed');
        self::assertStringContainsString('rb-test', $resp->body(), 'pinned source registry is displayed');

        $this->assertStatus(404, $this->get('/admin/packages/999999'));
    }

    public function test_install_plan_affordance_is_present_for_admins(): void
    {
        $ids = $this->seedCatalog();
        $this->actingAs($this->makeAdmin());

        $page = $this->get('/admin/packages/' . $ids['package_id']);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Install plan');
        $this->assertDontSeeText($page, 'Install does not exist yet');

        $this->assertStatus(200, $this->post('/admin/packages/' . $ids['package_id'] . '/plan', []));
        $this->assertStatus(405, $this->post('/admin/packages/' . $ids['package_id'], []));
    }
}
