<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Crypto;

use OndrejBakan\SteamAppTicket\Crypto\EncryptionKey;
use OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException;
use PHPUnit\Framework\TestCase;

final class EncryptionKeyTest extends TestCase
{
    private const string HEX_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testCreatesFromHex(): void
    {
        $key = EncryptionKey::fromHex(self::HEX_KEY);

        self::assertSame(hex2bin(self::HEX_KEY), $key->bytes());
        self::assertSame(32, strlen($key->bytes()));
    }

    public function testAcceptsUppercaseHex(): void
    {
        $key = EncryptionKey::fromHex(strtoupper(self::HEX_KEY));

        self::assertSame(hex2bin(self::HEX_KEY), $key->bytes());
    }

    public function testCreatesFromBinary(): void
    {
        $binary = str_repeat("\xAB", 32);

        self::assertSame($binary, EncryptionKey::fromBinary($binary)->bytes());
    }

    public function testFromInputDetectsHexAndRawForms(): void
    {
        self::assertSame(hex2bin(self::HEX_KEY), EncryptionKey::fromInput(self::HEX_KEY)->bytes());

        $raw = str_repeat("\x01", 32);
        self::assertSame($raw, EncryptionKey::fromInput($raw)->bytes());
    }

    public function testFromHexRejectsWrongLength(): void
    {
        $this->expectException(DecryptionFailedException::class);
        EncryptionKey::fromHex(substr(self::HEX_KEY, 0, 62));
    }

    public function testFromHexRejectsNonHexCharacters(): void
    {
        $this->expectException(DecryptionFailedException::class);
        EncryptionKey::fromHex(str_repeat('z', 64));
    }

    public function testFromBinaryRejectsWrongLength(): void
    {
        $this->expectException(DecryptionFailedException::class);
        EncryptionKey::fromBinary(str_repeat("\x01", 31));
    }

    public function testFromInputRejectsUnsupportedLengths(): void
    {
        $this->expectException(DecryptionFailedException::class);
        EncryptionKey::fromInput(str_repeat('a', 33));
    }

    public function testFromInputRejectsLongNonHexInput(): void
    {
        $this->expectException(DecryptionFailedException::class);
        EncryptionKey::fromInput(str_repeat('a', 63));
    }
}
