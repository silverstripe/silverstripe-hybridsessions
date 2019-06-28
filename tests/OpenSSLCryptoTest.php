<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\HybridSessions\Crypto\OpenSSLCrypto;

/**
 * @requires extension openssl
 */
class OpenSSLCryptoTest extends SapphireTest
{
    public function testIntegrity()
    {
        $key = random_bytes(8);
        $salt = random_bytes(16);

        $handler = new OpenSSLCrypto($key, $salt);

        for ($i = 0; $i < 1000; ++$i) {
            $data = random_bytes(1024 * 4);

            $this->assertEquals($data, $handler->decrypt($handler->encrypt($data)));
        }
    }
}
