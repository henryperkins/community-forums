<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\View;
use App\Security\Csrf;
use App\Security\WriteGate;
use App\Service\ConversationReadService;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BlockRepository;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\UserRepository;
use App\Service\ContentReferenceService;
use App\Service\DirectMessageService;
use App\Service\PreferenceService;
use App\Service\RateLimitService;
use App\Support\Markdown;

/**
 * Direct-message UI (P2-07): conversation list, a single conversation (marks
 * read on view), starting a new conversation, replying, and reporting a message.
 * Eligibility/blocks/throttles live in DirectMessageService; the controller adds
 * a per-sender rate limit and the read-gate for viewing a conversation.
 */
final class ConversationController extends Controller
{
    private const REASONS = ['spam', 'harassment', 'off_topic', 'nsfw', 'illegal', 'other'];

    public function index(Request $request): Response
    {
        $user = $this->requireDms();
        // ?filter[]=…/?q[]=… arrive as arrays — treat anything non-string as absent.
        $filter = $request->query('filter', 'all') === 'unread' ? 'unread' : 'all';
        $rawQ = $request->query('q', '');
        $q = is_string($rawQ) ? trim($rawQ) : '';
        $conversations = $this->container->get(ConversationRepository::class)->listForUser($user->id(), $q !== '' ? $q : null);
        if ($filter === 'unread') {
            $conversations = array_values(array_filter(
                $conversations,
                static fn (array $c): bool => !empty($c['is_unread']),
            ));
        }
        return $this->view('dm/index', [
            'conversations' => $conversations,
            'first_run' => $conversations === [] && $filter === 'all' && $q === '',
            'new_user_throttled' => $this->container->get(DirectMessageService::class)->isThrottledNewUser($user),
            'filter' => $filter,
            'q' => $q,
            'allow_groups' => $this->container->get(FeatureFlags::class)->enabled('group_dms'),
            'show_avatars' => $this->showAvatars($user),
        ]);
    }

