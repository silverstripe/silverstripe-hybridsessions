<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\HybridSessions\Store\CookieStore;
use SilverStripe\HybridSessions\Tests\Store\TestCookieStore;

abstract class AbstractTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp()
    {
        parent::setUp();

        TestCookieStore::$override_headers_sent = false;

        Injector::inst()->registerService(
            new TestCookieStore(),
            CookieStore::class
        );

        DBDatetime::set_mock_now('2010-03-15 12:00:00');
    }

    protected function tearDown()
    {
        DBDatetime::clear_mock_now();

        parent::tearDown();
    }

    abstract protected function getStore();

    /**
     * Test how this store handles large volumes of data (>1000 characters)
     */
    public function testStoreLargeData()
    {
        $session = uniqid();
        $store = $this->getStore();

        // Test new session is blank
        $result = $store->read($session);
        $this->assertEmpty($result);

        // Save data against session
        $data1 = array(
            'Large' => str_repeat('A', 600),
            'Content' => str_repeat('B', 600)
        );
        $store->write($session, serialize($data1));
        $result = $store->read($session);
        $this->assertEquals($data1, unserialize($result));
    }

    /**
     * Test storage of data
     */
    public function testStoreData()
    {
        $session = uniqid();
        $store = $this->getStore();

        // Test new session is blank
        $result = $store->read($session);
        $this->assertEmpty($result);

        // Save data against session
        $data1 = array(
            'Color' => 'red',
            'Animal' => 'elephant'
        );
        $store->write($session, serialize($data1));
        $result = $store->read($session);
        $this->assertEquals($data1, unserialize($result));

        // Save larger data
        $data2 = array(
            'Color' => 'blue',
            'Animal' => str_repeat('bat', 100)
        );
        $store->write($session, serialize($data2));
        $result = $store->read($session);
        $this->assertEquals($data2, unserialize($result));
    }

    /**
     * Test expiry of data
     */
    public function testExpiry()
    {
        $session1 = uniqid();
        $store = $this->getStore();

        // Store data now
        $data1 = array(
            'Food' => 'Pizza'
        );
        $store->write($session1, serialize($data1));
        $result1 = $store->read($session1);
        $this->assertEquals($data1, unserialize($result1));

        // Go to the future and test that the expiry is accurate
        DBDatetime::set_mock_now('2040-03-16 12:00:00');
        $result2 = $store->read($session1);

        $this->assertEmpty($result2);
    }
}
