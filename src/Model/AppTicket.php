<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

use DateTimeImmutable;

/**
 * Unified flat representation of a plain (unencrypted) Steam app ticket.
 *
 * Ownership-only tickets leave every authentication field null; full tickets
 * (the ones a game client sends to Steam) additionally expose the GC token and
 * session header data.
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
        public ?string $authTicket,
        public ?string $gcToken,
        public ?DateTimeImmutable $tokenGenerated,
        public ?string $sessionExternalIp,
        public ?int $clientConnectionTime,
        public ?int $clientConnectionCount,
    ) {
    }

    public function hasAuthHeader(): bool
    {
        return $this->authTicket !== null;
    }
}
