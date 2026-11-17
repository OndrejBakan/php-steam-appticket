# Steam App Ticket

Modern, dependency-free PHP library for parsing and validating Steam app
tickets. It supports both plain ownership/session tickets (including Steam's
RSA-SHA1 signature) and encrypted app tickets (protobuf envelope, AES-256-CBC,
CRC32 and salted SHA-1).

## Features

- Parses ownership-only and full app tickets with the GC token and session
  header.
- Verifies plain ticket signatures against Steam's well-known RSA public key.
- Decrypts and validates encrypted app tickets without any protobuf, GMP or
  BCMath dependency.
- Handles the optional version 2 encrypted-ticket trailer and legacy trailing
  `uint32`.
- Represents SteamIDs as unsigned decimal strings, so the complete `uint64`
  range works without signed-integer surprises.
- Immutable `readonly` result models with `DateTimeImmutable` timestamps in UTC.
- Injectable clock, signature verifier, decryptor and envelope decoder.
- Exception-based API plus `try*` helpers that only swallow domain failures.
- Strict, bounds-checked binary parsing with no warnings on malformed input.
- Targets PHP 8.2+ and passes PHPStan at level max.

## Requirements

- PHP 8.2 or newer on a 64-bit build
- `ext-ctype`
- `ext-hash`
- `ext-openssl`

There are no runtime Composer dependencies.

## Installation

```bash
composer require ondrejbakan/steamappticket
```

## Parsing a plain app ticket

```php
<?php

declare(strict_types=1);

use OndrejBakan\SteamAppTicket\SteamAppTicket;

$binaryTicket = file_get_contents('appticket.bin');

if ($binaryTicket === false) {
    throw new RuntimeException('Unable to read the ticket file.');
}

$service = SteamAppTicket::create();
$ticket = $service->parseAppTicket($binaryTicket, allowInvalidSignature: true);

echo $ticket->appId, PHP_EOL;
echo $ticket->getSteamId64(), PHP_EOL;
echo $ticket->isValid ? 'valid' : 'invalid', PHP_EOL;
```

Unsigned plain tickets are structurally parseable but not authentic. By
default, `parseAppTicket()` throws `InvalidSignatureException` when a ticket is
unsigned or its signature cannot be verified. Pass
`allowInvalidSignature: true` only when you explicitly want to inspect such a
ticket.

For a strict parse:

```php
<?php

declare(strict_types=1);

use OndrejBakan\SteamAppTicket\Exception\SteamAppTicketException;
use OndrejBakan\SteamAppTicket\SteamAppTicket;

try {
    $ticket = SteamAppTicket::create()->parseAppTicket($binaryTicket);
} catch (SteamAppTicketException $exception) {
    // Invalid ticket, invalid signature or malformed input.
    echo $exception->getMessage(), PHP_EOL;
}
```

## Parsing an encrypted app ticket

Encrypted ticket keys must be exactly 32 raw bytes or 64 hexadecimal
characters.

```php
<?php

declare(strict_types=1);

use OndrejBakan\SteamAppTicket\SteamAppTicket;

$encryptedTicket = file_get_contents('encrypted_appticket.bin');

if ($encryptedTicket === false) {
    throw new RuntimeException('Unable to read the encrypted ticket file.');
}

$key = hex2bin('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

if ($key === false) {
    throw new RuntimeException('The encryption key is not valid hexadecimal.');
}

$decoded = SteamAppTicket::create()->parseEncryptedAppTicket($encryptedTicket, $key);

echo $decoded->appId, PHP_EOL;
echo $decoded->getSteamId64(), PHP_EOL;
echo $decoded->userData, PHP_EOL;

if ($decoded->trailer !== null) {
    echo $decoded->trailer->unknown2, PHP_EOL;
}
```

Hex-encoded input is supported directly:

```php
<?php

declare(strict_types=1);

use OndrejBakan\SteamAppTicket\SteamAppTicket;

$hexTicket = '...';
$hexKey = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

$decoded = SteamAppTicket::create()->parseHexEncryptedAppTicket($hexTicket, $hexKey);
```

## Non-throwing helpers

Every parse method has a `try*` variant that returns `null` for domain errors:

