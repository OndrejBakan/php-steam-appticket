<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Support;

use OndrejBakan\SteamAppTicket\Contracts\SignatureVerifier;

/**
 * Signature verifier that records the exact bytes it was asked to verify.
 */
final class RecordingSignatureVerifier implements SignatureVerifier
{
    public ?string $data = null;

    public ?string $signature = null;

    public int $calls = 0;

    public function __construct(
        private readonly bool $result,
    ) {
    }

    public function verify(string $data, string $signature): bool
    {
        $this->calls++;
        $this->data = $data;
        $this->signature = $signature;

        return $this->result;
    }
}
