<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Crypto;

use OndrejBakan\SteamAppTicket\Contracts\SignatureVerifier;

/**
 * RSA-SHA1 verifier backed by ext-openssl, using Steam's public key by default.
 *
 * SHA-1 is mandated by the legacy Steam ticket protocol, not chosen here for
 * general-purpose hashing.
 */
final readonly class OpenSslSignatureVerifier implements SignatureVerifier
{
    public function __construct(
        private string $publicKeyPem = SteamPublicKey::PEM,
    ) {
    }

    public function verify(string $data, string $signature): bool
    {
        if ($signature === '' || !$this->isPlausiblePublicKey($this->publicKeyPem)) {
            return false;
        }

        $key = @openssl_pkey_get_public($this->publicKeyPem);

        if ($key === false) {
            return false;
        }

        return @openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA1) === 1;
    }

    /**
     * Cheap structural validation that keeps malformed PEM input from making
     * ext-openssl emit PHP warnings.
     */
    private function isPlausiblePublicKey(string $pem): bool
    {
        if (preg_match('/-----BEGIN PUBLIC KEY-----(.+?)-----END PUBLIC KEY-----/s', $pem, $matches) !== 1) {
            return false;
        }

        $base64 = str_replace(["\r", "\n", ' ', "\t"], '', $matches[1]);

        return $base64 !== '' && base64_decode($base64, true) !== false;
    }
}
