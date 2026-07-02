<?php

declare(strict_types=1);

namespace App\Security\Registry;

/**
 * Canonical package/registry identity (P5-01). Package identity is globally
 * namespaced `publisher/name`; registry sources are one lowercase label.
 * Malformed identity fails closed - it never enters the catalogue.
 */
final class PackageIdentity
{
    private const UID = '/\A[a-z0-9][a-z0-9\-_.]{0,92}\/[a-z0-9][a-z0-9\-_.]{0,92}\z/';
    private const SOURCE = '/\A[a-z0-9][a-z0-9\-_.]{0,92}\z/';

    public static function isValidUid(string $uid): bool
    {
        return preg_match(self::UID, $uid) === 1;
    }

    /** The namespace prefix of a valid uid ("acme" for "acme/midnight-theme"). */
    public static function publisherUid(string $uid): string
    {
        return explode('/', $uid, 2)[0];
    }

    public static function isValidSourceId(string $sourceId): bool
    {
        return preg_match(self::SOURCE, $sourceId) === 1;
    }
}
