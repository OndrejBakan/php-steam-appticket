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
        $steamId = SteamId::fromDecimalString('76561197960278073');

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
        $steamId = SteamId::fromDecimalString('00076561197960278073');

        self::assertSame('76561197960278073', $steamId->getSteamId64());
    }

    public function testZeroIsValidButNotIndividual(): void
    {
        $steamId = SteamId::fromDecimalString('0');

        self::assertSame(0, $steamId->toInt());
        self::assertFalse($steamId->isIndividual());
    }

    public function testIndividualRequiresDesktopInstance(): void
    {
        // Type 1 (individual) with instance 2: not a normal individual account.
        $steamId = new SteamId((1 << 56) | (1 << 52) | (2 << 32) | 1);

        self::assertSame(1, $steamId->getType());
        self::assertSame(2, $steamId->getInstance());
        self::assertFalse($steamId->isIndividual());
    }

    public function testIndividualRequiresIndividualType(): void
    {
        // Instance 1 but type 2 (multiseat).
        $steamId = new SteamId((1 << 56) | (2 << 52) | (1 << 32) | 1);

        self::assertSame(2, $steamId->getType());
        self::assertSame(1, $steamId->getInstance());
        self::assertFalse($steamId->isIndividual());
    }

    public function testEqualsComparesValues(): void
    {
        $first = new SteamId(76561197960278073);
        $second = SteamId::fromDecimalString('76561197960278073');
        $third = new SteamId(76561197960278074);

        self::assertTrue($first->equals($second));
        self::assertFalse($first->equals($third));
    }

    public function testConstructorRejectsNegativeValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SteamId(-1);
    }

    public function testRejectsNonDigitInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromDecimalString('76561197960278073x');
    }

    public function testRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromDecimalString('');
    }

    public function testRejectsNegativeString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromDecimalString('-76561197960278073');
    }

    public function testRejectsValuesAbovePhpIntMax(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SteamId::fromDecimalString('9223372036854775808');
    }

    public function testAcceptsPhpIntMax(): void
    {
        $steamId = SteamId::fromDecimalString((string) PHP_INT_MAX);

        self::assertSame(PHP_INT_MAX, $steamId->toInt());
    }
}
