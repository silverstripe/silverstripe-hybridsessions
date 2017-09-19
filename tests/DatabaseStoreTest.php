<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\HybridSessions\Store\DatabaseStore;

class DatabaseStoreTest extends AbstractTest
{
    protected function getStore()
    {
        $store = Injector::inst()->get(DatabaseStore::class);
        $store->setKey(uniqid());

        return $store;
    }
}
