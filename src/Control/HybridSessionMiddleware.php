<?php

namespace SilverStripe\HybridSessions\Control;

use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\HybridSessions\HybridSession;

class HybridSessionMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        // @todo
        //
        // if (HybridSession::is_enabled()) {
        //    session_write_close();
        // }

        return $delegate($request);
    }
}
