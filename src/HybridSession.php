<?php

namespace SilverStripe\HybridSessions;

use SilverStripe\HybridSessions\Store\BaseStore;
use SilverStripe\Core\Injector\Injector;

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

        foreach ($this->handlers as $handler) {
            $handler->setKey($key);
        }

        return $this;
    }

    /**
     * @return SessionHandlerInterface[]
     */
    public function getHandlers()
    {
        return $this->handlers;
    }

    /**
     * @param string $save_path
     * @param string $name
     *
     * @return bool
     */
    public function open($save_path, $name)
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                $handler->open($save_path, $name);
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                $handler->close();
            }
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
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                if ($data = $handler->read($session_id)) {
                    return $data;
                }
            }
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
        }
    }

    public function destroy($session_id)
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                $handler->destroy($session_id);
            }
        }
    }

    public function gc($maxlifetime)
    {
        if ($this->handlers) {
            foreach ($this->handlers as $handler) {
                $handler->gc($maxlifetime);
            }
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
