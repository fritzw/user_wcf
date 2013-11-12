<?php
/**
 * Copyright (c) 2013 Fritz Webering <fritz@webering.eu>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the LICENSE file.
 */

namespace OCA\user_wcf;


/**
 * This class authenticates users against a WCF database if they belong to
 * one or more of the groups listed in the $authorizedGroups paramter. The
 * database configuration is imported from the WCF configuration file of
 * the WCF installation given in the $wcfPath paramter.
 */
class User_WCF extends \OC_User_Backend implements \OC_User_Interface {
    protected $authorizedGroups;
    protected $groupsCondition;
    protected $db = NULL;
    protected $dbUser, $dbHost, $dbPassword, $dbName;

    public function __construct($wcfPath, $authorizedGroups) {
        require $wcfPath.'/config.inc.php';
        $this->dbHost = $dbHost;
        $this->dbUser = $dbUser;
        $this->dbPassword = $dbPassword;
        $this->dbName = $dbName;
        $this->wcfN = 'wcf'.WCF_N;

        $this->authorizedGroups = $authorizedGroups;
        $groups = array();
        foreach ($this->authorizedGroups as $group) {
            $groups[] = "{$this->wcfN}_group.groupName='$group'";
        }
        $this->groupsCondition = implode(' OR ', $groups);
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
        if (!$this->connect()) return FALSE;
        $authenticatedAs = FALSE;

        $username = $this->db->real_escape_string($uid);
        $where = "LOWER({$this->wcfN}_user.username)=LOWER('$username')";
        $result = $this->queryDb($where, 'password, salt');

        if ($result) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $doubleSalted = lib\StringUtil::getDoubleSaltedHash(
                    $password, $row['salt']);

                if ($doubleSalted === $row['password']) {
                    $authenticatedAs = $row['username'];
                    $this->warn('User "'.$authenticatedAs.
                        '" logged in successfully.');
                }
            }
            else {
                $this->debug("Username $username not found in WCF ".
                    "database in authorized groups.");
            }
            $result->close();
        }

        return $authenticatedAs;
    }

    public function userExists($uid) {
        if (!$this->connect()) return FALSE;
        $username = $this->db->real_escape_string($uid);
        $where = "LOWER({$this->wcfN}_user.username)=LOWER('$username')";
        $result = $this->queryDb($where, 'password, salt');

        $exists = FALSE;
        if ($result) {
            if ($result->num_rows > 0) {
                $exists = TRUE;
            }
            $result->close();
        }
        return $exists;
    }

    public function getUsers($search = '', $limit = null, $offset = null) {
        if (!$this->connect()) return array();
        $where = TRUE;
        if ($search !== '') {
            $search = $this->db->real_escape_string($search);
            $where = "username LIKE '$search'";
        }
        $append = ' ORDER BY username';
        if ($limit !== NULL) $append = $append.' LIMIT '.$limit;
        if ($offset !== NULL) $append = $append.' OFFSET '.$offset;

        $result = $this->queryDb($where, '', $append);
        if (!$result) {
            $this->warn("Found no users in the database.");
            return array();
        }
        $this->warn("Found {$result->num_rows} users in the database.");

        $users = array();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row['username'];
        }
        return $users;
    }

    protected function queryDb($where='TRUE', $addFields='', $append='') {
        if (!$this->connect()) return FALSE;

        if ($addFields !== '') $addFields = ', '.$addFields;
        $query = "SELECT username, groupName $addFields
                FROM {$this->wcfN}_user
                LEFT JOIN {$this->wcfN}_user_to_groups USING (userID)
                LEFT JOIN {$this->wcfN}_group USING (groupID)
                WHERE ($this->groupsCondition) AND ($where) $append";
        $result = $this->db->query($query);

        if ($result === FALSE) {
            $this->warn("Error querying data from WCF database: ".
                "{$this->db->error} ({$this->db->errno}). Query was: $query");
        }
        return $result;
    }

    private function connect() {
        if ($this->db === NULL) {
            $this->db = new \mysqli($this->dbHost, $this->dbUser,
                    $this->dbPassword, $this->dbName);
            if ($this->db->connect_error) {
                $this->warn('Unable to connect to database: '.
                    $this->db->connect_error);
                $this->db = FALSE;
            }
        }
        return $this->db;
    }

    private static function warn($text) {
        \OCP\Util::writeLog('user_wcf', $text, \OCP\Util::WARN);
    }

    private static function debug($text) {
        \OCP\Util::writeLog('user_wcf', $text, \OCP\Util::DEBUG);
    }
}
