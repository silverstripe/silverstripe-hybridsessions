<?php

namespace SilverStripe\HybridSessions\Store;

use SilverStripe\HybridSessions\Store\Base;
use SilverStripe\Core\Injector\Injector;

class SessionStore extends DatabaseStore
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

    public function setHandlers($handlers)
    {
        $this->handlers = $handlers;
        $this->setKey($this->getKey());
    }

    public function setKey($key)
    {
        parent::setKey($key);

        foreach ($this->handlers as $handler) {
            $handler->setKey($key);
        }
    }

    /**
     * @return array[SessionHandlerInterface]
     */
    public function getHandlers()
    {
        return $this->handlers;
    }

    public function open($save_path, $name)
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                $handler->open($save_path, $name);
            }
        } else {
            parent::open($save_path, $name);
        }

        return true;
    }

    public function close()
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                $handler->close();
            }
        } else {
            parent::open($save_path, $name);
        }

        return true;
    }

    public function read($session_id)
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                if ($data = $handler->read($session_id)) {
                    return $data;
                }
            }
        } else {
            return parent::read($session_id);
        }

        return '';
    }

    public function write($session_id, $session_data)
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                if ($handler->write($session_id, $session_data)) {
                    return;
                }
            }
        } else {
            parent::write($session_id, $session_data);

            return;
        }
    }

    public function destroy($session_id)
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                $handler->destroy($session_id);
            }
        } else {
            parent::destroy($session_id);
        }
    }

    public function gc($maxlifetime)
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                $handler->gc($maxlifetime);
            }
        } else {
            parent::gc($maxlifetime);
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
                'SessionStore::init() was not given a $key. Disabling cookie-based storage',
                E_USER_WARNING
            );
        } else {
            $instance->setKey($key);
        }

        register_sessionhandler($instance);

        self::$enabled = true;
    }

    public static function is_enabled()
    {
        return self::$enabled;
    }
}
