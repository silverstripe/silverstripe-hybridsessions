# Hybrid Sessions

## Introduction

Adds a session handler that tries storing the session in an encrypted cookie when possible, and if not (
because the session is too large, or headers have already been set) stores it in the database.

This allows using SilverStripe on multiple servers without sticky sessions (as long as you solve other
multi-server issues like asset storage and databases).

## Requirements

 * The mcrypt PHP extension
 * MySQL database is the only supported DB store.

## Installation

* Install with composer using `composer require silverstripe/hybridsession:*`
* /dev/build?flush=all to setup the necessary tables
* In order to initiate the session handler is is necessary to add a snippet of code to your
  _config.php, along with a private key used to encrypt user cookies.

in `mysite/_config.php`

```php
// Ensure that you define a sufficiently indeterminable
// value for SS_SESSION_KEY in your _ss_environment.php
HybridSessionStore::init(SS_SESSION_KEY);
```

## Security

As long as the key is unguessable and secret, generally this should be as secure as server-side-only cookies. The one
exception is that cookie sessions are vulnerable to replay attacks. This is only a problem if you're storing stuff you
shouldn't in the session anyway, but it's important you understand the issue. Ruby on rails has good documentation of
the issue at http://guides.rubyonrails.org/security.html#replay-attacks-for-cookiestore-sessions

## Caveats

This module is not fully compatible with the
[testsession](https://github.com/silverstripe-labs/silverstripe-testsession/) module, as the database
store is not available prior to DB connectivity, which uses the session to dictate DB connections.
Data smaller than 1000 bytes may still be stored via the cookie store, but larger data sets will be lost.