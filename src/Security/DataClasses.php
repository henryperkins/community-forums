<?php

declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;

/**
 * Code-owned data-class catalogue + human consent vocabulary (Foundation F4;
 * ADR 0004 D4; PHASE_5_PLAN section 5 #8). A data class names what data a
 * package may access or receive; manifests declare them, operators grant them,
 * and `installed_package_permissions.kind='data_class'` (0049) stores them.
 * High-risk access is exceptional and separately named; `protected` classes
 * are never grantable to any package. Mirrors CapabilityCatalog: a static
 * catalogue, not a service. Validated against by Inc 3 (P5-02) manifest
 * validation behind the `package_registry` flag (default-on since 2026-07-09).
 *
 * key => [risk(low|medium|high|protected), description, consent (null iff protected)].
 *
 * @phpstan-type DataClassDef array{0:string,1:string,2:?string}
 */
final class DataClasses
{
    /** @var array<string,array{0:string,1:string,2:?string}> */
    private const CLASSES = [
        'content.metadata' => ['low', 'Content identifiers and state only: thread/post/board IDs, timestamps, status transitions; never bodies.', 'See which public topics and replies changed (IDs and status only, never the text).'],
        'content.public' => ['low', 'Bodies and titles of content on public boards.', 'Read the text of public topics and replies.'],
        'content.private' => ['high', 'Bodies, titles, or existence of content on private or hidden boards.', 'Read content from private or hidden boards.'],
        'messages.direct' => ['high', 'Direct-message and group-conversation content or metadata.', "Read members' direct and group messages."],
        'user.directory' => ['medium', 'Public member-directory data: usernames, display names, public profile fields, join dates.', 'See the public member directory (usernames and public profiles).'],
        'user.pii' => ['high', 'Member email addresses, IP-derived data, and verification state.', "Access members' email addresses and other personal data."],
        'moderation.records' => ['high', 'Reports, moderation-log entries, appeals, and sanction history.', 'Read moderation reports and action history.'],
        'auth.events' => ['high', 'Authentication activity: sign-ins, MFA events, credential changes.', 'See sign-in and credential-change activity.'],
        'security.config' => ['protected', 'Secrets, signing keys, provider configuration, and trust roots. Never grantable to a package.', null],
        'package.own_storage' => ['low', "The package's own quota-limited storage namespace.", 'Store its own settings and data in an isolated package storage area.'],
    ];

    /** @return array<string,array{0:string,1:string,2:?string}> */
    public static function all(): array
    {
        return self::CLASSES;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CLASSES);
    }

    public static function has(string $key): bool
    {
        return isset(self::CLASSES[$key]);
    }

    public static function risk(string $key): string
    {
        return self::def($key)[0];
    }

    public static function isProtected(string $key): bool
    {
        return self::risk($key) === 'protected';
    }

    /** A protected data class can never be declared, granted, or consented for a package. */
    public static function grantable(string $key): bool
    {
        return !self::isProtected($key);
    }

    public static function consent(string $key): ?string
    {
        return self::def($key)[2];
    }

    /** @return array{0:string,1:string,2:?string} */
    private static function def(string $key): array
    {
        if (!isset(self::CLASSES[$key])) {
            throw new InvalidArgumentException("Unknown data class: {$key}");
        }

        return self::CLASSES[$key];
    }
}
