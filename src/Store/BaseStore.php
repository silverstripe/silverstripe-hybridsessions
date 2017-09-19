<?php

namespace SilverStripe\HybridSessions\Store;

use SessionHandlerInterface;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Core\Config\Configurable;

abstract class BaseStore implements SessionHandlerInterface
{
    use Configurable;

    /**
     * Session secret key
     *
     * @var string
     */
    protected $key = null;

    /**
     * Assign a new session secret key
     *
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * Get the session secret key
     *
     * @return string
     */
    protected function getKey()
    {
        return $this->key;
    }

    /**
     * Get lifetime in number of seconds
     *
     * @return int
     */
    protected function getLifetime()
    {
        $params = session_get_cookie_params();
        $cookieLifetime = (int)$params['lifetime'];
        $gcLifetime = (int)ini_get('session.gc_maxlifetime');

        return $cookieLifetime ? min($cookieLifetime, $gcLifetime) : $gcLifetime;
    }

    /**
     * Gets the current unix timestamp
     *
     * @return int
     */
    protected function getNow()
    {
        return (int) DBDatetime::now()->getTimestamp();
    }
}
