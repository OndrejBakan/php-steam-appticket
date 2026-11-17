<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Contracts;

/**
 * Verifies the RSA-SHA1 signature of a plain Steam ownership ticket.
 *
 * Implementations must return false (never throw) for malformed signatures.
 */
interface SignatureVerifier
{
    public function verify(string $data, string $signature): bool;
}
