<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Contracts;

/**
 * Verifies a detached signature over the ownership section of a plain ticket.
 */
interface SignatureVerifier
{
    /**
     * @param string $data      Raw bytes covered by the signature.
     * @param string $signature Raw signature bytes.
     *
     * @return bool True only when the signature is valid.
     */
    public function verify(string $data, string $signature): bool;
}
