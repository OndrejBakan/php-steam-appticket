<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Binary;

use InvalidArgumentException;
use OndrejBakan\SteamAppTicket\Binary\Uint64;
use PHPUnit\Framework\TestCase;

final class Uint64Test extends TestCase
{
    public function testRendersZero(): void
    {
        self::assertSame('0', Uint64::toDecimalString(str_repeat("\x00", 8)));
    }

    public function testRendersKnownValues(): void
    {
        self::assertSame('1', Uint64::toDecimalString("\x01\x00\x00\x00\x00\x00\x00\x00"));
        self::assertSame('255', Uint64::toDecimalString("\xFF\x00\x00\x00\x00\x00\x00\x00"));
        self::assertSame(
            '9223372036854775808',
            Uint64::toDecimalString("\x00\x00\x00\x00\x00\x00\x00\x80"),
        );
        self::assertSame(
            '18446744073709551615',
            Uint64::toDecimalString(str_repeat("\xFF", 8)),
        );
    }

    public function testRoundTripsValuesAcrossTheFullRange(): void
    {
        $values = [
            0,
            1,
            255,
            256,
            4294967296,
            PHP_INT_MAX,
            '9223372036854775808',
            '18446744073709551615',
        ];

        foreach ($values as $value) {
            $encoded = Uint64::toLittleEndianBytes($value);

            self::assertSame(8, strlen($encoded));
            self::assertSame(
                Uint64::normaliseDecimal($value),
                Uint64::toDecimalString($encoded),
            );
        }
    }

    public function testNormalisesDecimalStrings(): void
    {
        self::assertSame('42', Uint64::normaliseDecimal(42));
        self::assertSame('42', Uint64::normaliseDecimal('00042'));
        self::assertSame('0', Uint64::normaliseDecimal('0'));
        self::assertSame('0', Uint64::normaliseDecimal('0000'));
        self::assertSame('18446744073709551615', Uint64::normaliseDecimal('18446744073709551615'));
    }

    public function testRejectsNegativeIntegers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uint64::normaliseDecimal(-1);
    }

    public function testRejectsEmptyStrings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uint64::normaliseDecimal('');
    }

    public function testRejectsNonDigitStrings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uint64::normaliseDecimal('12a');
    }

    public function testRejectsWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uint64::normaliseDecimal(' 1');
    }

    public function testRejectsValuesAboveTheUint64Range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uint64::normaliseDecimal('18446744073709551616');
    }

    public function testRejectsNegativeStringViaByteEncoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uint64::toLittleEndianBytes('-1');
    }

    public function testRejectsByteStringsThatAreNotExactlyEightBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uint64::toDecimalString('1234567');
    }

    public function testFitsInPhpInt(): void
    {
        self::assertTrue(Uint64::fitsInPhpInt('0'));
        self::assertTrue(Uint64::fitsInPhpInt('42'));
        self::assertTrue(Uint64::fitsInPhpInt((string) PHP_INT_MAX));
        self::assertFalse(Uint64::fitsInPhpInt('9223372036854775808'));
        self::assertFalse(Uint64::fitsInPhpInt('18446744073709551615'));
    }
}
