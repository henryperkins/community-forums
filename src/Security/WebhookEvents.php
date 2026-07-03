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

    /**
     * Redact authoring identity from a domain-event payload when the content was
     * authored anonymously, so an installed package can never de-anonymize a
     * masked author. Anonymity is masked at render time everywhere else
     * (ADMIN §1.3, DECISIONS #anon); the producer payloads are the one path
     * that reaches third-party code, so they must mask it too.
     *
     * The author-id key(s) are nulled and an `is_anonymous` state flag is
     * stamped so consumers can distinguish "no author" from "author omitted".
     * Any actor id equal to the masked author (a self-edit / self-delete) is
     * also nulled, since it would otherwise re-identify them; an actor id that
     * differs (a moderator acting on the post) is preserved.
     *
     * @param array<string,mixed> $payload
     * @param list<string> $authorKeys keys holding the author's user id
     * @param list<string> $actorKeys  keys holding an acting user id to null iff it equals the author
     * @return array<string,mixed>
     */
    public static function maskAnonymousAuthor(array $payload, bool $isAnonymous, array $authorKeys, array $actorKeys = []): array
    {
        $payload['is_anonymous'] = $isAnonymous;
        if (!$isAnonymous) {
            return $payload;
        }

        $authorIds = [];
        foreach ($authorKeys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                $authorIds[] = (int) $payload[$key];
            }
            $payload[$key] = null;
        }
        foreach ($actorKeys as $key) {
            if (array_key_exists($key, $payload) && in_array((int) $payload[$key], $authorIds, true)) {
                $payload[$key] = null;
            }
        }

        return $payload;
    }

    /** @return array<string,string> non-test events produced by domain hooks */
    public static function domainEvents(): array
    {
        $events = self::EVENTS;
        unset($events['ping']);
        return $events;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::EVENTS;
    }
}
