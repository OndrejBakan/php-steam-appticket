<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

/**
 * Internal DTO mirroring the five-field SteamEncryptedAppTicket protobuf.
 *
 * @internal
 */
final readonly class EncryptedAppTicketEnvelope
{
    public function __construct(
        public int $ticketVersionNo,
        public int $crcEncryptedTicket,
        public int $cbEncryptedUserData,
        public int $cbEncryptedAppOwnershipTicket,
        public string $encryptedTicket,
    ) {
    }
}
