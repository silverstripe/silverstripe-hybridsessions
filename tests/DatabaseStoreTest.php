<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\HybridSessions\Store\DatabaseStore;

class DatabaseStoreTest extends AbstractTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!DB::get_conn() instanceof MySQLDatabase) {
            $this->markTestSkipped('Only MySQL databases are supported');
        }
    }

    protected function getStore()
    {
        $store = Injector::inst()->get(DatabaseStore::class);
        $store->setKey(uniqid());

        return $store;
    }

    public function testDataCodecIntegrity()
    {
        for ($i = 0; $i < 1000; ++$i) {
            $data = random_bytes(1024 * 4);

            $this->assertEquals($data, DatabaseStore::binaryDataJsonDecode(DatabaseStore::binaryDataJsonEncode($data)));
        }
    }
}
