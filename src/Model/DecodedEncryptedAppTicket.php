<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

use DateTimeImmutable;

/**
 * Result of decrypting an encrypted app ticket.
 *
 * Encrypted tickets are authenticated by CRC32 plus a salted SHA-1 hash and
 * therefore deliberately do not expose RSA signature/validity fields.
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
        public array $licenses,
        public array $dlc,
        public string $userData,
        public ?EncryptedTicketTrailer $trailer,
    ) {
    }
}
