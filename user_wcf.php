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
class User_WCF extends \OC_User_Backend {
    protected $authorizedGroups;
    protected $groupsCondition;
    protected $db = NULL;
    protected $dbUser, $dbHost, $dbPassword, $dbName;

    public function __construct($wcfPath, $authorizedGroups, $useGroupBackend=TRUE) {
        $this->db = lib\WCF_DB::getInstance($wcfPath);
        $this->db->setAuthorizedGroups($authorizedGroups);

        if ($useGroupBackend) {
            $groupBackend = new Group_WCF($wcfPath, $authorizedGroups);
            \OC_Group::useBackend($groupBackend);
        }
    }

    public function getSupportedActions() {
        return OC_USER_BACKEND_CHECK_PASSWORD | OC_USER_BACKEND_GET_DISPLAYNAME;
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
        $authenticatedAs = FALSE;

        $where = 'LOWER(username)=LOWER(?)';
        $result = $this->db->prepare('username, password, salt', $where);

        if ($result !== FALSE and $result->execute(array($uid))) {
            if (($row = $result->fetch()) !== FALSE) {
                $doubleSalted = lib\StringUtil::getDoubleSaltedHash(
                    $password, $row['salt']);

                if ($doubleSalted === $row['password']) {
                    $authenticatedAs = $row['username'];
                    $this->info('User "'.$authenticatedAs.
                        '" logged in successfully.');
                }
                else {
                    $this->info('Invalid password for user '.$uid);
                }
            }
            else {
                $this->info('User '.$uid.' is not in any authorized group.');
            }
            $result->closeCursor();
        }
        else {
            $this->info('Error while checking password for user '.$uid);
        }

        return $authenticatedAs;
    }

    public function userExists($uid) {
        $exists = FALSE;
        $result = $this->db->prepare('1', 'username=?');

        if ($result !== FALSE and $result->execute(array($uid))) {
            $exists = ($result->fetch() !== FALSE);
            $result->closeCursor();
        }
        return $exists;
    }

    public function getUsers($search='', $limit=null, $offset=null) {
        $users = array();
        $params = array();
        $where = NULL;
        $append = 'ORDER BY username';

        if ($search !== '') {
            $where = 'username LIKE ?';
            $params[] = '%'.$search.'%';
        }
        if ($limit !== NULL) {
            $append .= ' LIMIT '.intval($limit);
        }
        if ($offset !== NULL) {
            $append .= ' OFFSET '.intval($offset);;
        }

        $result = $this->db->prepare('username', $where, $append);
        if ($result !== FALSE and $result->execute($params)) {
            $i = 0;
            foreach ($result as $row) {
                $users[] = $row['username'];
            }
            $result->closeCursor();
        }

        return $users;
    }

    public static function info($text) {
        \OCP\Util::writeLog('user_wcf', $text, \OCP\Util::INFO);
    }

    public static function warn($text) {
        \OCP\Util::writeLog('user_wcf', $text, \OCP\Util::WARN);
    }

    public static function debug($text) {
        \OCP\Util::writeLog('user_wcf', $text, \OCP\Util::DEBUG);
    }
}
