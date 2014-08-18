<?php

/**
 * Tests {@see HybridSessionStore} class
 */
class HybridSessionStoreTest extends HybridSessionAbstractTest {

	/**
	 * @return HybridSessionStore_Cookie
	 */
	protected function getStore() {
		$store = Injector::inst()->get('HybridSessionStore');
		$store->setKey(uniqid());
		$store->open(getTempFolder().'/'.__CLASS__, 'SESSIONCOOKIE');
		return $store;
	}
}