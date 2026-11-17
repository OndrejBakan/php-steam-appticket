<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket;

use OndrejBakan\SteamAppTicket\Clock\SystemClock;
use OndrejBakan\SteamAppTicket\Contracts\Clock;
use OndrejBakan\SteamAppTicket\Contracts\Decryptor;
use OndrejBakan\SteamAppTicket\Contracts\SignatureVerifier;
use OndrejBakan\SteamAppTicket\Crypto\Aes256CbcDecryptor;
use OndrejBakan\SteamAppTicket\Crypto\OpenSslSignatureVerifier;
use OndrejBakan\SteamAppTicket\Exception\SteamAppTicketException;
use OndrejBakan\SteamAppTicket\Model\AppTicket;
use OndrejBakan\SteamAppTicket\Model\DecodedEncryptedAppTicket;
use OndrejBakan\SteamAppTicket\Parser\AppTicketParser;
use OndrejBakan\SteamAppTicket\Parser\EncryptedAppTicketParser;
use OndrejBakan\SteamAppTicket\Protobuf\EncryptedAppTicketDecoder;

/**
 * Small facade over the plain and encrypted ticket parsers.
 *
 * Every collaborator can be injected through {@see self::create()} for
 * deterministic tests and custom crypto stacks. The class itself is stateless
 * and holds no mutable error state.
 */
final readonly class SteamAppTicket
{
    public function __construct(
        private AppTicketParser $appTicketParser,
        private EncryptedAppTicketParser $encryptedAppTicketParser,
    ) {
    }

    public static function create(
        ?Clock $clock = null,
        ?SignatureVerifier $signatureVerifier = null,
        ?Decryptor $decryptor = null,
    ): self {
        $clock ??= new SystemClock();
        $signatureVerifier ??= new OpenSslSignatureVerifier();
        $decryptor ??= new Aes256CbcDecryptor();

        $appTicketParser = new AppTicketParser($clock, $signatureVerifier);

        return new self(
            appTicketParser: $appTicketParser,
            encryptedAppTicketParser: new EncryptedAppTicketParser(
                decoder: new EncryptedAppTicketDecoder(),
                decryptor: $decryptor,
                appTicketParser: $appTicketParser,
            ),
        );
    }

    /**
     * @throws \OndrejBakan\SteamAppTicket\Exception\InvalidTicketException
     * @throws \OndrejBakan\SteamAppTicket\Exception\InvalidSignatureException
     */
    public function parseAppTicket(string $ticket, bool $allowInvalidSignature = false): AppTicket
    {
        return $this->appTicketParser->parse($ticket, $allowInvalidSignature);
    }

    /**
     * Convenience wrapper that only swallows domain exceptions.
     */
    public function tryParseAppTicket(string $ticket, bool $allowInvalidSignature = false): ?AppTicket
    {
        try {
            return $this->parseAppTicket($ticket, $allowInvalidSignature);
        } catch (SteamAppTicketException) {
            return null;
        }
    }

    /**
     * @throws \OndrejBakan\SteamAppTicket\Exception\InvalidTicketException
     * @throws \OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException
     */
    public function parseEncryptedAppTicket(string $ticket, string $encryptionKey): DecodedEncryptedAppTicket
    {
        return $this->encryptedAppTicketParser->parse($ticket, $encryptionKey);
    }

    /**
     * Convenience wrapper that only swallows domain exceptions.
     */
    public function tryParseEncryptedAppTicket(string $ticket, string $encryptionKey): ?DecodedEncryptedAppTicket
    {
        try {
            return $this->parseEncryptedAppTicket($ticket, $encryptionKey);
        } catch (SteamAppTicketException) {
            return null;
        }
    }
}
