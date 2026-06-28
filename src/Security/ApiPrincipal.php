<?php

declare(strict_types=1);

namespace App\Security;

/** A non-human, scope-only API principal. Never a User; carries no role. */
final class ApiPrincipal
{
    /** @param string[] $scopes */
    public function __construct(
        private int $tokenId,
        private string $name,
        private array $scopes,
        private int $createdBy,
        private string $createdAt,
        private string $tokenHash,
    ) {
    }

    public function tokenId(): int { return $this->tokenId; }
    public function name(): string { return $this->name; }
    /** @return string[] */
    public function scopes(): array { return $this->scopes; }
    public function createdBy(): int { return $this->createdBy; }
    public function createdAt(): string { return $this->createdAt; }
    public function tokenHash(): string { return $this->tokenHash; }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
