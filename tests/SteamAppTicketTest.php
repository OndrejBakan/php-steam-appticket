<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests;

use OndrejBakan\SteamAppTicket\Crypto\SteamPublicKey;
use OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException;
use OndrejBakan\SteamAppTicket\Exception\InvalidSignatureException;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use OndrejBakan\SteamAppTicket\Model\AppTicket;
use OndrejBakan\SteamAppTicket\SteamAppTicket;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedClock;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedSignatureVerifier;
use OndrejBakan\SteamAppTicket\Tests\Support\TicketFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use TypeError;

final class SteamAppTicketTest extends TestCase
{
    private const NOW = 1700000000;

    public function testCreateParsesUnsignedOwnershipTicketWhenAllowed(): void
    {
        $ticket = TicketFactory::ownershipTicket(TicketFactory::ownershipBody());
        $service = SteamAppTicket::create(clock: new FixedClock(self::NOW));

        $parsed = $service->parseAppTicket($ticket, true);

        self::assertSame(730, $parsed->appId);
        self::assertNull($parsed->authTicket);
    }

    public function testParseAppTicketThrowsForMissingSignature(): void
    {
        $ticket = TicketFactory::ownershipTicket(TicketFactory::ownershipBody());

        $this->expectException(InvalidSignatureException::class);
        SteamAppTicket::create()->parseAppTicket($ticket);
    }

    public function testParseAppTicketThrowsForMalformedTicket(): void
    {
        $this->expectException(InvalidTicketException::class);
        SteamAppTicket::create()->parseAppTicket('not a ticket');
    }

    public function testTryParseAppTicketReturnsNullForDomainFailures(): void
    {
        $service = SteamAppTicket::create();

        self::assertNull($service->tryParseAppTicket('not a ticket'));
        self::assertNull($service->tryParseAppTicket(
            TicketFactory::ownershipTicket(TicketFactory::ownershipBody()),
        ));
        self::assertNull($service->tryParseAppTicket(
            TicketFactory::ownershipTicket(
                TicketFactory::ownershipBody(),
                str_repeat("\xAB", 128),
            ),
        ));
    }

    public function testTryParseAppTicketReturnsTicketOnSuccess(): void
    {
        $ticket = TicketFactory::ownershipTicket(TicketFactory::ownershipBody());
        $service = SteamAppTicket::create(clock: new FixedClock(self::NOW));

        $parsed = $service->tryParseAppTicket($ticket, true);

        if ($parsed === null) {
            throw new RuntimeException('Expected a parsed ticket.');
        }

        self::assertSame(730, $parsed->appId);
    }

    public function testInjectedSignatureVerifierIsUsed(): void
    {
        $ticket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(),
            str_repeat("\xAB", 128),
        );
        $service = SteamAppTicket::create(
            clock: new FixedClock(self::NOW),
            signatureVerifier: new FixedSignatureVerifier(true),
        );

        $parsed = $service->parseAppTicket($ticket);

