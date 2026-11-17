<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Model;

use InvalidArgumentException;
use OndrejBakan\SteamAppTicket\Model\SteamId;
use PHPUnit\Framework\TestCase;

final class SteamIdTest extends TestCase
{
    public function testExposesComponentsAndStringForm(): void
    {
        $steamId = SteamId::fromSteamId64('76561197960278073');

        self::assertSame(76561197960278073, $steamId->toInt());
        self::assertSame('76561197960278073', $steamId->getSteamId64());
        self::assertSame('76561197960278073', (string) $steamId);
        self::assertSame(12345, $steamId->getAccountId());
        self::assertSame(1, $steamId->getInstance());
        self::assertSame(1, $steamId->getType());
        self::assertSame(1, $steamId->getUniverse());
        self::assertTrue($steamId->isIndividual());
    }

    public function testAcceptsLeadingZeros(): void
    {
        $steamId = SteamId::fromSteamId64('00076561197960278073');

        self::assertSame('76561197960278073', $steamId->getSteamId64());
    }

    public function testZeroIsValidButNotIndividual(): void
    {
        $steamId = SteamId::fromSteamId64('0');

        self::assertSame(0, $steamId->toInt());
        self::assertSame('0', $steamId->getSteamId64());
        self::assertFalse($steamId->isIndividual());
    }

    public function testIndividualRequiresDesktopInstance(): void
    {
        $value = (1 << 56) | (1 << 52) | (2 << 32) | 1;
        $steamId = SteamId::fromSteamId64($value);

        self::assertSame(1, $steamId->getType());
        self::assertSame(2, $steamId->getInstance());
        self::assertFalse($steamId->isIndividual());
    }

    public function testIndividualRequiresIndividualType(): void
    {
        $value = (1 << 56) | (2 << 52) | (1 << 32) | 1;
        $steamId = SteamId::fromSteamId64($value);

        self::assertSame(2, $steamId->getType());
        self::assertSame(1, $steamId->getInstance());
        self::assertFalse($steamId->isIndividual());
    }

    public function testEqualsComparesValues(): void
    {
        $first = SteamId::fromSteamId64(76561197960278073);
        $second = SteamId::fromSteamId64('76561197960278073');
        $third = SteamId::fromSteamId64(76561197960278074);

        self::assertTrue($first->equals($second));
        self::assertFalse($first->equals($third));
    }

    public function testRejectsNegativeValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromSteamId64(-1);
    }

    public function testRejectsNegativeStrings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromSteamId64('-76561197960278073');
    }

    public function testRejectsNonDigitInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromSteamId64('76561197960278073x');
    }

    public function testRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromSteamId64('');
    }

    public function testRejectsWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromSteamId64(' 76561197960278073');
    }

    public function testRejectsValuesAboveUint64Maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromSteamId64('18446744073709551616');
    }

    public function testSupportsFullUint64Range(): void
    {
        $steamId = SteamId::fromSteamId64('18446744073709551615');

        self::assertSame('18446744073709551615', $steamId->getSteamId64());
        self::assertNull($steamId->toInt());
        self::assertSame(4294967295, $steamId->getAccountId());
        self::assertSame(1048575, $steamId->getInstance());
        self::assertSame(15, $steamId->getType());
        self::assertSame(255, $steamId->getUniverse());
        self::assertFalse($steamId->isIndividual());
    }

    public function testAcceptsPhpIntMax(): void
    {
        $steamId = SteamId::fromSteamId64(PHP_INT_MAX);

        self::assertSame(PHP_INT_MAX, $steamId->toInt());
        self::assertSame((string) PHP_INT_MAX, $steamId->getSteamId64());
    }

    public function testFromUint64BytesSupportsHighBitValues(): void
    {
        $steamId = SteamId::fromUint64Bytes("\x00\x00\x00\x00\x00\x00\x00\x80");

        self::assertSame('9223372036854775808', $steamId->getSteamId64());
        self::assertNull($steamId->toInt());
    }

    public function testFromUint64BytesRejectsWrongLengths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromUint64Bytes('1234567');
    }

    public function testFromUint64BytesRejectsTooManyBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromUint64Bytes('123456789');
    }
}
