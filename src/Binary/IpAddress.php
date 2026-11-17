<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Binary;

use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;

/**
 * Converts Steam's little-endian uint32 IP representation into a dotted quad.
 *
 * Values are range-checked before calling long2ip(), because long2ip()
 * silently wraps negative and out-of-range integers.
 */
final class IpAddress
{
    public const MAX_UINT32 = 4294967295;

    public static function fromUint32(int $value): string
    {
        if ($value < 0 || $value > self::MAX_UINT32) {
            throw new InvalidTicketException(sprintf('IPv4 value %d is outside the uint32 range.', $value));
        }

        $address = long2ip($value);

        if ($address === false) {
            throw new InvalidTicketException(sprintf('Unable to convert %d into an IPv4 address.', $value));
        }

        return $address;
    }
}
