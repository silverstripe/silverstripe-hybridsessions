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
     * @var SessionHandlerInterface[]
     */
    protected array $handlers = [];

    /**
     * True if this session store has been initialised
     */
    protected static bool $enabled = false;

    /**
     * @param SessionHandlerInterface[]
     */
    public function setHandlers(array $handlers): static
    {
        $this->handlers = $handlers;
        $this->setKey($this->getKey());

        return $this;
    }

    public function setKey(?string $key): void
    {
        parent::setKey($key);
        foreach ($this->getHandlers() as $handler) {
            if (method_exists($handler, 'setKey') && is_callable([$handler, 'setKey'])) {
                $handler->setKey($key);
            }
        }
    }

    /**
     * @return SessionHandlerInterface[]
     */
    public function getHandlers(): array
    {
        return $this->handlers ?: [];
    }

    public function open(string $save_path, string $name): bool
    {
        foreach ($this->getHandlers() as $handler) {
            $handler->open($save_path, $name);
        }

        return true;
    }

    public function close(): bool
    {
        foreach ($this->getHandlers() as $handler) {
            $handler->close();
        }

        return true;
    }

    public function read(string $session_id): string|false
    {
        foreach ($this->getHandlers() as $handler) {
            if ($data = $handler->read($session_id)) {
                return $data;
            }
        }

        return false;
    }

    public function write(string $session_id, string $session_data): bool
    {
        foreach ($this->getHandlers() as $handler) {
            if ($handler->write($session_id, $session_data)) {
                return true;
            }
        }

        return false;
    }

    public function destroy(string $session_id): bool
    {
        foreach ($this->getHandlers() as $handler) {
            $handler->destroy($session_id);
        }

        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        $killedSession = 0;
        foreach ($this->getHandlers() as $handler) {
            $handler->gc($maxlifetime);
            $killedSession++;
        }

        return $killedSession;
    }

    /**
     * Register the session handler as the default
     *
     * @param string $key Desired session key
     */
    public static function init(string $key = null)
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

    public static function is_enabled(): bool
    {
        return self::$enabled;
    }
}
