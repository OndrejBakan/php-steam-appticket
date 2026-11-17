<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Crypto;

use OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException;

/**
 * Validated AES-256 encryption key.
 *
 * Accepts either the raw 32-byte key or its 64-character hexadecimal form.
 * The raw bytes never leak through debug output or exception messages.
 */
final readonly class EncryptionKey
{
    public const KEY_LENGTH = 32;
    public const HEX_LENGTH = 64;

    private function __construct(
        private string $bytes,
    ) {
    }

    public static function fromBinary(#[\SensitiveParameter] string $key): self
    {
        if (strlen($key) !== self::KEY_LENGTH) {
            throw new DecryptionFailedException(sprintf(
                'Encryption key must be exactly %d raw bytes, %d given.',
                self::KEY_LENGTH,
                strlen($key),
            ));
        }

        return new self($key);
    }

    public static function fromHex(#[\SensitiveParameter] string $hex): self
    {
        if (strlen($hex) !== self::HEX_LENGTH || !ctype_xdigit($hex)) {
            throw new DecryptionFailedException(
                'Encryption key must be a 64-character hexadecimal string.',
            );
        }

        $binary = hex2bin($hex);

        if ($binary === false) {
            throw new DecryptionFailedException('Encryption key contains invalid hexadecimal characters.');
        }

        return new self($binary);
    }

    public static function fromInput(#[\SensitiveParameter] string $input): self
    {
        return match (strlen($input)) {
            self::KEY_LENGTH => self::fromBinary($input),
            self::HEX_LENGTH => self::fromHex($input),
            default => throw new DecryptionFailedException(
                'Encryption key must be either 32 raw bytes or 64 hexadecimal characters.',
            ),
        };
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    /**
     * @return array{bytes: string}
     */
    public function __debugInfo(): array
    {
        return ['bytes' => '[redacted]'];
    }
}
