<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Exception;

/**
 * The ticket has no signature, or its signature cannot be trusted.
 */
final class InvalidSignatureException extends SteamAppTicketException
{
}
