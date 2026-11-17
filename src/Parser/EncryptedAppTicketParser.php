<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Parser;

use OndrejBakan\SteamAppTicket\Binary\ByteReader;
use OndrejBakan\SteamAppTicket\Contracts\Decryptor;
use OndrejBakan\SteamAppTicket\Contracts\EncryptedAppTicketDecoder;
use OndrejBakan\SteamAppTicket\Crypto\Crc32;
use OndrejBakan\SteamAppTicket\Crypto\EncryptionKey;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use OndrejBakan\SteamAppTicket\Model\DecodedEncryptedAppTicket;
use OndrejBakan\SteamAppTicket\Model\EncryptedTicketTrailer;

/**
 * Decrypts and decodes Steam encrypted app tickets.
 *
 * Layout after AES-256-CBC decryption:
 *
 *     [user data][uint32 ownership length][ownership ticket][v2 trailer]
 *     [8-byte salt][20-byte SHA-1][optional legacy uint32]
 *
 * The hash covers everything up to (and including) the optional v2 trailer.
 */
final readonly class EncryptedAppTicketParser
{
    public const MIN_OWNERSHIP_TICKET_LENGTH = 4;
    public const VERSION_2_TRAILER_LENGTH = 20;
    public const HASH_SALT_LENGTH = 8;
    public const SHA1_LENGTH = 20;
    public const CHECKSUM_REMAINDER_LENGTH = self::HASH_SALT_LENGTH + self::SHA1_LENGTH;
    public const LEGACY_TRAILING_UINT32_LENGTH = 4;

    public function __construct(
        private EncryptedAppTicketDecoder $decoder,
        private Decryptor $decryptor,
        private AppTicketParser $appTicketParser,
    ) {
    }

    /**
     * @throws InvalidTicketException
     * @throws \OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException
     */
    public function parse(string $ticket, #[\SensitiveParameter] string $encryptionKey): DecodedEncryptedAppTicket
    {
        $envelope = $this->decoder->decode($ticket);
        $key = EncryptionKey::fromInput($encryptionKey);
        $decrypted = $this->decryptor->decrypt($envelope->encryptedTicket, $key);
        $decryptedLength = strlen($decrypted);

        if (Crc32::of($decrypted) !== $envelope->crcEncryptedTicket) {
            throw new InvalidTicketException('Encrypted ticket CRC32 does not match the decrypted payload.');
        }

        $userDataLength = $envelope->encryptedUserDataLength;

        if ($userDataLength > $decryptedLength - self::MIN_OWNERSHIP_TICKET_LENGTH) {
            throw new InvalidTicketException(
                'Encrypted user data length exceeds the decrypted ticket payload.',
            );
        }

        $reader = new ByteReader($decrypted);
        $reader->seek($userDataLength);
        $ownershipTicketLength = $reader->readUint32();

        if ($ownershipTicketLength < self::MIN_OWNERSHIP_TICKET_LENGTH) {
            throw new InvalidTicketException(
                'Encrypted ownership ticket length is smaller than its own length prefix.',
            );
        }

        if ($userDataLength + $ownershipTicketLength > $decryptedLength) {
            throw new InvalidTicketException(
                'Encrypted ownership ticket extends past the decrypted payload.',
            );
        }

        // The envelope's cb_encryptedappownershipticket field is informational:
        // the reference implementation trusts the uint32 prefix inside the
        // decrypted payload instead.
        $ownershipTicketData = substr($decrypted, $userDataLength, $ownershipTicketLength);
        $ownershipTicket = $this->appTicketParser->parse($ownershipTicketData, true);

        $trailer = null;
        $remainderOffset = 0;

        if ($envelope->ticketVersionNo === 2) {
            $trailerOffset = $userDataLength + $ownershipTicketLength;

            if ($trailerOffset + self::VERSION_2_TRAILER_LENGTH > $decryptedLength) {
                throw new InvalidTicketException('Version 2 encrypted ticket is missing its 20-byte trailer.');
            }

            $trailerReader = new ByteReader($decrypted);
            $trailerReader->seek($trailerOffset);

            $trailer = new EncryptedTicketTrailer(
                unknown2: $trailerReader->readUint64AsDecimalString(),
                unknown3: $trailerReader->readUint64AsDecimalString(),
                unknown4: $trailerReader->readUint32(),
            );
            $remainderOffset = self::VERSION_2_TRAILER_LENGTH;
        }

        $checksumStart = $userDataLength + $ownershipTicketLength + $remainderOffset;
        $remainder = substr($decrypted, $checksumStart);
        $unknown1 = null;

        if (strlen($remainder) >= self::CHECKSUM_REMAINDER_LENGTH) {
            $dataToHash = substr($decrypted, 0, $checksumStart);
            $salt = substr($remainder, 0, self::HASH_SALT_LENGTH);
            $expectedHash = substr($remainder, self::HASH_SALT_LENGTH, self::SHA1_LENGTH);

            if (!hash_equals(sha1($dataToHash . $salt, true), $expectedHash)) {
                throw new InvalidTicketException('Encrypted ticket salted SHA-1 checksum does not match.');
            }

            // The reference implementation reads a trailing uint32 after the
            // hash, so a truncated 28-31 byte remainder must be rejected.
            if (
                strlen($remainder)
                < self::CHECKSUM_REMAINDER_LENGTH + self::LEGACY_TRAILING_UINT32_LENGTH
            ) {
                throw new InvalidTicketException(
                    'Encrypted ticket checksum is not followed by its legacy trailing uint32.',
                );
            }

            $unpacked = unpack(
                'Vvalue',
                substr($remainder, self::CHECKSUM_REMAINDER_LENGTH, self::LEGACY_TRAILING_UINT32_LENGTH),
            );
            $value = $unpacked === false ? null : ($unpacked['value'] ?? null);

            if (!is_int($value)) {
                throw new InvalidTicketException('Unable to read the legacy trailing uint32.');
            }

            $unknown1 = $value;
        }

        return new DecodedEncryptedAppTicket(
            version: $ownershipTicket->version,
            steamId: $ownershipTicket->steamId,
            appId: $ownershipTicket->appId,
            ownershipTicketExternalIp: $ownershipTicket->ownershipTicketExternalIp,
            ownershipTicketInternalIp: $ownershipTicket->ownershipTicketInternalIp,
            ownershipFlags: $ownershipTicket->ownershipFlags,
            ownershipTicketGenerated: $ownershipTicket->ownershipTicketGenerated,
            ownershipTicketExpires: $ownershipTicket->ownershipTicketExpires,
            licenses: $ownershipTicket->licenses,
            dlc: $ownershipTicket->dlc,
            userData: substr($decrypted, 0, $userDataLength),
            isExpired: $ownershipTicket->isExpired,
            trailer: $trailer,
            unknown1: $unknown1,
        );
    }
}
