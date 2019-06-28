<?php

namespace SilverStripe\HybridSessions\Tests;


use SilverStripe\Dev\SapphireTest;
use SilverStripe\HybridSessions\Crypto\McryptCrypto;

class McryptCryptoTest extends SapphireTest
{
    /**
     * @requires extension mcrypt
     */
    public function testIntegrity()
    {
        $key = random_bytes(8);
        $salt = random_bytes(16);

        $handler = new McryptCrypto($key, $salt);

        for ($i=0; $i<1000; ++$i) {
            $data = random_bytes(1024 * 4);

            $this->assertEquals($data, $handler->decrypt($handler->encrypt($data)));
        }
    }
}
