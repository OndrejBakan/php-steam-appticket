<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Exception;

use RuntimeException;

/**
 * Base class for every expected failure while parsing or validating a ticket.
 *
 * Catching this type lets callers handle malformed, untrusted ticket data
 * without accidentally swallowing programming errors such as TypeError.
 */
class SteamAppTicketException extends RuntimeException
{
}
