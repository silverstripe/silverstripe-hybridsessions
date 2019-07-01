<?php

namespace SilverStripe\HybridSessions\Store;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\HybridSessions\Store\BaseStore;
use Exception;

class DatabaseStore extends BaseStore
{

    /**
     * Determine if the DB is ready to use.
     *
     * @return bool
     * @throws Exception
     */
    protected function isDatabaseReady()
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

    public function open($save_path, $name)
    {
        // no-op
    }

    public function close()
    {
        // no-op
    }

    public function read($session_id)
    {
        if (!$this->isDatabaseReady()) {
            return null;
        }

        $query = sprintf(
            'SELECT "Data" FROM "HybridSessionDataObject"
            WHERE "SessionID" = \'%s\' AND "Expiry" >= %s',
            Convert::raw2sql($session_id),
            $this->getNow()
        );

        $result = DB::query($query);

        if ($result && $result->numRecords()) {
            $data = $result->first();
            $decoded = static::binaryDataJsonDecode($data['Data']);
            return is_null($decoded) ? $data['Data'] : $decoded;
        }
    }

    public function write($session_id, $session_data)
    {
        if (!$this->isDatabaseReady()) {
            return false;
        }

        $expiry = $this->getNow() + $this->getLifetime();

        DB::query($str = sprintf(
            'INSERT INTO "HybridSessionDataObject" ("SessionID", "Expiry", "Data")
            VALUES (\'%1$s\', %2$u, \'%3$s\')
            ON DUPLICATE KEY UPDATE "Expiry" = %2$u, "Data" = \'%3$s\'',
            Convert::raw2sql($session_id),
            $expiry,
            Convert::raw2sql(static::binaryDataJsonEncode($session_data))
        ));

        return true;
    }

    public function destroy($session_id)
    {
        // NOP
    }

    public function gc($maxlifetime)
    {
        if (!$this->isDatabaseReady()) {
            return;
        }

        DB::query(sprintf(
            'DELETE FROM "HybridSessionDataObject" WHERE "Expiry" < %u',
            $this->getNow()
        ));
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
    public static function binaryDataJsonEncode($data)
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
    public static function binaryDataJsonDecode($text)
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
}
