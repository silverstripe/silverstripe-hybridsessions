<?php

namespace SilverStripe\HybridSessions\Crypto;

/**
 * Some cryptography used for Session cookie encryption. Requires the mcrypt extension.
 *
 * @deprecated 2.2.0 The PHP mcrypt library is deprecated. Please use OpenSSLCrypto instead.
 *
 * WARNING: Please beware that McryptCrypto does not preserve zero bytes at the end of encrypted messages.
 *          Thus, a message such as "data\x00" will become "data" after encrypt-decrypt.
 *          As such, it is not binary safe.
 *          It is guaranteed for UTF-8 encoded text not to have zero bytes. However, other encodings may contain those.
 *          For example, be careful with UTF-16LE, since characters less than U+0100 are very common.
 */
class McryptCrypto implements CryptoHandler
{
    protected $key;

    protected $ivSize;

    protected $keySize;

    protected $salt;

    protected $saltedKey;

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

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
    public function __construct($key, $salt)
    {
        $this->ivSize = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
        $this->keySize = mcrypt_get_key_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);

        $this->key = $key;
        $this->salt = $salt;
        $this->saltedKey = hash_pbkdf2('sha256', $this->key, $this->salt, 1000, $this->keySize, true);
    }

    /**
     * Encrypt and then sign some cleartext
     *
     * @param $cleartext - The cleartext to encrypt and sign
     * @return string - The encrypted-and-signed message as base64 ASCII.
     */
    public function encrypt($cleartext)
    {
        $iv = mcrypt_create_iv($this->ivSize, MCRYPT_DEV_URANDOM);

        $enc = mcrypt_encrypt(
            MCRYPT_RIJNDAEL_256,
            $this->saltedKey,
            $cleartext,
            MCRYPT_MODE_CBC,
            $iv
        );

        $hash = hash_hmac('sha256', $enc, $this->saltedKey);

        return base64_encode($iv . $hash . $enc);
    }

    /**
     * Check the signature on an encrypted-and-signed message, and if valid
     * decrypt the content
     *
     * @param $data - The encrypted-and-signed message as base64 ASCII
     *
     * @return bool|string - The decrypted cleartext or false if signature failed
     */
    public function decrypt($data)
    {
        $data = base64_decode($data);

        $iv   = substr($data, 0, $this->ivSize);
        $hash = substr($data, $this->ivSize, 64);
        $enc  = substr($data, $this->ivSize + 64);

        $cleartext = rtrim(mcrypt_decrypt(
            MCRYPT_RIJNDAEL_256,
            $this->saltedKey,
            $enc,
            MCRYPT_MODE_CBC,
            $iv
        ), "\x00");

        // Needs to be after decrypt so it always runs, to avoid timing attack
        $gen_hash = hash_hmac('sha256', $enc, $this->saltedKey);

        if ($gen_hash == $hash) {
            return $cleartext;
        }
        return false;
    }
}
