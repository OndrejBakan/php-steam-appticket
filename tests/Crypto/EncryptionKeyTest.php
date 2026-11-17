<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Crypto;

use OndrejBakan\SteamAppTicket\Crypto\EncryptionKey;
use OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncryptionKeyTest extends TestCase
{
    private const HEX_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testCreatesFromHex(): void
    {
        $key = EncryptionKey::fromHex(self::HEX_KEY);
        $expected = hex2bin(self::HEX_KEY);

        if ($expected === false) {
            throw new RuntimeException('Test key is not valid hexadecimal.');
        }

        self::assertSame($expected, $key->bytes());
        self::assertSame(32, strlen($key->bytes()));
    }

    public function testAcceptsUppercaseHex(): void
    {
        $key = EncryptionKey::fromHex(strtoupper(self::HEX_KEY));
        $expected = hex2bin(self::HEX_KEY);

        if ($expected === false) {
            throw new RuntimeException('Test key is not valid hexadecimal.');
        }

        self::assertSame($expected, $key->bytes());
    }

    public function testCreatesFromBinary(): void
    {
        $binary = str_repeat("\xAB", 32);

        self::assertSame($binary, EncryptionKey::fromBinary($binary)->bytes());
    }

    public function testFromInputDetectsHexAndRawForms(): void
    {
        $fromHex = EncryptionKey::fromInput(self::HEX_KEY);
        $expected = hex2bin(self::HEX_KEY);

        if ($expected === false) {
            throw new RuntimeException('Test key is not valid hexadecimal.');
        }

        self::assertSame($expected, $fromHex->bytes());

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

    public function testDebugOutputRedactsKeyMaterial(): void
    {
        $raw = str_repeat("\xAB", 32);
        $key = EncryptionKey::fromBinary($raw);

        ob_start();
        var_dump($key);
        $output = ob_get_clean();

        if (!is_string($output)) {
            throw new RuntimeException('Unable to capture debug output.');
        }

        self::assertStringContainsString('[redacted]', $output);
        self::assertStringNotContainsString(bin2hex($raw), $output);
        self::assertStringNotContainsString($raw, $output);
    }
}
