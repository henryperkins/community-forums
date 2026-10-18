<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What each declared feature flag is called and what turning it on or off
 * does, so the /admin/features confirm can name the consequence before an
 * operator commits (ADR 0049). Each sentence was traced to the code that
 * reads the flag on 2026-10-18; the citations are in
 * docs/evidence/features-guarded-toggles-2026-10-18/README.md. A subsystem
 * change that alters what its flag does must update its entry here.
 *
 * `warn_on` / `warn_off` carry the one side effect an operator must weigh in
 * that direction (sign-in lockout, outbound traffic, queued work, data the
 * rollback does not protect); most flags have none.
 */
final class FeatureFlagCatalog
{
    /** @var array<string,array{label:string,on:string,off:string,warn_on?:string,warn_off?:string}> */
    private const ENTRIES = [
        'engagement' => [
            'label' => 'Inbox, reactions and stars',
            'on' => 'Signed-in members get the Inbox, unread markers, reactions on posts and starred topics.',
            'off' => 'The Inbox, unread markers, reactions and stars disappear; reactions and stars already given are kept and return when it is back on.',
            'warn_off' => 'Signing in or registering lands members on the Inbox, so doing either without a return link ends on a not-found page while it is off.',
        ],
        'notifications' => [
            'label' => 'Notifications',
            'on' => 'Members get the notification bell, the Notifications page and topic watch controls, with alerts for activity that involves them.',
            'off' => 'The bell, the Notifications page and watch controls disappear, and reply, mention, message and reaction alerts stop; existing notifications are kept.',
            'warn_off' => 'Activity while it is off does not notify members later (only announcement broadcasts and staff report alerts wait for re-enabling). If worker:email and worker:digest keep running, queued mail is suppressed and due digests are skipped.',
        ],
        'email' => [
            'label' => 'Notification email',
            'on' => 'Members get instant notification emails and daily digests, and admins get the Email delivery console.',
            'off' => 'Notification emails, daily digests, announcement emails and the Email delivery console stop; password reset and verification emails still send.',
            'warn_on' => 'Nothing is sent until a sender is configured and the sending domain passes its checks.',
            'warn_off' => 'If worker:email and worker:digest keep running, queued emails are marked suppressed and due digests are skipped for good; pause those jobs to hold mail instead.',
        ],
        'mentions' => [
            'label' => 'Mentions',
            'on' => 'Typing @username links to that member\'s profile, and mentions in topics and replies notify them.',
            'off' => 'New @mentions are no longer linked or notified and the Inbox Mentions filter is hidden; mentions already posted keep their links.',
            'warn_off' => 'Mentions written while it is off stay unlinked and never notify, even after it is back on.',
        ],
        'search' => [
            'label' => 'Search',
            'on' => 'Everyone can search the topics and posts they are allowed to read, from the Search page and the top bar.',
            'off' => 'The Search page and the search boxes in the top bar and admin console disappear.',
            'warn_off' => 'Composer topic suggestions still query the full-text index while it is off.',
        ],
        'dms' => [
            'label' => 'Direct messages',
            'on' => 'Members can send private messages to members who accept them, from Messages, the New message action and profile Message buttons.',
            'off' => 'Messages, the New message action and profile Message buttons disappear; existing conversations are kept and return when it is back on.',
            'warn_off' => 'Group conversations go dark with it.',
        ],
        'moderation_queue' => [
            'label' => 'Reports and approvals queue',
            'on' => 'Members can report posts, and moderators and admins can triage reports and release or reject held posts on their boards.',
            'off' => 'Post report buttons and the Reports and Approvals queues disappear; open reports and held posts are kept for when it is back on.',
            'warn_off' => 'Posts held by board approval or anti-abuse and new direct-message reports keep arriving, but nothing can be reviewed until it is back on.',
        ],
        'community' => [
            'label' => 'Follows, badges and accepted answers',
            'on' => 'Members can follow people, read their Following feed, accept answers and earn automatic badges, and everyone can view Top contributors.',
            'off' => 'Following people, the Following feed, Top contributors, accepting answers and profile badges switch off; accepted answers keep showing and follows and badges are kept.',
            'warn_off' => 'Following boards and the Latest feed go dark with it (existing board follows still shape the Inbox), and automatic badges pause; run community:backfill-badges after turning it back on.',
        ],
        'oauth' => [
            'label' => 'External sign-in',
            'on' => 'Members can sign in with, and link their account to, each configured external sign-in provider.',
            'off' => 'Provider sign-in buttons and the Connections settings page disappear; linked provider accounts are kept and work again when it is back on.',
            'warn_off' => 'Members who have no password or passkey and sign in only through external providers cannot sign in until it is back on, unless they reset a password by email. Generic OIDC providers stop too.',
        ],
        'presence' => [
            'label' => 'Who\'s online',
            'on' => 'Everyone sees who is online in the rail, on the Members online page and as presence dots, except members who hide their presence.',
            'off' => 'The online roster, the Members online page and presence dots disappear, and last-seen times stop updating until it is back on.',
            'warn_off' => 'The gap in last-seen times is never backfilled, and the dashboard\'s Active members tile falls to zero meanwhile.',
        ],
        'announcements' => [
            'label' => 'Announcements',
            'on' => 'Admins can publish a site-wide banner and optionally send it to members as an in-app notice or an email.',
            'off' => 'The Announcements console closes; a banner already showing stays visible and cannot be edited or cleared until it is back on.',
            'warn_off' => 'Clear a live banner first: the only control that clears it is on the console this closes.',
        ],
        'rich_composer' => [
            'label' => 'Rich composer',
            'on' => 'Composers gain the formatting toolbar, live preview, suggestions, autosaved drafts and image paste, drop and upload.',
            'off' => 'Composers fall back to a plain text box that still posts; the toolbar, preview, suggestions, autosave, image uploads and visual editor stop.',
            'warn_off' => 'Draft autosave, cross-device drafts, image uploads, slash commands and the visual editor stop with it, although their own flags stay on.',
        ],
        'wysiwyg_composer' => [
            'label' => 'Visual editor',
            'on' => 'Composers open in a visual editor that writes Markdown, with a source mode for the raw text.',
            'off' => 'Composers use the Markdown text box with the formatting toolbar; posts and drafts are unaffected.',
            'warn_on' => 'Nothing changes while the rich composer is off.',
        ],
        'drafts' => [
            'label' => 'Draft autosave',
            'on' => 'Composers autosave unsent text in the browser, and members get a Drafts page that lists and discards saved drafts.',
            'off' => 'Autosave, cross-device draft sync and the Drafts page stop; drafts already saved are not deleted.',
        ],
        'server_drafts' => [
            'label' => 'Cross-device drafts',
            'on' => 'Signed-in members\' drafts sync to their account across devices, with a list and discard controls on the Drafts page.',
            'off' => 'Drafts stop syncing to the account and stay in each browser; drafts already stored on the account are kept and reappear when it is back on.',
            'warn_off' => 'The cleanup job still purges account drafts not saved for 90 days while it is off.',
        ],
        'uploads' => [
            'label' => 'Image uploads',
            'on' => 'Members can attach, paste and drop images in topics, replies and messages.',
            'off' => 'New image uploads stop and the composer reports that uploads are disabled; images already posted keep displaying.',
            'warn_off' => 'Images uploaded but not yet posted are not attached and are cleaned up after about a day. File attachments go dark with it.',
        ],
        'anti_abuse' => [
            'label' => 'Anti-abuse filters',
            'on' => 'New topics and replies from non-staff members are checked against blocked-word, link, duplicate and flood rules at the configured mode.',
            'off' => 'Content filters stop screening and holding new posts and the Anti-abuse settings page closes; posts already held stay in the Approvals queue.',
            'warn_on' => 'If the anti-abuse mode is hold or block, member posts can be held or rejected as soon as it is on.',
            'warn_off' => 'Spam and floods are not screened while it is off; rate limits still apply.',
        ],
        'appeals' => [
            'label' => 'Moderation appeals',
            'on' => 'Members can appeal a moderator\'s removal or account action within 30 days, and staff resolve appeals from the Appeals queue.',
            'off' => 'Members cannot open appeals and the staff Appeals queue closes; open appeals and their history are kept and reappear when it is back on.',
            'warn_off' => 'The 30-day appeal window keeps running while it is off, so some members may lose the chance to appeal.',
        ],
        'branding' => [
            'label' => 'Branding',
            'on' => 'The operator\'s logo, favicon, brand colours and theme preset replace the built-in look, and admins can edit them on the Branding page.',
            'off' => 'The site returns to the built-in logo, favicon and colours and the Branding page closes; the site name is unchanged and saved branding is kept.',
            'warn_off' => 'Custom CSS stops applying with it.',
        ],
        'custom_css' => [
            'label' => 'Custom CSS',
            'on' => 'Saved custom CSS loads on every page, admin pages included, and admins can edit it on the Branding page.',
            'off' => 'Custom CSS stops loading on every page and its editor leaves the Branding page; the saved CSS is kept.',
            'warn_off' => 'Browsers and caches may keep serving the old stylesheet for up to five minutes.',
        ],
        'seo' => [
            'label' => 'Search engine sitemap',
            'on' => 'Search engines get /sitemap.xml listing public boards and recently active public topics, and robots.txt points to it.',
            'off' => 'The sitemap returns not found and robots.txt stops pointing to it; public pages stay crawlable with their titles and descriptions.',
            'warn_off' => 'This does not hide the forum from search engines.',
        ],
        'product_tour' => [
            'label' => 'Welcome tour',
            'on' => 'Signed-in members who have not finished the welcome tour see it on their next forum page, and Settings offers Replay tour.',
            'off' => 'The welcome tour and the Replay tour button go away; each member\'s finished-tour record is kept.',
            'warn_on' => 'Everyone who has not finished it sees it, including members who joined while it was off.',
        ],
        'topic_workflow' => [
            'label' => 'Topic status, assignment and snooze',
            'on' => 'Topics get a status with history, optional assignment and personal snooze, and the Inbox gains Snoozed, Assigned, Needs answer, Decisions and Solved.',
            'off' => 'Status, assignment and snooze controls and those Inbox views go away; statuses, history, assignments and snoozes are kept.',
            'warn_off' => 'Snoozed and hidden topics reappear in every Inbox while it is off, and answers accepted or cleared meanwhile leave Solved stale; run php bin/console repair after turning it back on.',
        ],
        'group_dms' => [
            'label' => 'Group conversations',
            'on' => 'Members can start group conversations, and group owners can add or remove members, rename the group and hand over ownership.',
            'off' => 'New groups and roster or title changes stop and members cannot leave groups; existing groups stay readable and replyable.',
            'warn_off' => 'Leave, Add, Rename, Make owner and Remove still show in existing groups but return not found.',
        ],
        'tags' => [
            'label' => 'Topic tags',
            'on' => 'Visitors can browse tag pages, members can tag topics on boards that allow tags, and admins manage the tag catalogue.',
            'off' => 'Tag pages, tag chips, tag editing and the admin tag catalogue go away; tags, their topic assignments and tag follows are kept.',
            'warn_off' => 'Links to tag pages in existing posts return not found, following or unfollowing tags stops, and tag reference cards and tag-based related topics stop.',
        ],
        'expanded_feeds' => [
            'label' => 'Board and tag follows',
            'on' => 'Members can follow boards and tags, and the Following feed adds their posts plus a Latest tab of recent community activity.',
            'off' => 'Board and tag follow buttons and the Latest tab go away, and Following shows people only; existing board and tag follows are kept.',
            'warn_off' => 'Members cannot unfollow boards or tags while it is off, yet those follows still shape their Inbox.',
        ],
        'reputation_ledger' => [
            'label' => 'Leaderboard windows',
            'on' => 'The leaderboard gains Week and Month tabs and a board filter, ranked from the reputation event ledger.',
            'off' => 'The leaderboard shows the all-time ranking only; reputation events keep being recorded.',
        ],
        'badge_rules' => [
            'label' => 'Badge rules',
            'on' => 'Admins can create threshold badge rules, preview who qualifies, and award or revoke badges in confirmed batches.',
            'off' => 'The Badge rules tab, its pages and its actions go away; rules, awarded badges and award history are kept, and nothing is awarded or revoked.',
            'warn_off' => 'A rule\'s awards cannot be revoked while it is off; revoke first if a bad rule is the problem.',
        ],
        'community_memory' => [
            'label' => 'Living briefs and wikis',
            'on' => 'Topic pages show Living Briefs, their sources and related topics, and staff can write summaries and curate wikis.',
            'off' => 'Living Briefs, summary history, related topics and wiki editing are hidden and AI brief generation stops; summaries, revisions and queued jobs are kept.',
            'warn_on' => 'With automated context on and an OpenAI key set, queued AI generation resumes and sends public post excerpts to OpenAI.',
        ],
        'content_references' => [
            'label' => 'Reference cards',
            'on' => 'Links to boards, topics, posts and tags show preview cards to readers allowed to see the target.',
            'off' => 'Reference cards stop rendering and links in new posts and messages are not recorded; the links still work and recorded references are kept.',
            'warn_off' => 'Posts and messages written while it is off get no cards later unless edited; there is no backfill.',
        ],
        'link_previews' => [
            'label' => 'Link previews',
            'on' => 'Links on opted-in public boards get preview cards fetched by the server from allowlisted hosts.',
            'off' => 'Preview cards stop showing, new links are not queued and the preview worker makes no outbound requests; stored previews, opt-ins and the allowlist are kept.',
            'warn_on' => 'Links already queued on opted-in boards are fetched at the next worker:previews run, which is outbound traffic to allowlisted hosts.',
        ],
        'expanded_files' => [
            'label' => 'File attachments',
            'on' => 'The upload endpoint accepts PDF, text and data files, but members have no upload control yet.',
            'off' => 'File uploads and downloads return not found, including files already attached; image uploads are unaffected.',
        ],
        'polls' => [
            'label' => 'Polls',
            'on' => 'Topic authors, admins and board moderators can add one poll per topic; members vote and see results after voting or once it closes.',
            'off' => 'Poll panels and creating, voting on or closing polls go away; polls and votes are kept and reappear when it is back on.',
        ],
        'custom_emoji' => [
            'label' => 'Custom emoji',
            'on' => 'Emoji added under Custom emoji render from :shortcode: in new posts and messages, and reaction emoji join the picker.',
            'off' => 'New posts show custom :shortcode: as plain text, custom reactions leave the picker and the Custom emoji tab closes; published posts keep their emoji.',
            'warn_off' => 'Posts written while it is off keep plain :shortcode: text unless they are edited or re-rendered.',
        ],
        'slash_giphy' => [
            'label' => 'Slash menu and GIPHY picker',
            'on' => 'With a GIPHY key set, the composer gains a / menu of inserts and a GIPHY picker searched from members\' browsers.',
            'off' => 'The composer\'s / menu and GIPHY picker go away, and GIPHY images in existing posts stop loading because the page security policy no longer allows them.',
            'warn_on' => 'With a key set, members\' GIPHY searches and IP addresses go straight to GIPHY; the runbook requires a privacy disclosure first.',
            'warn_off' => 'The table, task list, poll outline and custom emoji inserts go with it, not just GIPHY.',
        ],
        'split_merge' => [
            'label' => 'Topic split and merge',
            'on' => 'Admins and board moderators can split replies into a new topic or merge one topic into another.',
            'off' => 'The split and merge panel and actions go away; topics already split or merged stay as they are and merged links keep redirecting.',
        ],
        'profile_media' => [
            'label' => 'Avatar uploads',
            'on' => 'Members can upload or remove their own avatar, and admins can clear a member\'s avatar or signature.',
            'off' => 'Avatar upload and removal stop and admins lose the clear avatar and clear signature actions; existing avatars and signatures keep showing.',
            'warn_off' => 'Admins cannot clear an abusive avatar or signature while it is off.',
        ],
        'board_folders' => [
            'label' => 'Board folders',
            'on' => 'Members can group boards into private folders in Settings, shown as sections in the board rail.',
            'off' => 'Folder controls in Settings and folder sections in the board rail go away; members\' folders are kept and return when it is back on.',
        ],
        'bookmark_folders' => [
            'label' => 'Bookmark folders',
            'on' => 'Members can create private bookmark folders and file their starred topics into them from Settings.',
            'off' => 'The Bookmark folders card leaves Settings and its actions return not found; stars and existing folders are kept.',
        ],
        'saved_feeds' => [
            'label' => 'Saved feeds',
            'on' => 'Members can save board filters as named feeds, open them from the sidebar and include them in their daily digest.',
            'off' => 'Saved feeds leave the sidebar and Settings, their pages return not found and digests stop including them; saved filters are kept.',
            'warn_off' => 'Digests sent while it is off leave out saved-feed activity, with no backfill.',
        ],
        'custom_profile_fields' => [
            'label' => 'Custom profile fields',
            'on' => 'Members can add up to three label and value pairs in profile settings, shown under Profile details on their profile.',
            'off' => 'The custom fields editor and the Profile details section disappear; saved fields are kept and reappear when it is back on.',
            'warn_on' => 'Every member\'s saved fields become public again at once; there is no per-field moderation tool.',
        ],
        'account_lifecycle' => [
            'label' => 'Account export, deactivation and deletion',
            'on' => 'Members can export their data, deactivate and reactivate their account, and request deletion with a 30-day grace period.',
            'off' => 'The Account settings page and its export, deactivate, reactivate and deletion actions return not found; account statuses and pending deletions are kept.',
            'warn_off' => 'Pending deletions still purge when their grace ends, but members can no longer cancel them; pause worker:purge-accounts to halt purges. Deactivated members cannot reactivate themselves.',
        ],
        'automated_context' => [
            'label' => 'Automated thread context',
            'on' => 'Members see a catch-up strip of replies since their last visit, and public threads can get AI briefs and tag-based related topics.',
            'off' => 'The catch-up strip disappears and new AI briefs and related topics stop being generated; published briefs stay visible.',
            'warn_on' => 'With thread memory on and an OpenAI key set, queued generation resumes and sends public post excerpts to OpenAI.',
        ],
        'package_registry' => [
            'label' => 'Package registry',
            'on' => 'Admins can manage registry trust and install, update, roll back and uninstall signed packages, and the registry and health workers run.',
            'off' => 'The Packages, Registry trust and package security pages return not found and both package workers skip their runs; installed packages are kept.',
            'warn_on' => 'The next cron runs fetch from every enabled registry and purge uninstalled packages whose retention has lapsed.',
            'warn_off' => 'Installed integrations and an active package theme keep running, but advisories stop and the emergency execution brake is unreachable; use PACKAGE_EXECUTION_DISABLED instead.',
        ],
        'package_themes' => [
            'label' => 'Package themes',
            'on' => 'Admins can preview, activate and roll back verified theme packages, and the site serves the active package theme.',
            'off' => 'The site drops any active package theme for the built-in theme and the Themes console returns not found; theme builds and the active selection are kept.',
            'warn_on' => 'If a package theme is active, every page\'s look changes at once.',
            'warn_off' => 'If a package theme is active, every page\'s look changes at once.',
        ],
        'capabilities' => [
            'label' => 'Roles and capabilities',
            'on' => 'Admins get the role editor, scoped role grants and the permission simulator; grants decide access only when CAPABILITIES_MODE is enforce.',
            'off' => 'Roles, role assignments and the permission simulator return not found and permission checks use the legacy role and board-moderator rules; grants are kept.',
            'warn_on' => 'Under CAPABILITIES_MODE=enforce, role grants decide live access at once.',
            'warn_off' => 'Under CAPABILITIES_MODE=enforce, people lose access they hold only through role grants until it is back on.',
        ],
        'passkeys' => [
            'label' => 'Passkey sign-in',
            'on' => 'Members can register passkeys in Security settings and sign in with them on the login page.',
            'off' => 'The passkey sign-in option and the passkey settings panel disappear; saved passkeys are kept and work again when it is back on.',
            'warn_off' => 'Members who rely on a passkey to sign in are locked out until it is back on, unless they reset a password by email.',
        ],
        'provider_registry' => [
            'label' => 'Generic OIDC sign-in providers',
            'on' => 'Admins can add, test and enable generic OIDC sign-in providers, and enabled ones appear as sign-in buttons.',
            'off' => 'Generic OIDC sign-in buttons and routes disappear and the Sign-in providers console returns not found; Google, Apple and GitHub sign-in are kept.',
            'warn_on' => 'Sign-in buttons return at once for every provider still marked enabled.',
            'warn_off' => 'Members whose only sign-in is a generic OIDC provider cannot sign in until it is back on, unless they reset a password by email.',
        ],
        'invitations' => [
            'label' => 'Invitations',
            'on' => 'Admins can issue invitation links, and invite-only registration admits people holding a valid link.',
            'off' => 'Invitation links and the Invitations console return not found and registration ignores invitations; invitation records are kept.',
            'warn_on' => 'Every unexpired, unrevoked invitation link works again, including any board membership it grants.',
            'warn_off' => 'If registration is invite-only, sign-up closes to everyone, including new external sign-in accounts.',
        ],
        'service_secrets' => [
            'label' => 'Encrypted secret vault',
            'on' => 'Admins can store and rotate encrypted secrets, which adding sign-in providers, creating webhooks and minting package credentials need.',
            'off' => 'New secrets cannot be stored or rotated, so adding sign-in providers, creating webhooks and minting package credentials are refused; existing secrets keep working.',
            'warn_off' => 'A leaked webhook, provider or package secret cannot be rotated until it is back on.',
        ],
        'api_tokens' => [
            'label' => 'API tokens and read-only API',
            'on' => 'Admins can mint scoped Bearer tokens, and token holders can read the /api/v1 endpoints.',
            'off' => 'Every /api/v1 request returns not found and tokens stop authenticating; tokens are kept and work again when it is back on.',
            'warn_on' => 'Every token that is not revoked or expired works again at once.',
            'warn_off' => 'Integrations that call /api/v1 fail at once, and tokens cannot be revoked from the console while it is off.',
        ],
        'webhooks' => [
            'label' => 'Outbound webhooks',
            'on' => 'Admins can register signed webhook endpoints, and the delivery worker posts subscribed forum events to them with retries.',
            'off' => 'The Webhooks console returns not found, new events are not queued and the delivery worker pauses; endpoints, history and queued deliveries are kept.',
            'warn_on' => 'The queued backlog is sent at the next worker:webhooks run, so endpoints can receive stale events.',
            'warn_off' => 'Events that happen while it is off are never delivered.',
        ],
        'first_party_hooks' => [
            'label' => 'Webhook domain events',
            'on' => 'New topics, replies, edits, deletions, solved answers, reports, registrations, bans and anti-abuse actions are queued for subscribed webhook endpoints.',
            'off' => 'Forum events stop reaching webhook endpoints and are not queued for later; the admin test ping still works.',
            'warn_off' => 'Events from the period it is off are never sent.',
        ],
        'server_extensions' => [
            'label' => 'Server extension runtime',
            'on' => 'Admins see a read-only Extensions status page and the extension worker claims queued jobs.',
            'off' => 'The Extensions page returns not found and the extension worker skips every run; handlers, queued jobs and run history are kept.',
        ],
        'governance' => [
            'label' => 'Governance controls',
            'on' => 'Nothing becomes available yet: no code reads this flag.',
            'off' => 'Nothing changes: no code reads this flag.',
        ],
        'service_principals' => [
            'label' => 'Service principals',
            'on' => 'Nothing becomes available yet: no code reads this flag.',
            'off' => 'Nothing changes: no code reads this flag.',
        ],
        'verified_links' => [
            'label' => 'Verified profile links',
            'on' => 'Nothing becomes available yet: no code reads this flag.',
            'off' => 'Nothing changes: no code reads this flag.',
        ],
    ];

    /**
     * The entry for $flag. An undeclared or not-yet-described flag gets the
     * runbook's generic rollback sentence rather than an invented claim.
     *
     * @return array{label:string,on:string,off:string,warn_on:?string,warn_off:?string}
     */
    public static function entry(string $flag): array
    {
        $entry = self::ENTRIES[$flag] ?? [
            'label' => $flag,
            'on' => 'Its pages and actions become available.',
            'off' => 'Its pages and actions go offline until it is turned back on; no data is deleted.',
        ];

        return [
            'label' => $entry['label'],
            'on' => $entry['on'],
            'off' => $entry['off'],
            'warn_on' => $entry['warn_on'] ?? null,
            'warn_off' => $entry['warn_off'] ?? null,
        ];
    }

    /** @return list<string> */
    public static function described(): array
    {
        return array_keys(self::ENTRIES);
    }
}
