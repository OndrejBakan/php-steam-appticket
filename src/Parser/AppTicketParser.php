<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Parser;

use DateTimeImmutable;
use OndrejBakan\SteamAppTicket\Binary\ByteReader;
use OndrejBakan\SteamAppTicket\Contracts\Clock;
use OndrejBakan\SteamAppTicket\Contracts\SignatureVerifier;
use OndrejBakan\SteamAppTicket\Exception\InvalidSignatureException;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use OndrejBakan\SteamAppTicket\Model\AppTicket;
use OndrejBakan\SteamAppTicket\Model\Dlc;
use OndrejBakan\SteamAppTicket\Model\SteamId;

/**
 * Parses plain (unencrypted) Steam app/session tickets.
 *
 * Both ownership-only tickets and full tickets carrying the GC/session header
 * are supported. All length checks are offset based, matching the reference
 * JavaScript implementation.
 */
final readonly class AppTicketParser
{
    private const int AUTH_HEADER_LENGTH = 20;
    private const int AUTH_TICKET_LENGTH = 52;
    private const int SESSION_HEADER_LENGTH = 24;
    private const int SIGNATURE_LENGTH = 128;

    public function __construct(
        private Clock $clock,
        private SignatureVerifier $signatureVerifier,
    ) {
    }

    /**
     * @throws InvalidTicketException
     * @throws InvalidSignatureException
     */
    public function parse(string $ticket, bool $allowInvalidSignature = false): AppTicket
    {
        $limit = strlen($ticket);
        $reader = new ByteReader($ticket);

        $initialLength = $reader->readUint32();

        $authTicket = null;
        $gcToken = null;
        $tokenGenerated = null;
        $sessionExternalIp = null;
        $clientConnectionTime = null;
        $clientConnectionCount = null;

        if ($initialLength === self::AUTH_HEADER_LENGTH) {
            if ($limit < self::AUTH_TICKET_LENGTH) {
                throw new InvalidTicketException(
                    'Full app ticket is too short to contain its 52-byte authentication header.',
                );
            }

            $authTicket = substr($ticket, 0, self::AUTH_TICKET_LENGTH);
            $gcToken = $reader->readUint64AsDecimalString();
            $reader->skip(8); // The actual SteamID is part of the ownership ticket.
            $tokenGenerated = self::toDate($reader->readUint32());

            $sessionHeaderLength = $reader->readUint32();

            if ($sessionHeaderLength !== self::SESSION_HEADER_LENGTH) {
                throw new InvalidTicketException(sprintf(
                    'Invalid session header length %d; expected %d.',
                    $sessionHeaderLength,
                    self::SESSION_HEADER_LENGTH,
                ));
            }

            $reader->skip(8); // Unknown values.
            $sessionExternalIp = $reader->readIpv4();
            $reader->skip(4); // Filler.
            $clientConnectionTime = $reader->readUint32();
            $clientConnectionCount = $reader->readUint32();

            $ownershipSectionLength = $reader->readUint32();

            if ($ownershipSectionLength + $reader->position() !== $limit) {
                throw new InvalidTicketException(
                    'Ownership section length does not match the remaining ticket size.',
                );
            }
        } else {
            $reader->skip(-4);
        }

        $ownershipTicketOffset = $reader->position();
        $ownershipTicketLength = $reader->readUint32();

        if (
            $ownershipTicketOffset + $ownershipTicketLength !== $limit
            && $ownershipTicketOffset + $ownershipTicketLength + self::SIGNATURE_LENGTH !== $limit
        ) {
            throw new InvalidTicketException(
                'Ownership ticket length does not match the ticket size (with or without a 128-byte signature).',
            );
        }

        $version = $reader->readUint32();
        $steamId = new SteamId($reader->readUint64());
        $appId = $reader->readUint32();
        $externalIp = $reader->readIpv4();
        $internalIp = $reader->readIpv4();
        $ownershipFlags = $reader->readUint32();
        $ownershipTicketGenerated = self::toDate($reader->readUint32());
        $ownershipTicketExpires = self::toDate($reader->readUint32());

        $licenses = self::readLicenses($reader);
        $dlc = self::readDlc($reader);

        $reader->skip(2); // Reserved.

        $signature = match ($reader->remaining()) {
            0 => null,
            self::SIGNATURE_LENGTH => $reader->read(self::SIGNATURE_LENGTH),
            default => throw new InvalidTicketException(
                'Ownership ticket ends with neither zero nor exactly 128 signature bytes.',
            ),
        };

        $signedData = substr($ticket, $ownershipTicketOffset, $ownershipTicketLength);
        $isExpired = $ownershipTicketExpires < $this->clock->now();
        $hasValidSignature = $signature !== null && $this->signatureVerifier->verify($signedData, $signature);
        $isValid = !$isExpired && ($signature === null || $hasValidSignature);

        if (!$allowInvalidSignature && !$hasValidSignature) {
            throw new InvalidSignatureException(
                'App ticket is unsigned or its signature could not be verified.',
            );
        }

        return new AppTicket(
            version: $version,
            steamId: $steamId,
            appId: $appId,
            ownershipTicketExternalIp: $externalIp,
            ownershipTicketInternalIp: $internalIp,
            ownershipFlags: $ownershipFlags,
            ownershipTicketGenerated: $ownershipTicketGenerated,
            ownershipTicketExpires: $ownershipTicketExpires,
            licenses: $licenses,
            dlc: $dlc,
            signature: $signature,
            isExpired: $isExpired,
            hasValidSignature: $hasValidSignature,
            isValid: $isValid,
            authTicket: $authTicket,
            gcToken: $gcToken,
            tokenGenerated: $tokenGenerated,
            sessionExternalIp: $sessionExternalIp,
            clientConnectionTime: $clientConnectionTime,
            clientConnectionCount: $clientConnectionCount,
        );
    }

    /**
     * @return list<int>
     */
    private static function readLicenses(ByteReader $reader): array
    {
        $count = $reader->readUint16();
        $licenses = [];

        for ($index = 0; $index < $count; $index++) {
            $licenses[] = $reader->readUint32();
        }

        return $licenses;
    }

    /**
     * @return list<Dlc>
     */
    private static function readDlc(ByteReader $reader): array
    {
        $count = $reader->readUint16();
        $dlc = [];

        for ($index = 0; $index < $count; $index++) {
            $appId = $reader->readUint32();
            $dlc[] = new Dlc(
                appId: $appId,
                licenses: self::readLicenses($reader),
            );
        }

        return $dlc;
    }

    private static function toDate(int $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . $timestamp);
    }
}