```php
<?php

declare(strict_types=1);

use OndrejBakan\SteamAppTicket\SteamAppTicket;

$service = SteamAppTicket::create();

$ticket = $service->tryParseAppTicket($binaryTicket, allowInvalidSignature: true);
$decoded = $service->tryParseEncryptedAppTicket($encryptedTicket, $key);

if ($ticket === null) {
    // Not a well-formed ticket, or the signature was rejected.
}

if ($decoded === null) {
    // Not a well-formed encrypted ticket, wrong key, or failed checksum.
}
```

The `try*` methods catch only `SteamAppTicketException`. Programming errors
such as `TypeError` still propagate.

## API reference

### `SteamAppTicket`

| Method | Returns |
| --- | --- |
| `create(?Clock $clock = null, ?SignatureVerifier $signatureVerifier = null, ?Decryptor $decryptor = null, ?EncryptedAppTicketDecoder $decoder = null)` | Configured facade |
| `parseAppTicket(string $ticket, bool $allowInvalidSignature = false)` | `AppTicket` |
| `tryParseAppTicket(string $ticket, bool $allowInvalidSignature = false)` | `?AppTicket` |
| `parseHexAppTicket(string $hexTicket, bool $allowInvalidSignature = false)` | `AppTicket` |
| `tryParseHexAppTicket(string $hexTicket, bool $allowInvalidSignature = false)` | `?AppTicket` |
| `parseEncryptedAppTicket(string $ticket, string $encryptionKey)` | `DecodedEncryptedAppTicket` |
| `tryParseEncryptedAppTicket(string $ticket, string $encryptionKey)` | `?DecodedEncryptedAppTicket` |
| `parseHexEncryptedAppTicket(string $hexTicket, string $encryptionKey)` | `DecodedEncryptedAppTicket` |
| `tryParseHexEncryptedAppTicket(string $hexTicket, string $encryptionKey)` | `?DecodedEncryptedAppTicket` |
| `parse(string $hexTicket, bool $allowInvalidSignature = false)` | `AppTicket|false` (deprecated 1.x compatibility) |

`SteamAppTicket::STEAM_PUBLIC_KEY` is also deprecated; use
`OndrejBakan\SteamAppTicket\Crypto\SteamPublicKey::PEM`.

### `AppTicket`

Read-only properties:

| Property | Type |
| --- | --- |
| `version` | `int` |
| `steamId` | `SteamId` |
| `appId` | `int` |
| `ownershipTicketExternalIp` | `string` |
| `ownershipTicketInternalIp` | `string` |
| `ownershipFlags` | `int` |
| `ownershipTicketGenerated` | `DateTimeImmutable` |
| `ownershipTicketExpires` | `DateTimeImmutable` |
| `licenses` | `list<int>` |
| `dlc` | `list<Dlc>` |
| `signature` | `?string` (raw bytes) |
| `isExpired` | `bool` |
| `hasValidSignature` | `bool` |
| `isValid` | `bool` |
| `authTicket` | `?string` (raw 52-byte header) |
| `gcToken` | `?string` (unsigned decimal) |
| `tokenGenerated` | `?DateTimeImmutable` |
| `sessionExternalIp` | `?string` |
| `clientConnectionTime` | `?int` |
| `clientConnectionCount` | `?int` |

Helpers: `hasAuthHeader()`, `hasSignature()`, `getSteamId64()`.

### `DecodedEncryptedAppTicket`

Read-only properties:

| Property | Type |
| --- | --- |
| `version` | `int` |
| `steamId` | `SteamId` |
| `appId` | `int` |
| `ownershipTicketExternalIp` | `string` |
| `ownershipTicketInternalIp` | `string` |
| `ownershipFlags` | `int` |
| `ownershipTicketGenerated` | `DateTimeImmutable` |
| `ownershipTicketExpires` | `DateTimeImmutable` |
| `licenses` | `list<int>` |
| `dlc` | `list<Dlc>` |
| `userData` | `string` (raw bytes) |
| `isExpired` | `bool` |
| `trailer` | `?EncryptedTicketTrailer` |
| `unknown1` | `?int` (legacy trailing `uint32`) |