        self::assertTrue($parsed->hasValidSignature);
        self::assertTrue($parsed->isValid);
    }

    public function testInjectedClockControlsExpiry(): void
    {
        $ticket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(expires: 1600000000),
        );
        $service = SteamAppTicket::create(clock: new FixedClock(self::NOW));

        $parsed = $service->parseAppTicket($ticket, true);

        self::assertTrue($parsed->isExpired);
        self::assertFalse($parsed->isValid);
    }

    public function testParseEncryptedAppTicketWithRawAndHexKeys(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->encryptedOwnershipTicket());
        $service = SteamAppTicket::create(clock: new FixedClock(self::NOW));

        $fromRaw = $service->parseEncryptedAppTicket($built['ticket'], $built['key']);
        $fromHex = $service->parseEncryptedAppTicket($built['ticket'], bin2hex($built['key']));

        self::assertSame('nonce', $fromRaw->userData);
        self::assertSame('nonce', $fromHex->userData);
        self::assertSame(730, $fromRaw->appId);
        self::assertSame(730, $fromHex->appId);
    }

    public function testParseEncryptedAppTicketThrowsForMalformedEnvelope(): void
    {
        $this->expectException(InvalidTicketException::class);
        SteamAppTicket::create()->parseEncryptedAppTicket('garbage', TicketFactory::DEFAULT_KEY);
    }

    public function testParseEncryptedAppTicketThrowsForWrongKeyLength(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->encryptedOwnershipTicket());

        $this->expectException(DecryptionFailedException::class);
        SteamAppTicket::create()->parseEncryptedAppTicket($built['ticket'], 'too-short');
    }

    public function testTryParseEncryptedAppTicketReturnsNullForDomainFailures(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->encryptedOwnershipTicket());
        $service = SteamAppTicket::create();

        self::assertNull($service->tryParseEncryptedAppTicket('garbage', TicketFactory::DEFAULT_KEY));
        self::assertNull($service->tryParseEncryptedAppTicket($built['ticket'], 'too-short'));
        self::assertNull($service->tryParseEncryptedAppTicket($built['ticket'], str_repeat("\xAA", 32)));
    }

    public function testTryParseEncryptedAppTicketReturnsTicketOnSuccess(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->encryptedOwnershipTicket());

        $parsed = SteamAppTicket::create()->tryParseEncryptedAppTicket(
            $built['ticket'],
            bin2hex($built['key']),
        );

        if ($parsed === null) {
            throw new RuntimeException('Expected a parsed encrypted ticket.');
        }

        self::assertSame('nonce', $parsed->userData);
    }

    public function testParseHexAppTicketAcceptsHexInput(): void
    {
        $ticket = TicketFactory::ownershipTicket(TicketFactory::ownershipBody());
        $service = SteamAppTicket::create(clock: new FixedClock(self::NOW));

        $parsed = $service->parseHexAppTicket(bin2hex($ticket), true);

        self::assertSame(730, $parsed->appId);
    }

    public function testParseHexAppTicketRejectsInvalidHex(): void
    {
        $this->expectException(InvalidTicketException::class);
        SteamAppTicket::create()->parseHexAppTicket('zz', true);
    }

    public function testTryParseHexAppTicketReturnsNullForInvalidHex(): void
    {
        self::assertNull(SteamAppTicket::create()->tryParseHexAppTicket('abc', true));
    }

    public function testParseHexEncryptedAppTicketAcceptsHexInput(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->encryptedOwnershipTicket());
        $service = SteamAppTicket::create(clock: new FixedClock(self::NOW));

        $parsed = $service->parseHexEncryptedAppTicket(
            bin2hex($built['ticket']),
            bin2hex($built['key']),
        );

        self::assertSame('nonce', $parsed->userData);
    }

    public function testTryParseHexEncryptedAppTicketReturnsNullForInvalidHex(): void
    {
        self::assertNull(SteamAppTicket::create()->tryParseHexEncryptedAppTicket(
            'not-hex',
            TicketFactory::DEFAULT_KEY,
        ));
    }

    public function testDeprecatedStaticParseSupportsLegacyHexInput(): void
    {
        $ticket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(expires: 2100000000),
        );

        $parsed = SteamAppTicket::parse(bin2hex($ticket), true);

        if (!$parsed instanceof AppTicket) {
            throw new RuntimeException('Expected the deprecated parser to succeed.');
        }

        self::assertSame(730, $parsed->appId);
    }

    public function testDeprecatedStaticParseReturnsFalseForDomainFailures(): void
    {
        self::assertFalse(SteamAppTicket::parse('not-hex', true));
        self::assertFalse(SteamAppTicket::parse('', true));
        self::assertFalse(SteamAppTicket::parse(bin2hex('short'), true));
    }

    public function testDeprecatedSteamPublicKeyConstantMatchesCryptoKey(): void
    {
        self::assertSame(SteamPublicKey::PEM, SteamAppTicket::STEAM_PUBLIC_KEY);
    }

    public function testTryMethodsPropagateTypeErrors(): void
    {
        $service = SteamAppTicket::create();
        $method = new ReflectionMethod($service, 'tryParseAppTicket');

        $this->expectException(TypeError::class);
        $method->invoke($service, new \stdClass());
    }

    public function testEncryptedTicketExpiryIsExposed(): void
    {
        $ownershipTicket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(generated: 1500000000, expires: 1600000000),
        );
        $built = TicketFactory::encryptedTicket('nonce', $ownershipTicket);
        $service = SteamAppTicket::create(clock: new FixedClock(self::NOW));

        $parsed = $service->parseEncryptedAppTicket($built['ticket'], $built['key']);

        self::assertTrue($parsed->isExpired);
        self::assertSame(1600000000, $parsed->ownershipTicketExpires->getTimestamp());
    }

    private function encryptedOwnershipTicket(): string
    {
        return TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(
                steamId: 76561197960278073,
                appId: 730,
                generated: 1690000000,
                expires: 1800000000,
                licenses: [1],
            ),
        );
    }
}
