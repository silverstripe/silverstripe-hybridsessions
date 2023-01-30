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
     */
    protected ?string $key = null;

    /**
     * Assign a new session secret key
     */
    public function setKey(?string $key): void
    {
        $this->key = $key;
    }

    /**
     * Get the session secret key
     */
    protected function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Get lifetime in number of seconds
     */
    protected function getLifetime(): int
    {
        $params = session_get_cookie_params();
        $cookieLifetime = (int)$params['lifetime'];
        $gcLifetime = (int)ini_get('session.gc_maxlifetime');

        return $cookieLifetime ? min($cookieLifetime, $gcLifetime) : $gcLifetime;
    }

    /**
     * Gets the current unix timestamp
     */
    protected function getNow(): int
    {
        return (int) DBDatetime::now()->getTimestamp();
    }
}
