<?php

/**
 * PHP 5.4 defines SessionHandlerInterface, but PHP 5.3 doesn't. For backwards compatibility, if it doesn't exist
 * (and no other fallback exists in other libraries) then define it.
 *
 * Then, either way, add a new function "register_sessionhandler" which takes a SessionHandlerInterface and
 * registers it (including registering session_write_close as a shutdown function)
 */
if(!interface_exists('SessionHandlerInterface')) {
	interface SessionHandlerInterface {
		/* Methods */
		public function close();
		public function destroy($session_id);
		public function gc($maxlifetime);
		public function open($save_path, $name);
		public function read($session_id);
		public function write($session_id, $session_data);
	}
}

if(version_compare(PHP_VERSION, '5.4.0', '<')) {
	function register_sessionhandler($handler) {
		session_set_save_handler(
			array($handler, 'open'),
			array($handler, 'close'),
			array($handler, 'read'),
			array($handler, 'write'),
			array($handler, 'destroy'),
			array($handler, 'gc')
		);

		register_shutdown_function('session_write_close');
	}
}
else {
	function register_sessionhandler($handler) {
		session_set_save_handler($handler, true);
	}
}

/***
 * For <PHP 5.5 compatibility
 * Reference: http://php.net/manual/en/function.hash-equals.php#115635
 ***/
