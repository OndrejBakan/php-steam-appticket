<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

use InvalidArgumentException;
use OndrejBakan\SteamAppTicket\Binary\Uint64;

/**
 * Immutable 64-bit Steam identifier.
 *
 * The value is stored as an unsigned decimal string so every possible uint64
 * survives without relying on signed PHP integer semantics. Account bits are
 * extracted from the original little-endian bytes.
 */
final readonly class SteamId
{
    private const INSTANCE_MASK = 0xFFFFF;
    private const TYPE_MASK = 0xF;
    private const UNIVERSE_MASK = 0xFF;
    private const INDIVIDUAL_TYPE = 1;
    private const DESKTOP_INSTANCE = 1;

    private function __construct(
        private string $steamId64,
        private string $littleEndianBytes,
    ) {
    }

    /**
     * Creates an identifier from the eight little-endian bytes found on the wire.
     */
    public static function fromUint64Bytes(string $littleEndianBytes): self
    {
        if (strlen($littleEndianBytes) !== Uint64::BYTE_LENGTH) {
            throw new InvalidArgumentException('A SteamID must be exactly 8 bytes long.');
        }

        return new self(
            steamId64: Uint64::toDecimalString($littleEndianBytes),
            littleEndianBytes: $littleEndianBytes,
        );
    }

    /**
     * Creates an identifier from its familiar decimal SteamID64 representation.
     *
     * @param int|string $steamId64 Decimal value; strings may cover the full uint64 range.
     */
    public static function fromSteamId64(int|string $steamId64): self
    {
        $decimal = Uint64::normaliseDecimal($steamId64);

        return new self(
            steamId64: $decimal,
            littleEndianBytes: Uint64::toLittleEndianBytes($decimal),
        );
    }

    /**
     * Unsigned decimal representation (0 to 18446744073709551615).
     */
    public function getSteamId64(): string
    {
        return $this->steamId64;
    }

    /**
     * Returns the identifier as an integer when it fits into PHP's signed
     * integer range, or null for values above PHP_INT_MAX.
     */
    public function toInt(): ?int
    {
        return Uint64::fitsInPhpInt($this->steamId64) ? (int) $this->steamId64 : null;
    }

    public function getAccountId(): int
    {
        return ord($this->littleEndianBytes[0])
            | (ord($this->littleEndianBytes[1]) << 8)
            | (ord($this->littleEndianBytes[2]) << 16)
            | (ord($this->littleEndianBytes[3]) << 24);
    }

    public function getInstance(): int
    {
        return (
            ord($this->littleEndianBytes[4])
            | (ord($this->littleEndianBytes[5]) << 8)
            | ((ord($this->littleEndianBytes[6]) & 0x0F) << 16)
        ) & self::INSTANCE_MASK;
    }

    public function getType(): int
    {
        return (ord($this->littleEndianBytes[6]) >> 4) & self::TYPE_MASK;
    }

    public function getUniverse(): int
    {
        return ord($this->littleEndianBytes[7]) & self::UNIVERSE_MASK;
    }

    /**
     * True for desktop individual accounts (type 1, instance 1).
     */
    public function isIndividual(): bool
    {
        return $this->getType() === self::INDIVIDUAL_TYPE
            && $this->getInstance() === self::DESKTOP_INSTANCE;
    }

    public function equals(self $other): bool
    {
        return $this->steamId64 === $other->steamId64;
    }

    public function __toString(): string
    {
        return $this->steamId64;
    }
}
