<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Code-owned Phase 5 numeric-budget catalogue (Foundation F9). Transcribes the
 * ADR 0004 D11 release-gate targets (PHASE_5_PLAN.md §11.3) as a static, testable
 * enumeration so `Phase5BudgetReportService` has machine-readable gate values —
 * this catalogue is entry-gate item **A3**'s target table. Mirrors
 * `App\Security\ApiScopes` (static data, not a service).
 *
 * `measurable_at` records which workstream first produces a *real* measurement:
 * `foundation` = measured now (resolver baseline via the legacy authority read;
 * webhook timeout is a configured cap); everything else is `PENDING` in the
 * foundation report and filled by its increment.
 * `registry.fetch_p95` needs a live registry endpoint over the network; it is
 * measured at staged enablement, not on the local fixture.
 */
final class Phase5Budgets
{
    /** @var array<string,array{metric:string,target:int,unit:string,statistic:string,measurable_at:string}> */
    private const BUDGETS = [
        'registry.snapshot_freshness'    => ['metric' => 'Registry snapshot freshness tolerance',        'target' => 86400, 'unit' => 's',  'statistic' => 'max', 'measurable_at' => 'inc2'],
        'registry.fetch_p95'             => ['metric' => 'Registry fetch duration',                       'target' => 2000,  'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'staged-enablement'],
        'registry.signature_verify_p95'  => ['metric' => 'Signature verification per package',            'target' => 250,   'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc2'],
        'package.install_update_p95'     => ['metric' => 'Declarative package install/update',            'target' => 10000, 'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc3'],
        // ADR 0004 D11 covers declarative package operations under the 10s p95 umbrella.
        'theme.build_apply_p95'          => ['metric' => 'Theme build + activate (declarative package)',  'target' => 10000, 'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc4'],
        'resolver.p95'                   => ['metric' => 'Capability resolver decision',                  'target' => 5,     'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'foundation'],
        'webauthn.ceremony_p95'          => ['metric' => 'WebAuthn/TOTP ceremony (server time)',          'target' => 2000,  'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc7'],
        'oidc.discovery_p95_cached'      => ['metric' => 'OIDC discovery/JWKS (cached)',                  'target' => 2000,  'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc8'],
        'oidc.discovery_p95_cold'        => ['metric' => 'OIDC discovery/JWKS (cold)',                    'target' => 5000,  'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc8'],
        'invitation.redemption_p95'      => ['metric' => 'Invitation redemption',                         'target' => 500,   'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc9'],
        'webhook.delivery_timeout'       => ['metric' => 'Webhook delivery timeout',                      'target' => 5000,  'unit' => 'ms', 'statistic' => 'max', 'measurable_at' => 'foundation'],
        'sandbox.walltime_default'       => ['metric' => 'Sandbox execution wall-time (default)',         'target' => 2000,  'unit' => 'ms', 'statistic' => 'max', 'measurable_at' => 'gate_b'],
    ];

    /** @return array<string,array{metric:string,target:int,unit:string,statistic:string,measurable_at:string}> */
    public static function all(): array
    {
        return self::BUDGETS;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::BUDGETS);
    }

    /** @return array{metric:string,target:int,unit:string,statistic:string,measurable_at:string} */
    public static function get(string $key): array
    {
        if (!isset(self::BUDGETS[$key])) {
            throw new \InvalidArgumentException("unknown budget: $key");
        }
        return self::BUDGETS[$key];
    }

    public static function target(string $key): int
    {
        return self::get($key)['target'];
    }
}
