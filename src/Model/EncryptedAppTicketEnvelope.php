<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

/**
 * Five-field protobuf envelope around an encrypted app ticket.
 *
 * Field names mirror the upstream `SteamEncryptedAppTicket` message while
 * using readable property names.
 */
final readonly class EncryptedAppTicketEnvelope
{
    public function __construct(
        public int $ticketVersionNo,
        public int $crcEncryptedTicket,
        public int $encryptedUserDataLength,
        public int $encryptedAppOwnershipTicketLength,
        public string $encryptedTicket,
    ) {
    }
}