    public function newForm(Request $request): Response
    {
        $user = $this->requireDms();
        $rawTo = $request->query('to', '');
        $to = is_string($rawTo) ? trim($rawTo) : '';
        $allowGroups = $this->container->get(FeatureFlags::class)->enabled('group_dms');
        return $this->view('dm/new', [
            'to' => $to,
            'title' => '',
            'errors' => [],
            'body' => '',
            'allowGroups' => $allowGroups,
            'conversations' => $this->container->get(ConversationRepository::class)->listForUser($user->id()),
            'new_user_throttled' => $this->container->get(DirectMessageService::class)->isThrottledNewUser($user),
            'show_avatars' => $this->showAvatars($user),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->requireDms();
        $this->throttle($request, $user);
        $this->container->get(WriteGate::class)->assertCanWrite($user);

        $allowGroups = $this->container->get(FeatureFlags::class)->enabled('group_dms');
        $to = trim((string) $request->str('to'));
        $title = trim((string) $request->str('title'));
        $body = (string) $request->post('body', '');

        try {
            $recipientIds = $this->resolveRecipients($to);
            if ($recipientIds === []) {
                throw new ValidationException(['to' => 'Enter at least one username.']);
            }
            // Group conversations are a deploy-dark Phase 4 feature (group_dms):
            // until the flag is flipped, only a 1:1 direct message may be started.
            $isDirect = count($recipientIds) === 1 && $title === '';
            if (!$isDirect && !$allowGroups) {
                throw new ValidationException(['to' => 'Group conversations are not available yet.']);
            }
            $service = $this->container->get(DirectMessageService::class);
            $result = $isDirect
                ? $service->start($user, $recipientIds[0], $body, $request->post('idempotency_key'))
                : $service->startGroup($user, $recipientIds, $title, $body, $request->post('idempotency_key'));
        } catch (ValidationException | ForbiddenException $e) {
            $errors = $e instanceof ValidationException ? $e->errors : ['to' => $e->getMessage()];
            $data = [
                'to' => $to, 'title' => $title, 'errors' => $errors, 'body' => $body,
                'allowGroups' => $allowGroups, 'allow_groups' => $allowGroups,
                'conversations' => $this->container->get(ConversationRepository::class)->listForUser($user->id()),
                'new_user_throttled' => $this->container->get(DirectMessageService::class)->isThrottledNewUser($user),
                'show_avatars' => $this->showAvatars($user),
            ];
            if ($request->post('origin') === 'dialog') {
                $data['compose'] = ['to' => $to, 'title' => $title, 'body' => $body, 'errors' => $errors, 'open' => true];
                return $this->view('dm/index', $data, 422);
            }
            return $this->view('dm/new', $data, 422);
        }
        $this->discardServerDraftFor($user, $request->path());
        return $this->redirect($this->letterLocation($request, (int) $result['conversation_id'], (int) $result['message_id']));
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $user = $this->requireDms();
        return $this->renderConversation($request, $user, (int) ($params['id'] ?? 0));
    }

    /**
     * Render a single conversation. Reused by reply() when a message fails
     * validation so the typed body is re-rendered in place (HTTP 422) instead of
     * a redirect to an empty composer — matching the new-message/thread-reply
     * pattern, and so the local composer draft is preserved rather than discarded
     * by the next page load (P3-03 follow-up).
     *
     * @param array<string,mixed> $extra body / errors to repopulate
     */
    private function renderConversation(Request $request, User $user, int $conversationId, array $extra = [], int $status = 200): Response
    {
        $convRepo = $this->container->get(ConversationRepository::class);
        $membership = $convRepo->membership($conversationId, $user->id());
        if ($membership === null) {
            throw new NotFoundException('Conversation not found.');
        }
        $conversation = $convRepo->find($conversationId);
        if ($conversation === null) {
            throw new NotFoundException('Conversation not found.');
        }

        $msgRepo = $this->container->get(DmMessageRepository::class);
        $perPage = 50;
        $total = $msgRepo->countVisibleForUser($conversationId, $user->id());
        $pages = max(1, (int) ceil($total / $perPage));
        // Open on the newest page by default so the latest messages are visible;
        // an explicit ?page= still pages backwards through history.
        $page = min($pages, max(1, $request->int('page', $pages)));
        $messages = $msgRepo->listVisibleForUser($conversationId, $user->id(), $perPage, ($page - 1) * $perPage);
        $markdown = $this->container->get(Markdown::class);
        foreach ($messages as &$message) {
            if (trim((string) ($message['body_html'] ?? '')) === '') {
                $message['body_html'] = $markdown->render((string) $message['body'], ['link_mentions' => true]);
            }
        }
        unset($message);
        $messageIds = array_map(static fn (array $message): int => (int) $message['id'], $messages);
        $referenceCards = [];
        if ($this->container->get(FeatureFlags::class)->enabled('content_references')) {
            $referenceCards = $this->container->get(ContentReferenceService::class)
                ->cardsForSources('dm_message', $messageIds, $user);
        }

        // Viewing a conversation clears it: mark read up to the latest message,
        // not just the last one on the rendered page, so the unread badge clears
        // for conversations longer than one page.
        if ($convRepo->isParticipant($conversationId, $user->id())) {
            $convRepo->markRead($conversationId, $user->id(), $msgRepo->latestId($conversationId));
        }
        $otherId = $convRepo->otherParticipant($conversationId, $user->id());
        $other = $otherId !== null ? $this->container->get(UserRepository::class)->find($otherId) : null;
        $isGroup = (string) ($conversation['kind'] ?? 'direct') === 'group';
        $isOwner = $isGroup && (int) ($conversation['owner_user_id'] ?? 0) === $user->id();

        $participants = $convRepo->participants($conversationId);
        return $this->view('dm/show', array_merge([
            'presence_states' => $this->container->get(ConversationReadService::class)->presence($user, $participants),
            'joined_after_message_id' => (int) ($membership['joined_after_message_id'] ?? 0),
            'conversation' => $conversation,
            'conversation_id' => $conversationId,
            'is_group' => $isGroup,
            'is_owner' => $isOwner,
            'can_reply' => $convRepo->isParticipant($conversationId, $user->id()),
            'participants' => $participants,
            'events' => $isGroup ? $convRepo->events($conversationId, 20) : [],
            'messages' => $messages,
            'reference_cards' => $referenceCards,
            'other' => $other,
            // The list is the always-present left column of the reading room, and
            // the details rail needs the viewer's mute + block state (additive reads).
            'conversations' => $this->container->get(ConversationRepository::class)->listForUser($user->id()),
            'allow_groups' => $this->container->get(FeatureFlags::class)->enabled('group_dms'),
            'muted' => (($membership['notification_mode'] ?? 'normal') === 'muted'),
            'other_is_blocked' => (!$isGroup && $otherId !== null)
                ? $this->container->get(BlockRepository::class)->blocks($user->id(), $otherId)
                : false,
            'other_last_read_message_id' => (!$isGroup && $otherId !== null)
                ? $convRepo->otherLastReadMessageId($conversationId, $user->id())
                : null,
            'page' => $page,
            'pages' => $pages,
            'reasons' => self::REASONS,
            'errors' => [],
            'body' => '',
            'show_avatars' => $this->showAvatars($user),
        ], $extra), $status);
    }

    /** @param array<string,string> $params */
    public function reply(Request $request, array $params): Response
    {
        $user = $this->requireDms();
        $this->throttle($request, $user);
        $this->container->get(WriteGate::class)->assertCanWrite($user);
        $conversationId = (int) ($params['id'] ?? 0);
        $body = (string) $request->post('body', '');

        try {
            $messageId = $this->container->get(DirectMessageService::class)->reply(
                $user,
                $conversationId,
                $body,
                $request->post('idempotency_key'),
            );
        } catch (ValidationException | ForbiddenException $e) {
            return $this->renderConversation($request, $user, $conversationId, [
                'errors' => $e instanceof ValidationException ? $e->errors : ['body' => $e->getMessage()],
                'body' => $body,
            ], 422);
        }
        $this->discardServerDraftFor($user, $request->path());
        return $this->redirect($this->letterLocation($request, $conversationId, $messageId));
    }

    /** CSRF-protected because reading advances a participant's watermark. */
    public function poll(Request $request, array $params): Response
    {
        $user = $this->requireDms();
        $result = $this->container->get(ConversationReadService::class)->poll(
            $user, (int) ($params['id'] ?? 0), max(0, (int) $request->post('after', 0)),
        );
        $cards = [];
        if ($result['messages'] !== [] && $this->container->get(FeatureFlags::class)->enabled('content_references')) {
            $cards = $this->container->get(ContentReferenceService::class)->cardsForSources(
                'dm_message', array_column($result['messages'], 'id'), $user,
            );
        }
        // JSON fragments need no global navigation, board or presence-roster reads.
        $view = clone $this->container->get(View::class);
        $view->beforeRender(static function (): void {});
        $view->share(['current_user' => $user, 'csrf_token' => fn (): string => $this->container->get(Csrf::class)->token()]);
        $result['html'] = $result['messages'] === [] ? '' : $view->partial('partials/dm_messages', $result + [
            'reasons' => self::REASONS, 'reference_cards' => $cards,
        ]);
        unset($result['messages'], $result['participants']);
        return Response::json($result)->header('Cache-Control', 'private, no-store');
    }

    /** @param array<string,string> $params */
    public function addMember(Request $request, array $params): Response
    {
        $user = $this->requireGroupDms();
        $conversationId = (int) ($params['id'] ?? 0);
        return $this->runGroupAction(
            fn () => $this->container->get(DirectMessageService::class)->addParticipant(
                $user,
                $conversationId,
                $this->resolveSingleUser((string) $request->str('username')),
            ),
            $conversationId,
            'Member added.',
        );
    }

    /** @param array<string,string> $params */
    public function removeMember(Request $request, array $params): Response
    {
        $user = $this->requireGroupDms();
        $conversationId = (int) ($params['id'] ?? 0);
        $target = (int) $request->post('user_id', 0);
        return $this->runGroupAction(
            fn () => $this->container->get(DirectMessageService::class)->removeParticipant($user, $conversationId, $target),
            $conversationId,
            'Member removed.',
        );
    }

    /** @param array<string,string> $params */
    public function rename(Request $request, array $params): Response
    {
        $user = $this->requireGroupDms();
        $conversationId = (int) ($params['id'] ?? 0);
        return $this->runGroupAction(
            fn () => $this->container->get(DirectMessageService::class)->rename($user, $conversationId, (string) $request->str('title')),
            $conversationId,
            'Group renamed.',
        );
    }

    /** @param array<string,string> $params */
    public function mute(Request $request, array $params): Response
    {
        $user = $this->requireDms();
        $conversationId = (int) ($params['id'] ?? 0);
        $muted = (string) $request->post('muted', '1') === '1';
        $this->container->get(DirectMessageService::class)->mute($user, $conversationId, $muted);
        return $this->redirectWithFlash('/messages/' . $conversationId, $muted ? 'Conversation muted.' : 'Conversation unmuted.');
    }

    /** @param array<string,string> $params */
    public function transfer(Request $request, array $params): Response
    {
        $user = $this->requireGroupDms();
        $conversationId = (int) ($params['id'] ?? 0);
        $target = (int) $request->post('user_id', 0);
        return $this->runGroupAction(
            fn () => $this->container->get(DirectMessageService::class)->transferOwner($user, $conversationId, $target),
            $conversationId,
            'Ownership transferred.',
        );
    }

    /** @param array<string,string> $params message id */
    public function report(Request $request, array $params): Response
    {
        $user = $this->requireDms();
        $this->container->get(RateLimitService::class)->enforce('dm_report', $request, $user);
        $messageId = (int) ($params['id'] ?? 0);
        $reasonCode = (string) $request->post('reason_code', '');
        $reasonCode = in_array($reasonCode, self::REASONS, true) ? $reasonCode : null;

        $this->container->get(DirectMessageService::class)
            ->reportMessage($user, $messageId, $reasonCode, (string) $request->str('reason'));

        $message = $this->container->get(DmMessageRepository::class)->find($messageId);
        $convId = $message !== null ? (int) $message['conversation_id'] : 0;
        return $this->redirectWithFlash('/messages/' . $convId, 'Thanks — our moderators will review this message.');
    }

    /**
     * Where a sent letter lands: the conversation's default (newest) page, which
     * holds the letter just sent. A no-JS send (the DM forms carry land=letter
     * inside <noscript>) anchors on it, since every .dm-line carries id="m{id}"
     * and nothing else would move that reader off the top of the page. A JS
     * send stays unanchored: app.js pins it to the end, which a #m fragment
     * would suppress on this load and on every reload or Back to it.
     */
    private function letterLocation(Request $request, int $conversationId, int $messageId): string
    {
        $anchor = $messageId > 0 && $request->post('land') === 'letter' ? '#m' . $messageId : '';
        return '/messages/' . $conversationId . $anchor;
    }

    private function requireDms(): User
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('dms')) {
            throw new NotFoundException('Not found.');
        }
        return $this->requireUser();
    }

