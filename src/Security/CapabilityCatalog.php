<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Code-owned core capability catalogue (Foundation F3). The single source of
 * truth `docs/phase5/capability-taxonomy.md` §4 (A1, ADR 0012) is transcribed
 * here; the `0066` seed populates the `capabilities`/`role_capabilities` tables
 * from this class, and `CapabilityInventoryService`'s coverage test enforces
 * parity. Mirrors `App\Security\ApiScopes` (a static catalogue, not a service).
 *
 * Deploy-dark: nothing resolves against this until the `capabilities` flag +
 * the resolver (P5-08, Increment 1) land. Reputation/badges/profile fields are
 * NEVER capabilities (taxonomy §8).
 */
final class CapabilityCatalog
{
    /** Non-delegable protected authority (taxonomy §4.5 / decision #22) — never role-mapped, never delegated. */
    public const PROTECTED = [
        'core.owner.transfer',
        'core.owner.recovery',
        'core.trust.manage_keys',
        'core.signature.override',
        'core.audit.integrity',
    ];

    /**
     * key => [scope, risk, delegable, protected, description, consent].
     * `consent` is the human string shown on grant/increase (taxonomy §5);
     * protected keys have null consent (never delegated).
     *
     * @var array<string,array{0:string,1:string,2:bool,3:bool,4:string,5:?string}>
     */
    private const CAPABILITIES = [
        // ── §4.1 read / visibility (system.guest) ───────────────────────────
        'core.board.read' => ['board', 'low', true, false, 'Read boards, threads, posts, and public site surfaces, subject to the board read gate.', "Read boards, threads, and posts you're allowed to see."],

        // ── §4.2 user baseline (system.user) ────────────────────────────────
        'core.thread.create'          => ['board', 'low', true, false, 'Start a topic in a board you can post to.', 'Start new topics in boards you can post to.'],
        'core.post.create'            => ['board', 'low', true, false, 'Post a reply (thread not locked).', 'Post replies in threads you can post to.'],
        'core.post.edit_own'          => ['self',  'low', true, false, 'Edit your own post.', 'Edit your own posts.'],
        'core.post.delete_own'        => ['self',  'low', true, false, 'Delete your own post.', 'Delete your own posts.'],
        'core.content.react'          => ['board', 'low', true, false, 'React to posts, star threads, and vote in polls.', 'React to posts, star threads, and vote in polls.'],
        'core.content.report'         => ['board', 'low', true, false, 'Report a post or message to moderators.', 'Report posts or messages to moderators.'],
        'core.thread.tag'             => ['board', 'low', true, false, 'Add or change tags on a thread you can post in.', 'Add or change tags on threads you can post in.'],
        'core.thread.mark_solved'     => ['board', 'low', true, false, 'Accept/clear the answer on your own thread (or any thread in a board you moderate).', 'Accept or clear the answer on your own threads.'],
        'core.poll.manage'            => ['board', 'low', true, false, 'Create or close polls on your own thread (or any thread in a board you moderate).', 'Create or close polls on your own threads.'],
        'core.thread.manage_workflow' => ['board', 'low', true, false, "Manage a thread's status and assignment (authors on their own threads; staff for staff-only statuses/assignment).", 'Manage the status and assignment of your own threads.'],
        'core.message.participate'    => ['self',  'low', true, false, 'Send and manage your own DMs and group conversations.', 'Send and manage your own direct and group messages.'],
        'core.upload.create'          => ['self',  'low', true, false, 'Upload images and files in the composer.', 'Upload images and files in the composer.'],
        'core.draft.manage_own'       => ['self',  'low', true, false, 'Save and restore your own composer drafts.', 'Save and restore your own composer drafts.'],
        'core.account.manage_self'    => ['self',  'low', true, false, 'View and manage your own member surfaces and account (profile, security, preferences, sessions, blocks, follows, subscriptions, organization, export/deactivate/delete-request).', 'View and manage your own account, profile, and preferences.'],

        // ── §4.3 moderation, board-scoped via canModerate (system.moderator) ─
        'core.post.delete_any'        => ['board', 'medium', true, false, "Delete any member's post in a board you moderate.", "Delete other members' posts in boards this role moderates."],
        'core.post.restore'           => ['board', 'medium', true, false, 'Restore a soft-deleted post.', 'Restore soft-deleted posts in boards this role moderates.'],
        'core.thread.lock'            => ['board', 'medium', true, false, 'Lock or unlock a thread.', 'Lock or unlock threads in boards this role moderates.'],
        'core.thread.pin'             => ['board', 'medium', true, false, 'Pin or unpin a thread.', 'Pin or unpin threads in boards this role moderates.'],
        'core.thread.move'            => ['board', 'medium', true, false, 'Move a thread (moderator on both boards).', 'Move threads between boards this role moderates.'],
        'core.thread.split_merge'     => ['board', 'medium', true, false, 'Split or merge threads (moderator on both).', 'Split or merge threads in boards this role moderates.'],
        'core.post.reveal_author'     => ['board', 'high',   true, false, 'Reveal the author of an anonymous post.', 'Reveal the author of an anonymous post in boards this role moderates.'],
        'core.content.approve'        => ['board', 'medium', true, false, 'Approve or reject held/pending content.', 'Approve or reject held content in boards this role moderates.'],
        'core.content.view_pending'   => ['board', 'low',    true, false, 'View held/pending content awaiting moderation.', 'View held content awaiting moderation in boards this role moderates.'],
        'core.report.handle'          => ['board', 'medium', true, false, 'Triage reports: view queue, claim, resolve, dismiss.', 'Triage and resolve reports in boards this role moderates.'],
        'core.appeal.resolve_content' => ['board', 'medium', true, false, 'Resolve appeals against post/content actions.', 'Resolve appeals about content actions in boards this role moderates.'],
        'core.memory.curate'          => ['board', 'medium', true, false, 'Curate community memory: summaries, related topics, wiki posts.', 'Curate community memory in boards this role moderates.'],
        'core.user.warn'              => ['site',  'medium', true, false, 'Issue a formal warning and add staff notes to a member (staff-any, site-wide).', 'Issue formal warnings and staff notes to members, across the whole site.'],

        // ── §4.4 administration (system.admin) ──────────────────────────────
        'core.user.suspend'           => ['site',     'high',   true, false, 'Suspend a member and lift suspensions.', 'Suspend members and lift suspensions across the whole site.'],
        'core.user.ban'               => ['site',     'high',   true, false, 'Ban a member and lift bans.', 'Ban members and lift bans across the whole site.'],
        'core.user.manage'            => ['site',     'medium', true, false, 'Administer member records: directory view, cosmetic title, clear signature, manual badge grant/revoke.', 'Administer member records: titles, signatures, and manual badges.'],
        'core.appeal.resolve_user'    => ['site',     'high',   true, false, 'Resolve appeals against account actions (warn/suspend/ban).', 'Resolve appeals about account actions (warnings, suspensions, bans).'],
        'core.category.manage'        => ['site',     'medium', true, false, 'Create, edit, delete, reorder categories.', 'Create, edit, delete, and reorder categories.'],
        'core.board.manage'           => ['category', 'medium', true, false, 'Create, edit, delete, archive, move, reorder boards; set posting floor.', 'Create, edit, archive, move, and reorder boards, and set their posting floor.'],
        'core.board.assign_moderators'=> ['board',    'high',   true, false, 'Assign or remove board moderators.', 'Assign or remove moderators on boards.'],
        'core.board.manage_members'   => ['board',    'medium', true, false, 'Add or remove members of a private board.', 'Add or remove members of private boards.'],
        'core.site.configure'         => ['site',     'medium', true, false, 'Configure site name, structure, moderation settings.', 'Configure site name, structure, and moderation settings.'],
        'core.site.branding'          => ['site',     'medium', true, false, 'Manage branding, theme, custom CSS.', 'Manage site branding, theme, and custom CSS.'],
        'core.site.tags'              => ['site',     'low',    true, false, 'Administer the tag catalogue.', 'Administer the tag catalogue.'],
        'core.site.badges'            => ['site',     'low',    true, false, 'Administer badge rules.', 'Administer badge rules.'],
        'core.site.emoji'             => ['site',     'low',    true, false, 'Administer custom emoji.', 'Administer custom emoji.'],
        'core.site.announcements'     => ['site',     'low',    true, false, 'Set or clear the announcement banner.', 'Set or clear the site announcement banner.'],
        'core.site.link_previews'     => ['site',     'low',    true, false, 'Refresh or purge link previews.', 'Refresh or purge link previews.'],
        'core.site.email'             => ['site',     'medium', true, false, 'Operate email: dashboard, test, domain verify, requeue, suppressions, export.', 'Operate site email: delivery, testing, domains, and suppressions.'],
        'core.site.api_tokens'        => ['site',     'high',   true, false, 'Mint and revoke read-only API tokens.', 'Mint and revoke read-only API tokens for the whole site.'],
        'core.site.webhooks'          => ['site',     'high',   true, false, 'Manage outbound webhooks (create, rotate secret, test, replay, delete).', 'Create and manage outbound webhooks, including their signing secrets, for the whole site.'],
        'core.site.secrets'           => ['site',     'high',   true, false, 'Manage service secrets in the vault.', 'Manage service secrets in the vault for the whole site.'],
        'core.package.manage'         => ['site',     'high',   true, false, 'Install, update, pin, roll back, enable, disable, uninstall packages/themes; manage registries.', 'Install, update, roll back, and remove packages and themes, and manage registries.'],
        'core.package.review'         => ['site',     'high',   true, false, 'Operate the publisher/review/advisory console.', 'Operate the package publisher, review, and advisory console.'],

        // ── §4.5 protected — non-delegable (no consent; never role-mapped) ───
        'core.owner.transfer'    => ['site', 'protected', false, true, 'Designate or transfer site ownership.', null],
        'core.owner.recovery'    => ['site', 'protected', false, true, 'Perform break-glass account/owner recovery.', null],
        'core.trust.manage_keys' => ['site', 'protected', false, true, 'Manage registry trust roots and signing keys (rotation/revocation).', null],
        'core.signature.override'=> ['site', 'protected', false, true, 'Override or bypass package signature verification.', null],
        'core.audit.integrity'   => ['site', 'protected', false, true, 'Authority over audit-log integrity.', null],
    ];

