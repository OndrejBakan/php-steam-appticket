<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Binary;

use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;

/**
 * Converts Steam's little-endian uint32 IP representation into a dotted quad.
 */
final class IpAddress
{
    private const int MAX_UINT32 = 0xFFFFFFFF;

    public static function fromUint32(int $value): string
    {
        if ($value < 0 || $value > self::MAX_UINT32) {
            throw new InvalidTicketException(sprintf('IPv4 value %d is outside the uint32 range.', $value));
        }

        $address = long2ip($value);

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidTicketException(sprintf('Unable to convert %d into an IPv4 address.', $value));
        }

        return $address;
    }
}
