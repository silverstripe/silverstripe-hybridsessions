<?php

namespace SilverStripe\HybridSessions\Store;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\HybridSessions\Store\BaseStore;
use Exception;
use SilverStripe\HybridSessions\HybridSessionDataObject;

class DatabaseStore extends BaseStore
{
    /**
     * Hashing algorithm used to encrypt $session_id (PHPSESSID)
     * Ensure that HybridSessionDataObject.SessionID is wide enough to accomodate the hash
     */
    private const HASH_ALGO = 'sha256';

    private ?bool $hashAlgoAvailable = null;

    /**
     * Determine if the DB is ready to use.
     * @throws Exception
     */
    protected function isDatabaseReady(): bool
    {
        // Such as during setup of testsession prior to DB connection.
        if (!DB::is_active()) {
            return false;
        }

        // If we have a DB of the wrong type then complain
        if (!(DB::get_conn() instanceof MySQLDatabase)) {
            throw new Exception('HybridSessions\Store\DatabaseStore currently only works with MySQL databases');
        }

        // Prevent freakout during dev/build
        return ClassInfo::hasTable('HybridSessionDataObject');
    }

    public function open(string $save_path, string $name): bool
    {
        // There's nothing for us to do to initialise the session.
        // We just return true to indicate that the store is ready to read/write session data.
        return true;
    }


    public function close(): bool
    {
        // There's nothing for us to do to close the session.
        // Returning false would indicate an error.
        return true;
    }

    public function read(string $session_id): string|false
    {
        if (!$this->isDatabaseReady()) {
            return false;
        }

        $query = sprintf(
            'SELECT "Data" FROM "HybridSessionDataObject"
            WHERE "SessionID" = \'%s\' AND "Expiry" >= %s',
            Convert::raw2sql($this->encryptSessionID($session_id)),
            $this->getNow()
        );

        $result = DB::query($query);

        if ($result && $result->numRecords()) {
            $data = $result->record();
            $decoded = static::binaryDataJsonDecode($data['Data']);
            return is_null($decoded) ? $data['Data'] : $decoded;
        }

        return false;
    }

    public function write(string $session_id, string $session_data): bool
    {
        if (!$this->isDatabaseReady()) {
            return false;
        }

        $expiry = $this->getNow() + $this->getLifetime();

        DB::query($str = sprintf(
            'INSERT INTO "HybridSessionDataObject" ("SessionID", "Expiry", "Data")
            VALUES (\'%1$s\', %2$u, \'%3$s\')
            ON DUPLICATE KEY UPDATE "Expiry" = %2$u, "Data" = \'%3$s\'',
            Convert::raw2sql($this->encryptSessionID($session_id)),
            $expiry,
            Convert::raw2sql(static::binaryDataJsonEncode($session_data))
        ));

        return true;
    }

    public function destroy(string $session_id): bool
    {
        if (!$this->isDatabaseReady()) {
            return false;
        }
        DB::query(sprintf(
            'DELETE FROM "HybridSessionDataObject"
            WHERE "SessionID" = \'%s\'',
            Convert::raw2sql($this->encryptSessionID($session_id))
        ));
        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        if (!$this->isDatabaseReady()) {
            return false;
        }

        DB::query(sprintf(
            'DELETE FROM "HybridSessionDataObject" WHERE "Expiry" < %u',
            $this->getNow()
        ));

        return DB::affected_rows();
    }

    /**
    * Encode binary data into ASCII string (a subset of UTF-8)
    *
    * Silverstripe <= 4.4 does not have a binary db field implementation, so we have to store
    * binary data as text
    *
    * @param string $data This is a binary blob
    */
    public static function binaryDataJsonEncode(string $data): string
    {
        return json_encode([
            DatabaseStore::class,
            base64_encode($data ?? '')
        ]);
    }

    /**
     * Decode ASCII string into original binary data (a php string)
     *
     * Silverstripe <= 4.4 does not have a binary db field implementation, so we have to store
     * binary data as text
     */
    public static function binaryDataJsonDecode(string $text): ?string
    {
        $struct = json_decode($text ?? '', true, 2);

        if (!is_array($struct) || count($struct ?? []) !== 2) {
            return null;
        }

        if (!isset($struct[0]) || !isset($struct[1]) || $struct[0] !== DatabaseStore::class) {
            return null;
        }

        return base64_decode($struct[1] ?? '');
    }

    private function encryptSessionID(string $sessionID): string
    {
        if (is_null($this->hashAlgoAvailable)) {
            $this->hashAlgoAvailable = in_array(DatabaseStore::HASH_ALGO, hash_algos());
        }
        return $this->hashAlgoAvailable ? hash(DatabaseStore::HASH_ALGO, $sessionID) : $sessionID;
    }
}
