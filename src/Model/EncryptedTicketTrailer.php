<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

/**
 * Undocumented 20-byte trailer present in version 2 encrypted app tickets.
 *
 * Steam does not publish the meaning of these fields. They are preserved as
 * raw decimal strings (uint64) and an integer (uint32) so callers can compare
 * or log them without losing precision.
 */
final readonly class EncryptedTicketTrailer
{
    public function __construct(
        public string $unknown2,
        public string $unknown3,
        public int $unknown4,
    ) {
    }
}
