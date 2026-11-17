<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Crypto;

use InvalidArgumentException;
use OndrejBakan\SteamAppTicket\Contracts\Decryptor;
use OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException;

/**
 * Steam's AES-256-CBC scheme: the first 16 bytes of the payload are an
 * AES-256-ECB-encrypted IV, the remainder is CBC ciphertext with PKCS#7
 * padding.
 */
final class Aes256CbcDecryptor implements Decryptor
{
    public const CBC_CIPHER = 'aes-256-cbc';
    public const ECB_CIPHER = 'aes-256-ecb';
    public const BLOCK_SIZE = 16;
    private const OPENSSL_OPTIONS = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING;

    public function decrypt(string $ciphertext, EncryptionKey $key): string
    {
        $length = strlen($ciphertext);

        if ($length < self::BLOCK_SIZE * 2 || ($length - self::BLOCK_SIZE) % self::BLOCK_SIZE !== 0) {
            throw new DecryptionFailedException(
                'Ciphertext must contain a 16-byte encrypted IV followed by whole AES blocks.',
            );
        }

        $keyBytes = $key->bytes();

        $iv = openssl_decrypt(
            substr($ciphertext, 0, self::BLOCK_SIZE),
            self::ECB_CIPHER,
            $keyBytes,
            self::OPENSSL_OPTIONS,
        );

        if ($iv === false || strlen($iv) !== self::BLOCK_SIZE) {
            throw new DecryptionFailedException('Unable to decrypt the IV block.');
        }

        $plaintext = openssl_decrypt(
            substr($ciphertext, self::BLOCK_SIZE),
            self::CBC_CIPHER,
            $keyBytes,
            self::OPENSSL_OPTIONS,
            $iv,
        );

        if ($plaintext === false) {
            throw new DecryptionFailedException('OpenSSL failed to decrypt the AES-256-CBC payload.');
        }

        try {
            return Pkcs7::unpad($plaintext, self::BLOCK_SIZE);
        } catch (InvalidArgumentException $exception) {
            throw new DecryptionFailedException('Decrypted ticket contains invalid PKCS#7 padding.', 0, $exception);
        }
    }
}
