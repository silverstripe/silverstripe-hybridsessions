<?php

namespace SilverStripe\HybridSessions\Tests\Store;

use SilverStripe\HybridSessions\Store\CookieStore;
use SilverStripe\Dev\TestOnly;

class TestCookieStore extends CookieStore implements TestOnly
{
    /**
     * Override value of 'headers_sent' but only if running tests.
     *
     * Set to true or false, or null to not override
     *
     * @var string
     */
    public static $override_headers_sent = null;

    protected function canWrite(): bool
    {
        if (TestCookieStore::$override_headers_sent !== null) {
            return !TestCookieStore::$override_headers_sent;
        }

        parent::canWrite();
    }
}
