<?php

namespace SilverStripe\HybridSessions\Control;

use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\HybridSessions\Store\SessionStore;

class HybridSessionMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $next)
    {
        if (SessionStore::is_enabled()) {
            session_write_close();
        }
    }
}
