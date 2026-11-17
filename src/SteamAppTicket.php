<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket;

use OndrejBakan\SteamAppTicket\Binary\Hex;
use OndrejBakan\SteamAppTicket\Clock\SystemClock;
use OndrejBakan\SteamAppTicket\Contracts\Clock;
use OndrejBakan\SteamAppTicket\Contracts\Decryptor;
use OndrejBakan\SteamAppTicket\Contracts\EncryptedAppTicketDecoder as EncryptedAppTicketDecoderContract;
use OndrejBakan\SteamAppTicket\Contracts\SignatureVerifier;
use OndrejBakan\SteamAppTicket\Crypto\Aes256CbcDecryptor;
use OndrejBakan\SteamAppTicket\Crypto\OpenSslSignatureVerifier;
use OndrejBakan\SteamAppTicket\Crypto\SteamPublicKey;
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
    /**
     * @deprecated Use {@see SteamPublicKey::PEM} instead.
     */
    public const STEAM_PUBLIC_KEY = SteamPublicKey::PEM;

    public function __construct(
        private AppTicketParser $appTicketParser,
        private EncryptedAppTicketParser $encryptedAppTicketParser,
    ) {
    }

    public static function create(
        ?Clock $clock = null,
        ?SignatureVerifier $signatureVerifier = null,
        ?Decryptor $decryptor = null,
        ?EncryptedAppTicketDecoderContract $decoder = null,
    ): self {
        $clock ??= new SystemClock();
        $signatureVerifier ??= new OpenSslSignatureVerifier();
        $decryptor ??= new Aes256CbcDecryptor();
        $decoder ??= new EncryptedAppTicketDecoder();

        $appTicketParser = new AppTicketParser($clock, $signatureVerifier);

        return new self(
            appTicketParser: $appTicketParser,
            encryptedAppTicketParser: new EncryptedAppTicketParser(
                decoder: $decoder,
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
     * @throws \OndrejBakan\SteamAppTicket\Exception\InvalidSignatureException
     */
    public function parseHexAppTicket(string $hexTicket, bool $allowInvalidSignature = false): AppTicket
    {
        return $this->parseAppTicket(Hex::decode($hexTicket), $allowInvalidSignature);
    }

    /**
     * Convenience wrapper that only swallows domain exceptions.
     */
    public function tryParseHexAppTicket(string $hexTicket, bool $allowInvalidSignature = false): ?AppTicket
    {
        try {
            return $this->parseHexAppTicket($hexTicket, $allowInvalidSignature);
        } catch (SteamAppTicketException) {
            return null;
        }
    }

    /**
     * @throws \OndrejBakan\SteamAppTicket\Exception\InvalidTicketException
     * @throws \OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException
     */
    public function parseEncryptedAppTicket(
        string $ticket,
        #[\SensitiveParameter] string $encryptionKey,
    ): DecodedEncryptedAppTicket {
        return $this->encryptedAppTicketParser->parse($ticket, $encryptionKey);
    }

    /**
     * Convenience wrapper that only swallows domain exceptions.
     */
    public function tryParseEncryptedAppTicket(
        string $ticket,
        #[\SensitiveParameter] string $encryptionKey,
    ): ?DecodedEncryptedAppTicket {
        try {
            return $this->parseEncryptedAppTicket($ticket, $encryptionKey);
        } catch (SteamAppTicketException) {
            return null;
        }
    }

    /**
     * @throws \OndrejBakan\SteamAppTicket\Exception\InvalidTicketException
     * @throws \OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException
     */
    public function parseHexEncryptedAppTicket(
        string $hexTicket,
        #[\SensitiveParameter] string $encryptionKey,
    ): DecodedEncryptedAppTicket {
        return $this->parseEncryptedAppTicket(Hex::decode($hexTicket), $encryptionKey);
    }

    /**
     * Convenience wrapper that only swallows domain exceptions.
     */
    public function tryParseHexEncryptedAppTicket(
        string $hexTicket,
        #[\SensitiveParameter] string $encryptionKey,
    ): ?DecodedEncryptedAppTicket {
        try {
            return $this->parseHexEncryptedAppTicket($hexTicket, $encryptionKey);
        } catch (SteamAppTicketException) {
            return null;
        }
    }

    /**
     * Legacy 1.x entry point for hex-encoded plain tickets.
     *
     * @deprecated Use {@see self::parseHexAppTicket()} or
     *             {@see self::tryParseHexAppTicket()} instead.
     *
     * @return AppTicket|false False when the input is not a valid domain ticket.
     */
    public static function parse(string $hexTicket, bool $allowInvalidSignature = false): AppTicket|false
    {
        try {
            return self::create()->parseHexAppTicket($hexTicket, $allowInvalidSignature);
        } catch (SteamAppTicketException) {
            return false;
        }
    }
}
