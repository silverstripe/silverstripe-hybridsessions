<?php

namespace SilverStripe\HybridSessions\Store;

use SilverStripe\Control\Cookie;
use SilverStripe\HybridSessions\Crypto\CryptoHandler;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;

/**
 * A session store which stores the session data in an encrypted & signed cookie.
 *
 * This way the server doesn't need to open a database connection or have a shared filesystem for reading
 * the session from - the client passes through the session with every request.
 *
 * This approach does have some limitations - cookies can only be quite small (4K total, but we limit to 1K)
 * and can only be set _before_ the server starts sending a response.
 *
 * So we clear the cookie on Session startup (which should always be before the headers get sent), but just
 * fail on Session write if we can't use cookies, assuming there's something watching for that & providing a fallback
 */
class CookieStore extends BaseStore
{

    /**
     * Maximum length of a cookie value in characters
     *
     * @var int
     * @config
     */
    private static $max_length = 1024;

    /**
     * Encryption service
     *
     * @var HybridSessionStore_Crypto
     */
    protected $crypto;

    /**
     * Name of cookie
     *
     * @var string
     */
    protected $cookie;

    /**
     * Known unmodified value of this cookie. If the cookie backend has been read into the application,
     * then the backend is unable to verify the modification state of this value internally within the
     * system, so this will be left null unless written back.
     *
     * If the content exceeds max_length then the backend can also not maintain this cookie, also
     * setting this variable to null.
     *
     * @var string
     */
    protected $currentCookieData;

    public function open($save_path, $name)
    {
        $this->cookie = $name . '_2';

        // Read the incoming value, then clear the cookie - we might not be able
        // to do so later if write() is called after headers are sent
        // This is intended to force a failover to the database store if the
        // modified session cannot be emitted.
        $this->currentCookieData = Cookie::get($this->cookie);

        if ($this->currentCookieData) {
            Cookie::set($this->cookie, '');
        }
    }

    public function close()
    {
    }

    /**
     * Get the cryptography store for the specified session
     *
     * @param string $session_id
     * @return HybridSessionStore_Crypto
     */
    protected function getCrypto($session_id)
    {
        $key = $this->getKey();

        if (!$key) {
            return null;
        }

        if (!$this->crypto || $this->crypto->getSalt() != $session_id) {
            $this->crypto = Injector::inst()->create(CryptoHandler::class, $key, $session_id);
        }

        return $this->crypto;
    }

    public function read($session_id)
    {
        // Check ability to safely decrypt content
        if (!$this->currentCookieData
            || !($crypto = $this->getCrypto($session_id))
        ) {
            return;
        }

        // Decrypt and invalidate old data
        $cookieData = $crypto->decrypt($this->currentCookieData);
        $this->currentCookieData = null;

        // Verify expiration
        if ($cookieData) {
            $expiry = (int)substr($cookieData, 0, 10);
            $data = substr($cookieData, 10);

            if ($expiry > $this->getNow()) {
                return $data;
            }
        }
    }

    /**
     * Determine if the session could be verifably written to cookie storage
     *
     * @return bool
     */
    protected function canWrite()
    {
        return !headers_sent();
    }

    public function write($session_id, $session_data)
    {
        $canWrite = $this->canWrite();
        $isExceedingCookieLimit = (strlen($session_data) > static::config()->get('max_length'));
        $crypto = $this->getCrypto($session_id);

        // Check ability to safely encrypt and write content
        if (!$canWrite || $isExceedingCookieLimit || !$crypto) {
            if ($canWrite && $isExceedingCookieLimit) {
                $params = session_get_cookie_params();
                // Clear stored cookie value and cookie when length exceeds the set limit
                $this->currentCookieData = null;
                Cookie::set(
                    $this->cookie,
                    '',
                    0,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            return false;
        }

        // Prepare content for write
        $params = session_get_cookie_params();
        // Total max lifetime, stored internally
        $lifetime = $this->getLifetime();
        $expiry = $this->getNow() + $lifetime;

        // Restore the known good cookie value
        $this->currentCookieData = $this->crypto->encrypt(
            sprintf('%010u', $expiry) . $session_data
        );

        // Respect auto-expire on browser close for the session cookie (in case the cookie lifetime is zero)
        $cookieLifetime = min((int)$params['lifetime'], $lifetime);

        Cookie::set(
            $this->cookie,
            $this->currentCookieData,
            $cookieLifetime / 86400,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );

        return true;
    }

    public function destroy($session_id)
    {
        $this->currentCookieData = null;

        $params = session_get_cookie_params();

        Cookie::force_expiry(
            $this->cookie,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    public function gc($maxlifetime)
    {
        // NOP
    }
}
