<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** Inc 2 exit gate: staff browse renders; install is absent. */
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
        $this->seedCatalog();
        $this->actingAs($this->makeAdmin());

        $resp = $this->get('/admin/packages');
        $this->assertStatus(200, $resp);
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));
        self::assertStringContainsString('acme/midnight-theme', $resp->body());
        self::assertStringContainsString('Midnight Theme', $resp->body());
        self::assertStringContainsString('reviewed_declarative', $resp->body());
        self::assertStringContainsString('Stale snapshot', $resp->body(), 'the fixture registry has no verified snapshot yet, so the freshness banner shows');
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

    public function test_install_is_absent_everywhere(): void
    {
        $ids = $this->seedCatalog();
        $this->actingAs($this->makeAdmin());

        $page = $this->get('/admin/packages/' . $ids['package_id'])->body();
        self::assertStringNotContainsStringIgnoringCase('install', $page, 'no install affordance may render in Inc 2');

        $this->assertStatus(404, $this->post('/admin/packages/' . $ids['package_id'] . '/install', []));
        $this->assertStatus(405, $this->post('/admin/packages/' . $ids['package_id'], []));
    }
}
