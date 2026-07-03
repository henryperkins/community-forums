<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Packages;

use App\Core\ValidationException;
use App\Service\Packages\PackageSettingsService;
use PHPUnit\Framework\TestCase;

final class PackageSettingsSchemaTest extends TestCase
{
    /** @return list<array<string,mixed>> */
    private function fields(): array
    {
        return [
            ['key' => 'api_key', 'type' => 'string',  'label' => 'API key', 'required' => true, 'secret' => true],
            ['key' => 'mode',    'type' => 'select',  'label' => 'Mode',    'options' => ['light', 'dark']],
            ['key' => 'retries', 'type' => 'integer', 'label' => 'Retries'],
            ['key' => 'notify',  'type' => 'boolean', 'label' => 'Notify'],
        ];
    }

    public function test_secret_value_goes_to_secrets_never_values(): void
    {
        $out = PackageSettingsService::validateInput($this->fields(), [
            'api_key' => 'sk-live-123', 'mode' => 'dark', 'retries' => '4', 'notify' => '1',
        ]);

        self::assertSame(['api_key' => 'sk-live-123'], $out['secrets']);
        self::assertArrayNotHasKey('api_key', $out['values']);
        self::assertSame('dark', $out['values']['mode']);
        self::assertSame(4, $out['values']['retries']);
        self::assertTrue($out['values']['notify']);
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        PackageSettingsService::validateInput($this->fields(), ['nope' => 'x']);
    }

    public function test_bad_select_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        PackageSettingsService::validateInput($this->fields(), ['mode' => 'neon']);
    }

    public function test_non_numeric_integer_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        PackageSettingsService::validateInput($this->fields(), ['retries' => 'lots']);
    }

    public function test_oversize_string_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        PackageSettingsService::validateInput(
            [['key' => 'label', 'type' => 'string', 'label' => 'Label']],
            ['label' => str_repeat('x', 5000)],
        );
    }

    public function test_missing_required_non_secret_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        PackageSettingsService::validateInput(
            [['key' => 'endpoint', 'type' => 'string', 'label' => 'Endpoint', 'required' => true]],
            [],
        );
    }

    public function test_validation_error_old_never_echoes_secret_plaintext(): void
    {
        try {
            PackageSettingsService::validateInput($this->fields(), ['api_key' => 'sk-secret', 'mode' => 'neon']);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertStringNotContainsString('sk-secret', json_encode($e->old));
            self::assertSame('neon', $e->old['settings']['mode'] ?? null);
        }
    }
}
