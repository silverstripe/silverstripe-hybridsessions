<?php

class HybridSessionDataObject extends DataObject {
	private static $db = array(
		'SessionID' => 'Varchar(64)',
		'Expiry' => 'Int',
		'Data' => 'Text'
	);

	private static $indexes = array(
		'SessionID' => array('type' => 'unique', 'value' => '"SessionID"'),
		'Expiry' => true
	);
}
