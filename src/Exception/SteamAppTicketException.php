<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Exception;

use RuntimeException;

/**
 * Base class for every expected (domain) failure raised by this library.
 *
 * Catching this exception is safe for both throwing and non-throwing API
 * variants; programming errors (TypeError, ValueError, ...) are intentionally
 * not part of this hierarchy.
 */
class SteamAppTicketException extends RuntimeException
{
}
