<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\TempFolder;
use SilverStripe\HybridSessions\Tests\AbstractTest;
use SilverStripe\HybridSessions\HybridSession;

class HybridSessionTest extends AbstractTest
{

    /**
     * @return HybridSessionStore_Cookie
     */
    protected function getStore()
    {
        $store = Injector::inst()->create(HybridSession::class);
        $store->setKey(uniqid());
        $store->open(TempFolder::getTempFolder(BASE_PATH).'/'.__CLASS__, 'SESSIONCOOKIE');

        return $store;
    }
}
