<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Crypto;

use OndrejBakan\SteamAppTicket\Crypto\Aes256CbcDecryptor;
use OndrejBakan\SteamAppTicket\Crypto\EncryptionKey;
use OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException;
use OndrejBakan\SteamAppTicket\Tests\Support\TicketFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Aes256CbcDecryptorTest extends TestCase
{
    private const KEY = '0123456789abcdef0123456789abcdef';

    private const IV = 'fedcba9876543210';

    public function testRoundTripsPlaintext(): void
    {
        $plaintext = 'a synthetic encrypted ticket payload';
        $ciphertext = TicketFactory::encrypt($plaintext, self::KEY, self::IV);

        self::assertSame(
            $plaintext,
            (new Aes256CbcDecryptor())->decrypt($ciphertext, $this->key()),
        );
    }

    public function testRoundTripsEmptyPlaintext(): void
    {
        $ciphertext = TicketFactory::encrypt('', self::KEY, self::IV);

        self::assertSame(32, strlen($ciphertext));
        self::assertSame('', (new Aes256CbcDecryptor())->decrypt($ciphertext, $this->key()));
    }

    public function testRoundTripsBlockAlignedPlaintext(): void
    {
        $plaintext = str_repeat('A', 16);
        $ciphertext = TicketFactory::encrypt($plaintext, self::KEY, self::IV);

        self::assertSame(48, strlen($ciphertext));
        self::assertSame($plaintext, (new Aes256CbcDecryptor())->decrypt($ciphertext, $this->key()));
    }

    public function testRejectsCiphertextWithoutPayloadBlocks(): void
    {
        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt(str_repeat('x', 16), $this->key());
    }

    public function testRejectsEmptyCiphertext(): void
    {
        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt('', $this->key());
    }

    public function testRejectsCiphertextThatIsNotBlockAligned(): void
    {
        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt(str_repeat('x', 20), $this->key());
    }

    public function testRejectsWrongKey(): void
    {
        $ciphertext = TicketFactory::encrypt('payload', self::KEY, self::IV);
        $wrongKey = EncryptionKey::fromBinary(str_repeat('k', 32));

        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt($ciphertext, $wrongKey);
    }

    public function testRejectsZeroPaddingLength(): void
    {
        $ciphertext = $this->encryptRaw(str_repeat('A', 16));

        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt($ciphertext, $this->key());
    }

    public function testRejectsPaddingLengthAboveBlockSize(): void
    {
        $ciphertext = $this->encryptRaw(str_repeat('A', 15) . "\x11");

        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt($ciphertext, $this->key());
    }

    public function testRejectsInconsistentPaddingBytes(): void
    {
        $ciphertext = $this->encryptRaw(str_repeat('A', 15) . "\x02");

        $this->expectException(DecryptionFailedException::class);
        (new Aes256CbcDecryptor())->decrypt($ciphertext, $this->key());
    }

    public function testAcceptsValidManualPadding(): void
    {
        $plaintext = str_repeat('A', 15);
        $ciphertext = $this->encryptRaw($plaintext . "\x01");

        self::assertSame(
            $plaintext,
            (new Aes256CbcDecryptor())->decrypt($ciphertext, $this->key()),
        );
    }

    private function key(): EncryptionKey
    {
        return EncryptionKey::fromBinary(self::KEY);
    }

    private function encryptRaw(string $plaintext): string
    {
        $encryptedIv = openssl_encrypt(
            self::IV,
            'aes-256-ecb',
            self::KEY,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        );
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            self::KEY,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            self::IV,
        );

        if (!is_string($encryptedIv) || !is_string($ciphertext)) {
            throw new RuntimeException('Unable to build raw AES test data.');
        }

        return $encryptedIv . $ciphertext;
    }
}
