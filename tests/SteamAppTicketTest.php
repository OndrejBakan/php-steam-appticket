<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests;

use OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException;
use OndrejBakan\SteamAppTicket\Exception\InvalidSignatureException;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use OndrejBakan\SteamAppTicket\SteamAppTicket;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedClock;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedSignatureVerifier;
use OndrejBakan\SteamAppTicket\Tests\Support\TicketFactory;
use PHPUnit\Framework\TestCase;
use TypeError;

final class SteamAppTicketTest extends TestCase
{
    private const int NOW = 1700000000;

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
    }

    public function testTryParseAppTicketReturnsTicketOnSuccess(): void
    {
        $ticket = TicketFactory::ownershipTicket(TicketFactory::ownershipBody());
        $service = SteamAppTicket::create(clock: new FixedClock(self::NOW));

        $parsed = $service->tryParseAppTicket($ticket, true);

        self::assertNotNull($parsed);
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

        self::assertNotNull($parsed);
        self::assertSame('nonce', $parsed->userData);
    }

    public function testTryParsePropagatesTypeErrors(): void
    {
        $service = SteamAppTicket::create();

        $this->expectException(TypeError::class);
        $service->tryParseAppTicket(123);
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
