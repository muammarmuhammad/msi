<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\Encryption;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\Helper\Security;
use Magento\Framework\Math\Random;

/**
 * Class Encryptor provides basic logic for hashing strings and encrypting/decrypting misc data
 */
class Encryptor implements EncryptorInterface
{
    /**
     * Key of md5 algorithm
     */
    const HASH_VERSION_MD5 = 0;

    /**
     * Key of sha256 algorithm
     */
    const HASH_VERSION_SHA256 = 1;

    /**
     * Key of latest used algorithm
     */
    const HASH_VERSION_LATEST = 1;

    /**
     * Default length of salt in bytes
     */
    const DEFAULT_SALT_LENGTH = 32;

    /**#@+
     * Exploded password hash keys
     */
    const PASSWORD_HASH = 'hash';
    const PASSWORD_SALT = 'salt';
    const PASSWORD_VERSION = 'version';
    /**#@-*/

    /**
     * Array key of encryption key in deployment config
     */
    const PARAM_CRYPT_KEY = 'crypt/key';

    /**#@+
     * Cipher versions
     */
    const CIPHER_BLOWFISH = 0;

    const CIPHER_RIJNDAEL_128 = 1;

    const CIPHER_RIJNDAEL_256 = 2;

    const CIPHER_LATEST = 2;
    /**#@-*/

    /**
     * Default hash string delimiter
     */
    const DELIMITER = ':';

    /**
     * @var array map of hash versions
     */
    private $hashVersionMap = [
        self::HASH_VERSION_MD5 => 'md5',
        self::HASH_VERSION_SHA256 => 'sha256'
    ];

    /**
     * @var array
     */
    private $passwordHashMap = [
        self::PASSWORD_HASH,
        self::PASSWORD_SALT,
        self::PASSWORD_VERSION
    ];

    /**
     * @var array
     */
    private $explodedPasswordHash = [
        self::PASSWORD_HASH => '',
        self::PASSWORD_SALT => '',
        self::PASSWORD_VERSION => self::HASH_VERSION_LATEST
    ];

    /**
     * Indicate cipher
     *
     * @var int
     */
    protected $cipher = self::CIPHER_LATEST;

    /**
     * Version of encryption key
     *
     * @var int
     */
    protected $keyVersion;

    /**
     * Array of encryption keys
     *
     * @var string[]
     */
    protected $keys = [];

    /**
     * @param Random $random
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        Random $random,
        DeploymentConfig $deploymentConfig
    ) {
        $this->random = $random;

        // load all possible keys
        $this->keys = preg_split('/\s+/s', trim($deploymentConfig->get(self::PARAM_CRYPT_KEY)));
        $this->keyVersion = count($this->keys) - 1;
    }

    /**
     * Check whether specified cipher version is supported
     *
     * Returns matched supported version or throws exception
     *
     * @param int $version
     * @return int
     * @throws \Exception
     */
    public function validateCipher($version)
    {
        $types = [self::CIPHER_BLOWFISH, self::CIPHER_RIJNDAEL_128, self::CIPHER_RIJNDAEL_256];

        $version = (int)$version;
        if (!in_array($version, $types, true)) {
            throw new \Exception((string)new \Magento\Framework\Phrase('Not supported cipher version'));
        }
        return $version;
    }

