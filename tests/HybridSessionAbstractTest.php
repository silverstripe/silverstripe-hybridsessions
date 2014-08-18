<?php

abstract class HybridSessionAbstractTest extends SapphireTest {

	public function setUp() {
		parent::setUp();

		HybridSessionAbstractTest_TestCookieBackend::$override_headers_sent = false;

		Injector::nest();
		Injector::inst()->registerService(
			new HybridSessionAbstractTest_TestCookieBackend(),
			'HybridSessionStore_Cookie'
		);

		SS_Datetime::set_mock_now('2010-03-15 12:00:00');

		if(get_class() === get_class($this)) {
			$this->markTestSkipped("Skipping abstract test");
			$this->skipTest = true;
		}
	}

	public function tearDown() {
		Injector::unnest();
		SS_Datetime::clear_mock_now();

		parent::tearDown();
	}

	/**
	 * @return HybridSessionStore_Base
	 */
	abstract protected function getStore();

	/**
	 * Test how this store handles large volumes of data (>1000 characters)
	 */
	public function testStoreLargeData() {
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
	public function testStoreData() {
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
	public function testExpiry() {
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
		SS_Datetime::set_mock_now('2040-03-16 12:00:00');
		$result2 = $store->read($session1);
		$this->assertEmpty($result2);
	}
}

class HybridSessionAbstractTest_TestCookieBackend extends HybridSessionStore_Cookie {


	/**
	 * Override value of 'headers_sent' but only if running tests.
	 *
	 * Set to true or false, or null to not override
	 *
	 * @var string
	 */
	public static $override_headers_sent = null;

	protected function canWrite() {
		if(self::$override_headers_sent !== null) {
			return !self::$override_headers_sent;
		}
		parent::canWrite();
	}
}