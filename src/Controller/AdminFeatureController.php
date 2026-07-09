<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\Request;
use App\Core\Response;
use App\Repository\SettingRepository;

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

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();

        $flags = $this->container->get(FeatureFlags::class);
        $defaults = FeatureFlags::defaults();
        $effective = $flags->all();
        $overrides = $this->container->get(SettingRepository::class)->get('features', []);
        $overrides = is_array($overrides) ? $overrides : [];

        $rowsByGroup = [];
        foreach (self::GROUPS as $label => $flagNames) {
            $rowsByGroup[$label] = [];
            foreach ($flagNames as $flag) {
                if (!array_key_exists($flag, $defaults)) {
                    continue;
                }
                $rowsByGroup[$label][] = $this->row($flag, $defaults, $effective, $overrides);
            }
        }

        $groupedFlags = array_flip(array_merge(...array_values(self::GROUPS)));
        $uncategorized = array_values(array_diff(array_keys($defaults), array_keys($groupedFlags)));
        if ($uncategorized !== []) {
            $rowsByGroup['Uncategorized'] = [];
            foreach ($uncategorized as $flag) {
                $rowsByGroup['Uncategorized'][] = $this->row($flag, $defaults, $effective, $overrides);
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
     * @param array<string,bool> $defaults
     * @param array<string,bool> $effective
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function row(string $flag, array $defaults, array $effective, array $overrides): array
    {
        $default = (bool) $defaults[$flag];
        $current = (bool) ($effective[$flag] ?? $default);
        $hasOverride = array_key_exists($flag, $overrides);
        $override = $hasOverride ? FeatureFlags::normalizeOverride($overrides[$flag]) : null;

        return [
            'flag' => $flag,
            'default' => $default,
            'effective' => $current,
            'default_text' => $default ? 'Default on' : 'Default off',
            'effective_text' => $current ? 'Effective on' : 'Effective off',
            'override_text' => $hasOverride ? ($override ? 'Override on' : 'Override off') : 'No override',
            'override_class' => $hasOverride ? ($override ? 'state-active' : 'state-paused') : 'state-pending',
            'rollback' => $this->rollbackNote($flag, $default, $current),
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
