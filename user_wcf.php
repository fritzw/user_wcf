<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\user_wcf;

class User_WCF extends \OC_User_Backend implements \OC_User_Interface {
    private $wcf_path;
    private $authorized_groups;

    public function __construct($wcf_path, $authorized_groups) {
        $this->wcf_path = $wcf_path;
        $this->authorized_groups = $authorized_groups;
        require_once $this->wcf_path."/lib/util/StringUtil.class.php";
    }

    /**
     * @brief Check if the password is correct
     * @param $uid The username
     * @param $password The password
     * @returns string
     *
     * Check if the password is correct without logging in the user
     * returns the user id or false
     */
    public function checkPassword($uid, $password) {
        $authenticated_as = False;

        $this->log('Hello World from OC_User_WCF');

        require $this->wcf_path.'/config.inc.php';

        /* Example contents of config.inc.php
        $dbHost = 'localhost';
        $dbUser = 'something';
        $dbPassword = 'some other thing';
        $dbName = 'your database name';
        $dbCharset = 'utf8';
        $dbClass = 'MySQLDatabase';
        if (!defined('WCF_N')) define('WCF_N', 1);
        */

        $mysqli = new \mysqli($dbHost, $dbUser, $dbPassword, $dbName);
        if ($mysqli->connect_errno) {
            $mysqli->close();
            $this->log("Unable to connect to the WCF databases: ".
                $mysqli->connect_error . " " . $mysqli->connect_errno);
            return False;
        }

        $username = $mysqli->real_escape_string($uid);
        $wcfN = 'wcf'.WCF_N;
        $result = $mysqli->query("SELECT userID, username, password, salt, groupName
                 FROM ${wcfN}_user
                LEFT JOIN ${wcfN}_user_to_groups USING (userID)
                LEFT JOIN ${wcfN}_group USING (groupID)
                WHERE LOWER(${wcfN}_user.username)=LOWER('$username')");

        if ($result === FALSE) {
            $this->log("Error querying data from WCF database: $mysqli->erorr ($mysqli->errno).");
        }

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            $doubleSalted = \StringUtil::getDoubleSaltedHash($password, $row['salt']);

            if ($doubleSalted === $row['password']) {
                $this->log("Password matches.");
                // password matches, check authorized groups
                while ($row) {
                    if (in_array($row['groupName'], $this->authorized_groups, TRUE)) {
                        $authenticated_as = $row['username'];
                        break;
                    }
                    $row = $result->fetch_assoc();
                }
            }
            else {
                $this->log("Password does not match.");
            }
        }
        else {
            $this->log("Username $username not found in WCF database.");
        }
        if ($result) $result->close();
        $mysqli->close();

        return $authenticated_as;
    }

    public function userExists($uid) {
        return true;
    }

    private function log($text) {
        \OCP\Util::writeLog('user_wcf', $text, \OCP\Util::DEBUG);
    }
}
