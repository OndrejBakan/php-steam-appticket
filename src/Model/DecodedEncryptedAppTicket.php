<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

use DateTimeImmutable;

/**
 * Immutable result of decrypting and decoding an encrypted Steam app ticket.
 *
 * The embedded ownership ticket has no RSA signature of its own: authenticity
 * comes from AES decryption with the app's secret key, the CRC32 check and the
 * salted SHA-1 checksum. Callers must still validate the SteamID, AppID and
 * user data against their own expectations, and should reject replayed
 * nonces.
 */
final readonly class DecodedEncryptedAppTicket
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
        public string $userData,
        public bool $isExpired,
        public ?EncryptedTicketTrailer $trailer = null,
        public ?int $unknown1 = null,
    ) {
    }

    /**
     * Unsigned decimal SteamID64 of the ticket owner.
     */
    public function getSteamId64(): string
    {
        return $this->steamId->getSteamId64();
    }

    public function hasTrailer(): bool
    {
        return $this->trailer !== null;
    }
}
