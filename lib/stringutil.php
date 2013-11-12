<?php
/**
 * Copyright (c) 2013 Fritz Webering <fritz@webering.eu>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the LICENSE file.
 */


namespace OCA\User_WCF\lib;


/**
 * Helper functions for calculating hashes according to WCF config.
 */
class StringUtil {    
    /**
     * hashes the given value.
     *
     * @param     string         $value
     * @return    string         $hash
     */
    public static function getHash($value) {
        if (!defined('ENCRYPTION_METHOD') || ENCRYPTION_METHOD === 'sha1')
            return sha1($value);
        if (ENCRYPTION_METHOD === 'md5') return md5($value);
        if (ENCRYPTION_METHOD === 'crc32') return crc32($value);
        if (ENCRYPTION_METHOD === 'crypt') return crypt($value);
    }


    /**
     * Returns a salted hash of the given value.
     *
     * @param     string         $value
     * @param    string        $salt
     * @return     string         $hash
     */
    public static function getSaltedHash($value, $salt) {
        if (!defined('ENCRYPTION_ENABLE_SALTING') || ENCRYPTION_ENABLE_SALTING) {
            if (!defined('ENCRYPTION_ENCRYPT_BEFORE_SALTING')
                    || ENCRYPTION_ENCRYPT_BEFORE_SALTING) {
                $value = self::getHash($value);
            }

            if (!defined('ENCRYPTION_SALT_POSITION')
                    || ENCRYPTION_SALT_POSITION == 'before') {
                return self::getHash($salt . $value);
            }
            elseif (ENCRYPTION_SALT_POSITION == 'after') {
                return self::getHash($value . $salt);
            }
        }
        else {
            return self::getHash($value);
        }
    }


    /**
     * Returns a double salted hash of the given value.
     *
     * @param    string         $value
     * @param    string         $salt
     * @return   string         $hash
     */
    public static function getDoubleSaltedHash($value, $salt) {
        return self::getHash($salt . self::getSaltedHash($value, $salt));
    }
}
