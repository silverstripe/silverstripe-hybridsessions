<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\TempFolder;
use SilverStripe\HybridSessions\Store\CookieStore;
use SilverStripe\HybridSessions\Tests\Store\TestCookieStore;

class CookieStoreTest extends AbstractTest
{
    protected function getStore()
    {
        $store = Injector::inst()->get(CookieStore::class);
        $store->setKey(uniqid());
        $store->open(TempFolder::getTempFolder(BASE_PATH) . '/' . __CLASS__, 'SESSIONCOOKIE');

        return $store;
    }

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

        // Cookies should not try to store data that large
        $this->assertEmpty($result);
    }

    /**
     * Ensure that subsequent reads without the necessary write do not report data
     */
    public function testReadInvalidatesData()
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
        $this->assertEquals($data1, unserialize($result ?? ''));

        // Since we have read the data into the result, the application could modify this content
        // and be unable to write it back due to headers being sent. We should thus assume
        // that subsequent reads without a successful write do not purport to have valid data
        $data1['Color'] = 'blue';
        $result = $store->read($session);
        $this->assertEmpty($result);

        // Check that writing to cookie fails after headers are sent and these results remain
        // invalidated
        TestCookieStore::$override_headers_sent = true;
        $store->write($session, serialize($data1));
        $result = $store->read($session);
        $this->assertEmpty($result);
    }

    public function testGc()
    {
        $store = $this->getStore();
        $this->assertFalse(
            $store->gc(123),
            'CookieStore cannot clean up session because the sessions are in cookies in the browser'
        );
    }
}
