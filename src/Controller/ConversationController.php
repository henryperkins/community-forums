<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\UserRepository;
use App\Security\RateLimiter;
use App\Service\DirectMessageService;

/**
 * Direct-message UI (P2-07): conversation list, a single conversation (marks
 * read on view), starting a new conversation, replying, and reporting a message.
 * Eligibility/blocks/throttles live in DirectMessageService; the controller adds
 * a per-sender rate limit and the read-gate for viewing a conversation.
 */
final class ConversationController extends Controller
{
    private const SEND_MAX = 20;
    private const SEND_WINDOW = 60;
    private const REASONS = ['spam', 'harassment', 'off_topic', 'nsfw', 'illegal', 'other'];

    public function index(Request $request): Response
    {
        $user = $this->requireDms();
        return $this->view('dm/index', [
            'conversations' => $this->container->get(ConversationRepository::class)->listForUser($user->id()),
        ]);
    }

    public function newForm(Request $request): Response
    {
        $user = $this->requireDms();
        $to = trim((string) $request->query('to', ''));
        return $this->view('dm/new', ['to' => $to, 'errors' => [], 'body' => '']);
    }

    public function create(Request $request): Response
    {
        $user = $this->requireDms();
        $this->throttle($user);

        $to = trim((string) $request->str('to'));
        $body = (string) $request->post('body', '');
        $recipient = $to !== '' ? $this->container->get(UserRepository::class)->findByUsername($to) : null;
        if ($recipient === null) {
            return $this->view('dm/new', ['to' => $to, 'errors' => ['to' => 'No member with that username.'], 'body' => $body], 422);
        }

        try {
            $result = $this->container->get(DirectMessageService::class)->start($user, (int) $recipient['id'], $body);
        } catch (ValidationException $e) {
            return $this->view('dm/new', ['to' => $to, 'errors' => $e->errors, 'body' => $body], 422);
        }
        return $this->redirect('/messages/' . $result['conversation_id']);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $user = $this->requireDms();
        $conversationId = (int) ($params['id'] ?? 0);
        $convRepo = $this->container->get(ConversationRepository::class);
        if (!$convRepo->isParticipant($conversationId, $user->id())) {
            throw new NotFoundException('Conversation not found.');
        }

        $msgRepo = $this->container->get(DmMessageRepository::class);
        $perPage = 50;
        $total = $msgRepo->countByConversation($conversationId);
        $pages = max(1, (int) ceil($total / $perPage));
        // Open on the newest page by default so the latest messages are visible;
        // an explicit ?page= still pages backwards through history.
        $page = min($pages, max(1, $request->int('page', $pages)));
        $messages = $msgRepo->listByConversation($conversationId, $perPage, ($page - 1) * $perPage);

        // Viewing a conversation clears it: mark read up to the latest message,
        // not just the last one on the rendered page, so the unread badge clears
        // for conversations longer than one page.
        $convRepo->markRead($conversationId, $user->id(), $msgRepo->latestId($conversationId));
        $otherId = $convRepo->otherParticipant($conversationId, $user->id());
        $other = $otherId !== null ? $this->container->get(UserRepository::class)->find($otherId) : null;

        return $this->view('dm/show', [
            'conversation_id' => $conversationId,
            'messages' => $messages,
            'other' => $other,
            'page' => $page,
            'pages' => $pages,
            'reasons' => self::REASONS,
            'errors' => [],
        ]);
    }

    /** @param array<string,string> $params */
    public function reply(Request $request, array $params): Response
    {
        $user = $this->requireDms();
        $this->throttle($user);
        $conversationId = (int) ($params['id'] ?? 0);

        try {
            $this->container->get(DirectMessageService::class)->reply($user, $conversationId, (string) $request->post('body', ''));
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/messages/' . $conversationId, $e->first());
        }
        return $this->redirect('/messages/' . $conversationId);
    }

    /** @param array<string,string> $params message id */
    public function report(Request $request, array $params): Response
    {
        $user = $this->requireDms();
        $messageId = (int) ($params['id'] ?? 0);
        $reasonCode = (string) $request->post('reason_code', '');
        $reasonCode = in_array($reasonCode, self::REASONS, true) ? $reasonCode : null;

        $this->container->get(DirectMessageService::class)
            ->reportMessage($user, $messageId, $reasonCode, (string) $request->str('reason'));

        $message = $this->container->get(DmMessageRepository::class)->find($messageId);
        $convId = $message !== null ? (int) $message['conversation_id'] : 0;
        return $this->redirectWithFlash('/messages/' . $convId, 'Thanks — our moderators will review this message.');
    }

    private function requireDms(): User
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('dms')) {
            throw new NotFoundException('Not found.');
        }
        return $this->requireUser();
    }

    private function throttle(User $user): void
    {
        $limiter = $this->container->get(RateLimiter::class);
        $key = 'dm-send:' . $user->id();
        if ($limiter->tooManyAttempts($key, self::SEND_MAX)) {
            throw new \App\Core\HttpException(429, 'You are sending messages too quickly. Please slow down.');
        }
        $limiter->hit($key, self::SEND_WINDOW);
    }
}
