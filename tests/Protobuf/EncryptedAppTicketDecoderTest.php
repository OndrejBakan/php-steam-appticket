<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests\Protobuf;

use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use OndrejBakan\SteamAppTicket\Protobuf\EncryptedAppTicketDecoder;
use OndrejBakan\SteamAppTicket\Tests\Support\TicketFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EncryptedAppTicketDecoderTest extends TestCase
{
    public function testDecodesAllFields(): void
    {
        $payload = TicketFactory::varintField(1, 2)
            . TicketFactory::varintField(2, 0xDEADBEEF)
            . TicketFactory::varintField(3, 15)
            . TicketFactory::varintField(4, 512)
            . TicketFactory::bytesField(5, 'cipher-bytes');

        $envelope = (new EncryptedAppTicketDecoder())->decode($payload);

        self::assertSame(2, $envelope->ticketVersionNo);
        self::assertSame(0xDEADBEEF, $envelope->crcEncryptedTicket);
        self::assertSame(15, $envelope->cbEncryptedUserData);
        self::assertSame(512, $envelope->cbEncryptedAppOwnershipTicket);
        self::assertSame('cipher-bytes', $envelope->encryptedTicket);
    }

    public function testMissingOptionalsDefaultToZeroAndEmptyString(): void
    {
        $envelope = (new EncryptedAppTicketDecoder())->decode(TicketFactory::varintField(2, 99));

        self::assertSame(0, $envelope->ticketVersionNo);
        self::assertSame(99, $envelope->crcEncryptedTicket);
        self::assertSame(0, $envelope->cbEncryptedUserData);
        self::assertSame(0, $envelope->cbEncryptedAppOwnershipTicket);
        self::assertSame('', $envelope->encryptedTicket);
    }

    public function testEmptyPayloadYieldsEmptyEnvelope(): void
    {
        $envelope = (new EncryptedAppTicketDecoder())->decode('');

        self::assertSame(0, $envelope->ticketVersionNo);
        self::assertSame(0, $envelope->crcEncryptedTicket);
        self::assertSame(0, $envelope->cbEncryptedUserData);
        self::assertSame(0, $envelope->cbEncryptedAppOwnershipTicket);
        self::assertSame('', $envelope->encryptedTicket);
    }

    public function testFieldsMayAppearInAnyOrder(): void
    {
        $payload = TicketFactory::bytesField(5, 'ticket')
            . TicketFactory::varintField(3, 3)
            . TicketFactory::varintField(1, 1)
            . TicketFactory::varintField(4, 4)
            . TicketFactory::varintField(2, 2);

        $envelope = (new EncryptedAppTicketDecoder())->decode($payload);

        self::assertSame(1, $envelope->ticketVersionNo);
        self::assertSame(2, $envelope->crcEncryptedTicket);
        self::assertSame(3, $envelope->cbEncryptedUserData);
        self::assertSame(4, $envelope->cbEncryptedAppOwnershipTicket);
        self::assertSame('ticket', $envelope->encryptedTicket);
    }

    public function testDuplicateFieldsUseTheLastValue(): void
    {
        $payload = TicketFactory::varintField(3, 1)
            . TicketFactory::bytesField(5, 'first')
            . TicketFactory::varintField(3, 9)
            . TicketFactory::bytesField(5, 'second');

        $envelope = (new EncryptedAppTicketDecoder())->decode($payload);

        self::assertSame(9, $envelope->cbEncryptedUserData);
        self::assertSame('second', $envelope->encryptedTicket);
    }

    public function testSkipsUnknownFieldsOfSupportedWireTypes(): void
    {
        $payload = TicketFactory::varintField(6, 123)
            . TicketFactory::fixed64Field(7, 456)
            . TicketFactory::bytesField(8, 'skip me')
            . TicketFactory::fixed32Field(9, 789)
            . TicketFactory::varintField(1, 2)
            . TicketFactory::varintField(10, 0)
            . TicketFactory::bytesField(5, 'payload');

        $envelope = (new EncryptedAppTicketDecoder())->decode($payload);

        self::assertSame(2, $envelope->ticketVersionNo);
        self::assertSame('payload', $envelope->encryptedTicket);
    }

    public function testDecodesMaxUint32Varint(): void
    {
        $payload = TicketFactory::tag(1, 0) . "\xFF\xFF\xFF\xFF\x0F";

        self::assertSame(0xFFFFFFFF, (new EncryptedAppTicketDecoder())->decode($payload)->ticketVersionNo);
    }

    public function testSkipsUnknown64BitVarintField(): void
    {
        // Largest uint64 varint: nine 0xFF bytes followed by 0x01.
        $maxUint64 = str_repeat("\xFF", 9) . "\x01";
        $payload = TicketFactory::tag(6, 0) . $maxUint64
            . TicketFactory::varintField(1, 2)
            . TicketFactory::bytesField(5, 'payload');

        $envelope = (new EncryptedAppTicketDecoder())->decode($payload);

        self::assertSame(2, $envelope->ticketVersionNo);
        self::assertSame('payload', $envelope->encryptedTicket);
    }

    public function testThrowsOnUnknownVarintOverflowing64Bits(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode(
            TicketFactory::tag(6, 0) . str_repeat("\xFF", 9) . "\x02",
        );
    }

    public function testThrowsOnTruncatedUnknownVarint(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode(TicketFactory::tag(6, 0) . "\x80");
    }

    public function testThrowsOnTruncatedVarint(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode(TicketFactory::tag(1, 0) . "\x80");
    }

    public function testThrowsOnVarintLongerThanFiveBytes(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode(TicketFactory::tag(1, 0) . "\x80\x80\x80\x80\x80\x01");
    }

    public function testThrowsOnVarintOverflowingUint32(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode(TicketFactory::tag(1, 0) . "\xFF\xFF\xFF\xFF\x1F");
    }

    public function testThrowsOnTruncatedLengthDelimitedField(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode(
            TicketFactory::tag(5, 2) . TicketFactory::varint(5) . 'abc',
        );
    }

    public function testThrowsOnMissingPayloadTag(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode("\x80");
    }

    public function testThrowsOnFieldZero(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode("\x00");
    }

    public function testThrowsOnWrongWireTypeForVersionField(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode(
            TicketFactory::tag(1, 2) . TicketFactory::varint(1) . 'x',
        );
    }

    public function testThrowsOnWrongWireTypeForEncryptedTicketField(): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode(TicketFactory::tag(5, 0) . TicketFactory::varint(1));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidWireTypes(): iterable
    {
        yield 'start group' => [3];
        yield 'end group' => [4];
        yield 'reserved 6' => [6];
        yield 'reserved 7' => [7];
    }

    #[DataProvider('invalidWireTypes')]
    public function testThrowsOnUnknownWireTypes(int $wireType): void
    {
        $this->expectException(InvalidTicketException::class);
        (new EncryptedAppTicketDecoder())->decode(TicketFactory::tag(6, $wireType));
    }
}
