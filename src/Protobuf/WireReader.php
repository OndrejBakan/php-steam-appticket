<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Protobuf;

use OndrejBakan\SteamAppTicket\Binary\ByteReader;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;

/**
 * Minimal, strictly bounds-checked protobuf wire-format reader.
 *
 * Only the primitives needed by SteamEncryptedAppTicket are supported:
 * varints, length-delimited payloads and skipping unknown fields with wire
 * types 0, 1, 2 and 5.
 */
final class WireReader
{
    public const MAX_VARINT_BYTES = 5;
    public const MAX_VARINT_VALUE = 4294967295;
    public const MAX_64BIT_VARINT_BYTES = 10;

    private readonly ByteReader $reader;

    public function __construct(string $payload)
    {
        $this->reader = new ByteReader($payload);
    }

    public function hasMore(): bool
    {
        return $this->reader->remaining() > 0;
    }

    /**
     * @return array{int, int} Field number and wire type.
     */
    public function readTag(): array
    {
        $tag = $this->readVarint();
        $fieldNumber = $tag >> 3;
        $wireType = $tag & 0x07;

        if ($fieldNumber < 1) {
            throw new InvalidTicketException('Protobuf field numbers must be at least 1.');
        }

        return [$fieldNumber, $wireType];
    }

    /**
     * Reads a uint32 varint, rejecting encodings longer than 32 bits.
     */
    public function readVarint(): int
    {
        $value = 0;

        for ($index = 0; $index < self::MAX_VARINT_BYTES; $index++) {
            $byte = $this->reader->readUint8();

            if ($index === self::MAX_VARINT_BYTES - 1 && ($byte & 0xF0) !== 0) {
                throw new InvalidTicketException('Protobuf varint exceeds the 32-bit range.');
            }

            $value |= ($byte & 0x7F) << ($index * 7);

            if (($byte & 0x80) === 0) {
                if ($value > self::MAX_VARINT_VALUE) {
                    throw new InvalidTicketException('Protobuf varint exceeds the 32-bit range.');
                }

                return $value;
            }
        }

        throw new InvalidTicketException('Protobuf varint is longer than 5 bytes.');
    }

    public function readLengthDelimited(): string
    {
        return $this->reader->read($this->readVarint());
    }

    public function skip(int $wireType): void
    {
        switch ($wireType) {
            case 0:
                $this->skipVarint();

                return;
            case 1:
                $this->reader->skip(8);

                return;
            case 2:
                $this->skipLengthDelimited();

                return;
            case 5:
                $this->reader->skip(4);

                return;
        }

        throw new InvalidTicketException(sprintf('Unsupported protobuf wire type %d.', $wireType));
    }

    private function skipLengthDelimited(): void
    {
        $this->reader->skip($this->readVarint());
    }

    /**
     * Consumes an unknown varint field. Unknown fields may legally be 64-bit
     * values, so the ten-byte protobuf limit applies instead of the five-byte
     * uint32 limit used for the known envelope fields.
     */
    private function skipVarint(): void
    {
        for ($index = 0; $index < self::MAX_64BIT_VARINT_BYTES; $index++) {
            $byte = $this->reader->readUint8();

            if ($index === self::MAX_64BIT_VARINT_BYTES - 1 && ($byte & 0xFE) !== 0) {
                throw new InvalidTicketException('Protobuf varint exceeds the 64-bit range.');
            }

            if (($byte & 0x80) === 0) {
                return;
            }
        }

        throw new InvalidTicketException('Protobuf varint is longer than 10 bytes.');
    }
}
