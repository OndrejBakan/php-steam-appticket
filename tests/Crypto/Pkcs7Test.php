<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Crypto;

use InvalidArgumentException;
use OndrejBakan\SteamAppTicket\Crypto\Pkcs7;
use PHPUnit\Framework\TestCase;

final class Pkcs7Test extends TestCase
{
    public function testPadsAlignedDataWithAFullBlock(): void
    {
        $padded = Pkcs7::pad('0123456789abcdef', 16);

        self::assertSame(32, strlen($padded));
        self::assertSame(str_repeat("\x10", 16), substr($padded, 16));
    }

    public function testPadsUnalignedData(): void
    {
        $padded = Pkcs7::pad('hello', 16);

        self::assertSame(16, strlen($padded));
        self::assertSame('hello' . str_repeat("\x0B", 11), $padded);
    }

    public function testRoundTripsAlignedAndUnalignedData(): void
    {
        foreach (['', 'hello', '0123456789abcdef', str_repeat('x', 31)] as $value) {
            self::assertSame($value, Pkcs7::unpad(Pkcs7::pad($value, 16), 16));
        }
    }

    public function testUnpadRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad('', 16);
    }

    public function testUnpadRejectsNonBlockMultiple(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad('short', 16);
    }

    public function testUnpadRejectsZeroPaddingLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad(str_repeat("\x00", 16), 16);
    }

    public function testUnpadRejectsPaddingLongerThanBlockSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad(str_repeat("\xFF", 16), 16);
    }

    public function testUnpadRejectsInconsistentPaddingBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad(str_repeat('A', 15) . "\x02", 16);
    }

    public function testPadRejectsInvalidBlockSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::pad('data', 0);
    }

    public function testUnpadRejectsInvalidBlockSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pkcs7::unpad('data', 0);
    }
}
