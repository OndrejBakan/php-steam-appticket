<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

/**
 * Optional version 2 trailer found in newer encrypted app tickets.
 */
final readonly class EncryptedTicketTrailer
{
    /**
     * @param string $unknown2 Unsigned 64-bit value rendered as a decimal string.
     * @param string $unknown3 Unsigned 64-bit value rendered as a decimal string.
     */
    public function __construct(
        public string $unknown2,
        public string $unknown3,
        public int $unknown4,
    ) {
    }
}
