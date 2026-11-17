<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Crypto;

use OndrejBakan\SteamAppTicket\Crypto\OpenSslSignatureVerifier;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

final class OpenSslSignatureVerifierTest extends TestCase
{
    private static OpenSSLAsymmetricKey $privateKey;

    private static string $publicKeyPem;

    private static string|false $previousRandFile = false;

    public static function setUpBeforeClass(): void
    {
        self::$previousRandFile = getenv('RANDFILE');
        putenv('RANDFILE=' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opencode-steam-appticket.rnd');

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);

        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($details['key']);

        self::$privateKey = $key;
        self::$publicKeyPem = $details['key'];
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$previousRandFile === false) {
            putenv('RANDFILE');
        } else {
            putenv('RANDFILE=' . self::$previousRandFile);
        }
    }

    public function testVerifiesGeneratedSignature(): void
    {
        $signature = $this->sign('steam ticket payload');
        $verifier = new OpenSslSignatureVerifier(self::$publicKeyPem);

        self::assertTrue($verifier->verify('steam ticket payload', $signature));
    }

    public function testRejectsTamperedPayload(): void
    {
        $signature = $this->sign('steam ticket payload');
        $verifier = new OpenSslSignatureVerifier(self::$publicKeyPem);

        self::assertFalse($verifier->verify('steam ticket payload!', $signature));
    }

    public function testRejectsTamperedSignature(): void
    {
        $signature = $this->sign('steam ticket payload');
        $signature[0] = chr(ord($signature[0]) ^ 0xFF);
        $verifier = new OpenSslSignatureVerifier(self::$publicKeyPem);

        self::assertFalse($verifier->verify('steam ticket payload', $signature));
    }

    public function testRejectsEmptySignature(): void
    {
        $verifier = new OpenSslSignatureVerifier(self::$publicKeyPem);

        self::assertFalse($verifier->verify('steam ticket payload', ''));
    }

    public function testRejectsRandomSignature(): void
    {
        $verifier = new OpenSslSignatureVerifier(self::$publicKeyPem);

        self::assertFalse($verifier->verify('steam ticket payload', str_repeat("\x42", 256)));
    }

    public function testDefaultSteamPublicKeyIsParsable(): void
    {
        $verifier = new OpenSslSignatureVerifier();

        self::assertFalse($verifier->verify('steam ticket payload', str_repeat("\x00", 128)));
    }

    public function testInvalidPemReturnsFalseWithoutWarnings(): void
    {
        $verifier = new OpenSslSignatureVerifier('this is not a public key');

        self::assertFalse($verifier->verify('steam ticket payload', str_repeat("\x00", 128)));
    }

    public function testInvalidBase64PemReturnsFalseWithoutWarnings(): void
    {
        $verifier = new OpenSslSignatureVerifier(
            "-----BEGIN PUBLIC KEY-----\n!!!not-base64!!!\n-----END PUBLIC KEY-----",
        );

        self::assertFalse($verifier->verify('steam ticket payload', str_repeat("\x00", 128)));
    }

    private function sign(string $data): string
    {
        $signature = '';

        self::assertTrue(openssl_sign($data, $signature, self::$privateKey, OPENSSL_ALGO_SHA1));

        return $signature;
    }
}
