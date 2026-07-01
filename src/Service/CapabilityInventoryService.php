<?php

declare(strict_types=1);

namespace App\Service;

/**
 * The route/permission golden matrix (Foundation F3). Maps every non-protected
 * core capability to its authoritative call-site anchor(s) from
 * docs/phase5/capability-taxonomy.md §4, plus the §8 non-capability exclusions.
 * CapabilityInventoryCoverageTest asserts this matrix and CapabilityCatalog stay
 * in exact correspondence — the enforcement of A1.
 */
final class CapabilityInventoryService
{
    /** @var array<string,list<string>> capability_key => authoritative call-site anchors */
    private const MATRIX = [
        'core.board.read' => ['src/Security/BoardPolicy.php:27', 'src/Security/BoardPolicy.php:37'],
        'core.thread.create' => ['src/Service/PostingService.php:80', 'src/Security/BoardPolicy.php:66'],
        'core.post.create' => ['src/Service/PostingService.php:197'],
        'core.post.edit_own' => ['src/Service/PostingService.php:300'],
        'core.post.delete_own' => ['src/Service/PostingService.php:364'],
        'core.content.react' => ['src/Controller/EngagementController.php:32', 'src/Service/PollService.php:80'],
        'core.content.report' => ['src/Controller/ReportController.php:29', 'src/Service/DirectMessageService.php:207'],
        'core.thread.tag' => ['src/Controller/TagController.php:149'],
        'core.thread.mark_solved' => ['src/Service/SolvedAnswerService.php:196'],
        'core.poll.manage' => ['src/Service/PollService.php:151'],
        'core.thread.manage_workflow' => ['src/Service/ThreadWorkflowService.php:197', 'src/Service/ThreadWorkflowService.php:217', 'src/Service/ThreadWorkflowService.php:236'],
        'core.message.participate' => ['src/Service/DirectMessageService.php:57', 'src/Service/DirectMessageService.php:80'],
        'core.upload.create' => ['src/Controller/MediaController.php:31', 'src/Controller/MediaController.php:70'],
        'core.draft.manage_own' => ['src/Controller/DraftController.php:23'],
        'core.account.manage_self' => ['src/Controller/AccountController.php', 'src/Controller/SettingsController.php'],
        'core.post.delete_any' => ['src/Service/ModerationService.php:97'],
        'core.post.restore' => ['src/Service/ModerationService.php:139'],
        'core.thread.lock' => ['src/Service/ModerationService.php:76'],
        'core.thread.pin' => ['src/Service/ModerationService.php:55'],
        'core.thread.move' => ['src/Service/ModerationService.php:177'],
        'core.thread.split_merge' => ['src/Service/ThreadSplitMergeService.php:35', 'src/Service/ThreadSplitMergeService.php:114'],
        'core.post.reveal_author' => ['src/Service/ModerationService.php:249'],
        'core.content.approve' => ['src/Controller/ApprovalController.php:67', 'src/Controller/ApprovalController.php:86'],
        'core.content.view_pending' => ['src/Controller/ApprovalController.php:29', 'src/Controller/ThreadController.php:366', 'src/Controller/MediaController.php:204'],
        'core.report.handle' => ['src/Service/ReportService.php:74'],
        'core.appeal.resolve_content' => ['src/Service/AppealService.php:256'],
        'core.memory.curate' => ['src/Service/CommunityMemoryService.php:284'],
        'core.user.warn' => ['src/Service/UserModerationService.php:179'],
        'core.user.suspend' => ['src/Service/UserModerationService.php:69', 'src/Service/UserModerationService.php:187'],
        'core.user.ban' => ['src/Service/UserModerationService.php:86'],
        'core.user.manage' => ['src/Controller/AdminUserController.php:32', 'src/Controller/AdminUserController.php:56'],
        'core.appeal.resolve_user' => ['src/Service/AppealService.php:249'],
        'core.category.manage' => ['src/Controller/AdminController.php:76'],
        'core.board.manage' => ['src/Service/AdminService.php:198', 'src/Service/AdminService.php:229'],
        'core.board.assign_moderators' => ['src/Service/AdminService.php:466', 'src/Service/AdminService.php:496'],
        'core.board.manage_members' => ['src/Service/AdminService.php:523', 'src/Service/AdminService.php:547'],
        'core.site.configure' => ['src/Controller/AdminController.php:54', 'src/Controller/AdminController.php:65'],
        'core.site.branding' => ['src/Controller/BrandingController.php:73'],
        'core.site.tags' => ['src/Controller/TagController.php:84'],
        'core.site.badges' => ['src/Controller/AdminBadgeRuleController.php:21'],
        'core.site.emoji' => ['src/Controller/AdminCustomEmojiController.php:20'],
        'core.site.announcements' => ['src/Controller/AdminAnnouncementController.php:38'],
        'core.site.link_previews' => ['src/Controller/AdminLinkPreviewController.php:16'],
        'core.site.email' => ['src/Controller/AdminEmailController.php:35'],
        'core.site.api_tokens' => ['src/Controller/AdminApiTokenController.php:26'],
        'core.site.webhooks' => ['src/Controller/AdminWebhookController.php:33'],
        'core.site.secrets' => ['src/Service/SecretVault.php'],
        'core.package.manage' => ['src/Controller/AdminExtensionController.php:19'],
        'core.package.review' => ['src/Controller/AdminExtensionController.php:19'],
    ];

    /** @var array<string,string> non-capability call site => taxonomy §8 reason code */
    private const EXCLUSIONS = [
        'src/Security/WriteGate.php:22' => 'account_state',
        'src/Security/BoardPolicy.php (visibility/membership)' => 'board_read_gate',
        'src/Core/FeatureFlags.php::enabled' => 'feature_flag',
        'src/Security/ApiScopes.php' => 'api_scope',
        'src/Service/ReputationLedgerService.php' => 'reputation_badges',
        'src/Service/BadgeService.php::evaluateForUser' => 'reputation_badges',
        'src/Service/*ProfileField* (owner self-edit)' => 'profile_fields',
        'src/Controller/AuthController.php (login/register/reset/verify)' => 'bootstrap_auth',
        'src/Service/AccountLifecycleService.php:230 (last-admin guard)' => 'structural_invariant',
    ];

    /** @return array<string,list<string>> */
    public function matrix(): array
    {
        return self::MATRIX;
    }

    /** @return array<string,string> */
    public function exclusions(): array
    {
        return self::EXCLUSIONS;
    }
}