    /**
     * @inheritdoc
     */
    public function getHash($password, $salt = false)
    {
        if ($salt === false) {
            return $this->hash($password);
        }
        if ($salt === true) {
            $salt = self::DEFAULT_SALT_LENGTH;
        }
        if (is_integer($salt)) {
            $salt = $this->random->getRandomString($salt);
        }

        return implode(
            self::DELIMITER,
            [
                $this->hash($salt . $password),
                $salt,
                self::HASH_VERSION_LATEST
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function hash($data, $version = self::HASH_VERSION_LATEST)
    {
        return hash($this->hashVersionMap[(int)$version], $data);
    }

    /**
     * @inheritdoc
     */
    public function validateHash($password, $hash)
    {
        return $this->isValidHash($password, $hash);
    }

    /**
     * @inheritdoc
     */
    public function isValidHash($password, $hash)
    {
        $this->explodePasswordHash($hash);

        return Security::compareStrings(
            $this->hash($this->getPasswordSalt() . $password, $this->getPasswordVersion()),
            $this->getPasswordHash()
        );
    }

    /**
     * @inheritdoc
     */
    public function validateHashVersion($hash)
    {
        $this->explodePasswordHash($hash);

        return $this->getPasswordVersion() === self::HASH_VERSION_LATEST;
    }

    /**
     * @param string $hash
     * @return array
     */
    private function explodePasswordHash($hash)
    {
        $explodedPassword = explode(self::DELIMITER, $hash);

        foreach ($this->passwordHashMap as $key => $hashMapKey) {
            if (isset($explodedPassword[$key])) {
                $this->explodedPasswordHash[$hashMapKey] = $explodedPassword[$key];
            }
        }

        return $this->explodedPasswordHash;
    }

    /**
     * @return string
     */
    private function getPasswordHash()
    {
        return (string)$this->explodedPasswordHash[self::PASSWORD_HASH];
    }

    /**
     * @return string
     */
    private function getPasswordSalt()
    {
        return (string)$this->explodedPasswordHash[self::PASSWORD_SALT];
    }

    /**
     * @return int
     */
    private function getPasswordVersion()
    {
        return (int)$this->explodedPasswordHash[self::PASSWORD_VERSION];
    }

    /**
     * Prepend key and cipher versions to encrypted data after encrypting
     *
     * @param string $data
     * @return string
     */
    public function encrypt($data)
    {
        $crypt = $this->getCrypt();
        if (null === $crypt) {
            return $data;
        }
        return $this->keyVersion . ':' . $this->cipher . ':' . (MCRYPT_MODE_CBC ===
        $crypt->getMode() ? $crypt->getInitVector() . ':' : '') . base64_encode(
            $crypt->encrypt((string)$data)
        );
    }

    /**
     * Look for key and crypt versions in encrypted data before decrypting
     *
     * Unsupported/unspecified key version silently fallback to the oldest we have
     * Unsupported cipher versions eventually throw exception
     * Unspecified cipher version fallback to the oldest we support
     *
     * @param string $data
     * @return string
     */
    public function decrypt($data)
    {
        if ($data) {
            $parts = explode(':', $data, 4);
            $partsCount = count($parts);

            $initVector = false;
            // specified key, specified crypt, specified iv
            if (4 === $partsCount) {
                list($keyVersion, $cryptVersion, $iv, $data) = $parts;
                $initVector = $iv ? $iv : false;
                $keyVersion = (int)$keyVersion;
                $cryptVersion = self::CIPHER_RIJNDAEL_256;
                // specified key, specified crypt
            } elseif (3 === $partsCount) {
                list($keyVersion, $cryptVersion, $data) = $parts;
                $keyVersion = (int)$keyVersion;
                $cryptVersion = (int)$cryptVersion;
                // no key version = oldest key, specified crypt
            } elseif (2 === $partsCount) {
                list($cryptVersion, $data) = $parts;
                $keyVersion = 0;
                $cryptVersion = (int)$cryptVersion;
                // no key version = oldest key, no crypt version = oldest crypt
            } elseif (1 === $partsCount) {
                $keyVersion = 0;
                $cryptVersion = self::CIPHER_BLOWFISH;
                // not supported format
            } else {
                return '';
            }
            // no key for decryption
            if (!isset($this->keys[$keyVersion])) {
                return '';
            }
            $crypt = $this->getCrypt($this->keys[$keyVersion], $cryptVersion, $initVector);
            if (null === $crypt) {
                return '';
            }
            return trim($crypt->decrypt(base64_decode((string)$data)));
        }
        return '';
    }

    /**
     * Return crypt model, instantiate if it is empty
     *
     * @param string|null $key NULL value means usage of the default key specified on constructor
     * @return \Magento\Framework\Encryption\Crypt
     * @throws \Exception
     */
    public function validateKey($key)
    {
        if (preg_match('/\s/s', $key)) {
            throw new \Exception((string)new \Magento\Framework\Phrase('The encryption key format is invalid.'));
        }
        return $this->getCrypt($key);
    }

    /**
     * Attempt to append new key & version
     *
     * @param string $key
     * @return $this
     */
    public function setNewKey($key)
    {
        $this->validateKey($key);
        $this->keys[] = $key;
        $this->keyVersion += 1;
        return $this;
    }

    /**
     * Export current keys as string
     *
     * @return string
     */
    public function exportKeys()
    {
        return implode("\n", $this->keys);
    }

    /**
     * Initialize crypt module if needed
     *
     * By default initializes with latest key and crypt versions
     *
     * @param string $key
     * @param int $cipherVersion
     * @param bool $initVector
     * @return Crypt|null
     */
    protected function getCrypt($key = null, $cipherVersion = null, $initVector = true)
    {
        if (null === $key && null === $cipherVersion) {
            $cipherVersion = self::CIPHER_RIJNDAEL_256;
        }

        if (null === $key) {
            $key = $this->keys[$this->keyVersion];
        }

        if (!$key) {
            return null;
        }

        if (null === $cipherVersion) {
            $cipherVersion = $this->cipher;
        }
        $cipherVersion = $this->validateCipher($cipherVersion);

        if ($cipherVersion === self::CIPHER_RIJNDAEL_128) {
            $cipher = MCRYPT_RIJNDAEL_128;
            $mode = MCRYPT_MODE_ECB;
        } elseif ($cipherVersion === self::CIPHER_RIJNDAEL_256) {
            $cipher = MCRYPT_RIJNDAEL_256;
            $mode = MCRYPT_MODE_CBC;
        } else {
            $cipher = MCRYPT_BLOWFISH;
            $mode = MCRYPT_MODE_ECB;
        }

        return new Crypt($key, $cipher, $mode, $initVector);
    }
}
