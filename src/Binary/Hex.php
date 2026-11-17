<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Binary;

use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;

/**
 * Strict hexadecimal decoding for the convenience hex-based API.
 */
final class Hex
{
    public static function decode(string $hex): string
    {
        if ($hex === '') {
            return '';
        }

        if (strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
            throw new InvalidTicketException('Ticket data must be an even-length hexadecimal string.');
        }

        $decoded = hex2bin($hex);

        if ($decoded === false) {
            throw new InvalidTicketException('Ticket data contains invalid hexadecimal characters.');
        }

        return $decoded;
    }
}