if(!function_exists('hash_equals')) {
    function hash_equals($str1, $str2) {
        if(strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;
            for($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
            return !$ret;
        }
    }
}
/**
 * Class HybridSessionStore_Crypto
 * Some cryptography used for Session cookie encryption. Requires the OpenSSL PHP extension.
 *
 */
class HybridSessionStore_Crypto {

	private $key;

	public $salt;
	private $saltedKey;

	/**
	 * @param $key a per-site secret string which is used as the base encryption key.
	 * @param $salt a per-session random string which is used as a salt to generate a per-session key
	 *
	 * The base encryption key needs to stay secret. If an attacker ever gets it, they can read their session,
	 * and even modify & re-sign it.
	 *
	 * The salt is a random per-session string that is used with the base encryption key to create a per-session key.
	 * This (amongst other things) makes sure an attacker can't use a known-plaintext attack to guess the key.
	 *
	 * Normally we could create a salt on encryption, send it to the client as part of the session (it doesn't
	 * need to remain secret), then use the returned salt to decrypt. But we already have the Session ID which makes
	 * a great salt, so no need to generate & handle another one.
	 */
	public function __construct($key, $salt) {
		$this->key = $key;
		$this->salt = $salt;
		$this->saltedKey = hash_pbkdf2('sha256', $this->key, $this->salt, 1000, 0, true);
	}

	/**
	 * Encrypt and then sign some cleartext
	 *
	 * @param $cleartext - The cleartext to encrypt and sign
	 * @return string - The encrypted-and-signed message as base64 ASCII.
	 */
	public function encrypt($cleartext) {
		$cipher = "AES-256-CBC";
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertext_raw = openssl_encrypt($cleartext, $cipher, $this->saltedKey, OPENSSL_RAW_DATA, $iv);
		$hmac = hash_hmac('sha256', $ciphertext_raw, $this->saltedKey, true);
		$ciphertext = base64_encode($iv.$hmac.$ciphertext_raw);

		return base64_encode($iv.$hmac.$ciphertext_raw);
	}

	/**
	 * Check the signature on an encrypted-and-signed message, and if valid decrypt the content
	 *
	 * @param $data - The encrypted-and-signed message as base64 ASCII
	 * @return bool|string - The decrypted cleartext or false if signature failed
	 */
	public function decrypt($data) {
		$c = base64_decode($data);
		$cipher = "AES-256-CBC";
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = substr($c, 0, $ivlen);
		$hmac = substr($c, $ivlen, $sha2len = 32);
		$ciphertext_raw = substr($c, $ivlen+$sha2len);
		$cleartext = openssl_decrypt($ciphertext_raw, $cipher, $this->saltedKey, OPENSSL_RAW_DATA, $iv);
		$calcmac = hash_hmac('sha256', $ciphertext_raw, $this->saltedKey, true);

		if (hash_equals($hmac, $calcmac)) {
			return $cleartext;
		}

		return false;
	}
}

abstract class HybridSessionStore_Base implements SessionHandlerInterface {

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
	public function setKey($key) {
		$this->key = $key;
	}

	/**
	 * Get the session secret key
	 *
	 * @return string
	 */
	protected function getKey() {
		return $this->key;
	}

	/**
	 * Get lifetime in number of seconds
	 *
	 * @return int
	 */
	protected function getLifetime() {
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
	protected function getNow() {
		return (int)SS_Datetime::now()->Format('U');
	}
}

/**
 * Class HybridSessionStore_Cookie
 *
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
class HybridSessionStore_Cookie extends HybridSessionStore_Base {

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

	public function open($save_path, $name) {
		$this->cookie = $name.'_2';
		// Read the incoming value, then clear the cookie - we might not be able
		// to do so later if write() is called after headers are sent
		// This is intended to force a failover to the database store if the
		// modified session cannot be emitted.
		$this->currentCookieData = Cookie::get($this->cookie);
		if ($this->currentCookieData) Cookie::set($this->cookie, '');
	}

	public function close() {
	}

	/**
	 * Get the cryptography store for the specified session
	 *
	 * @param string $session_id
	 * @return HybridSessionStore_Crypto
	 */
	protected function getCrypto($session_id) {
		$key = $this->getKey();
		if(!$key) return null;
		if (!$this->crypto || $this->crypto->salt != $session_id) {
			$this->crypto = new HybridSessionStore_Crypto($key, $session_id);
		}
		return $this->crypto;
	}

	public function read($session_id) {
		// Check ability to safely decrypt content
		if(!$this->currentCookieData
			|| !($crypto = $this->getCrypto($session_id))
		) return;

		// Decrypt and invalidate old data
		$cookieData = $crypto->decrypt($this->currentCookieData);
		$this->currentCookieData = null;

		// Verify expiration
		if ($cookieData) {
			$expiry = (int)substr($cookieData, 0, 10);
			$data = substr($cookieData, 10);

			if ($expiry > $this->getNow()) return $data;
		}
	}

	/**
	 * Determine if the session could be verifably written to cookie storage
	 *
	 * @return bool
	 */
	protected function canWrite() {
		return !headers_sent();
	}

	public function write($session_id, $session_data) {
		// Check ability to safely encrypt and write content
		if(!$this->canWrite()
			|| (strlen($session_data) > Config::inst()->get(__CLASS__, 'max_length'))
			|| !($crypto = $this->getCrypto($session_id))
		) return false;

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

	public function destroy($session_id) {
		$this->currentCookieData = null;
		Cookie::force_expiry($this->cookie);
	}

	public function gc($maxlifetime) {
		// NOP
	}
}

class HybridSessionStore_Database extends HybridSessionStore_Base {

	/**
	 * Determine if the DB is ready to use.
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function isDatabaseReady() {
		// Such as during setup of testsession prior to DB connection.
		if(!DB::isActive()) return false;

		// If we have a DB of the wrong type then complain
		if (!(DB::getConn() instanceof MySQLDatabase)) {
			throw new Exception('HybridSessionStore currently only works with MySQL databases');
		}

		// Prevent freakout during dev/build
		return ClassInfo::hasTable('HybridSessionDataObject');
	}

	public function open($save_path, $name) {
	}

	public function close() {
	}

	public function read($session_id) {
		if(!$this->isDatabaseReady()) return null;

		$result = DB::query(sprintf(
			'SELECT "Data" FROM "HybridSessionDataObject"
			WHERE "SessionID" = \'%s\' AND "Expiry" >= %u',
			Convert::raw2sql($session_id),
			$this->getNow()
		));

		if ($result && $result->numRecords()) {
			$data = $result->first();
            $decoded = $this->binaryDataJsonDecode($data['Data']);
            return is_null($decoded) ? $data['Data'] : $decoded;
		}
	}

	public function write($session_id, $session_data) {
		if(!$this->isDatabaseReady()) return false;

		$expiry = $this->getNow() + $this->getLifetime();
		DB::query($str = sprintf(
			'INSERT INTO "HybridSessionDataObject" ("SessionID", "Expiry", "Data")
			VALUES (\'%1$s\', %2$u, \'%3$s\')
			ON DUPLICATE KEY UPDATE "Expiry" = %2$u, "Data" = \'%3$s\'',
			Convert::raw2sql($session_id),
			$expiry,
            Convert::raw2sql($this->binaryDataJsonEncode($session_data))
		));

		return true;
	}

    /**
     * Encode binary data into ASCII string (a subset of UTF-8)
     *
     * Silverstripe <= 4.4 does not have a binary db field implementation, so we have to store
     * binary data as text
     *
     * @param string $data This is a binary blob
     *
     * @return string
     */
    private function binaryDataJsonEncode($data)
    {
        return json_encode([
            self::class,
            base64_encode($data)
        ]);
    }

    /**
     * Decode ASCII string into original binary data (a php string)
     *
     * Silverstripe <= 4.4 does not have a binary db field implementation, so we have to store
     * binary data as text
     *
     * @param string $text
     *
     * @param null|string
     */
    private function binaryDataJsonDecode($text)
    {
        $struct = json_decode($text, true, 2);
        if (!is_array($struct) || count($struct) !== 2) {
            return null;
        }
        if (!isset($struct[0]) || !isset($struct[1]) || $struct[0] !== self::class) {
            return null;
        }
        return base64_decode($struct[1]);
    }

	public function destroy($session_id) {
		// NOP
	}

	public function gc($maxlifetime) {
		if(!$this->isDatabaseReady()) return;
		DB::query(sprintf(
			'DELETE FROM "HybridSessionDataObject" WHERE "Expiry" < %u',
			$this->getNow()
		));
	}
}


class HybridSessionStore extends HybridSessionStore_Base {

	/**
	 * List of session handlers
	 *
	 * @var array[HybridSessionStore_Base]
	 */
	protected $handlers = array();

	/**
	 * True if this session store has been initialised
	 *
	 * @var bool
	 */
	protected static $enabled = false;

	/**
	 * @param array[HybridSessionStore_Base]
	 */
	public function setHandlers($handlers) {
		$this->handlers = $handlers;
		$this->setKey($this->getKey());
	}

	public function setKey($key) {
		parent::setKey($key);
		foreach($this->handlers as $handler) {
			$handler->setKey($key);
		}
	}

	/**
	 * @return array[SessionHandlerInterface]
	 */
	public function getHandlers() {
		return $this->handlers;
	}

	public function open($save_path, $name) {
		foreach ($this->handlers as $handler) {
			$handler->open($save_path, $name);
		}

		return true;
	}

	public function close(){
		foreach ($this->handlers as $handler) {
			$handler->close();
		}

		return true;
	}

	public function read($session_id) {
		foreach ($this->handlers as $handler) {
			if ($data = $handler->read($session_id)) return $data;
		}

		return '';
	}

	public function write($session_id, $session_data) {
		foreach ($this->handlers as $handler) {
			if ($handler->write($session_id, $session_data)) return true;
		}
	}

	public function destroy($session_id) {
		foreach ($this->handlers as $handler) {
			$handler->destroy($session_id);
		}
		return true;
	}

	public function gc($maxlifetime) {
		foreach ($this->handlers as $handler) {
			$handler->gc($maxlifetime);
		}
	}

	/**
	 * Register the session handler as the default
	 *
	 * @param string $key Desired session key
	 */
	public static function init($key = null) {
		$instance = Injector::inst()->get(__CLASS__);
		if(empty($key)) {
			user_error(
				'HybridSessionStore::init() was not given a $key. Disabling cookie-based storage',
				E_USER_WARNING
			);
		} else {
			$instance->setKey($key);
		}
		register_sessionhandler($instance);
		self::$enabled = true;
	}

	public static function is_enabled() {
		return self::$enabled;
	}
}

class HybridSessionStore_RequestFilter implements RequestFilter {
	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {
		// NOP
	}

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {
		if(HybridSessionStore::is_enabled()) {
			session_write_close();
		}
	}
}
