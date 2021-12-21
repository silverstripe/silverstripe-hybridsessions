<?php

namespace SilverStripe\HybridSessions\Crypto;

interface CryptoHandler
{
    /**
     * @param string $data
     *
     * @return string
     */
    public function encrypt($data);

    /**
     * @param string $data
     *
     * @return string
     */
    public function decrypt($data);

    /**
     * @return string
     */
    public function getKey();

    /**
     * @return string
     */
    public function getSalt();
}
