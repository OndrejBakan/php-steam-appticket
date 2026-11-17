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
    private const string CBC_CIPHER = 'aes-256-cbc';
    private const string ECB_CIPHER = 'aes-256-ecb';
    private const int KEY_LENGTH = 32;
    private const int BLOCK_SIZE = 16;
    private const int OPENSSL_OPTIONS = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING;

    public function decrypt(string $ciphertext, string $key): string
    {
        if (strlen($key) !== self::KEY_LENGTH) {
            throw new DecryptionFailedException(sprintf(
                'AES-256 requires a %d-byte key, %d given.',
                self::KEY_LENGTH,
                strlen($key),
            ));
        }

        $length = strlen($ciphertext);

        if ($length <= self::BLOCK_SIZE || ($length - self::BLOCK_SIZE) % self::BLOCK_SIZE !== 0) {
            throw new DecryptionFailedException(
                'Ciphertext must contain a 16-byte encrypted IV followed by whole AES blocks.',
            );
        }

        $iv = openssl_decrypt(
            substr($ciphertext, 0, self::BLOCK_SIZE),
            self::ECB_CIPHER,
            $key,
            self::OPENSSL_OPTIONS,
        );

        if ($iv === false || strlen($iv) !== self::BLOCK_SIZE) {
            throw new DecryptionFailedException('Unable to decrypt the IV block.');
        }

        $plaintext = openssl_decrypt(
            substr($ciphertext, self::BLOCK_SIZE),
            self::CBC_CIPHER,
            $key,
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
