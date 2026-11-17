<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Exception;

/**
 * Raised when an encrypted ticket cannot be decrypted, either because the key
 * is invalid or because the ciphertext/padding is malformed.
 */
class DecryptionFailedException extends SteamAppTicketException
{
}
