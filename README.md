# Steam App Ticket (PHP)

A modern, strictly typed PHP library for parsing and validating Steam app
tickets — both the **plain/unencrypted** tickets used by game clients and the
**encrypted** tickets requested from Steam by a game backend.

This package is a unified port of two reference implementations:

- [`DoctorMcKay/node-steam-appticket`](https://github.com/DoctorMcKay/node-steam-appticket) (v2.0.1)
- [`oddshotgames/steam-appticket-go`](https://github.com/oddshotgames/steam-appticket-go)

It keeps Node's battle-tested, offset-based parsing rules and combines them
with Go's single flat result model, optional version 2 trailer and
decimal-string handling for unsigned 64-bit values. The Go implementation's
incorrect ownership length checks were deliberately **not** ported.

---

## Requirements

- PHP **8.4** or newer
- `ext-openssl`
- `ext-ctype`

No protobuf extension, protobuf library or PECL package is needed.
The five-field `SteamEncryptedAppTicket` envelope is decoded by a small,
strictly bounds-checked wire-format reader shipped with this package.

## Installation

```bash
composer require ondrejbakan/steam-appticket
```

## Quick start

### Encrypted app ticket (recommended for backends)

The client obtains an encrypted ticket from Steam (usually via the Steamworks
API) and sends it to your backend, often base64-encoded. Your backend knows the
app's 32-byte encryption key from the Steamworks partner site.

```php
use OndrejBakan\SteamAppTicket\SteamAppTicket;

$service = SteamAppTicket::create();

$encodedTicket = $_POST['ticket'];
$ticket = base64_decode($encodedTicket, true);

if ($ticket === false) {
    // Reject malformed base64 before parsing.
}

// The key may be 32 raw bytes or 64 hexadecimal characters.
$key = getenv('STEAM_APP_TICKET_KEY');

$parsed = $service->tryParseEncryptedAppTicket($ticket, $key);

if ($parsed === null) {
    // Malformed envelope, wrong key, failed CRC32 or failed salted SHA-1.
}

// Always validate the contents for your own application:
if ($parsed->appId !== 730 || $parsed->steamId->getSteamId64() !== $expectedSteamId) {
    // Reject.
}

if ($parsed->userData !== $expectedNonce) {
    // Reject replayed tickets.
}
```

### Plain (unencrypted) app ticket

Plain tickets carry an RSA-SHA1 signature created by Steam. The signature is
verified automatically against Steam's well-known public key.

```php
use OndrejBakan\SteamAppTicket\SteamAppTicket;

$service = SteamAppTicket::create();

$parsed = $service->parseAppTicket($ticket);

if (!$parsed->isValid) {
    // Expired, unsigned or invalid signature (the last two normally throw).
}

printf("Ticket owner: %s\n", $parsed->steamId->getSteamId64());
printf("AppID: %d\n", $parsed->appId);
printf("Expires: %s\n", $parsed->ownershipTicketExpires->format(DATE_ATOM));
```

Some cheap client-generated tickets are unsigned. If you intentionally want to
inspect them, pass `allowInvalidSignature: true`:

```php
$parsed = $service->parseAppTicket($ticket, allowInvalidSignature: true);

if ($parsed->signature === null) {
    // Unsigned ownership-only ticket.
}
```

> Plain tickets must still be verified with Steam's
> [`ISteamUserAuth/AuthenticateUserTicket`](https://partner.steamgames.com/doc/webapi/ISteamUserAuth)
> endpoint before you trust them for authentication. The local signature only
> proves that Steam issued the ownership data.

## API

### `SteamAppTicket`

```php
final class SteamAppTicket
{
    public static function create(
        ?Contracts\Clock $clock = null,
        ?Contracts\SignatureVerifier $signatureVerifier = null,
        ?Contracts\Decryptor $decryptor = null,
    ): self;

    public function parseAppTicket(string $ticket, bool $allowInvalidSignature = false): Model\AppTicket;

    public function tryParseAppTicket(string $ticket, bool $allowInvalidSignature = false): ?Model\AppTicket;

    public function parseEncryptedAppTicket(string $ticket, string $encryptionKey): Model\DecodedEncryptedAppTicket;

    public function tryParseEncryptedAppTicket(string $ticket, string $encryptionKey): ?Model\DecodedEncryptedAppTicket;
}
```

The constructor accepts pre-built `Parser\AppTicketParser` and
`Parser\EncryptedAppTicketParser` instances if you wire the object graph
yourself. `create()` wires production defaults:

| Collaborator | Default |
| --- | --- |
| Clock | `Clock\SystemClock` (UTC) |
| Signature verifier | `Crypto\OpenSslSignatureVerifier` (Steam public key, RSA-SHA1) |
| Decryptor | `Crypto\Aes256CbcDecryptor` (AES-256-CBC, decrypted IV) |
| Envelope decoder | `Protobuf\EncryptedAppTicketDecoder` |

### `parse*` versus `try*`

| Method | On malformed data | On invalid signature | On decryption failure |
| --- | --- | --- | --- |
| `parseAppTicket()` | throws `InvalidTicketException` | throws `InvalidSignatureException` | — |
| `tryParseAppTicket()` | `null` | `null` | — |
| `parseEncryptedAppTicket()` | throws `InvalidTicketException` | — | throws `DecryptionFailedException` |
| `tryParseEncryptedAppTicket()` | `null` | — | `null` |

`try*` catches **only** `Exception\SteamAppTicketException` (the domain base
class). Programming errors such as `TypeError` or other `Error` instances are
allowed to propagate — they indicate a bug in the caller, not bad input.

### Exceptions

```
RuntimeException
└── SteamAppTicketException           domain base class
    ├── InvalidTicketException        malformed structure / failed integrity check
    ├── InvalidSignatureException     missing or unverifiable RSA signature
    └── DecryptionFailedException     bad key, ciphertext or PKCS#7 padding
```

The library never stores mutable error state (`lastError()` and friends do not
exist). Every failure is either an exception or `null` from a `try*` method.

## Result models

All result models are `final readonly` classes.

### `Model\AppTicket` (plain tickets)

| Property | Type | Notes |
| --- | --- | --- |
| `version` | `int` | Ownership ticket version |
| `steamId` | `SteamId` | Ticket owner |
| `appId` | `int` | App the ticket authenticates |
| `ownershipTicketExternalIp` | `string` | Dotted-quad, authenticated by Steam |
| `ownershipTicketInternalIp` | `string` | Dotted-quad, may be spoofed |
| `ownershipFlags` | `int` | Steam ownership flags |
| `ownershipTicketGenerated` | `DateTimeImmutable` | UTC |
| `ownershipTicketExpires` | `DateTimeImmutable` | UTC |
| `licenses` | `list<int>` | Package IDs granting the app |
| `dlc` | `list<Dlc>` | `Dlc` has `int $appId` and `list<int> $licenses` |
| `signature` | `?string` | Raw 128-byte RSA signature, or `null` |
| `isExpired` | `bool` | `expires < clock->now()` |
| `hasValidSignature` | `bool` | Signature present and verified |
| `isValid` | `bool` | Not expired and signature valid (or absent) |
| `authTicket` | `?string` | First 52 bytes; full tickets only |
| `gcToken` | `?string` | Unsigned 64-bit decimal string |
| `tokenGenerated` | `?DateTimeImmutable` | UTC; full tickets only |
| `sessionExternalIp` | `?string` | Dotted-quad; full tickets only |
| `clientConnectionTime` | `?int` | Milliseconds; full tickets only |
| `clientConnectionCount` | `?int` | Full tickets only |

`hasAuthHeader(): bool` returns `true` when `authTicket` is not `null`.

Ownership-only tickets leave every authentication field `null`.

### `Model\DecodedEncryptedAppTicket`

| Property | Type | Notes |
| --- | --- | --- |
| `version` | `int` | Ownership ticket version |
| `steamId` | `SteamId` | Ticket owner |
| `appId` | `int` | App the ticket authenticates |
| `ownershipTicketExternalIp` | `string` | Dotted-quad |
| `ownershipTicketInternalIp` | `string` | Dotted-quad |
| `ownershipFlags` | `int` | Steam ownership flags |
| `ownershipTicketGenerated` | `DateTimeImmutable` | UTC |
| `licenses` | `list<int>` | Package IDs |
| `dlc` | `list<Dlc>` | Same `Dlc` model as plain tickets |
| `userData` | `string` | Raw user-supplied bytes |
| `trailer` | `?EncryptedTicketTrailer` | Version 2 only |

Encrypted tickets have no RSA signature, so expiry, signature and validity
properties are intentionally **not** exposed. Authenticity comes from the
CRC32 check, the salted SHA-1 hash and — above all — the secrecy of your
encryption key.

### `Model\EncryptedTicketTrailer`

| Property | Type | Notes |
| --- | --- | --- |
| `unknown2` | `string` | Undocumented uint64, decimal string |
| `unknown3` | `string` | Undocumented uint64, decimal string |
| `unknown4` | `int` | Undocumented uint32 |

Steam does not document these 20 bytes. They are preserved so you can log or
compare them without losing 64-bit precision. Treat their meaning as opaque.

### `Model\SteamId`

```php
$steamId = Model\SteamId::fromDecimalString('76561197960278073');

$steamId->getSteamId64();   // "76561197960278073"
$steamId->toInt();          // 76561197960278073
$steamId->getAccountId();   // 12345
$steamId->getInstance();    // 1
$steamId->getType();        // 1 (individual)
$steamId->getUniverse();    // 1 (public)
$steamId->isIndividual();   // true
$steamId->equals($other);   // value comparison
(string) $steamId;          // "76561197960278073"
```

Negative values and decimal strings above `PHP_INT_MAX` are rejected with an
`InvalidArgumentException`. `isIndividual()` is `true` only for individual
desktop accounts (type `1`, instance `1`).

## Dependency injection

Every collaborator is injected through a narrow interface, so the parsers are
fully deterministic in tests and easy to replace in production.

```php
use OndrejBakan\SteamAppTicket\Contracts\Clock;
use OndrejBakan\SteamAppTicket\Contracts\SignatureVerifier;
use OndrejBakan\SteamAppTicket\SteamAppTicket;

final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

$service = SteamAppTicket::create(
    clock: new FrozenClock(new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'))),
    signatureVerifier: new MyHardwareBackedVerifier(),
    decryptor: new MyFipsDecryptor(),
);
```

The four seams are:

- `Contracts\Clock` — current time for expiry decisions
- `Contracts\SignatureVerifier` — RSA signature verification for plain tickets
- `Contracts\Decryptor` — AES-256-CBC decryption
- `Contracts\EncryptedAppTicketDecoder` — protobuf envelope decoding

`SteamAppTicket` itself has no constructor side effects and no global state.

## Security notes

1. **Check the AppID.** A valid ticket for another app must not authenticate
   your app.
2. **Check the SteamID** when you already know which user should be calling.
3. **Check the age** of the ticket. Encrypted tickets do not carry an expiry
   timestamp; enforce your own maximum age using `ownershipTicketGenerated`.
4. **Use `userData` as a nonce** to prevent replay. Have the client include a
   server-issued random value, then compare it on the backend.
5. **Keep the encryption key secret.** Anyone with the key can mint tickets
   that pass every integrity check. A leaked key invalidates all guarantees.
6. **Verify plain tickets with Steam.** The local RSA check proves Steam
   signed the ownership data, not that the ticket is currently valid for a
   session. Call `ISteamUserAuth/AuthenticateUserTicket` before trusting a
   plain ticket for login.
7. **Do not trust `sessionExternalIp` or `ownershipTicketInternalIp`.** They
   are not authenticated (Node documents the same limitation).

## Behaviour and format notes

- **Encrypted payload encryption** — the first 16 bytes are an AES-256-ECB
  encrypted IV; the remainder is AES-256-CBC ciphertext with strict PKCS#7
  padding. Both AES operations use raw output and zero padding internally so
  padding is handled by this library rather than OpenSSL.
- **Encrypted integrity** — the envelope carries an IEEE CRC-32 of the
  decrypted payload. If the payload ends with at least 28 bytes beyond the
  ownership ticket (and optional v2 trailer), the next 8 bytes are a salt and
  the following 20 bytes are `SHA-1(data + salt)`.
- **Plain integrity** — the ownership ticket (including its own length prefix
  and excluding the 128-byte signature) is signed with RSA-SHA1 by Steam's
  system key.
- **Expiry semantics** — a ticket is expired only when
  `ownershipTicketExpires < clock->now()`. A ticket expiring exactly now is
  still considered valid.
- **Ownership length** — the uint32 ownership length includes its own four
  bytes. An optional trailing 128-byte signature is not included in that
  length.
- **Full ticket layout** — `initialLength == 20` selects the 52-byte
  authentication header. Its outer section length is validated with an
  offset-based check against the total ticket size.
- **Version 2 trailers** — encrypted tickets with `ticketVersionNo == 2`
  carry 20 undocumented bytes between the ownership ticket and the checksum.
  The checksum covers those bytes. The trailer is exposed, never interpreted.
- **Checksum tolerances** — a checksum remainder of exactly 28 bytes is
  accepted (Go's tolerance), and additional trailing bytes after the hash are
  ignored (these carry Node's legacy `unknown1` uint32). Neither form is
  required to have the extra four bytes.
- **Protobuf** — fields 1–5 are decoded in any order, unknown fields with
  wire types 0, 1, 2 and 5 are skipped, duplicates are last-wins, and absent
  optional fields default to `0` / empty bytes. Field 4
  (`cb_encrypted_appownership_ticket`) is decoded but not used for slicing,
  matching upstream behaviour.
- **Value types** — raw bytes are PHP strings, timestamps are immutable UTC
  `DateTimeImmutable` objects, IPs are dotted-quad strings, and unsigned
  64-bit values that can exceed `PHP_INT_MAX` are exposed as decimal strings.

## Architecture

```
src/
├── SteamAppTicket.php                 Facade and default wiring
├── Binary/
│   ├── ByteReader.php                 Bounds-checked little-endian reader
│   └── IpAddress.php                  uint32 -> dotted quad
├── Clock/
│   └── SystemClock.php
├── Contracts/
│   ├── Clock.php
│   ├── Decryptor.php
│   ├── EncryptedAppTicketDecoder.php
│   └── SignatureVerifier.php
├── Crypto/
│   ├── Aes256CbcDecryptor.php
│   ├── Crc32.php
│   ├── EncryptionKey.php
│   ├── OpenSslSignatureVerifier.php
│   ├── Pkcs7.php
│   └── SteamPublicKey.php
├── Exception/
│   ├── SteamAppTicketException.php
│   ├── InvalidTicketException.php
│   ├── InvalidSignatureException.php
│   └── DecryptionFailedException.php
├── Model/
│   ├── AppTicket.php
│   ├── DecodedEncryptedAppTicket.php
│   ├── Dlc.php
│   ├── EncryptedAppTicketEnvelope.php
│   ├── EncryptedTicketTrailer.php
│   └── SteamId.php
├── Parser/
│   ├── AppTicketParser.php
│   └── EncryptedAppTicketParser.php
└── Protobuf/
    ├── EncryptedAppTicketDecoder.php
    └── WireReader.php
```

## Deliberate design decisions versus the upstream libraries

- **One flat `AppTicket` result.** Node returns either an ownership-ticket
  object or an extended full-ticket object; Go uses struct embedding. PHP
  exposes a single final readonly model whose authentication fields are
  nullable for ownership-only tickets. This makes type handling simpler for
  consumers.
- **No mutable error state.** Node returns `null` on failure; Go returns
  sentinel errors. This library uses a small exception hierarchy and offers
  explicit `try*` conveniences. Domain failures and programming errors stay
  clearly separated.
- **Constructor injection everywhere.** Time, signature verification,
  decryption and envelope decoding are interfaces. There is no service
  locator, no global function and no static mutable state.
- **Own minimal protobuf reader.** Instead of generated protobuf code plus a
  runtime, the five-field envelope is parsed by a strict wire-format reader.
  Any truncation, invalid wire type, field number zero or overflow becomes an
  `InvalidTicketException`.
- **Node's length checks, not Go's.** The Go port compares the ownership
  length against the whole ticket and misuses buffer lengths; the PHP port
  follows Node's offset-based checks (`offset + length == limit` or
  `offset + length + 128 == limit`), which correctly reject trailing garbage.
- **No unused HMAC branch.** Go's symmetric decryptor has an optional HMAC
  check that is never enabled. This package implements only the live code
  path and validates PKCS#7 padding strictly.
- **64-bit safety.** `gcToken`, `unknown2` and `unknown3` are decimal strings,
  not native integers or floats, so `18446744073709551615` round-trips
  exactly. `SteamId` rejects high-bit values instead of silently overflowing.
- **v2 trailer as a value object.** Node deletes the undocumented fields
  before returning; Go flattens them into the main result. Here they are
  grouped in an optional `EncryptedTicketTrailer`, keeping the main model
  clean while preserving the data.
- **Tolerant checksum remainder.** Node requires four further bytes for the
  legacy `unknown1` uint32, while Go accepts exactly 28 bytes. The PHP parser
  accepts both forms: a 28-byte remainder is sufficient and any additional
  trailing bytes are ignored.

## Testing and static analysis

The suite uses PHPUnit 11.5 and builds all tickets synthetically — no network
access, Steam credentials or real tickets are required.

```bash
composer install

composer test          # PHPUnit 11.5 (fail on warning/risky)
composer cs            # php-cs-fixer dry run (PSR-12 + strict types)
composer cs:fix        # apply code style fixes
composer stan          # PHPStan level 8, src/
```

Direct commands:

```bash
php vendor/bin/phpunit
php vendor/bin/php-cs-fixer fix --dry-run --diff
php vendor/bin/phpstan analyse --no-progress
```

The suite covers the binary reader, protobuf decoder, CRC-32, PKCS#7, AES,
RSA-SHA1 verification with a generated key pair, SteamID arithmetic, both
parsers (including full tickets, v2 trailers, CRC/hash failures and truncation)
and the facade's exception/`try*` behaviour.

## License

MIT. See [LICENSE](LICENSE).
