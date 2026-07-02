<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Packages;

use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Phase5\SigningHarness;

final class ManifestValidatorTest extends TestCase
{
    private ManifestValidator $validator;
    private SigningHarness $harness;

    protected function setUp(): void
    {
        $this->validator = new ManifestValidator();
        $this->harness = SigningHarness::generate();
    }

    /** @param array<string,mixed> $overrides */
    private function manifest(array $overrides = []): array
    {
        return $this->harness->mintManifest($overrides);
    }

    /** @param array<string,mixed> $overrides */
    private function assertRefusal(string $expectedCode, array $overrides): void
    {
        try {
            $this->validator->validate($this->manifest($overrides), 'acme/midnight-theme', '1.0.0');
            self::fail('expected refusal ' . $expectedCode);
        } catch (PackagePolicyException $e) {
            self::assertSame($expectedCode, $e->code);
        }
    }

    public function test_valid_manifest_produces_the_typed_snapshot(): void
    {
        $manifest = $this->validator->validate($this->manifest([
            'permissions' => [
                'data_classes' => ['package.own_storage', 'content.public'],
                'api_scopes' => ['read:boards'],
                'events' => ['topic.created'],
                'outbound_hosts' => ['api.example.com'],
                'jobs' => [['name' => 'sync', 'schedule' => 'daily']],
            ],
            'settings_schema' => ['fields' => [
                ['key' => 'api_key', 'type' => 'string', 'label' => 'API key', 'required' => true],
                ['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => ['light', 'dark']],
            ]],
            'install' => ['retention_days' => 14],
        ]), 'acme/midnight-theme', '1.0.0');

        self::assertSame('acme/midnight-theme', $manifest->uid);
        self::assertSame('theme', $manifest->type);
        self::assertSame('1.0.0', $manifest->version);
        self::assertSame('Midnight Theme', $manifest->name);
        self::assertSame('0.1.0', $manifest->coreMin);
        self::assertNull($manifest->coreMax);
        self::assertTrue($manifest->coreCompatible());
        self::assertSame(14, $manifest->retentionDays);
        self::assertSame(64, $manifest->storageQuotaKb);
        self::assertSame(['homepage' => 'https://acme.example/midnight'], $manifest->support);
        self::assertCount(2, $manifest->settingsSchema['fields']);

        $keys = array_map(static fn (array $p): string => $p['kind'] . ':' . $p['key'], $manifest->permissions);
        self::assertSame([
            'data_class:package.own_storage', 'data_class:content.public',
            'api_scope:read:boards', 'event:topic.created',
            'outbound_host:api.example.com', 'job:sync',
        ], $keys);
        foreach ($manifest->permissions as $p) {
            self::assertContains($p['risk'], ['low', 'medium', 'high']);
            self::assertNotSame('', $p['label']);
        }
    }

    public function test_incompatible_core_range_still_validates_and_reports_incompatibility(): void
    {
        $manifest = $this->validator->validate(
            $this->manifest(['core' => ['min' => '99.0.0', 'max' => null]]),
            'acme/midnight-theme',
            '1.0.0',
        );
        self::assertFalse($manifest->coreCompatible());
    }

    /** @param array<string,mixed> $overrides */
    #[DataProvider('providerRefusals')]
    public function test_malformed_manifests_refuse_with_the_exact_code(string $code, array $overrides): void
    {
        $this->assertRefusal($code, $overrides);
    }

    /** @return iterable<string,array{string,array<string,mixed>}> */
    public static function providerRefusals(): iterable
    {
        yield 'wrong format' => ['manifest_format', ['format' => 'rb-manifest.v1']];
        yield 'unknown top-level key' => ['unknown_field', ['sneaky' => true]];
        yield 'uid mismatch' => ['manifest_identity', ['uid' => 'acme/other']];
        yield 'version mismatch' => ['manifest_identity', ['version' => '9.9.9']];
        yield 'invalid uid syntax' => ['manifest_identity', ['uid' => 'NotValid']];
        yield 'server_extension refused' => ['manifest_type', ['type' => 'server_extension']];
        yield 'unknown type' => ['manifest_type', ['type' => 'widget']];
        yield 'empty name' => ['manifest_name', ['name' => '  ']];
        yield 'name too long' => ['manifest_name', ['name' => str_repeat('x', 191)]];
        yield 'description too long' => ['manifest_field', ['description' => str_repeat('x', 513)]];
        yield 'core missing min' => ['manifest_core', ['core' => ['min' => null, 'max' => '2.0.0']]];
        yield 'core invalid min' => ['manifest_core', ['core' => ['min' => 'not-semver']]];
        yield 'core unknown key' => ['manifest_core', ['core' => ['min' => '0.1.0', 'pin' => true]]];
        yield 'unknown permission kind' => ['unknown_field', ['permissions' => ['broker_services' => ['db']]]];
        yield 'unknown capability' => ['unknown_capability', ['permissions' => ['capabilities' => ['core.nonsense']]]];
        yield 'protected capability' => ['protected_capability', ['permissions' => ['capabilities' => ['core.owner.transfer']]]];
        yield 'unknown data class' => ['unknown_data_class', ['permissions' => ['data_classes' => ['content.secret']]]];
        yield 'protected data class' => ['protected_data_class', ['permissions' => ['data_classes' => ['security.config']]]];
        yield 'unknown api scope' => ['unknown_api_scope', ['permissions' => ['api_scopes' => ['write:everything']]]];
        yield 'unknown event' => ['unknown_event', ['permissions' => ['events' => ['user.deleted']]]];
        yield 'ping event refused' => ['unknown_event', ['permissions' => ['events' => ['ping']]]];
        yield 'host with scheme' => ['outbound_host', ['permissions' => ['outbound_hosts' => ['https://api.example.com']]]];
        yield 'host uppercase' => ['outbound_host', ['permissions' => ['outbound_hosts' => ['API.example.com']]]];
        yield 'host wildcard' => ['outbound_host', ['permissions' => ['outbound_hosts' => ['*.example.com']]]];
        yield 'host bare label' => ['outbound_host', ['permissions' => ['outbound_hosts' => ['localhost']]]];
        yield 'duplicate permission' => ['manifest_field', ['permissions' => ['data_classes' => ['content.public', 'content.public']]]];
        yield 'job missing schedule' => ['job_declaration', ['permissions' => ['jobs' => [['name' => 'sync']]]]];
        yield 'job bad name' => ['job_declaration', ['permissions' => ['jobs' => [['name' => 'Sync!', 'schedule' => 'daily']]]]];
        yield 'job unknown schedule' => ['job_declaration', ['permissions' => ['jobs' => [['name' => 'sync', 'schedule' => 'yearly']]]]];
        yield 'job unknown key' => ['job_declaration', ['permissions' => ['jobs' => [['name' => 'sync', 'schedule' => 'daily', 'cron' => '* * * * *']]]]];
        yield 'settings not fields' => ['settings_schema', ['settings_schema' => ['fielden' => []]]];
        yield 'settings empty fields' => ['settings_schema', ['settings_schema' => ['fields' => []]]];
        yield 'settings bad key' => ['settings_schema', ['settings_schema' => ['fields' => [['key' => 'Bad Key', 'type' => 'string', 'label' => 'x']]]]];
        yield 'settings duplicate key' => ['settings_schema', ['settings_schema' => ['fields' => [
            ['key' => 'a', 'type' => 'string', 'label' => 'x'],
            ['key' => 'a', 'type' => 'string', 'label' => 'y'],
        ]]]];
        yield 'settings unknown type' => ['settings_schema', ['settings_schema' => ['fields' => [['key' => 'a', 'type' => 'json', 'label' => 'x']]]]];
        yield 'select without options' => ['settings_schema', ['settings_schema' => ['fields' => [['key' => 'a', 'type' => 'select', 'label' => 'x']]]]];
        yield 'options on non-select' => ['settings_schema', ['settings_schema' => ['fields' => [
            ['key' => 'a', 'type' => 'string', 'label' => 'x', 'options' => ['y']],
        ]]]];
        yield 'quota negative' => ['storage_quota', ['storage_quota_kb' => -1]];
        yield 'quota over cap' => ['storage_quota', ['storage_quota_kb' => 10_241]];
        yield 'quota non-int' => ['storage_quota', ['storage_quota_kb' => '64']];
        yield 'retention zero' => ['install_policy', ['install' => ['retention_days' => 0]]];
        yield 'retention over cap' => ['install_policy', ['install' => ['retention_days' => 366]]];
        yield 'install unknown key' => ['install_policy', ['install' => ['auto_update' => true]]];
        yield 'support http' => ['support_link', ['support' => ['homepage' => 'http://acme.example']]];
        yield 'support unknown key' => ['support_link', ['support' => ['donate' => 'https://acme.example']]];
    }
}
