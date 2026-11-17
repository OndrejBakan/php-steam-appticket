<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

use DateTimeImmutable;

/**
 * Immutable result of parsing a plain (unencrypted) Steam app ticket.
 *
 * Ownership-only tickets leave the authentication fields null. Full tickets
 * additionally expose the GC token and client session header.
 */
final readonly class AppTicket
{
    /**
     * @param list<int> $licenses
     * @param list<Dlc> $dlc
     */
    public function __construct(
        public int $version,
        public SteamId $steamId,
        public int $appId,
        public string $ownershipTicketExternalIp,
        public string $ownershipTicketInternalIp,
        public int $ownershipFlags,
        public DateTimeImmutable $ownershipTicketGenerated,
        public DateTimeImmutable $ownershipTicketExpires,
        public array $licenses,
        public array $dlc,
        public ?string $signature,
        public bool $isExpired,
        public bool $hasValidSignature,
        public bool $isValid,
        public ?string $authTicket = null,
        public ?string $gcToken = null,
        public ?DateTimeImmutable $tokenGenerated = null,
        public ?string $sessionExternalIp = null,
        public ?int $clientConnectionTime = null,
        public ?int $clientConnectionCount = null,
    ) {
    }

    public function hasAuthHeader(): bool
    {
        return $this->authTicket !== null;
    }

    public function hasSignature(): bool
    {
        return $this->signature !== null;
    }

    /**
     * Unsigned decimal SteamID64 of the ticket owner.
     */
    public function getSteamId64(): string
    {
        return $this->steamId->getSteamId64();
    }
}
