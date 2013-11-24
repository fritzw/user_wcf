<?php
/**
 * Copyright (c) 2013 Fritz Webering <fritz@webering.eu>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the LICENSE file.
 */

namespace OCA\user_wcf;


/**
 * This loads group memberships from a WCF database for all groups listed
 * in the $authorizedGroups paramter. The database configuration is imported
 * from the WCF configuration file of the WCF installation given in the
 * $wcfPath paramter.
 */

class Group_WCF extends \OC_Group_Backend {
    protected $db;
    
    public function __construct($wcfPath, $authorizedGroups) {
        $this->db = lib\WCF_DB::getInstance($wcfPath);
    }
    /**
    * @brief Check if backend implements actions
    * @param int $actions bitwise-or'ed actions
    * @return boolean
    *
    * Returns the supported actions as int to be
    * compared with OC_GROUP_BACKEND_CREATE_GROUP etc.
    */
    public function getSupportedActions() {
        return OC_GROUP_BACKEND_GET_DISPLAYNAME;
    }

    /**
     * @brief is user in group?
     * @param string $uid uid of the user
     * @param string $gid gid of the group
     * @return bool
     *
     * Checks whether the user is member of a group or not.
     */
    public function inGroup($uid, $gid) {
        $inGroup = FALSE;
        $result = $this->db->prepare('1', 'username=? AND groupName=?');

        if ($result !== FALSE and $result->execute(array($uid, $gid))) {
            if ($result->fetch() !== FALSE) {
                $inGroup = TRUE;
            }
            $result->closeCursor();
        }
        return $inGroup;
    }

    /**
     * @brief Get all groups a user belongs to
     * @param string $uid Name of the user
     * @return array with group names
     *
     * This function fetches all groups a user belongs to. It does not check
     * if the user exists at all.
     */
    public function getUserGroups($uid) {
        $groups = array();
        $result = $this->db->prepare('groupName', 'username=?');

        if ($result !== FALSE and $result->execute(array($uid))) {
            foreach ($result as $row) {
                $groups[] = $row['groupName'];
            }
            $result->closeCursor();
        }
        return $groups;
    }

    /**
     * @brief get a list of all groups
     * @param string $search
     * @param int $limit
     * @param int $offset
     * @return array with group names
     *
     * Returns a list with all groups
     */
    public function getGroups($search = '', $limit = -1, $offset = 0) {
        $groups = array();
        $params = array();
        $where = NULL;
        $append = 'ORDER BY groupName';

        if ($search !== '') {
            $search = (string) $search;
            $where = 'groupName LIKE ?';
            $params[] = '%'.$search.'%';
        }
        if ($limit !== -1) {
            $append .= ' LIMIT '.intval($limit);
        }
        if ($offset !== 0) {
            $append .= ' OFFSET '.intval($offset);
        }

        $result = $this->db->prepare('groupName', $where, $append);

        if ($result !== FALSE and $result->execute($params)) {
            foreach ($result as $row) {
                $groups[] = $row['groupName'];
            }
            $result->closeCursor();
        }
        return $groups;
    }
 
    /**
     * check if a group exists
     * @param string $gid
     * @return bool
     */
    public function groupExists($gid) {
        $exists = FALSE;
        $result = $this->db->prepare('groupName', 'groupName=?');

        if ($result !== FALSE and $result->execute(array($gid))) {
            $exists = ($result->fetch() !== FALSE);
            $result->closeCursor();
        }
        return $exists;
    }

    /**
     * @brief get a list of all users in a group
     * @param string $gid
     * @param string $search
     * @param int $limit
     * @param int $offset
     * @return array with user ids
     */
    public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
        $users = array();
        $params = array($gid);
        $where = 'groupName=?';
        $append = 'ORDER BY username';

        if ($search !== '') {
            $where .= ' AND username LIKE ?';
            $params[] = $search;
        }
        if ($limit !== -1) {
            $append .= ' LIMIT '.intval($limit);
        }
        if ($offset !== 0) {
            $append .= ' OFFSET '.intval($offset);
        }

        $result = $this->db->prepare('username', $where, $append);
        if ($result !== FALSE and $result->execute($params)) {
            foreach ($result as $row) {
                $users[] = $row['username'];
            }
            $result->closeCursor();
        }

        return $users;
    }
}
