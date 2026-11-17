<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Crypto;

use OndrejBakan\SteamAppTicket\Crypto\Crc32;
use PHPUnit\Framework\TestCase;

final class Crc32Test extends TestCase
{
    public function testMatchesKnownVector(): void
    {
        self::assertSame(0xCBF43926, Crc32::of('123456789'));
    }

    public function testEmptyStringIsZero(): void
    {
        self::assertSame(0, Crc32::of(''));
    }

    public function testAlwaysReturnsUnsignedRange(): void
    {
        foreach (['a', 'ticket', str_repeat("\xFF", 64), 'hello world'] as $value) {
            $checksum = Crc32::of($value);

            self::assertGreaterThanOrEqual(0, $checksum);
            self::assertLessThanOrEqual(0xFFFFFFFF, $checksum);
            self::assertSame(crc32($value) & 0xFFFFFFFF, $checksum);
        }
    }
}
