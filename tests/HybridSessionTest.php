<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\TempFolder;
use SilverStripe\HybridSessions\Tests\AbstractTest;
use SilverStripe\HybridSessions\HybridSession;

class HybridSessionTest extends AbstractTest
{

    protected function setUp()
    {
        parent::setUp();

        if (!DB::get_conn() instanceof MySQLDatabase) {
            // we can't always use the DB driver, so remove it if we aren't running a MySQL DB
            Config::modify()->set(HybridSession::class, 'dependencies', [
                'handlers' => [
                    '%$\SilverStripe\HybridSessions\Store\CookieStore',
                ],
            ]);
        }
    }

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
