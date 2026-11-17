<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Parser;

use OndrejBakan\SteamAppTicket\Crypto\Aes256CbcDecryptor;
use OndrejBakan\SteamAppTicket\Crypto\Crc32;
use OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use OndrejBakan\SteamAppTicket\Exception\SteamAppTicketException;
use OndrejBakan\SteamAppTicket\Parser\AppTicketParser;
use OndrejBakan\SteamAppTicket\Parser\EncryptedAppTicketParser;
use OndrejBakan\SteamAppTicket\Protobuf\EncryptedAppTicketDecoder;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedClock;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedSignatureVerifier;
use OndrejBakan\SteamAppTicket\Tests\Support\TicketFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncryptedAppTicketParserTest extends TestCase
{
    private const NOW = 1700000000;

    private const STEAM_ID = 76561197960278073;

    public function testParsesVersion1TicketWithoutChecksum(): void
    {
        $ownershipTicket = $this->ownershipTicket();
        $built = TicketFactory::encryptedTicket('nonce-123', $ownershipTicket);

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        self::assertSame(3, $parsed->version);
        self::assertSame(self::STEAM_ID, $parsed->steamId->toInt());
        self::assertSame((string) self::STEAM_ID, $parsed->getSteamId64());
        self::assertSame(730, $parsed->appId);
        self::assertSame('203.0.113.7', $parsed->ownershipTicketExternalIp);
        self::assertSame('10.0.0.5', $parsed->ownershipTicketInternalIp);
        self::assertSame(0, $parsed->ownershipFlags);
        self::assertSame(1690000000, $parsed->ownershipTicketGenerated->getTimestamp());
        self::assertSame(1800000000, $parsed->ownershipTicketExpires->getTimestamp());
        self::assertFalse($parsed->isExpired);
        self::assertSame([1, 2], $parsed->licenses);

        $firstDlc = $parsed->dlc[0] ?? null;

        if ($firstDlc === null) {
            throw new RuntimeException('Expected a DLC entry.');
        }

        self::assertCount(1, $parsed->dlc);
        self::assertSame(731, $firstDlc->appId);
        self::assertSame([3], $firstDlc->licenses);
        self::assertSame('nonce-123', $parsed->userData);
        self::assertNull($parsed->trailer);
        self::assertNull($parsed->unknown1);
        self::assertFalse($parsed->hasTrailer());
    }

    public function testParsesVersion1TicketWithHashAndLegacyTrailingUint32(): void
    {
        $built = TicketFactory::encryptedTicket(
            userData: 'nonce',
            ownershipTicket: $this->ownershipTicket(),
            salt: '12345678',
            extra: TicketFactory::uint32(0xAABBCCDD),
        );

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        self::assertSame('nonce', $parsed->userData);
        self::assertSame(0xAABBCCDD, $parsed->unknown1);
        self::assertNull($parsed->trailer);
    }

    public function testRejectsChecksumRemainderOfExactlyTwentyEightBytes(): void
    {
        $built = TicketFactory::encryptedTicket(
            userData: 'nonce',
            ownershipTicket: $this->ownershipTicket(),
            salt: '12345678',
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($built['ticket'], $built['key']);
    }

    public function testParsesVersion2TrailerWithChecksum(): void
    {
        $trailer = TicketFactory::version2Trailer(111, 222, 333);
        $built = TicketFactory::encryptedTicket(
            userData: 'nonce',
            ownershipTicket: $this->ownershipTicket(),
            ticketVersionNo: 2,
            salt: '12345678',
            trailer: $trailer,
            extra: TicketFactory::uint32(42),
        );

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        $trailer = $parsed->trailer;

        if ($trailer === null) {
            throw new RuntimeException('Expected a version 2 trailer.');
        }

        self::assertTrue($parsed->hasTrailer());
        self::assertSame('111', $trailer->unknown2);
        self::assertSame('222', $trailer->unknown3);
        self::assertSame(333, $trailer->unknown4);
        self::assertSame(42, $parsed->unknown1);
        self::assertSame('nonce', $parsed->userData);
    }

    public function testVersion2TrailerAllowsHighUint64Values(): void
    {
        $trailer = str_repeat("\xFF", 8) . TicketFactory::uint64(42) . TicketFactory::uint32(7);
        $built = TicketFactory::encryptedTicket(
            userData: 'nonce',
            ownershipTicket: $this->ownershipTicket(),
            ticketVersionNo: 2,
            salt: '12345678',
            trailer: $trailer,
            extra: TicketFactory::uint32(0),
        );

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        $parsedTrailer = $parsed->trailer;

        if ($parsedTrailer === null) {
            throw new RuntimeException('Expected a version 2 trailer.');
        }

        self::assertSame('18446744073709551615', $parsedTrailer->unknown2);
        self::assertSame('42', $parsedTrailer->unknown3);
        self::assertSame(7, $parsedTrailer->unknown4);
    }

    public function testVersion2TrailerIsCoveredByChecksum(): void
    {
        $ownershipTicket = $this->ownershipTicket();
        $userData = 'nonce';
        $salt = '12345678';
        $trailer = TicketFactory::version2Trailer(1, 2, 3);
        $otherTrailer = TicketFactory::version2Trailer(1, 2, 4);
        $hash = sha1($userData . $ownershipTicket . $otherTrailer . $salt, true);
        $plaintext = $userData . $ownershipTicket . $trailer . $salt . $hash;
        $ticket = TicketFactory::envelopeFor(
            plaintext: $plaintext,
            ticketVersionNo: 2,
            userDataLength: strlen($userData),
            ownershipTicketLength: strlen($ownershipTicket),
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);
    }

    public function testParsesVersion2TicketWithoutChecksum(): void
    {
        $built = TicketFactory::encryptedTicket(
            userData: 'nonce',
            ownershipTicket: $this->ownershipTicket(),
            ticketVersionNo: 2,
            trailer: TicketFactory::version2Trailer(5, 6, 7),
        );

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        $trailer = $parsed->trailer;

        if ($trailer === null) {
            throw new RuntimeException('Expected a version 2 trailer.');
        }

        self::assertSame('5', $trailer->unknown2);
        self::assertSame('6', $trailer->unknown3);
        self::assertSame(7, $trailer->unknown4);
        self::assertNull($parsed->unknown1);
    }

    public function testAcceptsHexAndRawKeysInterchangeably(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->ownershipTicket());

        $fromRaw = $this->parser()->parse($built['ticket'], $built['key']);
        $fromHex = $this->parser()->parse($built['ticket'], bin2hex($built['key']));

        self::assertSame($fromRaw->userData, $fromHex->userData);
        self::assertSame($fromRaw->steamId->toInt(), $fromHex->steamId->toInt());
    }

    public function testRejectsWrongCrc(): void
    {
        $userData = 'nonce';
        $ownershipTicket = $this->ownershipTicket();
        $plaintext = $userData . $ownershipTicket;
        $ticket = TicketFactory::envelope(
            ticketVersionNo: 1,
            crcEncryptedTicket: Crc32::of($plaintext) ^ 1,
            encryptedUserDataLength: strlen($userData),
            encryptedAppOwnershipTicketLength: strlen($ownershipTicket),
            encryptedTicket: TicketFactory::encrypt($plaintext),
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);
    }

    public function testRejectsWrongChecksum(): void
    {
        $userData = 'nonce';
        $ownershipTicket = $this->ownershipTicket();
        $salt = '12345678';
        $hash = sha1($userData . $ownershipTicket . $salt, true);
        $hash[0] = chr(ord($hash[0]) ^ 0xFF);
        $plaintext = $userData . $ownershipTicket . $salt . $hash;
        $ticket = TicketFactory::envelopeFor(
            plaintext: $plaintext,
            ticketVersionNo: 1,
            userDataLength: strlen($userData),
            ownershipTicketLength: strlen($ownershipTicket),
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);
    }

    public function testRejectsTruncatedVersion2Trailer(): void
    {
        $userData = 'nonce';
        $ownershipTicket = $this->ownershipTicket();
        $plaintext = $userData . $ownershipTicket;
        $ticket = TicketFactory::envelopeFor(
            plaintext: $plaintext,
            ticketVersionNo: 2,
            userDataLength: strlen($userData),
            ownershipTicketLength: strlen($ownershipTicket),
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);
    }

    public function testRejectsUserDataLengthLargerThanPayload(): void
    {
        $userData = 'nonce';
        $ownershipTicket = $this->ownershipTicket();
        $plaintext = $userData . $ownershipTicket;
        $ticket = TicketFactory::envelopeFor(
            plaintext: $plaintext,
            ticketVersionNo: 1,
            userDataLength: strlen($plaintext) + 1,
            ownershipTicketLength: strlen($ownershipTicket),
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);
    }

    public function testRejectsOwnershipLengthBelowFour(): void
    {
        $userData = 'nonce';
        $plaintext = $userData . TicketFactory::uint32(0) . str_repeat("\x00", 16);
        $ticket = TicketFactory::envelopeFor(
            plaintext: $plaintext,
            ticketVersionNo: 1,
            userDataLength: strlen($userData),
            ownershipTicketLength: 4,
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);
    }

    public function testRejectsOwnershipTicketExtendingPastPayload(): void
    {
        $userData = 'nonce';
        $plaintext = $userData . TicketFactory::uint32(1000) . str_repeat("\x00", 16);
        $ticket = TicketFactory::envelopeFor(
            plaintext: $plaintext,
            ticketVersionNo: 1,
            userDataLength: strlen($userData),
            ownershipTicketLength: 1000,
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);
    }

    public function testRejectsMalformedEnvelope(): void
    {
        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse('garbage', TicketFactory::DEFAULT_KEY);
    }

    public function testWrongKeyIsADomainFailure(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->ownershipTicket());

        $this->expectException(SteamAppTicketException::class);
        $this->parser()->parse($built['ticket'], str_repeat("\xAA", 32));
    }

    public function testRejectsWrongKeyLength(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->ownershipTicket());

        $this->expectException(DecryptionFailedException::class);
        $this->parser()->parse($built['ticket'], str_repeat('k', 31));
    }

    public function testIgnoresUnknownProtobufFields(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->ownershipTicket());
        $ticket = TicketFactory::varintField(6, 1)
            . TicketFactory::fixed32Field(7, 2)
            . $built['ticket']
            . TicketFactory::bytesField(8, 'unknown');

        $parsed = $this->parser()->parse($ticket, $built['key']);

        self::assertSame('nonce', $parsed->userData);
    }

    public function testParsesEmptyUserData(): void
    {
        $built = TicketFactory::encryptedTicket('', $this->ownershipTicket());

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        self::assertSame('', $parsed->userData);
    }

    public function testAcceptsUnsignedOwnershipTicketInsideEncryptedTicket(): void
    {
        $built = TicketFactory::encryptedTicket('nonce', $this->ownershipTicket());

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        self::assertSame(self::STEAM_ID, $parsed->steamId->toInt());
    }

    public function testTrailerIsNullForUnknownTicketVersions(): void
    {
        $built = TicketFactory::encryptedTicket(
            userData: 'nonce',
            ownershipTicket: $this->ownershipTicket(),
            ticketVersionNo: 3,
        );

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        self::assertNull($parsed->trailer);
        self::assertSame('nonce', $parsed->userData);
    }

    public function testEnvelopeOwnershipLengthFieldIsInformationalOnly(): void
    {
        $userData = 'nonce';
        $ownershipTicket = $this->ownershipTicket();
        $plaintext = $userData . $ownershipTicket;
        $ticket = TicketFactory::envelope(
            ticketVersionNo: 1,
            crcEncryptedTicket: Crc32::of($plaintext),
            encryptedUserDataLength: strlen($userData),
            encryptedAppOwnershipTicketLength: 999,
            encryptedTicket: TicketFactory::encrypt($plaintext),
        );

        $parsed = $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);

        self::assertSame('nonce', $parsed->userData);
    }

    public function testIgnoresShortTrailingGarbageBelowChecksumSize(): void
    {
        $userData = 'nonce';
        $ownershipTicket = $this->ownershipTicket();
        $plaintext = $userData . $ownershipTicket . str_repeat('x', 27);
        $ticket = TicketFactory::envelopeFor(
            plaintext: $plaintext,
            ticketVersionNo: 1,
            userDataLength: strlen($userData),
            ownershipTicketLength: strlen($ownershipTicket),
        );

        $parsed = $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);

        self::assertSame('nonce', $parsed->userData);
        self::assertNull($parsed->unknown1);
    }

    public function testIgnoresBytesAfterUnknown1(): void
    {
        $userData = 'nonce';
        $ownershipTicket = $this->ownershipTicket();
        $salt = '12345678';
        $trailing = TicketFactory::uint32(5) . 'ignored';
        $plaintext = $userData . $ownershipTicket . $salt . sha1($userData . $ownershipTicket . $salt, true) . $trailing;
        $ticket = TicketFactory::envelopeFor(
            plaintext: $plaintext,
            ticketVersionNo: 1,
            userDataLength: strlen($userData),
            ownershipTicketLength: strlen($ownershipTicket),
        );

        $parsed = $this->parser()->parse($ticket, TicketFactory::DEFAULT_KEY);

        self::assertSame(5, $parsed->unknown1);
    }

    public function testRejectsSignedOwnershipSectionForNodeParity(): void
    {
        $signature = str_repeat("\xAA", 128);
        $ownershipTicket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(),
            $signature,
        );
        $built = TicketFactory::encryptedTicket(
            userData: 'signed-ownership',
            ownershipTicket: $ownershipTicket,
            salt: '12345678',
            extra: TicketFactory::uint32(0),
        );

        $this->expectException(InvalidTicketException::class);
        $this->parser()->parse($built['ticket'], $built['key']);
    }

    public function testAcceptsFullUint64SteamIdInsideEncryptedTicket(): void
    {
        $ownershipTicket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(steamId: '18446744073709551615'),
        );
        $built = TicketFactory::encryptedTicket('nonce', $ownershipTicket);

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        self::assertSame('18446744073709551615', $parsed->getSteamId64());
        self::assertNull($parsed->steamId->toInt());
    }

    public function testExpiredEncryptedTicketIsMarkedExpired(): void
    {
        $ownershipTicket = TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(generated: 1500000000, expires: 1600000000),
        );
        $built = TicketFactory::encryptedTicket('nonce', $ownershipTicket);

        $parsed = $this->parser()->parse($built['ticket'], $built['key']);

        self::assertTrue($parsed->isExpired);
        self::assertSame(1600000000, $parsed->ownershipTicketExpires->getTimestamp());
    }

    private function parser(): EncryptedAppTicketParser
    {
        return new EncryptedAppTicketParser(
            new EncryptedAppTicketDecoder(),
            new Aes256CbcDecryptor(),
            new AppTicketParser(
                new FixedClock(self::NOW),
                new FixedSignatureVerifier(true),
            ),
        );
    }

    private function ownershipTicket(): string
    {
        return TicketFactory::ownershipTicket(
            TicketFactory::ownershipBody(
                steamId: self::STEAM_ID,
                appId: 730,
                generated: 1690000000,
                expires: 1800000000,
                licenses: [1, 2],
                dlc: [['appId' => 731, 'licenses' => [3]]],
            ),
        );
    }
}
