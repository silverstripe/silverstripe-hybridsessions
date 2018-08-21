<?php

namespace SilverStripe\HybridSessions;

use SessionHandlerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\HybridSessions\Store\BaseStore;

class HybridSession extends BaseStore
{

    /**
     * List of session handlers
     *
     * @var array
     */
    protected $handlers = [];

    /**
     * True if this session store has been initialised
     *
     * @var bool
     */
    protected static $enabled = false;

    /**
     * @param SessionHandlerInterface[]
     *
     * @return $this
     */
    public function setHandlers($handlers)
    {
        $this->handlers = $handlers;
        $this->setKey($this->getKey());

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setKey($key)
    {
        parent::setKey($key);

        foreach ($this->getHandlers() as $handler) {
            $handler->setKey($key);
        }

        return $this;
    }

    /**
     * @return SessionHandlerInterface[]
     */
    public function getHandlers()
    {
        return $this->handlers ?: [];
    }

    /**
     * @param string $save_path
     * @param string $name
     *
     * @return bool
     */
    public function open($save_path, $name)
    {
        foreach ($this->getHandlers() as $handler) {
            $handler->open($save_path, $name);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {
        foreach ($this->getHandlers() as $handler) {
            $handler->close();
        }

        return true;
    }

    /**
     * @param string $session_id
     *
     * @return string
     */
    public function read($session_id)
    {
        foreach ($this->getHandlers() as $handler) {
            if ($data = $handler->read($session_id)) {
                return $data;
            }
        }

        return '';
    }

    public function write($session_id, $session_data)
    {
        foreach ($this->getHandlers() as $handler) {
            if ($handler->write($session_id, $session_data)) {
                return true;
            }
        }

        return false;
    }

    public function destroy($session_id)
    {
        foreach ($this->getHandlers() as $handler) {
            $handler->destroy($session_id);
        }

        return true;
    }

    public function gc($maxlifetime)
    {
        foreach ($this->getHandlers() as $handler) {
            $handler->gc($maxlifetime);
        }
    }

    /**
     * Register the session handler as the default
     *
     * @param string $key Desired session key
     */
    public static function init($key = null)
    {
        $instance = Injector::inst()->get(__CLASS__);

        if (empty($key)) {
            user_error(
                'HybridSession::init() was not given a $key. Disabling cookie-based storage',
                E_USER_WARNING
            );
        } else {
            $instance->setKey($key);
        }

        session_set_save_handler($instance, true);

        self::$enabled = true;
    }

    public static function is_enabled()
    {
        return self::$enabled;
    }
}
