<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Contracts;

use DateTimeImmutable;

/**
 * Supplies the current time so ticket expiry checks are deterministic in tests.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
