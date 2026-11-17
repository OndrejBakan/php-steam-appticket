<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Exception;

/**
 * The encrypted ticket could not be decrypted with the supplied key.
 */
final class DecryptionFailedException extends SteamAppTicketException
{
}
