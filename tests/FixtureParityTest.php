<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Tests;

use OndrejBakan\SteamAppTicket\Model\AppTicket;
use OndrejBakan\SteamAppTicket\Model\DecodedEncryptedAppTicket;
use OndrejBakan\SteamAppTicket\Model\Dlc;
use OndrejBakan\SteamAppTicket\SteamAppTicket;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedClock;
use OndrejBakan\SteamAppTicket\Tests\Support\FixedSignatureVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Compares parser output against the reference Node implementation.
 *
 * The corpus in tests/Fixtures was generated with the upstream
 * steam-appticket package; node-results.json contains its exact output.
 */
final class FixtureParityTest extends TestCase
{
    private const NOW = 1700000000;

    /**
     * @return iterable<string, array{
     *     0: array{name: string, kind: string, ticket: string, key: ?string},
     *     1: array<string, mixed>|null
     * }>
     */
    public static function referenceFixtures(): iterable
    {
        $fixtures = self::loadTickets();
        $results = self::loadNodeResults();

        foreach ($fixtures as $fixture) {
            if (!array_key_exists($fixture['name'], $results)) {
                throw new RuntimeException(sprintf('Missing Node result for fixture "%s".', $fixture['name']));
            }

            yield $fixture['name'] => [$fixture, $results[$fixture['name']]];
        }
    }

    /**
     * @param array{name: string, kind: string, ticket: string, key: ?string} $fixture
     * @param array<string, mixed>|null $expected
     */
    #[DataProvider('referenceFixtures')]
    public function testMatchesNodeReference(array $fixture, ?array $expected): void
    {
        $ticket = base64_decode($fixture['ticket'], true);

        if ($ticket === false) {
            throw new RuntimeException(sprintf('Fixture "%s" is not valid base64.', $fixture['name']));
        }

        $service = SteamAppTicket::create(
            clock: new FixedClock(self::NOW),
            signatureVerifier: new FixedSignatureVerifier(false),
        );

        if ($fixture['kind'] === 'plain') {
            if ($expected === null) {
                throw new RuntimeException(sprintf('Plain fixture "%s" cannot have a null result.', $fixture['name']));
            }

            $parsed = $service->parseAppTicket($ticket, true);

            self::assertSame($expected, self::normalisePlain($parsed));

            return;
        }

        if ($fixture['kind'] !== 'encrypted') {
            throw new RuntimeException(sprintf('Fixture "%s" has an unknown kind.', $fixture['name']));
        }

        $key = $fixture['key'];

        if ($key === null) {
            throw new RuntimeException(sprintf('Encrypted fixture "%s" is missing its key.', $fixture['name']));
        }

        $parsed = $service->tryParseEncryptedAppTicket($ticket, $key);

        if ($expected === null) {
            self::assertNull($parsed, sprintf('Fixture "%s" must be rejected.', $fixture['name']));

            return;
        }

        if ($parsed === null) {
            throw new RuntimeException(sprintf('Fixture "%s" was unexpectedly rejected.', $fixture['name']));
        }

        self::assertSame($expected, self::normaliseEncrypted($parsed));
    }

