<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Model;

/**
 * Downloadable content entry embedded in an app ticket.
 */
final readonly class Dlc
{
    /**
     * @param list<int> $licenses License identifiers that grant this DLC.
     */
    public function __construct(
        public int $appId,
        public array $licenses,
    ) {
    }
}
