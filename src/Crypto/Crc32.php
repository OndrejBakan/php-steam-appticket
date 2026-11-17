<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Crypto;

/**
 * IEEE CRC-32 (the variant used by Steam's encrypted app ticket envelope).
 */
final class Crc32
{
    private const int UINT32_RANGE = 0x1_0000_0000;

    public static function of(string $data): int
    {
        $checksum = crc32($data);

        return $checksum < 0 ? $checksum + self::UINT32_RANGE : $checksum;
    }
}
