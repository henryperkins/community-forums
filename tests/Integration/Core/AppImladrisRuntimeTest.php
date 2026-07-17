<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppImladrisRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_every_layout_loads_the_runtime_design_system_before_application_overrides(): void
    {
        foreach (['/', '/login', '/privacy'] as $path) {
            $response = $this->get($path);
            $this->assertStatus(200, $response);
            $body = $response->body();

            $imladris = strpos($body, '<link rel="stylesheet" href="/assets/imladris.css">');
            $application = strpos($body, '<link rel="stylesheet" href="/assets/app.css">');

            self::assertNotFalse($imladris, $path . ' loads the Imladris runtime');
            self::assertNotFalse($application, $path . ' keeps application overrides');
            self::assertLessThan($application, $imladris, $path . ' loads Imladris before app.css');
            self::assertStringNotContainsString('_ds_bundle.js', $body);
        }
    }
}