    public function testFixtureCorpusIsComplete(): void
    {
        $fixtures = self::loadTickets();
        $results = self::loadNodeResults();

        self::assertCount(14, $fixtures);
        self::assertCount(14, $results);

        $rejected = array_filter(
            $results,
            static fn (?array $node): bool => $node === null,
        );

        self::assertCount(2, $rejected);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalisePlain(AppTicket $ticket): array
    {
        return [
            'version' => $ticket->version,
            'steamID' => $ticket->getSteamId64(),
            'appID' => $ticket->appId,
            'ownershipTicketExternalIP' => $ticket->ownershipTicketExternalIp,
            'ownershipTicketInternalIP' => $ticket->ownershipTicketInternalIp,
            'ownershipFlags' => $ticket->ownershipFlags,
            'ownershipTicketGenerated' => $ticket->ownershipTicketGenerated->getTimestamp(),
            'ownershipTicketExpires' => $ticket->ownershipTicketExpires->getTimestamp(),
            'licenses' => $ticket->licenses,
            'dlc' => array_map(
                static fn (Dlc $dlc): array => [
                    'appID' => $dlc->appId,
                    'licenses' => $dlc->licenses,
                ],
                $ticket->dlc,
            ),
            'signature' => $ticket->signature === null ? null : base64_encode($ticket->signature),
            'isExpired' => $ticket->isExpired,
            'hasValidSignature' => $ticket->hasValidSignature,
            'isValid' => $ticket->isValid,
            'authTicket' => $ticket->authTicket === null ? null : base64_encode($ticket->authTicket),
            'gcToken' => $ticket->gcToken,
            'tokenGenerated' => $ticket->tokenGenerated?->getTimestamp(),
            'sessionExternalIP' => $ticket->sessionExternalIp,
            'clientConnectionTime' => $ticket->clientConnectionTime,
            'clientConnectionCount' => $ticket->clientConnectionCount,
        ];
    }

    /**
     * Node deletes expiry, signature and unknown fields from encrypted results,
     * so the comparison uses the same Node-shaped projection. Trailer and
     * unknown1 values are asserted by the encrypted parser tests instead.
     *
     * @return array<string, mixed>
     */
    private static function normaliseEncrypted(DecodedEncryptedAppTicket $ticket): array
    {
        return [
            'version' => $ticket->version,
            'steamID' => $ticket->getSteamId64(),
            'appID' => $ticket->appId,
            'ownershipTicketExternalIP' => $ticket->ownershipTicketExternalIp,
            'ownershipTicketInternalIP' => $ticket->ownershipTicketInternalIp,
            'ownershipFlags' => $ticket->ownershipFlags,
            'ownershipTicketGenerated' => $ticket->ownershipTicketGenerated->getTimestamp(),
            'licenses' => $ticket->licenses,
            'dlc' => array_map(
                static fn (Dlc $dlc): array => [
                    'appID' => $dlc->appId,
                    'licenses' => $dlc->licenses,
                ],
                $ticket->dlc,
            ),
            'userData' => base64_encode($ticket->userData),
            'unknown2' => null,
            'unknown3' => null,
            'unknown4' => null,
        ];
    }

    /**
     * @return list<array{name: string, kind: string, ticket: string, key: ?string}>
     */
    private static function loadTickets(): array
    {
        $decoded = self::decodeJsonFile(__DIR__ . '/Fixtures/tickets.json');

        if (!is_array($decoded)) {
            throw new RuntimeException('Fixture file must contain a list.');
        }

        $fixtures = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Every fixture entry must be an object.');
            }

            $name = $entry['name'] ?? null;
            $kind = $entry['kind'] ?? null;
            $ticket = $entry['ticket'] ?? null;
            $key = $entry['key'] ?? null;

            if (!is_string($name) || !is_string($kind) || !is_string($ticket)) {
                throw new RuntimeException('Fixture entries need string name, kind and ticket fields.');
            }

            if ($key !== null && !is_string($key)) {
                throw new RuntimeException('Fixture keys must be strings or null.');
            }

            $fixtures[] = [
                'name' => $name,
                'kind' => $kind,
                'ticket' => $ticket,
                'key' => $key,
            ];
        }

        return $fixtures;
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private static function loadNodeResults(): array
    {
        $decoded = self::decodeJsonFile(__DIR__ . '/Fixtures/node-results.json');

        if (!is_array($decoded)) {
            throw new RuntimeException('Node result file must contain a list.');
        }

        $results = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Every Node result entry must be an object.');
            }

            $name = $entry['name'] ?? null;

            if (!is_string($name) || !array_key_exists('node', $entry)) {
                throw new RuntimeException('Node result entries need a name and a node field.');
            }

            $node = $entry['node'];

            if ($node === null) {
                $results[$name] = null;

                continue;
            }

            if (!is_array($node)) {
                throw new RuntimeException('Node results must be objects or null.');
            }

            $normalised = [];

            foreach ($node as $key => $value) {
                if (!is_string($key)) {
                    throw new RuntimeException('Node result keys must be strings.');
                }

                $normalised[$key] = $value;
            }

            $results[$name] = $normalised;
        }

        return $results;
    }

    private static function decodeJsonFile(string $path): mixed
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read %s.', $path));
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
