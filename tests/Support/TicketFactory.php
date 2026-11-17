<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Support;

use InvalidArgumentException;
use OndrejBakan\SteamAppTicket\Binary\Uint64;
use OndrejBakan\SteamAppTicket\Crypto\Crc32;
use RuntimeException;

/**
 * Builds structurally valid synthetic plain and encrypted app tickets.
 *
 * Nothing in here talks to Steam; the timestamps, identifiers and keys are
 * deterministic so parser behaviour can be asserted precisely.
 */
final class TicketFactory
{
    public const DEFAULT_KEY = '0123456789abcdef0123456789abcdef';

    public const DEFAULT_IV = 'fedcba9876543210';

    /**
     * @param list<int> $licenses
     * @param list<array{appId: int, licenses: list<int>}> $dlc
     */
    public static function ownershipBody(
        int $version = 3,
        int|string $steamId = 76561197960278073,
        int $appId = 730,
        string $externalIp = '203.0.113.7',
        string $internalIp = '10.0.0.5',
        int $flags = 0,
        int $generated = 1700000000,
        int $expires = 1800000000,
        array $licenses = [1, 2],
        array $dlc = [],
        string $reserved = "\x00\x00",
    ): string {
        $body = self::uint32($version)
            . self::uint64($steamId)
            . self::uint32($appId)
            . self::uint32(self::ipToUint32($externalIp))
            . self::uint32(self::ipToUint32($internalIp))
            . self::uint32($flags)
            . self::uint32($generated)
            . self::uint32($expires)
            . self::licenseList($licenses)
            . self::uint16(count($dlc));

        foreach ($dlc as $entry) {
            $body .= self::uint32($entry['appId']) . self::licenseList($entry['licenses']);
        }

        return $body . $reserved;
    }

    public static function ownershipTicket(string $body, ?string $signature = null): string
    {
        return self::uint32(4 + strlen($body)) . $body . ($signature ?? '');
    }

    public static function fullTicket(
        string $ownershipTicket,
        int|string $gcToken = 1234567890123456789,
        int $tokenGenerated = 1699990000,
        string $sessionExternalIp = '198.51.100.9',
        int $clientConnectionTime = 123456,
        int $clientConnectionCount = 3,
        int $sessionHeaderLength = 24,
    ): string {
        $authHeader = self::uint32(20)
            . self::uint64($gcToken)
            . self::uint64(0)
            . self::uint32($tokenGenerated)
            . self::uint32($sessionHeaderLength)
            . str_repeat("\x00", 8)
            . self::uint32(self::ipToUint32($sessionExternalIp))
            . str_repeat("\x00", 4)
            . self::uint32($clientConnectionTime)
            . self::uint32($clientConnectionCount);

        return $authHeader . self::uint32(strlen($ownershipTicket)) . $ownershipTicket;
    }

    public static function encrypt(string $plaintext, ?string $key = null, ?string $iv = null): string
    {
        $key ??= self::DEFAULT_KEY;
        $iv ??= self::DEFAULT_IV;

        if (strlen($key) !== 32) {
            throw new InvalidArgumentException('TicketFactory requires a 32-byte AES key.');
        }

        $encryptedIv = openssl_encrypt(
            $iv,
            'aes-256-ecb',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        );

        if ($encryptedIv === false) {
            throw new RuntimeException('Unable to encrypt the synthetic IV.');
        }

        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt the synthetic ticket.');
        }

