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
        // Our interpretation of "or creates a new one" in
        // https://www.php.net/manual/en/sessionhandlerinterface.open.php allows for the session to have been created
        // in memory, to be stored by the appropriate handler on session_write_close(). If the session fails to be
        // written, we return false in write(), so we will be alerted to there being some error.
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
        // Return a blank string if no record was found in any store
        // The "session" still exists in memory until a new record is created in the first writable store in write()
        // Our interpretation then of "If the record was not found" in
        // https://www.php.net/manual/en/sessionhandlerinterface.read.php is that we have "found" the NEW session
        // in memory. This prevents `PHP Warning: session_start(): Failed to read session data: user`
        // when session_start() is called within SilverStripe\Control\Session::start()
        return '';
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

        HybridSession::$enabled = true;
    }

    public static function is_enabled(): bool
    {
        return HybridSession::$enabled;
    }
}
