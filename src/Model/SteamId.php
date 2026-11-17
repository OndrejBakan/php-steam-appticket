<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

use InvalidArgumentException;

/**
 * Immutable representation of a 64-bit Steam identifier.
 *
 * Only the individual/universe bits are exposed because app tickets always
 * identify an individual user account.
 */
final readonly class SteamId
{
    private const int ACCOUNT_ID_MASK = 0xFFFFFFFF;
    private const int INSTANCE_MASK = 0xFFFFF;
    private const int TYPE_MASK = 0xF;
    private const int UNIVERSE_MASK = 0xFF;
    private const int TYPE_INDIVIDUAL = 1;
    private const int INSTANCE_DESKTOP = 1;

    public function __construct(
        private int $value,
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException('A SteamID cannot be negative.');
        }
    }

    /**
     * Parses a decimal SteamID64 string without losing precision on values
     * above the signed 64-bit boundary (they are rejected instead).
     */
    public static function fromDecimalString(string $value): self
    {
        if ($value === '' || !ctype_digit($value)) {
            throw new InvalidArgumentException(sprintf('SteamID "%s" must contain decimal digits only.', $value));
        }

        $normalised = ltrim($value, '0');

        if ($normalised === '') {
            $normalised = '0';
        }

        $max = (string) PHP_INT_MAX;

        if (strlen($normalised) > strlen($max) || (strlen($normalised) === strlen($max) && strcmp($normalised, $max) > 0)) {
            throw new InvalidArgumentException(sprintf('SteamID "%s" exceeds PHP_INT_MAX.', $value));
        }

        return new self((int) $normalised);
    }

    public function getSteamId64(): string
    {
        return (string) $this->value;
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function getAccountId(): int
    {
        return $this->value & self::ACCOUNT_ID_MASK;
    }

    public function getInstance(): int
    {
        return ($this->value >> 32) & self::INSTANCE_MASK;
    }

    public function getType(): int
    {
        return ($this->value >> 52) & self::TYPE_MASK;
    }

    public function getUniverse(): int
    {
        return ($this->value >> 56) & self::UNIVERSE_MASK;
    }

    /**
     * True for desktop individual accounts (type 1, instance 1), the only
     * identifiers that appear in app tickets.
     */
    public function isIndividual(): bool
    {
        return $this->getType() === self::TYPE_INDIVIDUAL
            && $this->getInstance() === self::INSTANCE_DESKTOP;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->getSteamId64();
    }
}
