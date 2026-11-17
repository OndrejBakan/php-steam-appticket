<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Crypto;

/**
 * IEEE CRC-32 (the variant used by Steam's encrypted app ticket envelope).
 */
final class Crc32
{
    private const UINT32_RANGE = 4294967296;

    public static function of(string $data): int
    {
        $checksum = crc32($data);

        return $checksum < 0 ? $checksum + self::UINT32_RANGE : $checksum;
    }
}
