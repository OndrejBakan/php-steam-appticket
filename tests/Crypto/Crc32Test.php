<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Crypto;

use OndrejBakan\SteamAppTicket\Crypto\Crc32;
use PHPUnit\Framework\TestCase;

final class Crc32Test extends TestCase
{
    public function testKnownCheckValue(): void
    {
        self::assertSame(3421780262, Crc32::of('123456789'));
    }

    public function testEmptyStringHasZeroChecksum(): void
    {
        self::assertSame(0, Crc32::of(''));
    }

    public function testHighBitChecksumsRemainNonNegative(): void
    {
        $checksum = Crc32::of('steam app ticket');

        self::assertGreaterThanOrEqual(0, $checksum);
        self::assertLessThanOrEqual(4294967295, $checksum);
    }
}
