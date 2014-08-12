** Hybrid Sessions

Adds a session handler that tries storing the session in an encrypted cookie when possible, and if not (
because the session is too large, or headers have already been set) stores it in the database.

This allows using SilverStripe on multiple servers without sticky sessions (as long as you solve other
multi-server issues like asset storage and databases).

Requires the mcrypt extension

** Security

You should set HybridSessionStore_Cookie::$key to something random. You can alternatively set a SS_SESSION_KEY constant

As long as this key is unguessable and secret, generally this should be as secure as server-side-only cookies. The one
exception is that cookie sessions are vulnerable to replay attacks. This is only a problem if you're storing stuff you
shouldn't in the session anyway, but it's important you understand the issue. Ruby on rails has good documentation of
the issue at http://guides.rubyonrails.org/security.html#replay-attacks-for-cookiestore-sessions
