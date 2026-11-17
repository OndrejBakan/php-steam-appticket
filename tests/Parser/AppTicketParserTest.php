<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Parser;

use OndrejBakan\SteamAppTicket\Exception\InvalidSignatureException;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use OndrejBakan\SteamAppTicket\Parser\AppTicketParser;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedClock;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedSignatureVerifier;
use OndrejBakan\SteamAppTicket\Tests\Support\RecordingSignatureVerifier;
use OndrejBakan\SteamAppTicket\Tests\Support\TicketFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AppTicketParserTest extends TestCase
{
    private const NOW = 1700000000;

    private const STEAM_ID = 76561197960278073;

    public function testParsesOwnershipOnlyUnsignedTicketWhenAllowed(): void
    {
        $body = TicketFactory::ownershipBody(
            version: 3,
            steamId: self::STEAM_ID,
            appId: 730,
            externalIp: '203.0.113.7',
            internalIp: '192.168.1.10',
            flags: 4,
            generated: 1690000000,
            expires: 1800000000,
            licenses: [10, 20, 30],
            dlc: [
                ['appId' => 731, 'licenses' => [40, 50]],
                ['appId' => 732, 'licenses' => []],
            ],
        );

        $ticket = $this->parser()->parse(TicketFactory::ownershipTicket($body), true);

        self::assertSame(3, $ticket->version);
        self::assertSame(self::STEAM_ID, $ticket->steamId->toInt());
        self::assertSame((string) self::STEAM_ID, $ticket->getSteamId64());
        self::assertSame(730, $ticket->appId);
        self::assertSame('203.0.113.7', $ticket->ownershipTicketExternalIp);
        self::assertSame('192.168.1.10', $ticket->ownershipTicketInternalIp);
        self::assertSame(4, $ticket->ownershipFlags);
        self::assertSame(1690000000, $ticket->ownershipTicketGenerated->getTimestamp());
        self::assertSame(1800000000, $ticket->ownershipTicketExpires->getTimestamp());
        self::assertSame('UTC', $ticket->ownershipTicketGenerated->getTimezone()->getName());
        self::assertSame([10, 20, 30], $ticket->licenses);

        $firstDlc = $ticket->dlc[0] ?? null;
        $secondDlc = $ticket->dlc[1] ?? null;

        if ($firstDlc === null || $secondDlc === null) {
            throw new RuntimeException('Expected two DLC entries.');
        }

        self::assertCount(2, $ticket->dlc);
        self::assertSame(731, $firstDlc->appId);
        self::assertSame([40, 50], $firstDlc->licenses);
        self::assertSame(732, $secondDlc->appId);
        self::assertSame([], $secondDlc->licenses);
        self::assertNull($ticket->signature);
        self::assertFalse($ticket->isExpired);
        self::assertFalse($ticket->hasValidSignature);
        self::assertTrue($ticket->isValid);
        self::assertNull($ticket->authTicket);
        self::assertNull($ticket->gcToken);
        self::assertNull($ticket->tokenGenerated);
        self::assertNull($ticket->sessionExternalIp);
        self::assertNull($ticket->clientConnectionTime);
        self::assertNull($ticket->clientConnectionCount);
        self::assertFalse($ticket->hasAuthHeader());
        self::assertFalse($ticket->hasSignature());
    }

    public function testParsesSignedTicketWithValidSignature(): void
    {
        $body = TicketFactory::ownershipBody(expires: 1800000000);
        $signature = str_repeat("\xAA", 128);

        $ticket = $this->parser(true)->parse(TicketFactory::ownershipTicket($body, $signature));

        self::assertSame($signature, $ticket->signature);
        self::assertTrue($ticket->hasValidSignature);
        self::assertFalse($ticket->isExpired);
        self::assertTrue($ticket->isValid);
        self::assertTrue($ticket->hasSignature());
    }

    public function testInvalidSignatureThrowsByDefault(): void
    {
        $ticket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(),
            str_repeat("\xBB", 128),
        );

        $this->expectException(InvalidSignatureException::class);
        $this->parser(false)->parse($ticket);
    }

    public function testInvalidSignatureCanBeAllowed(): void
    {
        $ticket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(),
            str_repeat("\xBB", 128),
        );

        $parsed = $this->parser(false)->parse($ticket, true);

        self::assertNotNull($parsed->signature);
        self::assertFalse($parsed->hasValidSignature);
        self::assertFalse($parsed->isValid);
    }

    public function testMissingSignatureThrowsByDefault(): void
    {
        $ticket = TicketFactory::ownershipTicket(TicketFactory::ownershipBody());

        $this->expectException(InvalidSignatureException::class);
        $this->parser()->parse($ticket);
    }

    public function testExpiredTicketIsMarkedInvalid(): void
    {
        $body = TicketFactory::ownershipBody(generated: 1500000000, expires: 1600000000);
        $ticket = TicketFactory::ownershipTicket($body, str_repeat("\xCC", 128));

        $parsed = $this->parser(true)->parse($ticket);

        self::assertTrue($parsed->isExpired);
        self::assertTrue($parsed->hasValidSignature);
        self::assertFalse($parsed->isValid);
    }

    public function testTicketExpiringExactlyNowIsNotExpired(): void
    {
        $body = TicketFactory::ownershipBody(generated: 1600000000, expires: self::NOW);
        $ticket = TicketFactory::ownershipTicket($body, str_repeat("\xCC", 128));

        $parsed = $this->parser(true)->parse($ticket);

        self::assertFalse($parsed->isExpired);
        self::assertTrue($parsed->isValid);
    }

    public function testExpiredUnsignedTicketAllowedButInvalid(): void
    {
        $body = TicketFactory::ownershipBody(expires: 1600000000);
        $ticket = TicketFactory::ownershipTicket($body);

        $parsed = $this->parser()->parse($ticket, true);

        self::assertTrue($parsed->isExpired);
        self::assertFalse($parsed->hasValidSignature);
        self::assertFalse($parsed->isValid);
    }

    public function testParsesFullTicketAuthenticationFields(): void
    {
        $ownershipTicket = TicketFactory::ownershipTicket(TicketFactory::ownershipBody());
        $ticket = TicketFactory::fullTicket(
            ownershipTicket: $ownershipTicket,
            gcToken: 1234567890123456789,
            tokenGenerated: 1699990000,
            sessionExternalIp: '198.51.100.9',
            clientConnectionTime: 987654,
            clientConnectionCount: 7,
        );

        $parsed = $this->parser()->parse($ticket, true);

        if ($parsed->authTicket === null) {
            throw new RuntimeException('Expected an authentication header.');
        }

        self::assertSame(52, strlen($parsed->authTicket));
        self::assertSame(substr($ticket, 0, 52), $parsed->authTicket);
        self::assertSame('1234567890123456789', $parsed->gcToken);
        self::assertSame(1699990000, $parsed->tokenGenerated?->getTimestamp());
        self::assertSame('198.51.100.9', $parsed->sessionExternalIp);
        self::assertSame(987654, $parsed->clientConnectionTime);
        self::assertSame(7, $parsed->clientConnectionCount);
        self::assertTrue($parsed->hasAuthHeader());
    }

    public function testFullTicketSupportsHighUint64GcToken(): void
    {
        $ticket = TicketFactory::fullTicket(
            TicketFactory::ownershipTicket(TicketFactory::ownershipBody()),
            gcToken: '18446744073709551615',
        );

        $parsed = $this->parser()->parse($ticket, true);

        self::assertSame('18446744073709551615', $parsed->gcToken);
    }

    public function testParsesFullTicketWithSignedOwnershipTicket(): void
    {
        $signature = str_repeat("\xDD", 128);
        $ownershipTicket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(),
            $signature,
        );
        $ticket = TicketFactory::fullTicket($ownershipTicket);

        $parsed = $this->parser(true)->parse($ticket);

        self::assertSame($signature, $parsed->signature);
        self::assertTrue($parsed->hasValidSignature);
        self::assertTrue($parsed->hasAuthHeader());
        self::assertTrue($parsed->isValid);
    }

    public function testFullTicketWithUnsignedOwnershipRequiresAllowFlag(): void
    {
        $ticket = TicketFactory::fullTicket(
            TicketFactory::ownershipTicket(TicketFactory::ownershipBody()),
        );

        $parsed = $this->parser()->parse($ticket, true);

        self::assertTrue($parsed->hasAuthHeader());
        self::assertNull($parsed->signature);
        self::assertFalse($parsed->hasValidSignature);
    }

    public function testSignatureVerifierReceivesExactOwnershipSectionAndSignature(): void
    {
        $body = TicketFactory::ownershipBody();
        $signature = str_repeat("\xEE", 128);
        $ownershipTicket = TicketFactory::ownershipTicket($body, $signature);
        $verifier = new RecordingSignatureVerifier(true);

        $parser = new AppTicketParser(new FixedClock(self::NOW), $verifier);
        $parsed = $parser->parse($ownershipTicket);

        self::assertTrue($parsed->hasValidSignature);
        self::assertSame(1, $verifier->calls);
        self::assertSame(TicketFactory::uint32(4 + strlen($body)) . $body, $verifier->data);
        self::assertSame($signature, $verifier->signature);
    }

    public function testUnsignedTicketDoesNotInvokeSignatureVerifier(): void
    {
        $verifier = new RecordingSignatureVerifier(true);
        $parser = new AppTicketParser(new FixedClock(self::NOW), $verifier);

        $parser->parse(TicketFactory::ownershipTicket(TicketFactory::ownershipBody()), true);

        self::assertSame(0, $verifier->calls);
    }

    public function testAcceptsFullUint64SteamId(): void
    {
        $ticket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(steamId: '18446744073709551615'),
        );

        $parsed = $this->parser()->parse($ticket, true);

        self::assertSame('18446744073709551615', $parsed->steamId->getSteamId64());
        self::assertNull($parsed->steamId->toInt());
        self::assertSame(4294967295, $parsed->steamId->getAccountId());
        self::assertSame(1048575, $parsed->steamId->getInstance());
        self::assertSame(15, $parsed->steamId->getType());
        self::assertSame(255, $parsed->steamId->getUniverse());
        self::assertFalse($parsed->steamId->isIndividual());
    }

    public function testParsesMaximumIpv4Addresses(): void
    {
        $ticket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(
                externalIp: '255.255.255.255',
                internalIp: '0.0.0.0',
            ),
        );

        $parsed = $this->parser()->parse($ticket, true);

        self::assertSame('255.255.255.255', $parsed->ownershipTicketExternalIp);
        self::assertSame('0.0.0.0', $parsed->ownershipTicketInternalIp);
    }

    public function testRejectsWrongSessionHeaderLength(): void
    {
        $ticket = TicketFactory::fullTicket(
            TicketFactory::ownershipTicket(TicketFactory::ownershipBody()),
            sessionHeaderLength: 23,
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, true);
    }

    public function testRejectsOuterSectionLengthMismatch(): void
    {
        $ticket = TicketFactory::fullTicket(
            TicketFactory::ownershipTicket(TicketFactory::ownershipBody()),
        ) . 'x';

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, true);
    }

    public function testRejectsInnerLengthMismatch(): void
    {
        $ticket = TicketFactory::ownershipTicket(TicketFactory::ownershipBody()) . 'x';

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, true);
    }

    public function testRejectsUnexpectedSignatureLength(): void
    {
        $body = TicketFactory::ownershipBody();
        $ticket = TicketFactory::uint32(4 + strlen($body) + 100)
            . $body
            . str_repeat("\x99", 100);

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, true);
    }

    public function testRejectsShortFullTicket(): void
    {
        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse(pack('V', 20) . 'short', true);
    }

    public function testRejectsEmptyTicket(): void
    {
        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse('', true);
    }

    public function testRejectsTruncatedOwnershipTicket(): void
    {
        $ticket = TicketFactory::ownershipTicket(TicketFactory::ownershipBody());

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse(substr($ticket, 0, 20), true);
    }

    public function testRejectsTruncatedLicenseList(): void
    {
        $body = substr(TicketFactory::ownershipBody(licenses: [1, 2, 3]), 0, -10);
        $ticket = TicketFactory::uint32(4 + strlen($body)) . $body;

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, true);
    }

    private function parser(bool $validSignature = true): AppTicketParser
    {
        return new AppTicketParser(
            new FixedClock(self::NOW),
            new FixedSignatureVerifier($validSignature),
        );
    }
}
