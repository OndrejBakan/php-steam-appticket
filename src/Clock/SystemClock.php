<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Clock;

use DateTimeImmutable;
use DateTimeZone;
use OndrejBakan\SteamAppTicket\Contracts\Clock;

/**
 * Default clock backed by the system time, normalised to UTC.
 */
final readonly class SystemClock implements Clock
{
    public function __construct(
        private DateTimeZone $timeZone = new DateTimeZone('UTC'),
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timeZone);
    }
}
