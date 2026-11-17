<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Protobuf;

use OndrejBakan\SteamAppTicket\Contracts\EncryptedAppTicketDecoder as EncryptedAppTicketDecoderContract;
use OndrejBakan\SteamAppTicket\Exception\InvalidTicketException;
use OndrejBakan\SteamAppTicket\Model\EncryptedAppTicketEnvelope;

/**
 * Decodes SteamEncryptedAppTicket without any protobuf dependency.
 *
 * Fields may appear in any order; unknown fields are skipped, duplicate fields
 * are last-wins and absent optional fields default to 0 (empty bytes).
 */
final class EncryptedAppTicketDecoder implements EncryptedAppTicketDecoderContract
{
    private const int WIRE_TYPE_VARINT = 0;
    private const int WIRE_TYPE_LENGTH_DELIMITED = 2;

    public function decode(string $payload): EncryptedAppTicketEnvelope
    {
        $ticketVersionNo = 0;
        $crcEncryptedTicket = 0;
        $cbEncryptedUserData = 0;
        $cbEncryptedAppOwnershipTicket = 0;
        $encryptedTicket = '';

        $reader = new WireReader($payload);

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            switch ($fieldNumber) {
                case 1:
                    $ticketVersionNo = $this->readUint32Field($reader, $fieldNumber, $wireType);
                    break;
                case 2:
                    $crcEncryptedTicket = $this->readUint32Field($reader, $fieldNumber, $wireType);
                    break;
                case 3:
                    $cbEncryptedUserData = $this->readUint32Field($reader, $fieldNumber, $wireType);
                    break;
                case 4:
                    $cbEncryptedAppOwnershipTicket = $this->readUint32Field($reader, $fieldNumber, $wireType);
                    break;
                case 5:
                    $encryptedTicket = $this->readBytesField($reader, $fieldNumber, $wireType);
                    break;
                default:
                    $reader->skip($wireType);
                    break;
            }
        }

        return new EncryptedAppTicketEnvelope(
            ticketVersionNo: $ticketVersionNo,
            crcEncryptedTicket: $crcEncryptedTicket,
            cbEncryptedUserData: $cbEncryptedUserData,
            cbEncryptedAppOwnershipTicket: $cbEncryptedAppOwnershipTicket,
            encryptedTicket: $encryptedTicket,
        );
    }

    private function readUint32Field(WireReader $reader, int $fieldNumber, int $wireType): int
    {
        if ($wireType !== self::WIRE_TYPE_VARINT) {
            throw new InvalidTicketException(sprintf(
                'Protobuf field %d expects wire type %d, got %d.',
                $fieldNumber,
                self::WIRE_TYPE_VARINT,
                $wireType,
            ));
        }

        return $reader->readVarint();
    }

    private function readBytesField(WireReader $reader, int $fieldNumber, int $wireType): string
    {
        if ($wireType !== self::WIRE_TYPE_LENGTH_DELIMITED) {
            throw new InvalidTicketException(sprintf(
                'Protobuf field %d expects wire type %d, got %d.',
                $fieldNumber,
                self::WIRE_TYPE_LENGTH_DELIMITED,
                $wireType,
            ));
        }

        return $reader->readLengthDelimited();
    }
}
