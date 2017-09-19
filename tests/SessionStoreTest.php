<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\TempFolder;
use SilverStripe\HybridSessions\Tests\AbstractTest;
use SilverStripe\HybridSessions\Store\SessionStore;

class SessionStoreTest extends AbstractTest
{

    /**
     * @return HybridSessionStore_Cookie
     */
    protected function getStore()
    {
        $store = Injector::inst()->get(SessionStore::class);
        $store->setKey(uniqid());
        $store->open(TempFolder::getTempFolder(BASE_PATH).'/'.__CLASS__, 'SESSIONCOOKIE');

        return $store;
    }
}
