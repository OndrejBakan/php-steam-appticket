<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Binary;

use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;

/**
 * Strict, bounds-checked sequential reader over a raw byte string.
 *
 * The reader never uses streams and never advances its cursor on a failed
 * read, so a truncated ticket can only ever surface as an
 * InvalidTicketException.
 */
final class ByteReader
{
    private readonly int $limit;

    private int $position = 0;

    public function __construct(
        private readonly string $data,
    ) {
        $this->limit = strlen($data);
    }

    public function position(): int
    {
        return $this->position;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function remaining(): int
    {
        return $this->limit - $this->position;
    }

    public function seek(int $absoluteOffset): void
    {
        if ($absoluteOffset < 0 || $absoluteOffset > $this->limit) {
            throw new InvalidTicketException(sprintf(
                'Cannot seek to offset %d; valid range is 0..%d.',
                $absoluteOffset,
                $this->limit,
            ));
        }

        $this->position = $absoluteOffset;
    }

    public function skip(int $delta): void
    {
        $target = $this->position + $delta;

        if ($target < 0 || $target > $this->limit) {
            throw new InvalidTicketException(sprintf(
                'Cannot skip %d bytes from offset %d; valid range is 0..%d.',
                $delta,
                $this->position,
                $this->limit,
            ));
        }

        $this->position = $target;
    }

    public function read(int $length): string
    {
        if ($length < 0) {
            throw new InvalidTicketException(sprintf('Cannot read a negative number of bytes (%d).', $length));
        }

        if ($this->position + $length > $this->limit) {
            throw new InvalidTicketException(sprintf(
                'Unexpected end of ticket: wanted %d bytes at offset %d, only %d remaining.',
                $length,
                $this->position,
                $this->remaining(),
            ));
        }

        $bytes = substr($this->data, $this->position, $length);
        $this->position += $length;

        return $bytes;
    }

    public function readUint8(): int
    {
        return ord($this->read(1));
    }

    public function readUint16(): int
    {
        $unpacked = unpack('vvalue', $this->read(2));

        if ($unpacked === false) {
            throw new InvalidTicketException('Failed to unpack a little-endian uint16.');
        }

        return $unpacked['value'];
    }

    public function readUint32(): int
    {
        $unpacked = unpack('Vvalue', $this->read(4));

        if ($unpacked === false) {
            throw new InvalidTicketException('Failed to unpack a little-endian uint32.');
        }

        return $unpacked['value'];
    }

    /**
     * Reads a little-endian uint64 that must fit into a non-negative PHP int.
     *
     * Steam identifiers and most on-wire values stay below 2^63; values with
     * the high bit set are rejected rather than silently turned negative.
     */
    public function readUint64(): int
    {
        $unpacked = unpack('Pvalue', $this->read(8));

        if ($unpacked === false) {
            throw new InvalidTicketException('Failed to unpack a little-endian uint64.');
        }

        $value = $unpacked['value'];

        if ($value < 0) {
            throw new InvalidTicketException('uint64 value exceeds the supported non-negative integer range.');
        }

        return $value;
    }

    /**
     * Reads a full unsigned 64-bit value and renders it as a decimal string so
     * values above PHP_INT_MAX remain usable.
     */
    public function readUint64AsDecimalString(): string
    {
        $unpacked = unpack('Pvalue', $this->read(8));

        if ($unpacked === false) {
            throw new InvalidTicketException('Failed to unpack a little-endian uint64.');
        }

        return sprintf('%u', $unpacked['value']);
    }

    public function readIpv4(): string
    {
        return IpAddress::fromUint32($this->readUint32());
    }
}