    /**
     * Cumulative role → capability increments (taxonomy §6). Guest ⊂ user ⊂
     * moderator ⊂ admin; protected keys (§4.5) are intentionally absent.
     *
     * @var array<string,list<string>>
     */
    private const ROLE_INCREMENTS = [
        'system.guest' => ['core.board.read'],
        'system.user' => [
            'core.thread.create', 'core.post.create', 'core.post.edit_own', 'core.post.delete_own',
            'core.content.react', 'core.content.report', 'core.thread.tag', 'core.thread.mark_solved',
            'core.poll.manage', 'core.thread.manage_workflow', 'core.message.participate',
            'core.upload.create', 'core.draft.manage_own', 'core.account.manage_self',
        ],
        'system.moderator' => [
            'core.post.delete_any', 'core.post.restore', 'core.thread.lock', 'core.thread.pin',
            'core.thread.move', 'core.thread.split_merge', 'core.post.reveal_author', 'core.content.approve',
            'core.content.view_pending', 'core.report.handle', 'core.appeal.resolve_content',
            'core.memory.curate', 'core.user.warn',
        ],
        'system.admin' => [
            'core.user.suspend', 'core.user.ban', 'core.user.manage', 'core.appeal.resolve_user',
            'core.category.manage', 'core.board.manage', 'core.board.assign_moderators',
            'core.board.manage_members', 'core.site.configure', 'core.site.branding', 'core.site.tags',
            'core.site.badges', 'core.site.emoji', 'core.site.announcements', 'core.site.link_previews',
            'core.site.email', 'core.site.api_tokens', 'core.site.webhooks', 'core.site.secrets',
            'core.package.manage', 'core.package.review',
        ],
    ];

    /** @return array<string,array{scope:string,risk:string,delegable:bool,protected:bool,description:string,consent:?string}> */
    public static function all(): array
    {
        $out = [];
        foreach (self::CAPABILITIES as $key => $t) {
            $out[$key] = [
                'scope' => $t[0], 'risk' => $t[1], 'delegable' => $t[2],
                'protected' => $t[3], 'description' => $t[4], 'consent' => $t[5],
            ];
        }
        return $out;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CAPABILITIES);
    }

    public static function has(string $key): bool
    {
        return isset(self::CAPABILITIES[$key]);
    }

    public static function isProtected(string $key): bool
    {
        return in_array($key, self::PROTECTED, true);
    }

    public static function consent(string $key): ?string
    {
        return self::CAPABILITIES[$key][5] ?? null;
    }

    /**
     * Cumulative role maps (guest ⊂ user ⊂ moderator ⊂ admin).
     *
     * @return array<string,list<string>>
     */
    public static function roleCapabilities(): array
    {
        $out = [];
        $acc = [];
        foreach (self::ROLE_INCREMENTS as $role => $keys) {
            $acc = array_merge($acc, $keys);
            $out[$role] = $acc;
        }
        return $out;
    }
}