    /**
     * Group conversations are a deploy-dark Phase 4 feature (group_dms). Creating
     * and managing groups requires both the base DM flag and group_dms, so the
     * subsystem stays fully dark until the flag is flipped — rather than shipping
     * live just because the legacy `dms` flag defaults on.
     */
    private function requireGroupDms(): User
    {
        $flags = $this->container->get(FeatureFlags::class);
        if (!$flags->enabled('dms') || !$flags->enabled('group_dms')) {
            throw new NotFoundException('Not found.');
        }
        return $this->requireUser();
    }

    private function throttle(Request $request, User $user): void
    {
        $this->container->get(RateLimitService::class)->enforce('dm', $request, $user);
    }

    private function showAvatars(User $user): bool
    {
        return (bool) $this->container->get(PreferenceService::class)->reading($user->id())['show_avatars'];
    }

    /** @return list<int> */
    private function resolveRecipients(string $raw): array
    {
        $ids = [];
        foreach (preg_split('/[\s,]+/', $raw) ?: [] as $username) {
            $username = ltrim(trim((string) $username), '@');
            if ($username === '') {
                continue;
            }
            $row = $this->container->get(UserRepository::class)->findByUsername($username);
            if ($row === null) {
                throw new ValidationException(['to' => 'No member found with the username "' . $username . '".']);
            }
            $ids[] = (int) $row['id'];
        }
        return array_values(array_unique($ids));
    }

    private function resolveSingleUser(string $username): int
    {
        $username = ltrim(trim($username), '@');
        if ($username === '') {
            throw new ValidationException(['username' => 'Enter a username.']);
        }
        $row = $this->container->get(UserRepository::class)->findByUsername($username);
        if ($row === null) {
            throw new ValidationException(['username' => 'No member found with that username.']);
        }
        return (int) $row['id'];
    }

    private function runGroupAction(callable $action, int $conversationId, string $success): Response
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/messages/' . $conversationId, $e->first());
        }
        return $this->redirectWithFlash('/messages/' . $conversationId, $success);
    }
}
