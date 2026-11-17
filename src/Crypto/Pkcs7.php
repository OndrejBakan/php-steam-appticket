<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Crypto;

use InvalidArgumentException;

/**
 * Strict PKCS#7 padding helper.
 *
 * Padding is always added, even when the input is already block-aligned, as
 * required by PKCS#7.
 */
final class Pkcs7
{
    public static function pad(string $data, int $blockSize): string
    {
        self::assertBlockSize($blockSize);

        $padLength = $blockSize - (strlen($data) % $blockSize);

        return $data . str_repeat(chr($padLength), $padLength);
    }

    public static function unpad(string $data, int $blockSize): string
    {
        self::assertBlockSize($blockSize);

        if ($data === '') {
            throw new InvalidArgumentException('Cannot remove PKCS#7 padding from an empty string.');
        }

        $length = strlen($data);

        if ($length % $blockSize !== 0) {
            throw new InvalidArgumentException('Padded data length must be a multiple of the block size.');
        }

        $padLength = ord($data[$length - 1]);
        $validLength = $padLength >= 1 && $padLength <= $blockSize;
        $paddingLength = $validLength ? $padLength : $blockSize;

        if ($length < $paddingLength) {
            throw new InvalidArgumentException('Invalid PKCS#7 padding length.');
        }

        $padding = substr($data, $length - $paddingLength);
        $invalid = $validLength ? 0 : 1;

        for ($index = 0; $index < $paddingLength; $index++) {
            $invalid |= ord($padding[$index]) ^ $padLength;
        }

        if ($invalid !== 0) {
            throw new InvalidArgumentException('Invalid PKCS#7 padding.');
        }

        return substr($data, 0, $length - $padLength);
    }

    private static function assertBlockSize(int $blockSize): void
    {
        if ($blockSize < 1) {
            throw new InvalidArgumentException('PKCS#7 block size must be at least 1 byte.');
        }
    }
}
