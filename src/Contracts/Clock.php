<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Contracts;

use DateTimeImmutable;

/**
 * Abstraction over the current time so expiry checks are deterministic in
 * tests and explicit in production code.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
