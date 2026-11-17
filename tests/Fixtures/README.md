# Fixture provenance

These fixtures were generated with the upstream Node
[`steam-appticket`](https://www.npmjs.com/package/steam-appticket) implementation
using synthetic tickets. They contain no real Steam credentials or secrets.

- `tickets.json` contains the raw ticket bytes (base64), the ticket kind and the
  synthetic AES key (hex) where applicable.
- `node-results.json` contains the exact output of the Node parser for every
  fixture.

`tests/FixtureParityTest.php` replays every fixture through the PHP parser and
compares the shared fields with the Node results. The two entries whose Node
result is `null` document deliberate reference-compatibility behaviour:

- a checksum remainder of exactly 28 bytes, which Node rejects because it
  cannot read the legacy trailing `uint32`;
- an encrypted payload that embeds a signed ownership ticket; the reference
  parser slices only the declared ownership length, so the checksum no longer
  matches.

The ticket keys are test-only values and are safe to commit.
