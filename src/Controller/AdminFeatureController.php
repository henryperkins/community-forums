<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\Request;
use App\Core\Response;
use App\Repository\SettingRepository;
use App\Security\AuthorityGate;

final class AdminFeatureController extends Controller
{
    /** @var array<string,list<string>> */
    private const GROUPS = [
        'Phase 2 / Base' => [
            'engagement', 'notifications', 'email', 'mentions', 'search', 'dms',
            'moderation_queue', 'community', 'oauth', 'presence', 'announcements',
        ],
        'Phase 3 / Composer and Trust' => [
            'rich_composer', 'wysiwyg_composer', 'drafts', 'server_drafts',
            'uploads', 'anti_abuse', 'appeals', 'branding', 'custom_css', 'seo',
            'product_tour',
        ],
        'Phase 4 Gate A' => [
            'topic_workflow', 'group_dms', 'tags', 'expanded_feeds',
            'reputation_ledger', 'badge_rules', 'community_memory',
            'content_references',
        ],
        'Phase 4 Carryover' => [
            'link_previews', 'expanded_files', 'polls', 'custom_emoji',
            'slash_giphy', 'split_merge', 'profile_media', 'board_folders',
            'bookmark_folders', 'saved_feeds', 'custom_profile_fields',
            'account_lifecycle', 'automated_context',
        ],
        'Phase 5 Gate A' => [
            'package_registry', 'package_themes', 'capabilities', 'passkeys',
            'provider_registry', 'invitations', 'service_secrets', 'api_tokens',
            'webhooks', 'first_party_hooks',
        ],
        'Phase 5 Gate B' => [
            'server_extensions', 'governance', 'service_principals',
            'verified_links',
        ],
    ];

    private const GATE_B_RESERVED = [
        'status' => 'Reserved (ADR 0018)',
        'class' => '',
        'note' => 'Phase 5 Gate B; stays dark until its workstream lands its own release evidence.',
    ];

    /**
     * Read-only readiness classification for the rows that are not simply
     * shipped: the default-dark carryovers and the ADR-reserved Gate B set.
     * (`group_dms` graduated to default-on on 2026-07-18 — ADR 0022 — and left
     * this map with the last "Ready for acceptance" row.)
     * The two "Operational configuration required" rows (`capabilities` in
     * shadow posture, `slash_giphy` without a key) are computed live in
     * readiness() so the badge clears once the operational step is done.
     * Deliberately not a toggle — enablement stays an explicit
     * settings.features write (docs/runbooks/operations.md §2). Findings and
     * next steps trace to docs/evidence/deploy-dark-features.md (2026-07-13).
     *
     * @var array<string,array{status:string,class:string,note:string,href?:string,link?:string}>
     */
    private const READINESS = [
        'expanded_files' => [
            'status' => 'Missing user UI',
            'class' => 'state-paused',
            'note' => 'Backend POST /upload/file exists, but no member file chooser, no-JS upload form, or quarantine states render; the admin scanner health/outage workflows are also unbuilt.',
        ],
        'link_previews' => [
            'status' => 'Missing admin operations',
            'class' => 'state-paused',
            'note' => 'Inert until link_preview_allowed_hosts is populated; GET /admin/link-previews does not exist (the POST refresh/purge routes are unlinked), and the per-board opt-in and author removal are absent.',
        ],
        'custom_css' => [
            'status' => 'Safety-blocked',
            'class' => 'state-failed',
            'note' => 'Theme safe mode does not suppress /brand.css custom CSS, so the documented recovery path leaves broken CSS active. Repair that before considering enablement.',
            'href' => '/admin/branding',
            'link' => 'Custom CSS editor',
        ],
        'server_extensions' => self::GATE_B_RESERVED,
        'governance' => self::GATE_B_RESERVED,
        'service_principals' => self::GATE_B_RESERVED,
        'verified_links' => self::GATE_B_RESERVED,
    ];

    /**
     * Operations/health surface per flag, linked only while the flag is
     * effectively on — most of these consoles 404 while their flag is dark,
     * and the readiness / rollback columns already carry the dark story.
     *
     * @var array<string,string>
     */
    private const OPERATIONS = [
        'email' => '/admin/email',
        'announcements' => '/admin/announcements',
        'branding' => '/admin/branding',
        'tags' => '/admin/tags',
        'badge_rules' => '/admin/badge-rules',
        'community_memory' => '/admin/thread-intelligence',
        'automated_context' => '/admin/thread-intelligence',
        'package_registry' => '/admin/packages',
        'package_themes' => '/admin/themes',
        'capabilities' => '/admin/roles',
        'provider_registry' => '/admin/providers',
        'invitations' => '/admin/invitations',
        'api_tokens' => '/admin/api-tokens',
        'webhooks' => '/admin/webhooks',
        'server_extensions' => '/admin/extensions',
    ];

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();

        $flags = $this->container->get(FeatureFlags::class);
        $defaults = FeatureFlags::defaults();
        $effective = $flags->all();
        $settings = $this->container->get(SettingRepository::class);
        $overrides = $settings->get('features', []);
        $overrides = is_array($overrides) ? $overrides : [];

        $readiness = $this->readiness($effective, $settings);

        $rowsByGroup = [];
        foreach (self::GROUPS as $label => $flagNames) {
            $rowsByGroup[$label] = [];
            foreach ($flagNames as $flag) {
                if (!array_key_exists($flag, $defaults)) {
                    continue;
                }
                $rowsByGroup[$label][] = $this->row($flag, $defaults, $effective, $overrides, $readiness);
            }
        }

