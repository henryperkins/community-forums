<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\AuthorityGate;
use App\Security\WriteGate;
use App\Service\FeatureFlagAdminService;
use App\Service\LinkPreviewAdminService;
use App\Support\FeatureFlagCatalog;

final class AdminFeatureController extends Controller
{
    private const READINESS_FILTERS = [
        '' => 'All readiness',
        'attention' => 'Needs attention',
        'clear' => 'No readiness warning',
        'missing_ui' => 'Missing user UI',
        'safety_blocked' => 'Safety-blocked',
        'configuration' => 'Operational configuration required',
        'reserved' => 'Reserved (ADR 0018)',
    ];

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
     * this map with the last "Ready for acceptance" row; `link_previews`
     * graduated on 2026-08-09 — ADR 0025 — and took "Missing admin operations"
     * with it, moving to the live-computed dormancy badge below.)
     * The three "Operational configuration required" rows (`capabilities` in
     * shadow posture, `link_previews` with no allowlist or no opted-in board,
     * `slash_giphy` without a key) are computed live in readiness() so the badge
     * clears once the operational step is done.
     * The static rows are exactly the flags the console will switch off but
     * never on (FeatureFlags::consoleEnableBlocker(), ADR 0049); enabling one
     * stays an explicit settings.features write (docs/runbooks/operations.md
     * §2), and AppAdminFeatureToggleTest pins the two lists equal. Findings and
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
        'link_previews' => '/admin/link-previews',
        'provider_registry' => '/admin/providers',
        'invitations' => '/admin/invitations',
        'api_tokens' => '/admin/api-tokens',
        'webhooks' => '/admin/webhooks',
        'server_extensions' => '/admin/extensions',
    ];

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $viewer = $this->requireAdmin();

        return $this->inventory($viewer, $this->filters([
            'q' => $request->query('q', ''),
            'readiness' => $request->query('readiness', ''),
            'state' => $request->query('state', ''),
        ]));
    }

    /**
     * The inventory. A refused flag write re-renders it as a 422 carrying the
     * operator's filters and the refusal as an alert, because the console's
     * flash only ever speaks success.
     *
     * @param array{q:string,readiness:string,state:string} $filters
     */
    private function inventory(User $viewer, array $filters, ?string $toggleError = null, int $status = 200): Response
    {
        $flags = $this->container->get(FeatureFlags::class);
        $defaults = FeatureFlags::defaults();
        $effective = $flags->all();
        $settings = $this->container->get(SettingRepository::class);
        $overrides = $settings->get('features', []);
        $overrides = FeatureFlags::isOverrideMap($overrides) ? $overrides : [];

        $readiness = $this->readiness($effective, $settings);
        $access = [
            'corrupt' => $flags->overridesCorrupt(),
            'can_write' => $this->container->get(WriteGate::class)->canWrite($viewer),
            'outcome' => $this->flashOutcome(),
        ];

        $rowsByGroup = [];
        foreach (self::GROUPS as $label => $flagNames) {
            $rowsByGroup[$label] = [];
            foreach ($flagNames as $flag) {
                if (!array_key_exists($flag, $defaults)) {
                    continue;
                }
                $rowsByGroup[$label][] = $this->row($flag, $defaults, $effective, $overrides, $readiness, $access);
            }
        }

        $groupedFlags = array_flip(array_merge(...array_values(self::GROUPS)));
        $uncategorized = array_values(array_diff(array_keys($defaults), array_keys($groupedFlags)));
        if ($uncategorized !== []) {
            $rowsByGroup['Uncategorized'] = [];
            foreach ($uncategorized as $flag) {
                $rowsByGroup['Uncategorized'][] = $this->row($flag, $defaults, $effective, $overrides, $readiness, $access);
            }
        }

        ['q' => $search, 'readiness' => $readinessFilter, 'state' => $stateFilter] = $filters;
        $shownCount = 0;
        foreach ($rowsByGroup as $group => $rows) {
            $rows = array_values(array_filter($rows, static function (array $row) use ($search, $readinessFilter, $stateFilter): bool {
                if ($search !== '' && stripos($row['flag'], $search) === false) {
                    return false;
                }
                if ($stateFilter !== '' && $row['effective'] !== ($stateFilter === 'on')) {
                    return false;
                }
                return self::matchesReadiness($row['readiness_status'], $readinessFilter);
            }));
            if ($rows === []) {
                unset($rowsByGroup[$group]);
            } else {
                $rowsByGroup[$group] = $rows;
                $shownCount += count($rows);
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
            'features_corrupt' => $access['corrupt'],
            'filters' => ['q' => $search, 'readiness' => $readinessFilter, 'state' => $stateFilter],
            'filters_active' => $search !== '' || $readinessFilter !== '' || $stateFilter !== '',
            'readiness_options' => self::READINESS_FILTERS,
            'return_path' => $this->inventoryPath($filters),
            'toggle_error' => $toggleError,
            'stats' => [
                'declared' => count($defaults),
                'shown' => $shownCount,
                'default_on' => $defaultOn,
                'default_off' => count($defaults) - $defaultOn,
                'effective_on' => $effectiveOn,
                'effective_off' => count($defaults) - $effectiveOn,
                'overrides' => count($declaredOverrides),
                'unknown_overrides' => count($unknownOverrides),
            ],
        ], $status);
    }

    /**
     * One guarded flag write (ADR 0049). The inline confirm posts the explicit
     * target state; the service owns the policy, the read-modify-write of
     * `settings.features` and its audit entry. Success returns to the changed
     * row; a refusal re-renders the inventory as a 422 alert.
     *
     * @param array<string,string> $params
     */
    public function update(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $flag = (string) ($params['flag'] ?? '');
        if (!array_key_exists($flag, FeatureFlags::defaults())) {
            throw new NotFoundException('Not found.');
        }
        $filters = $this->returnFilters($request);

        $target = $request->post('enabled');
        if (!in_array($target, ['0', '1'], true)) {
            return $this->inventory($admin, $filters, $flag . ' was not changed: the request did not say whether to turn it on or off.', 422);
        }

        try {
            $result = $this->container->get(FeatureFlagAdminService::class)->setEnabled($admin, $flag, $target === '1');
        } catch (ValidationException $e) {
            return $this->inventory($admin, $filters, $flag . ' was not changed. ' . implode(' ', $e->errors), 422);
        }

        // The write inverted the effective state, so a kept On/Off filter would
        // hide the row whose cells prove it; a live-computed readiness filter
        // that the row no longer matches would hide it the same way.
        $filters['state'] = '';
        if ($filters['readiness'] !== '') {
            $readiness = $this->readiness(
                $this->container->get(FeatureFlags::class)->all(),
                $this->container->get(SettingRepository::class),
            );
            if (!self::matchesReadiness($readiness[$flag]['status'] ?? null, $filters['readiness'])) {
                $filters['readiness'] = '';
            }
        }

        return $this->redirectWithFlash(
            $this->inventoryPath($filters) . '#flag-' . $flag,
            $this->outcome($result, $admin),
        );
    }

    /**
     * The flash for a flag write. Its opening words are also what
     * flashOutcome() reads back to mark the row, so change both together.
     *
     * @param array{flag:string,changed:bool,enabled:bool,override:?bool,audit_id:?int,last_change:?array{actor_id:?int,created_at:string}} $result
     */
    private function outcome(array $result, User $admin): string
    {
        $flag = $result['flag'];
        $state = $result['enabled'] ? 'on' : 'off';
        if (!$result['changed']) {
            // A double submit lands here after its first request changed the
            // flag, so say who did, rather than leave "nothing changed" alone.
            $last = $result['last_change'];
            $by = '';
            if ($last !== null) {
                $actor = $last['actor_id'] === $admin->id() ? 'you' : null;
                if ($actor === null && $last['actor_id'] !== null) {
                    $row = $this->container->get(UserRepository::class)->find($last['actor_id']);
                    $actor = $row !== null ? '@' . $row['username'] : null;
                }
                $by = " It was turned $state" . ($actor !== null ? " by $actor" : '') . ' on ' . human_datetime($last['created_at']) . '.';
            }
            return "$flag is already $state.$by Nothing changed.";
        }
        $write = $result['override'] === null
            ? "Removed the features.$flag override, so the code default applies."
            : "Saved features.$flag=" . ($result['override'] ? 'true' : 'false') . '.';

        return "$flag turned $state. $write The change is recorded in the audit log.";
    }

    /**
     * The flag the current one-shot flash reports on, read back from
     * outcome()'s opening words so the row it names can say so where the
     * #flag- fragment lands. The cookie is client-held, so a row only shows
     * it while its live Effective cell agrees; the cells and the audit log
     * are the proof, the marker is wayfinding.
     *
     * @return array{flag:string,changed:bool,enabled:bool}|null
     */
    private function flashOutcome(): ?array
    {
        $flash = $this->flash()->current();
        if ($flash === null || preg_match('/^([a-z0-9_]+) (turned|is already) (on|off)\./', $flash, $match) !== 1) {
            return null;
        }

        return ['flag' => $match[1], 'changed' => $match[2] === 'turned', 'enabled' => $match[3] === 'on'];
    }

    /**
     * The filters a flag write came from: only this inventory's own query
     * fields, re-validated, never an arbitrary posted URL (cf. the link-preview
     * console).
     *
     * @return array{q:string,readiness:string,state:string}
     */
    private function returnFilters(Request $request): array
    {
        $return = parse_url($this->localReturn($request, '/admin/features'));
        $query = [];
        if (is_array($return) && ($return['path'] ?? '') === '/admin/features') {
            parse_str((string) ($return['query'] ?? ''), $query);
        }

        return $this->filters($query);
    }

    /** @param array{q:string,readiness:string,state:string} $filters */
    private function inventoryPath(array $filters): string
    {
        $query = array_filter($filters, static fn (string $value): bool => $value !== '');

        return '/admin/features' . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /**
     * The inventory's three GET filters, normalized the same way for the page
     * itself and for the return state a flag write carries back to it.
     *
     * @param array<string,mixed> $raw
     * @return array{q:string,readiness:string,state:string}
     */
    private function filters(array $raw): array
    {
        $search = $raw['q'] ?? '';
        $readiness = $raw['readiness'] ?? '';
        $state = $raw['state'] ?? '';

        return [
            'q' => is_string($search) ? mb_substr(trim($search), 0, 128) : '',
            'readiness' => is_string($readiness) && array_key_exists($readiness, self::READINESS_FILTERS) ? $readiness : '',
            'state' => is_string($state) && in_array($state, ['on', 'off'], true) ? $state : '',
        ];
    }

    private static function matchesReadiness(?string $status, string $readinessFilter): bool
    {
        return match ($readinessFilter) {
            'attention' => $status !== null,
            'clear' => $status === null,
            'missing_ui' => $status === 'Missing user UI',
            'safety_blocked' => $status === 'Safety-blocked',
            'configuration' => $status === 'Operational configuration required',
            'reserved' => $status === 'Reserved (ADR 0018)',
            default => true,
        };
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

        if ($effective['link_previews'] ?? false) {
            // Two operator steps stand between "available" and "unfurling", and
            // both are live state, so the badge clears itself once they are done.
            // The policy itself lives in the service that owns link previews.
            $missing = $this->container->get(LinkPreviewAdminService::class)->missingActivationSteps();
            if ($missing !== []) {
                $readiness['link_previews'] = [
                    'status' => 'Operational configuration required',
                    'class' => 'state-pending',
                    'note' => 'Available but inert: ' . implode(' and ', $missing) . ', so nothing is fetched. Both are set on the link previews console (docs/runbooks/link_previews.md).',
                    'href' => '/admin/link-previews',
                    'link' => 'Link previews console',
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
     * @param array{corrupt:bool,can_write:bool,outcome:?array{flag:string,changed:bool,enabled:bool}} $access
     * @return array<string,mixed>
     */
    private function row(string $flag, array $defaults, array $effective, array $overrides, array $readiness, array $access): array
    {
        $default = (bool) $defaults[$flag];
        $current = (bool) ($effective[$flag] ?? $default);
        $hasOverride = array_key_exists($flag, $overrides);
        $override = $hasOverride ? FeatureFlags::normalizeOverride($overrides[$flag]) : null;
        $meta = $readiness[$flag] ?? null;
        $outcome = $access['outcome'];

        return [
            'toggle' => $this->toggle($flag, $default, $current, $access['corrupt'], $access['can_write']),
            'outcome' => $outcome !== null && $outcome['flag'] === $flag && $outcome['enabled'] === $current
                ? ['changed' => $outcome['changed'], 'state' => $current ? 'on' : 'off']
                : null,
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

    /**
     * The row's one write affordance (ADR 0049): a confirm that asks for the
     * opposite of the effective state, or a locked chip with its reason when
     * this console cannot act. The policy is FeatureFlags'; the copy is the
     * catalog's; the service enforces both again on POST.
     *
     * @return array{mode:string,target:bool,verb:string,label:string,consequence:string,warning:?string,write:string,one_way:?string,note:?string}
     */
    private function toggle(string $flag, bool $default, bool $effective, bool $corrupt, bool $canWrite): array
    {
        $target = !$effective;
        $entry = FeatureFlagCatalog::entry($flag);
        $blocker = FeatureFlags::consoleEnableBlocker($flag);
        $note = match (true) {
            $corrupt => 'Locked until settings.features is repaired.',
            !$canWrite => 'Locked: your account cannot make changes right now.',
            $target && $blocker !== null => 'Cannot be turned on here. ' . $blocker,
            default => null,
        };

        return [
            'mode' => $note === null ? 'confirm' : 'locked',
            'target' => $target,
            'verb' => $target ? 'Turn on' : 'Turn off',
            'label' => $entry['label'],
            'consequence' => $target ? $entry['on'] : $entry['off'],
            'warning' => $target ? $entry['warn_on'] : $entry['warn_off'],
            // Back to the code default removes the key, as the runbook does.
            'write' => $target === $default ? 'remove' : 'store',
            'one_way' => !$target && $blocker !== null
                ? 'This console cannot turn it back on. ' . $blocker
                : null,
            'note' => $note,
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
