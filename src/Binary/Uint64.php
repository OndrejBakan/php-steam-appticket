<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Binary;

use InvalidArgumentException;

/**
 * Dependency-free conversion between unsigned 64-bit integers and their
 * little-endian byte representation.
 *
 * Values are handled as decimal strings because PHP integers are signed and
 * cannot represent the upper half of the uint64 range portably. No GMP or
 * BCMath extension is required.
 */
final class Uint64
{
    public const MAX_DECIMAL = '18446744073709551615';
    public const BYTE_LENGTH = 8;

    /**
     * Renders exactly eight little-endian bytes as an unsigned decimal string.
     */
    public static function toDecimalString(string $littleEndianBytes): string
    {
        if (strlen($littleEndianBytes) !== self::BYTE_LENGTH) {
            throw new InvalidArgumentException('An unsigned 64-bit value must be exactly 8 bytes long.');
        }

        $unpacked = unpack('C*', $littleEndianBytes);

        if ($unpacked === false) {
            throw new InvalidArgumentException('Unable to unpack the unsigned 64-bit value.');
        }

        $limbs = [];

        foreach ($unpacked as $limb) {
            if (!is_int($limb)) {
                throw new InvalidArgumentException('Unable to unpack the unsigned 64-bit value.');
            }

            $limbs[] = $limb;
        }

        $digits = '';

        do {
            $quotient = [0, 0, 0, 0, 0, 0, 0, 0];
            $carry = 0;
            $allZero = true;

            for ($index = 7; $index >= 0; $index--) {
                $current = ($carry << 8) | $limbs[$index];
                $quotient[$index] = intdiv($current, 10);
                $carry = $current % 10;

                if ($quotient[$index] !== 0) {
                    $allZero = false;
                }
            }

            $digits = (string) $carry . $digits;
            $limbs = $quotient;
        } while (!$allZero);

        $digits = ltrim($digits, '0');

        return $digits === '' ? '0' : $digits;
    }

    /**
     * Validates and normalises a decimal uint64 value.
     *
     * @param int|string $value Decimal value; strings may cover the full uint64 range.
     */
    public static function normaliseDecimal(int|string $value): string
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException('An unsigned 64-bit value cannot be negative.');
            }

            return (string) $value;
        }

        if ($value === '' || !ctype_digit($value)) {
            throw new InvalidArgumentException('An unsigned 64-bit value must contain decimal digits only.');
        }

        $normalised = ltrim($value, '0');

        if ($normalised === '') {
            return '0';
        }

        if (
            strlen($normalised) > strlen(self::MAX_DECIMAL)
            || (strlen($normalised) === strlen(self::MAX_DECIMAL) && strcmp($normalised, self::MAX_DECIMAL) > 0)
        ) {
            throw new InvalidArgumentException('An unsigned 64-bit value cannot exceed 18446744073709551615.');
        }

        return $normalised;
    }

    /**
     * Encodes a decimal uint64 value as exactly eight little-endian bytes.
     *
     * @param int|string $value Decimal value; strings may cover the full uint64 range.
     */
    public static function toLittleEndianBytes(int|string $value): string
    {
        $decimal = self::normaliseDecimal($value);
        $bytes = [0, 0, 0, 0, 0, 0, 0, 0];

        for ($index = 0; $index < self::BYTE_LENGTH; $index++) {
            [$decimal, $remainder] = self::divmodDecimal($decimal, 256);
            $bytes[$index] = $remainder;

            if ($decimal === '0') {
                break;
            }
        }

        return pack('C*', ...$bytes);
    }

    /**
     * True when a normalised decimal value fits into a non-negative PHP int.
     */
    public static function fitsInPhpInt(string $decimal): bool
    {
        $maxInt = (string) PHP_INT_MAX;

        if (strlen($decimal) !== strlen($maxInt)) {
            return strlen($decimal) < strlen($maxInt);
        }

        return strcmp($decimal, $maxInt) <= 0;
    }

    /**
     * @return array{string, int} Quotient and remainder.
     */
    private static function divmodDecimal(string $number, int $divisor): array
    {
        $quotient = '';
        $remainder = 0;
        $length = strlen($number);

        for ($index = 0; $index < $length; $index++) {
            $current = $remainder * 10 + (int) $number[$index];
            $digit = intdiv($current, $divisor);

            if ($quotient !== '' || $digit !== 0) {
                $quotient .= (string) $digit;
            }

            $remainder = $current % $divisor;
        }

        return [$quotient === '' ? '0' : $quotient, $remainder];
    }
}
