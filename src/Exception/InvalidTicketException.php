<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Exception;

/**
 * Raised when a ticket is structurally malformed or fails an integrity check
 * (CRC32, salted SHA-1, length validation, protobuf decoding, ...).
 */
class InvalidTicketException extends SteamAppTicketException
{
}
