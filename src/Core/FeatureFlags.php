<?php

declare(strict_types=1);

namespace App\Core;

use App\Repository\SettingRepository;

/**
 * Phase 2 feature flags (PHASE_2_PLAN §6 Milestone 0, §12 staged release).
 *
 * Each Phase 2 subsystem is gated so it can be enabled independently and rolled
 * back without a data change. Code defaults are ON so a fresh install is fully
 * functional and the test suite exercises every path; an operator running a
 * "deploy dark" staged rollout disables flags via the `features` setting
 * (a JSON object of flag => bool), which overrides the defaults per flag.
 */
final class FeatureFlags
{
    /** @var array<string,bool> */
    private const DEFAULTS = [
        'engagement' => true,        // reactions, stars, per-thread unread (P2-01/P2-02)
        'notifications' => true,     // subscriptions + in-app bell (P2-03)
        'email' => true,             // email worker, instant + daily digest (P2-04)
        'mentions' => true,          // @mentions (P2-05)
        'search' => true,            // MySQL FULLTEXT search (P2-06)
        'dms' => true,               // direct messages (P2-07)
        'moderation_queue' => true,  // reports + scoped moderators (P2-08)
        'community' => true,         // follows/feed, badges, solved, leaderboard (P2-09)
        'oauth' => true,             // OAuth sign-in / account linking (P2-10)
        'presence' => true,          // last-seen presence roster (P2-11)
    ];

    /** @var array<string,bool>|null */
    private ?array $cache = null;

    public function __construct(private SettingRepository $settings)
    {
    }

    public function enabled(string $flag): bool
    {
        $map = $this->cache ??= $this->load();
        return $map[$flag] ?? false;
    }

    /** @return array<string,bool> */
    public function all(): array
    {
        return $this->cache ??= $this->load();
    }

    /** @return array<string,bool> */
    private function load(): array
    {
        $map = self::DEFAULTS;
        $overrides = $this->settings->get('features', []);
        if (is_array($overrides)) {
            foreach ($overrides as $key => $value) {
                if (is_string($key)) {
                    $map[$key] = (bool) $value;
                }
            }
        }
        return $map;
    }
}
