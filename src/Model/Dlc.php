<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

/**
 * A piece of downloadable content included in an ownership ticket.
 */
final readonly class Dlc
{
    /**
     * @param list<int> $licenses
     */
    public function __construct(
        public int $appId,
        public array $licenses,
    ) {
    }
}
