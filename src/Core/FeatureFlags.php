<?php

declare(strict_types=1);

namespace App\Core;

use App\Repository\SettingRepository;

/**
 * Phase 2 + Phase 3 feature flags (PHASE_2_PLAN §6 Milestone 0; PHASE_3_PLAN
 * §6 / §13 staged release).
 *
 * Each subsystem is gated so it can be enabled independently and rolled back
 * without a data change. Phase 2/3 defaults are ON so a fresh install is fully
 * functional; Phase 4 Gate A defaults are mixed: graduated workstreams default
 * ON, while unaccepted workstreams stay deploy-dark. The `features` setting
 * (a JSON object of flag => bool) overrides defaults per flag.
 *
 * Note: a Phase 3 flag only gates feature *availability*. The conservative
 * staged-rollout posture (e.g. anti-abuse "observe" mode) lives in config, not
 * here, so enabling the flag never silently holds or blocks content.
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
        'announcements' => true,     // admin site banner + opt-in in-app broadcast (ADMIN §7.4; SCHEMA §7 #13)

        // ── Phase 3 (Gate A) ─────────────────────────────────────────────
        'rich_composer' => true,     // shared composer toolbar + server preview (P3-02); textarea always works
        'drafts' => true,            // local autosave drafts + Drafts view (P3-03)
        'server_drafts' => false,    // authenticated cross-device draft sync (ADR 0010 pull-forward; deploy-dark)
        'uploads' => true,           // image upload/paste/drop + private delivery (P3-04)
        'anti_abuse' => true,        // central limiter, content filters, holds, audit (P3-05)
        'appeals' => false,          // self-service moderation appeals + staff queue (ADR 0007 carryover; deploy-dark)
        'branding' => true,          // operator branding: name/logo/favicon/colors (P3-07)
        'custom_css' => false,        // guarded raw CSS editor for trusted operators (ADR 0009)
        'seo' => true,               // public metadata, sitemap, robots (P3-10)
        'product_tour' => true,      // new-user onboarding tour (P3-11)

        // ── Phase 4 Gate A ───────────────────────────────────────────────
        'topic_workflow' => true,     // canonical status, history, snooze, assignment — GA default-on (2026-07-01; reversible via features override)
        'group_dms' => false,         // group conversation creation/invites
        'tags' => true,               // curated tag catalogue + thread tagging — GA default-on (2026-07-01; reversible via features override)
        'expanded_feeds' => true,     // board/tag follows, Following + Latest feeds — GA default-on (2026-07-01; reversible via features override)
        'reputation_ledger' => true,  // idempotent reputation events + windowed ranks — GA default-on (2026-07-01; reversible via features override)
        'badge_rules' => false,       // custom badge rules/backfill/revoke history
        'community_memory' => false,  // summaries, related topics, wiki revisions
        'content_references' => false,// persisted board/thread/post references + read-gated cards

        // ── Phase 4 carryover completion (deploy-dark, independently reversible)
        'link_previews' => false,      // allowlisted server-fetched URL metadata + purge/refresh
        'expanded_files' => false,     // PDF/text-family uploads behind scanner/quarantine gates
        'polls' => true,               // one poll per thread, no-JS vote/result flows — GA default-on (2026-06-30; reversible via features override)
        'custom_emoji' => false,       // operator-managed static PNG/WebP shortcode assets
        'slash_giphy' => false,        // PE slash inserts + client-side GIPHY picker config
        'split_merge' => false,        // moderator split/merge dry-run/apply/repair operations
        'profile_media' => false,      // avatar upload/signature moderation surfaces
        'board_folders' => true,       // private personal board folders — GA default-on (2026-07-01; reversible via features override)
        'bookmark_folders' => true,    // private folders for starred/bookmarked threads — GA default-on (2026-07-01; reversible via features override)
        'saved_feeds' => true,         // private saved feed filters/digest composition — GA default-on (2026-07-01; reversible via features override)
        'custom_profile_fields' => false, // bounded extra public profile fields
        'account_lifecycle' => false,  // self-serve export/deactivate/reactivate/30-day-grace delete (ADR 0006; deploy-dark)
        'automated_context' => false,  // since-last-read context + suggested related topics

        // ── Phase 5 Gate A (deploy-dark foundation/workstreams) ─────────────
        // These gate availability of the Phase 5 ecosystem/identity/governance
        // subsystems. They stay OFF until each workstream has its Milestone-0
        // trust approvals and acceptance evidence (PHASE_5_PLAN §2/§13). The
        // foundation migrations (0049–0053) are additive and inert; B2 behavior
        // remains unavailable while its service flags are dark.
        'package_registry' => false,  // signed registry, package catalogue/install/update (P5-01/02/04)
        'package_themes' => false,    // declarative theme packages + preview/safe-mode (P5-03)
        'capabilities' => false,      // DB-backed roles/capability resolver, scoped grants (P5-08/09)
        'passkeys' => false,          // WebAuthn registration/sign-in/step-up (P5-11)
        'provider_registry' => false, // generic OIDC + provider registry expansion (P5-12)
        'invitations' => false,       // invitation lifecycle / invite-based registration (P5-13)

        // ── Phase 5 Gate A — B2 trusted-extension foundation (deploy-dark) ─
        // Encrypted service-secret registry (SecretVault). Doubles as a write
        // kill switch: dark blocks store/rotate; reveal/revoke/prune still work.
        'service_secrets' => false,   // reversible secret vault for providers/webhooks (B2 sub-project 1)
        'api_tokens' => false,        // admin/service Bearer API tokens + read-only /api/v1 (B2 sub-project 2)
        'webhooks' => false,          // outbound webhook delivery engine + admin UI (B2 sub-project 3)
        'first_party_hooks' => false, // code-only first-party hooks + domain webhook producers (B2 sub-project 4)

        // ── Phase 5 Gate B (reserved; dark until Gate A is accepted) ───────
        'server_extensions' => false, // sandboxed isolated server-extension runtime (P5-05/06)
        'governance' => false,        // operator groups, approvals, access review (P5-10)
        'service_principals' => false,// remote-app service identities (P5-14)
        'verified_links' => false,    // verified profile links + richer fields (P5-15)
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
