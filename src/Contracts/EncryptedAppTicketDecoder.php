<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Contracts;

use OndrejBakan\SteamAppTicket\Model\EncryptedAppTicketEnvelope;

/**
 * Decodes the protobuf envelope that wraps an encrypted app ticket.
 */
interface EncryptedAppTicketDecoder
{
    public function decode(string $payload): EncryptedAppTicketEnvelope;
}
