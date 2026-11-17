<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Support;

use DateTimeImmutable;
use OndrejBakan\SteamAppTicket\Contracts\Clock;

/**
 * Deterministic clock for expiry-related tests.
 */
final readonly class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(int|DateTimeImmutable $now)
    {
        $this->now = is_int($now) ? new DateTimeImmutable('@' . $now) : $now;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
