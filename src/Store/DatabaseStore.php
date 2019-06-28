<?php

namespace SilverStripe\HybridSessions\Store;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\HybridSessions\Store\BaseStore;
use SilverStripe\HybridSessions\Store\DatabaseStore\DataCodec;
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
            $decoded = DataCodec::decode($data['Data']);
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
            Convert::raw2sql(DataCodec::encode($session_data))
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
}
