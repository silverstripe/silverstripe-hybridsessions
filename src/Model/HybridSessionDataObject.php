<?php

namespace SilverStripe\HybridSessions;

use SilverStripe\ORM\DataObject;

class HybridSessionDataObject extends DataObject
{
    private static $db = [
        'SessionID' => 'Varchar(64)',
        'Expiry' => 'Int',
        'Data' => 'Text'
    ];

    private static $indexes = [
        'SessionID' => [
            'type' => 'unique'
        ],
        'Expiry' => true
    ];

    private static $table_name = 'HybridSessionDataObject';
}
