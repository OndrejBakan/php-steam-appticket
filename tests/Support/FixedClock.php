<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Support;

use DateTimeImmutable;
use OndrejBakan\SteamAppTicket\Contracts\Clock;

/**
 * Deterministic clock pinned to a single Unix timestamp.
 */
final readonly class FixedClock implements Clock
{
    public function __construct(
        private int $timestamp,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . $this->timestamp);
    }
}
