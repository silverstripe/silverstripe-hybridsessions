<?php

namespace SilverStripe\HybridSessions\Store\DatabaseStore;

/**
 * Encoding and Decoding binary data into text (UTF-8) with a simple json markup that ensures
 * it's not going to decode some random data (backward compatibility)
 *
 * Silverstripe 4.4 does not have a binary database field implementation, so we have to store
 * binary data as text.
 *
 * @internal This class is internal API of DatabaseStore and may change without warnings
 */
class DataCodec
{
    /**
     * Encode binary data into ASCII string (a subset of UTF-8)
     *
     * @param string $data This is a binary blob
     *
     * @return string
     */
    public static function encode($data) {
        return json_encode([
            self::class,
            base64_encode($data)
        ]);
    }

    /**
     * Decode ASCII string into original binary data (a php string)
     *
     * @param string $text
     *
     * @param null|string
     */
    public static function decode($text) {
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
