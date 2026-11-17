<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Crypto;

use OndrejBakan\SteamAppTicket\Crypto\Aes256CbcDecryptor;
use OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException;
use OndrejBakan\SteamAppTicket\Tests\Support\TicketFactory;
use PHPUnit\Framework\TestCase;

final class Aes256CbcDecryptorTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef';

    private const string IV = 'fedcba9876543210';

    public function testRoundTripsPlaintext(): void
    {
        $plaintext = 'a synthetic encrypted ticket payload';
        $ciphertext = TicketFactory::encrypt($plaintext, self::KEY, self::IV);

        self::assertSame($plaintext, (new Aes256CbcDecryptor())->decrypt($ciphertext, self::KEY));
    }

    public function testRoundTripsEmptyPlaintext(): void
    {
        $ciphertext = TicketFactory::encrypt('', self::KEY, self::IV);

        self::assertSame(32, strlen($ciphertext));
        self::assertSame('', (new Aes256CbcDecryptor())->decrypt($ciphertext, self::KEY));
    }

    public function testRoundTripsBlockAlignedPlaintext(): void
    {
        $plaintext = str_repeat('A', 16);
        $ciphertext = TicketFactory::encrypt($plaintext, self::KEY, self::IV);

        self::assertSame(48, strlen($ciphertext));
        self::assertSame($plaintext, (new Aes256CbcDecryptor())->decrypt($ciphertext, self::KEY));
    }

    public function testRejectsWrongKeyLength(): void
    {
        $ciphertext = TicketFactory::encrypt('payload', self::KEY, self::IV);

        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt($ciphertext, str_repeat('k', 31));
    }

    public function testRejectsCiphertextWithoutPayloadBlocks(): void
    {
        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt(str_repeat('x', 16), self::KEY);
    }

    public function testRejectsCiphertextThatIsNotBlockAligned(): void
    {
        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt(str_repeat('x', 20), self::KEY);
    }

    public function testRejectsZeroPaddingLength(): void
    {
        $ciphertext = $this->encryptRaw(str_repeat('A', 16), self::KEY, self::IV);

        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt($ciphertext, self::KEY);
    }

    public function testRejectsPaddingLengthAboveBlockSize(): void
    {
        $ciphertext = $this->encryptRaw(str_repeat('A', 15) . "\x11", self::KEY, self::IV);

        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt($ciphertext, self::KEY);
    }

    public function testRejectsInconsistentPaddingBytes(): void
    {
        $ciphertext = $this->encryptRaw(str_repeat('A', 15) . "\x02", self::KEY, self::IV);

        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt($ciphertext, self::KEY);
    }

    private function encryptRaw(string $plaintext, string $key, string $iv): string
    {
        $encryptedIv = openssl_encrypt(
            $iv,
            'aes-256-ecb',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        );
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv,
        );

        self::assertIsString($encryptedIv);
        self::assertIsString($ciphertext);

        return $encryptedIv . $ciphertext;
    }
}
