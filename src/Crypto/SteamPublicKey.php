<?php

declare(strict_types=1);

namespace OndrejBakan\SteamAppTicket\Crypto;

/**
 * Steam's well-known RSA public key used to verify plain app tickets.
 */
final class SteamPublicKey
{
    public const string PEM = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIGdMA0GCSqGSIb3DQEBAQUAA4GLADCBhwKBgQDf7BrWLBBmLBc1OhSwfFkRf53T
2Ct64+AVzRkeRuh7h3SiGEYxqQMUeYKO6UWiSRKpI2hzic9pobFhRr3Bvr/WARvY
gdTckPv+T1JzZsuVcNfFjrocejN1oWI0Rrtgt4Bo+hOneoo3S57G9F1fOpn5nsQ6
6WOiu4gZKODnFMBCiQIBEQ==
-----END PUBLIC KEY-----
PEM;
}
