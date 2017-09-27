<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\Control\Middleware\SessionMiddleware;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\HybridSessions\Control\HybridSessionMiddleware;

class ConfigurationTest extends SapphireTest
{
    public function testHybridSessionsSessionMiddlewareReplacesCore()
    {
        $this->assertInstanceOf(
            HybridSessionMiddleware::class,
            Injector::inst()->get(SessionMiddleware::class),
            'HybridSession\'s middleware should replace the default SessionMiddleware'
        );
    }
}
