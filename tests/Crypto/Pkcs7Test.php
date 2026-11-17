<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Crypto;

use InvalidArgumentException;
use OndrejBakan\SteamAppTicket\Crypto\Pkcs7;
use PHPUnit\Framework\TestCase;

final class Pkcs7Test extends TestCase
{
    public function testPadsEmptyDataWithAFullBlock(): void
    {
        self::assertSame(str_repeat("\x10", 16), Pkcs7::pad('', 16));
    }

    public function testPadsBlockAlignedDataWithAnotherBlock(): void
    {
        $padded = Pkcs7::pad(str_repeat('A', 16), 16);

        self::assertSame(32, strlen($padded));
        self::assertSame(str_repeat('A', 16) . str_repeat("\x10", 16), $padded);
    }

    public function testPadsPartialBlock(): void
    {
        $padded = Pkcs7::pad(str_repeat('A', 15), 16);

        self::assertSame(str_repeat('A', 15) . "\x01", $padded);
    }

    public function testRoundTripsPadding(): void
    {
        $values = ['', 'a', '123456789012345', '1234567890123456', str_repeat('x', 100)];

        foreach ($values as $value) {
            self::assertSame($value, Pkcs7::unpad(Pkcs7::pad($value, 16), 16));
        }
    }

    public function testSupportsAlternativeBlockSizes(): void
    {
        self::assertSame('abcde' . "\x03\x03\x03", Pkcs7::pad('abcde', 8));
        self::assertSame('abcde', Pkcs7::unpad('abcde' . "\x03\x03\x03", 8));
    }

    public function testRejectsEmptyPaddedData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad('', 16);
    }

    public function testRejectsDataThatIsNotBlockAligned(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad('abc', 16);
    }

    public function testRejectsZeroPaddingLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad(str_repeat('A', 15) . "\x00", 16);
    }

    public function testRejectsPaddingLongerThanBlockSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad(str_repeat('A', 15) . "\x11", 16);
    }

    public function testRejectsInconsistentPaddingBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad(str_repeat('A', 15) . "\x02", 16);
    }

    public function testRejectsInvalidBlockSizeWhenPadding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::pad('abc', 0);
    }

    public function testRejectsInvalidBlockSizeWhenUnpadding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad('abc', 0);
    }
}
