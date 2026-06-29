<?php

declare(strict_types=1);

namespace App\Security;

/** Event-name catalogue for outbound webhooks. */
final class WebhookEvents
{
    /** @var array<string,string> event name => human description */
    public const EVENTS = [
        'ping' => 'Test event (fires from the admin Send test event action)',
        'topic.created' => 'A new topic/thread became publicly visible',
        'reply.created' => 'A new reply became publicly visible',
        'post.edited' => 'A post was edited by its author',
        'post.deleted' => 'A post was deleted by an author or moderator',
        'thread.solved' => 'A thread was marked solved / answer accepted',
        'report.created' => 'A post was reported',
        'report.resolved' => 'A report was resolved or dismissed',
        'member.registered' => 'A new member account was created',
        'member.banned' => 'A member was banned',
        'moderation.auto_action' => 'Anti-abuse took an automated action',
    ];

    public static function isValid(string $event): bool
    {
        return isset(self::EVENTS[$event]);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::EVENTS;
    }
}
