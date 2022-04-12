<?php

namespace SilverStripe\HybridSessions\Crypto;

/**
 * Some cryptography used for Session cookie encryption.
 *
 */
class OpenSSLCrypto implements CryptoHandler
{
    protected $key;

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
     * @param string $key a per-site secret string which is used as the base encryption key.
     * @param string $salt a per-session random string which is used as a salt to generate a per-session key
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
        $this->key = $key;
        $this->salt = $salt;
        $this->saltedKey = hash_pbkdf2('sha256', $this->key ?? '', $this->salt ?? '', 1000, 0, true);
    }

    /**
     * Encrypt and then sign some cleartext
     *
     * @param string $cleartext - The cleartext to encrypt and sign
     * @return string - The encrypted-and-signed message as base64 ASCII.
     */
    public function encrypt($cleartext)
    {
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher ?? '');
        $iv = openssl_random_pseudo_bytes($ivlen ?? 0);
        $ciphertext_raw = openssl_encrypt(
            $cleartext ?? '',
            $cipher ?? '',
            $this->saltedKey ?? '',
            $options = OPENSSL_RAW_DATA,
            $iv ?? ''
        );
        $hmac = hash_hmac('sha256', $ciphertext_raw ?? '', $this->saltedKey ?? '', $as_binary = true);
        $ciphertext = base64_encode($iv . $hmac . $ciphertext_raw);

        return base64_encode($iv . $hmac . $ciphertext_raw);
    }

    /**
     * Check the signature on an encrypted-and-signed message, and if valid
     * decrypt the content
     *
     * @param string $data - The encrypted-and-signed message as base64 ASCII
     *
     * @return bool|string - The decrypted cleartext or false if signature failed
     */
    public function decrypt($data)
    {
        $c = base64_decode($data ?? '');
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher ?? '');
        $iv = substr($c ?? '', 0, $ivlen);
        $hmac = substr($c ?? '', $ivlen ?? 0, $sha2len = 32);
        $ciphertext_raw = substr($c ?? '', $ivlen + $sha2len);
        $cleartext = openssl_decrypt(
            $ciphertext_raw ?? '',
            $cipher ?? '',
            $this->saltedKey ?? '',
            $options = OPENSSL_RAW_DATA,
            $iv ?? ''
        );
        $calcmac = hash_hmac('sha256', $ciphertext_raw ?? '', $this->saltedKey ?? '', $as_binary = true);

        if (hash_equals($hmac ?? '', $calcmac ?? '')) {
            return $cleartext;
        }

        return false;
    }
}
