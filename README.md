# Hybrid Sessions

[![Build Status](https://travis-ci.org/silverstripe/silverstripe-hybridsessions.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-hybridsessions)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-hybridsessions/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-hybridsessions/?branch=master)
[![codecov](https://codecov.io/gh/silverstripe/silverstripe-hybridsessions/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-hybridsessions)
[![SilverStripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Introduction

Adds a session handler that tries storing the session in an encrypted cookie when possible, and if not (
because the session is too large, or headers have already been set) stores it in the database.

This allows using SilverStripe on multiple servers without sticky sessions (as long as you solve other
multi-server issues like asset storage and databases).

## Requirements

 * MySQL database is the only supported DB store.

## Installation

* Install with composer using `composer require silverstripe/hybridsessions:*`
* /dev/build?flush=all to setup the necessary tables
* In order to initiate the session handler is is necessary to add a snippet of code to your
  \_config.php, along with a private key used to encrypt user cookies.

in `mysite/_config.php`

```php
// Ensure that you define a sufficiently indeterminable value for SS_SESSION_KEY in your `.env`
use SilverStripe\HybridSessions\HybridSession;

HybridSession::init(SS_SESSION_KEY);
```

## Security

As long as the key is unguessable and secret, generally this should be as secure as server-side-only cookies. The one
exception is that cookie sessions are vulnerable to replay attacks. This is only a problem if you're storing stuff you
shouldn't in the session anyway, but it's important you understand the issue. Ruby on rails has good documentation of
the issue at http://guides.rubyonrails.org/security.html#replay-attacks-for-cookiestore-sessions

### Crypto handlers

This module ships with two default cryptographic handlers:

* `OpenSSLCrypto`: uses the OpenSSL library (default).
* `McryptCrypto`: uses the mcrypt library. Please note that this is 
  [deprecated, and removed from PHP 7.2](https://secure.php.net/releases/7_2_0.php). We advise against its use.

You can also implement your own cryptographic handler by creating a class that implements the
`SilverStripe\HybridSessions\Crypto\CryptoHandler` interface.

To configure the active handler, add YAML configuration to your project:

```yaml
---
Name: myappsessionstores
After: sessionstores
---
SilverStripe\HybridSessions\HybridSession:
  SilverStripe\HybridSessions\Crypto\CryptoHandler:
    class: MyCustomCryptoHandler
```

## Caveats

This module is not fully compatible with the
[testsession](https://github.com/silverstripe-labs/silverstripe-testsession/) module, as the database
store is not available prior to DB connectivity, which uses the session to dictate DB connections.
Data smaller than 1000 bytes may still be stored via the cookie store, but larger data sets will be lost.
