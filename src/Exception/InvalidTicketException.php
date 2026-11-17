<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Exception;

/**
 * The supplied bytes are not a well-formed Steam app ticket.
 */
final class InvalidTicketException extends SteamAppTicketException
{
}
