<?php

/**
 * Tests the {@see HybridSessionStore_Database} class
 */
class HybridSessionDatabaseTest extends HybridSessionAbstractTest {

	/**
	 * @return HybridSessionStore_Database
	 */
	protected function getStore() {
		$store = Injector::inst()->get('HybridSessionStore_Database');
		$store->setKey(uniqid());
		return $store;
	}

}
