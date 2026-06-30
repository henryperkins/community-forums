<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Authenticated-user value object. Wraps a users row with the role/state
 * predicates that authorization decisions depend on. Roles are cumulative
 * (admin ⊇ moderator ⊇ user); account state beats role (a suspended admin
 * loses write powers — enforced by the write gate, not here).
 */
final class User
{
    /** @param array<string,mixed> $row */
    public function __construct(private array $row)
    {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->row['id'];
    }

    public function username(): string
    {
        return (string) $this->row['username'];
    }

    public function displayName(): string
    {
        $name = $this->row['display_name'] ?? null;
        return is_string($name) && $name !== '' ? $name : $this->username();
    }

    public function email(): string
    {
        return (string) $this->row['email'];
    }

    public function role(): string
    {
        return (string) ($this->row['role'] ?? 'user');
    }

    public function status(): string
    {
        return (string) ($this->row['status'] ?? 'active');
    }

    public function isAdmin(): bool
    {
        return $this->role() === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->role() === 'moderator' || $this->isAdmin();
    }

    /**
     * Account state resolved against suspended_until: a suspension whose
     * window has elapsed no longer blocks writes. Banned never auto-clears.
     */
    public function isActive(): bool
    {
        $status = $this->status();
        if (in_array($status, ['banned', 'deactivated', 'pending_deletion', 'deleted'], true)) {
            return false;
        }
        if ($status === 'suspended') {
            $until = $this->row['suspended_until'] ?? null;
            if ($until === null) {
                return false; // indefinite suspension
            }
            $ts = strtotime((string) $until . ' UTC');
            return $ts !== false && $ts <= time();
        }
        return true;
    }

    public function isSuspended(): bool
    {
        return $this->status() === 'suspended' && !$this->isActive();
    }

    public function isBanned(): bool
    {
        return $this->status() === 'banned';
    }

    public function isDeactivated(): bool
    {
        return $this->status() === 'deactivated';
    }

    public function isPendingDeletion(): bool
    {
        return $this->status() === 'pending_deletion';
    }

    public function isEmailVerified(): bool
    {
        return ($this->row['email_verified_at'] ?? null) !== null;
    }

    public function owns(int $authorId): bool
    {
        return $this->id() === $authorId;
    }

    public function passwordHash(): ?string
    {
        $hash = $this->row['password_hash'] ?? null;
        return is_string($hash) ? $hash : null;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->row;
    }
}