Helpers: `hasTrailer()`, `getSteamId64()`.

### `SteamId`

- `SteamId::fromSteamId64(int|string $steamId64)`
- `SteamId::fromUint64Bytes(string $littleEndianBytes)`
- `getSteamId64(): string`, `toInt(): ?int`
- `getAccountId()`, `getInstance()`, `getType()`, `getUniverse()`
- `isIndividual()`, `equals()`, `__toString()`

`toInt()` returns `null` when the value is above `PHP_INT_MAX`; use the decimal
string form for the full `uint64` range.

### `Dlc`

- `appId: int`
- `licenses: list<int>`

### `EncryptedTicketTrailer`

- `unknown2: string` (unsigned decimal)
- `unknown3: string` (unsigned decimal)
- `unknown4: int`

## Exceptions

```text
RuntimeException
└── SteamAppTicketException
    ├── InvalidTicketException
    ├── InvalidSignatureException
    └── DecryptionFailedException
```

Catch `SteamAppTicketException` to handle malformed or untrusted ticket data
without swallowing unrelated programming errors.

## Security notes

- **Plain tickets:** The RSA-SHA1 signature is verified against Steam's
  public key. SHA-1 is required by Steam's legacy ticket format; it is not a
  general-purpose hashing choice. Unsigned plain tickets carry no authenticity
  proof and should not be trusted.
- **Encrypted tickets:** Authenticity comes from AES-256-CBC with the app's
  secret key, the CRC32 check and the salted SHA-1 checksum. A successful
  decrypt proves possession of the app key and integrity of the payload, not
  that the ticket is fresh.
- **Replay protection:** Validate the user data/nonce against your own
  server-side state and reject replayed tickets.
- **Expected identity:** After parsing, compare `appId`, `steamId` and any
  application-specific `userData` with the values you expect. Parsing alone
  does not grant ownership.
- **Expiry:** `isExpired` is advisory metadata derived from the ticket.
- **Secrets:** Never log encryption keys or authentication tickets. The
  `EncryptionKey` value object redacts its bytes from debug output, and key
  parameters are marked `#[\SensitiveParameter]` so PHP stack traces do not
  include them.

## Architecture

```text
src/
├── Binary/       Bounds-checked byte reader, uint64 and IP helpers
├── Clock/        System clock abstraction
├── Contracts/    Clock, Decryptor, SignatureVerifier, decoder interfaces
├── Crypto/       AES-256-CBC, PKCS#7, CRC32, RSA verifier, key value object
├── Exception/    Domain exception hierarchy
├── Model/        Immutable result and identifier models
├── Parser/       Plain and encrypted ticket parsers
├── Protobuf/     Dependency-free protobuf envelope decoder
└── SteamAppTicket.php
```

The facade is stateless. All collaborators can be replaced through
`SteamAppTicket::create()` for deterministic tests or custom crypto
implementations.

## Migrating from 1.x

| 1.x | 2.x |
| --- | --- |
| `new SteamAppTicket($hex)` | `SteamAppTicket::create()->parseHexAppTicket($hex, true)` |
| `SteamAppTicket::parse($hex)` returning object/false | `tryParseHexAppTicket()` returning `?AppTicket`, or deprecated `parse()` |
| Public mutable properties | Read-only model properties |
| `$ticket->appID` | `$ticket->appId` |
| `$ticket->steamID` | `$ticket->steamId` / `getSteamId64()` |
| `$ticket->dlcs` | `$ticket->dlc` |
| `DLC` | `Dlc` |
| Generic `Exception` | Domain exceptions under `SteamAppTicketException` |
| No encrypted ticket support | `parseEncryptedAppTicket()` and hex variant |

Timestamps are now `DateTimeImmutable` objects rather than integers or mutable
`DateTime` instances.

## Development

```bash
composer install
composer test      # PHPUnit
composer stan      # PHPStan level max
composer cs        # php-cs-fixer dry run
composer cs:fix    # apply style fixes
composer check     # cs + stan + test
```

The fixture-parity test suite compares parser output with the upstream Node
implementation using synthetic encrypted-ticket fixtures and its expected
results.

## License

MIT. See [LICENSE](LICENSE).
