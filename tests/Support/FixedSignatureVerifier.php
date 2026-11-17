<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Support;

use OndrejBakan\SteamAppTicket\Contracts\SignatureVerifier;

/**
 * Signature verifier with a canned answer, so parser tests need no real RSA.
 */
final readonly class FixedSignatureVerifier implements SignatureVerifier
{
    public function __construct(
        private bool $result,
    ) {
    }

    public function verify(string $data, string $signature): bool
    {
        return $this->result;
    }
}
