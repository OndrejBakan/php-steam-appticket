<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Binary;

use OndrejBakan\SteamAppTicket\Binary\IpAddress;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use PHPUnit\Framework\TestCase;

final class IpAddressTest extends TestCase
{
    public function testConvertsKnownValues(): void
    {
        self::assertSame('0.0.0.0', IpAddress::fromUint32(0));
        self::assertSame('127.0.0.1', IpAddress::fromUint32(0x7F000001));
        self::assertSame('203.0.113.7', IpAddress::fromUint32(0xCB007107));
        self::assertSame('255.255.255.255', IpAddress::fromUint32(4294967295));
    }

    public function testRejectsNegativeValues(): void
    {
        $this->expectException(InvalidTicketException::class);
        IpAddress::fromUint32(-1);
    }

    public function testRejectsValuesAboveUint32(): void
    {
        $this->expectException(InvalidTicketException::class);
        IpAddress::fromUint32(4294967296);
    }
}
