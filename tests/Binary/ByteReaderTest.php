<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Binary;

use OndrejBakan\SteamAppTicket\Binary\ByteReader;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use PHPUnit\Framework\TestCase;

final class ByteReaderTest extends TestCase
{
    public function testReadsLittleEndianIntegersAndAdvancesPosition(): void
    {
        $reader = new ByteReader(pack('v', 0x1234) . pack('V', 0x89ABCDEF) . "\x7F");

        self::assertSame(7, $reader->limit());
        self::assertSame(0, $reader->position());
        self::assertSame(7, $reader->remaining());
        self::assertSame(0x1234, $reader->readUint16());
        self::assertSame(0x89ABCDEF, $reader->readUint32());
        self::assertSame(0x7F, $reader->readUint8());
        self::assertSame(7, $reader->position());
        self::assertSame(0, $reader->remaining());
    }

    public function testReadsIpv4AddressesFromLittleEndianUint32(): void
    {
        $reader = new ByteReader(pack('V', 0x7F000001) . pack('V', 0xFFFFFFFF) . pack('V', 0));

        self::assertSame('127.0.0.1', $reader->readIpv4());
        self::assertSame('255.255.255.255', $reader->readIpv4());
        self::assertSame('0.0.0.0', $reader->readIpv4());
    }

    public function testReadsUint64AsDecimalStringIncludingHighBitValues(): void
    {
        $reader = new ByteReader(pack('P', 42) . str_repeat("\xFF", 8));

        self::assertSame('42', $reader->readUint64AsDecimalString());
        self::assertSame('18446744073709551615', $reader->readUint64AsDecimalString());
    }

    public function testReadsUint64AsIntWhenItFits(): void
    {
        $reader = new ByteReader(pack('P', 76561197960278073));

        self::assertSame(76561197960278073, $reader->readUint64());
    }

    public function testReadUint64RejectsHighBitValues(): void
    {
        $reader = new ByteReader(str_repeat("\xFF", 8));

        $this->expectException(InvalidTicketException::class);
        $reader->readUint64();
    }

    public function testReadingPastTheEndThrowsAndDoesNotAdvance(): void
    {
        $reader = new ByteReader('ab');

        try {
            $reader->read(3);
            self::fail('Expected a truncation exception.');
        } catch (InvalidTicketException) {
            self::assertSame(0, $reader->position());
            self::assertSame(2, $reader->remaining());
        }

        $this->expectException(InvalidTicketException::class);
        $reader->readUint32();
    }

    public function testNegativeReadLengthThrows(): void
    {
        $reader = new ByteReader('abc');

        $this->expectException(InvalidTicketException::class);
        $reader->read(-1);
    }

    public function testZeroLengthReadReturnsEmptyString(): void
    {
        $reader = new ByteReader('abc');

        self::assertSame('', $reader->read(0));
        self::assertSame(0, $reader->position());
    }

    public function testSeekAndSkipValidateBounds(): void
    {
        $reader = new ByteReader('abcd');

        $reader->seek(2);
        self::assertSame(2, $reader->position());
        $reader->skip(2);
        self::assertSame(4, $reader->position());
        self::assertSame(0, $reader->remaining());

        $reader->seek(0);
        $reader->skip(-0);
        self::assertSame(0, $reader->position());

        $this->expectException(InvalidTicketException::class);
        $reader->skip(-1);
    }

    public function testSeekBeyondLimitThrows(): void
    {
        $reader = new ByteReader('abcd');

        $this->expectException(InvalidTicketException::class);
        $reader->seek(5);
    }

    public function testSkipBeyondLimitThrows(): void
    {
        $reader = new ByteReader('abcd');

        $this->expectException(InvalidTicketException::class);
        $reader->skip(5);
    }
}
