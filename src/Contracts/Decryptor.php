<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Contracts;

/**
 * Decrypts the encrypted app ticket payload with a 32-byte AES-256 key.
 *
 * Implementations must throw a domain exception on failure; returning partial
 * or unauthenticated data is not acceptable.
 */
interface Decryptor
{
    public function decrypt(string $ciphertext, string $key): string;
}
