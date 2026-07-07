<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Per-key constants for the 54 catalogued capabilities (Inc 6 follow-up).
 * Enforcement call sites reference these instead of free string literals:
 * a typo'd string fail-darks under enforce (denies everyone, invisibly to
 * CI), while a typo'd constant is a fatal at first touch. CapTest pins
 * Cap ⟷ CapabilityCatalog parity; CapabilityLiteralsTest additionally
 * validates every remaining quoted literal (the catalogue/matrix/oracle
 * data tables keep strings — they define them) against the catalogue.
 *
 * Generated from CapabilityCatalog::keys(): name = key minus the `core.`
 * prefix, upper-cased, dots to underscores.
 */
final class Cap
{
    public const BOARD_READ = 'core.board.read';
    public const THREAD_CREATE = 'core.thread.create';
    public const POST_CREATE = 'core.post.create';
    public const POST_EDIT_OWN = 'core.post.edit_own';
    public const POST_DELETE_OWN = 'core.post.delete_own';
    public const CONTENT_REACT = 'core.content.react';
    public const CONTENT_REPORT = 'core.content.report';
    public const THREAD_TAG = 'core.thread.tag';
    public const THREAD_MARK_SOLVED = 'core.thread.mark_solved';
    public const POLL_MANAGE = 'core.poll.manage';
    public const THREAD_MANAGE_WORKFLOW = 'core.thread.manage_workflow';
    public const MESSAGE_PARTICIPATE = 'core.message.participate';
    public const UPLOAD_CREATE = 'core.upload.create';
    public const DRAFT_MANAGE_OWN = 'core.draft.manage_own';
    public const ACCOUNT_MANAGE_SELF = 'core.account.manage_self';
    public const POST_DELETE_ANY = 'core.post.delete_any';
    public const POST_RESTORE = 'core.post.restore';
    public const THREAD_LOCK = 'core.thread.lock';
    public const THREAD_PIN = 'core.thread.pin';
    public const THREAD_MOVE = 'core.thread.move';
    public const THREAD_SPLIT_MERGE = 'core.thread.split_merge';
    public const POST_REVEAL_AUTHOR = 'core.post.reveal_author';
    public const CONTENT_APPROVE = 'core.content.approve';
    public const CONTENT_VIEW_PENDING = 'core.content.view_pending';
    public const REPORT_HANDLE = 'core.report.handle';
    public const APPEAL_RESOLVE_CONTENT = 'core.appeal.resolve_content';
    public const MEMORY_CURATE = 'core.memory.curate';
    public const USER_WARN = 'core.user.warn';
    public const USER_SUSPEND = 'core.user.suspend';
    public const USER_BAN = 'core.user.ban';
    public const USER_MANAGE = 'core.user.manage';
    public const APPEAL_RESOLVE_USER = 'core.appeal.resolve_user';
    public const CATEGORY_MANAGE = 'core.category.manage';
    public const BOARD_MANAGE = 'core.board.manage';
    public const BOARD_ASSIGN_MODERATORS = 'core.board.assign_moderators';
    public const BOARD_MANAGE_MEMBERS = 'core.board.manage_members';
    public const SITE_CONFIGURE = 'core.site.configure';
    public const SITE_BRANDING = 'core.site.branding';
    public const SITE_TAGS = 'core.site.tags';
    public const SITE_BADGES = 'core.site.badges';
    public const SITE_EMOJI = 'core.site.emoji';
    public const SITE_ANNOUNCEMENTS = 'core.site.announcements';
    public const SITE_LINK_PREVIEWS = 'core.site.link_previews';
    public const SITE_EMAIL = 'core.site.email';
    public const SITE_API_TOKENS = 'core.site.api_tokens';
    public const SITE_WEBHOOKS = 'core.site.webhooks';
    public const SITE_SECRETS = 'core.site.secrets';
    public const PACKAGE_MANAGE = 'core.package.manage';
    public const PACKAGE_REVIEW = 'core.package.review';
    public const OWNER_TRANSFER = 'core.owner.transfer';
    public const OWNER_RECOVERY = 'core.owner.recovery';
    public const TRUST_MANAGE_KEYS = 'core.trust.manage_keys';
    public const SIGNATURE_OVERRIDE = 'core.signature.override';
    public const AUDIT_INTEGRITY = 'core.audit.integrity';
}
