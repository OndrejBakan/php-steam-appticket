<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Exception;

/**
 * Raised when a plain (unencrypted) app ticket does not carry a valid
 * RSA-SHA1 signature and invalid signatures were not explicitly allowed.
 */
class InvalidSignatureException extends SteamAppTicketException
{
}
