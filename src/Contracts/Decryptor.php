<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Contracts;

use OndrejBakan\SteamAppTicket\Crypto\EncryptionKey;

/**
 * Symmetrically decrypts the payload of an encrypted app ticket.
 */
interface Decryptor
{
    /**
     * @param string $ciphertext Encrypted payload exactly as stored on the wire.
     *
     * @return string Decrypted, unpadded plaintext.
     *
     * @throws \OndrejBakan\SteamAppTicket\Exception\DecryptionFailedException
     */
    public function decrypt(string $ciphertext, EncryptionKey $key): string;
}