        return $encryptedIv . $ciphertext;
    }

    public static function envelope(
        int $ticketVersionNo,
        int $crcEncryptedTicket,
        int $encryptedUserDataLength,
        int $encryptedAppOwnershipTicketLength,
        string $encryptedTicket,
    ): string {
        return self::varintField(1, $ticketVersionNo)
            . self::varintField(2, $crcEncryptedTicket)
            . self::varintField(3, $encryptedUserDataLength)
            . self::varintField(4, $encryptedAppOwnershipTicketLength)
            . self::bytesField(5, $encryptedTicket);
    }

    public static function envelopeFor(
        string $plaintext,
        int $ticketVersionNo,
        int $userDataLength,
        int $ownershipTicketLength,
        ?string $key = null,
        ?string $iv = null,
    ): string {
        return self::envelope(
            ticketVersionNo: $ticketVersionNo,
            crcEncryptedTicket: self::crc32($plaintext),
            encryptedUserDataLength: $userDataLength,
            encryptedAppOwnershipTicketLength: $ownershipTicketLength,
            encryptedTicket: self::encrypt($plaintext, $key, $iv),
        );
    }

    /**
     * @return array{ticket: string, plaintext: string, key: string, crc: int}
     */
    public static function encryptedTicket(
        string $userData,
        string $ownershipTicket,
        int $ticketVersionNo = 1,
        ?string $salt = null,
        ?string $trailer = null,
        string $extra = '',
        ?string $key = null,
        ?string $iv = null,
    ): array {
        $key ??= self::DEFAULT_KEY;
        $plaintext = $userData . $ownershipTicket . ($trailer ?? '');

        if ($salt !== null) {
            $plaintext .= $salt . self::saltedSha1($plaintext, $salt);
        }

        $plaintext .= $extra;

        return [
            'ticket' => self::envelopeFor(
                plaintext: $plaintext,
                ticketVersionNo: $ticketVersionNo,
                userDataLength: strlen($userData),
                ownershipTicketLength: strlen($ownershipTicket),
                key: $key,
                iv: $iv,
            ),
            'plaintext' => $plaintext,
            'key' => $key,
            'crc' => self::crc32($plaintext),
        ];
    }

    public static function crc32(string $data): int
    {
        return Crc32::of($data);
    }

    public static function saltedSha1(string $dataToHash, string $salt): string
    {
        return sha1($dataToHash . $salt, true);
    }

    public static function version2Trailer(int|string $unknown2, int|string $unknown3, int $unknown4): string
    {
        return self::uint64($unknown2) . self::uint64($unknown3) . self::uint32($unknown4);
    }

    public static function varint(int $value): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Varints cannot be negative.');
        }

        $encoded = '';

        do {
            $byte = $value & 0x7F;
            $value >>= 7;

            if ($value !== 0) {
                $byte |= 0x80;
            }

            $encoded .= chr($byte);
        } while ($value !== 0);

        return $encoded;
    }

    public static function tag(int $fieldNumber, int $wireType): string
    {
        return self::varint(($fieldNumber << 3) | $wireType);
    }

    public static function varintField(int $fieldNumber, int $value): string
    {
        return self::tag($fieldNumber, 0) . self::varint($value);
    }

    public static function bytesField(int $fieldNumber, string $bytes): string
    {
        return self::tag($fieldNumber, 2) . self::varint(strlen($bytes)) . $bytes;
    }

    public static function fixed64Field(int $fieldNumber, int|string $value): string
    {
        return self::tag($fieldNumber, 1) . self::uint64($value);
    }

    public static function fixed32Field(int $fieldNumber, int $value): string
    {
        return self::tag($fieldNumber, 5) . self::uint32($value);
    }

    public static function uint16(int $value): string
    {
        return pack('v', $value);
    }

    public static function uint32(int $value): string
    {
        return pack('V', $value);
    }

    public static function uint64(int|string $value): string
    {
        return Uint64::toLittleEndianBytes($value);
    }

    public static function ipToUint32(string $address): int
    {
        $value = ip2long($address);

        if ($value === false) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid IPv4 address.', $address));
        }

        return $value;
    }

    /**
     * @param list<int> $licenses
     */
    private static function licenseList(array $licenses): string
    {
        $encoded = self::uint16(count($licenses));

        foreach ($licenses as $license) {
            $encoded .= self::uint32($license);
        }

        return $encoded;
    }
}
