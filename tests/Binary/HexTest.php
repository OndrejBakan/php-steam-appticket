<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Binary;

use OndrejBakan\SteamAppTicket\Binary\Hex;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use PHPUnit\Framework\TestCase;

final class HexTest extends TestCase
{
    public function testDecodesLowercaseHex(): void
    {
        self::assertSame("\x00\xAB\xFF", Hex::decode('00abff'));
    }

    public function testDecodesUppercaseHex(): void
    {
        self::assertSame("\x00\xAB\xFF", Hex::decode('00ABFF'));
    }

    public function testDecodesMixedCaseHex(): void
    {
        self::assertSame("\xDE\xAD\xBE\xEF", Hex::decode('DeAdBeEf'));
    }

    public function testEmptyStringDecodesToEmptyBytes(): void
    {
        self::assertSame('', Hex::decode(''));
    }

    public function testRejectsOddLengthInput(): void
    {
        $this->expectException(InvalidTicketException::class);
        Hex::decode('abc');
    }

    public function testRejectsNonHexCharacters(): void
    {
        $this->expectException(InvalidTicketException::class);
        Hex::decode('zz');
    }

    public function testRejectsWhitespace(): void
    {
        $this->expectException(InvalidTicketException::class);
        Hex::decode('ab cd');
    }
}
