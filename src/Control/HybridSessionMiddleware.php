<?php

namespace SilverStripe\HybridSessions\Control;

use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\HybridSessions\HybridSession;

class HybridSessionMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        try {
            // Start session and execute
            $request->getSession()->init($request);

            // Generate output
            return $delegate($request);
        } finally {
            // Save session data, even if there was an exception after the
            // response has been returned.

            // Note that save() will start/resume the session if required.
            $request->getSession()->save($request);

            session_write_close();
        }
    }
}