        $groupedFlags = array_flip(array_merge(...array_values(self::GROUPS)));
        $uncategorized = array_values(array_diff(array_keys($defaults), array_keys($groupedFlags)));
        if ($uncategorized !== []) {
            $rowsByGroup['Uncategorized'] = [];
            foreach ($uncategorized as $flag) {
                $rowsByGroup['Uncategorized'][] = $this->row($flag, $defaults, $effective, $overrides, $readiness);
            }
        }

        $declaredOverrides = array_intersect_key($overrides, $defaults);
        $unknownOverrides = [];
        foreach (array_diff_key($overrides, $defaults) as $flag => $value) {
            if (!is_string($flag)) {
                continue;
            }
            $unknownOverrides[] = [
                'flag' => $flag,
                'value_text' => FeatureFlags::normalizeOverride($value) ? 'Override on' : 'Override off',
                'raw_value' => $this->rawValue($value),
            ];
        }

        $defaultOn = count(array_filter($defaults));
        $effectiveDeclared = array_intersect_key($effective, $defaults);
        $effectiveOn = count(array_filter($effectiveDeclared));

        return $this->view('admin/features', [
            'groups' => $rowsByGroup,
            'unknown_overrides' => $unknownOverrides,
            'features_corrupt' => $flags->overridesCorrupt(),
            'stats' => [
                'declared' => count($defaults),
                'default_on' => $defaultOn,
                'default_off' => count($defaults) - $defaultOn,
                'effective_on' => $effectiveOn,
                'effective_off' => count($defaults) - $effectiveOn,
                'overrides' => count($declaredOverrides),
                'unknown_overrides' => count($unknownOverrides),
            ],
        ]);
    }

    /**
     * The full readiness map for this request: the static classification plus
     * the two live-computed "Operational configuration required" rows, with
     * every href dropped when the surface it points at would 404 right now.
     *
     * @param array<string,bool> $effective
     * @return array<string,array{status:string,class:string,note:string,href?:string,link?:string}>
     */
    private function readiness(array $effective, SettingRepository $settings): array
    {
        $readiness = self::READINESS;
        if (!($effective['branding'] ?? false)) {
            unset($readiness['custom_css']['href'], $readiness['custom_css']['link']);
        }

        if ($effective['capabilities'] ?? false) {
            $mode = $this->container->get(AuthorityGate::class)->mode();
            if ($mode !== 'enforce') {
                $readiness['capabilities'] = [
                    'status' => 'Operational configuration required',
                    'class' => 'state-pending',
                    'note' => 'Resolver posture is "' . $mode . '" — legacy authorization still decides every live answer. Soak resolver.shadow_mismatch, then set CAPABILITIES_MODE=enforce (docs/runbooks/capabilities.md §Staged rollout).',
                    'href' => '/admin/roles',
                    'link' => 'Roles & resolver posture',
                ];
            }
        }

        if ($effective['slash_giphy'] ?? false) {
            $key = $settings->getString('giphy_public_key', (string) $this->config()->get('giphy.public_key', ''));
            if ($key === '') {
                $readiness['slash_giphy'] = [
                    'status' => 'Operational configuration required',
                    'class' => 'state-pending',
                    'note' => 'giphy_public_key is unset, which hides the entire slash menu (all insert commands, not just GIPHY search). Configure a key with the required privacy disclosure, or decouple the non-GIPHY inserts from the provider key (docs/runbooks/slash_giphy.md).',
                ];
            }
        }

        return $readiness;
    }

    /**
     * @param array<string,bool> $defaults
     * @param array<string,bool> $effective
     * @param array<string,mixed> $overrides
     * @param array<string,array{status:string,class:string,note:string,href?:string,link?:string}> $readiness
     * @return array<string,mixed>
     */
    private function row(string $flag, array $defaults, array $effective, array $overrides, array $readiness): array
    {
        $default = (bool) $defaults[$flag];
        $current = (bool) ($effective[$flag] ?? $default);
        $hasOverride = array_key_exists($flag, $overrides);
        $override = $hasOverride ? FeatureFlags::normalizeOverride($overrides[$flag]) : null;
        $meta = $readiness[$flag] ?? null;

        return [
            'flag' => $flag,
            'default' => $default,
            'effective' => $current,
            'default_text' => $default ? 'Default on' : 'Default off',
            'effective_text' => $current ? 'Effective on' : 'Effective off',
            'override_text' => $hasOverride ? ($override ? 'Override on' : 'Override off') : 'No override',
            'override_class' => $hasOverride ? ($override ? 'state-active' : 'state-paused') : 'state-pending',
            'rollback' => $this->rollbackNote($flag, $default, $current),
            'operations_href' => $current ? (self::OPERATIONS[$flag] ?? null) : null,
            'readiness_status' => $meta['status'] ?? null,
            'readiness_class' => $meta['class'] ?? '',
            'readiness_note' => $meta['note'] ?? '',
            'readiness_href' => $meta['href'] ?? null,
            'readiness_link' => $meta['link'] ?? 'Open',
        ];
    }

    private function rollbackNote(string $flag, bool $default, bool $effective): string
    {
        if ($effective && $default) {
            return "Set features.$flag=false to roll back; remove the override to restore the default.";
        }
        if ($effective) {
            return "Remove the override or set features.$flag=false to return to default-dark.";
        }
        if ($default) {
            return "Remove features.$flag=false or set it true to restore the default-on surface.";
        }
        return "Default-dark; leave unset or false until the acceptance evidence is complete.";
    }

    private function rawValue(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : gettype($value);
    }
}
