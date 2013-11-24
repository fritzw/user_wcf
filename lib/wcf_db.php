<?php

namespace OCA\User_WCF\lib;

class WCF_DB {
    public $wcfN;

    protected $connected = NULL;
    protected $db;
    protected $dsn='', $dbUser, $dbPassword;
    protected $authorizedGroups = NULL;
    protected $groupsCondition = 'FALSE'; // Allow no groups by default

    protected static $instances = array();

    public static function getInstance($wcfPath) {
        if (!array_key_exists($wcfPath, WCF_DB::$instances)) {
            WCF_DB::$instances[$wcfPath] = new WCF_DB($wcfPath);
        }
        return WCF_DB::$instances[$wcfPath];
    }

    protected function __construct($wcfPath) {
        require $wcfPath.'/config.inc.php';

        if ($dbClass === 'MySQLDatabase') {
            $this->dsn = 'mysql';
        }
        else {
            User_WCF::warn('user_wcf', 'Unsupported database type '.
                $dbClass.', only MySQLDatabase is supported at the moment.',
                \OCP\Util::WARN);
            $connected = FALSE;
            return;
        }

        $this->dsn .= ":host=$dbHost;dbname=$dbName;charset=$dbCharset";
        $this->wcfN = 'wcf'.WCF_N;
        $this->dbUser = $dbUser;
        $this->dbPassword = $dbPassword;
    }



    public function prepare($fields, $where=NULL, $append=NULL) {
        if (!$this->connect()) return FALSE;

        if ($where === NULL) $where = 'TRUE';

        $query = "SELECT DISTINCT $fields FROM {$this->wcfN}_user
                LEFT JOIN {$this->wcfN}_user_to_groups USING (userID)
                LEFT JOIN {$this->wcfN}_group USING (groupID)
                WHERE ($this->groupsCondition) AND ($where) $append";

        $result = $this->db->prepare($query);

        if ($result === FALSE) {
            User_WCF::warn('Error '.$this->db->errorInfo().' preparing statement: '.
                $this->db->errorInfo()[2].'. Query was: '.$query);
        }
        return $result;
    }


    /**
     * @brief Set the groups that are allowed to access OwnCloud.
     * @param $groups An array containing one or more group names or TRUE to
     *                allow all groups. Anything else will prevent all logins.
     *
     *
     * Restricts the queries to return only users that are members of the
     * specified groups. Other groups will never be returned by queries.
     */
    public function setAuthorizedGroups ($groups) {
        if (is_array($groups) and count($groups) > 0) {
            $this->authorizedGroups = $groups;
            $conditions = array();
            foreach ($this->authorizedGroups as $group) {
                $conditions[] = "groupName='$group'";
            }
            $this->groupsCondition = implode(' OR ', $conditions);
        }
        elseif ($groups === TRUE) {
            $this->groupsCondition = 'TRUE';
        }
        else {
            $this->groupsCondition = 'FALSE';
            User_WCF::warn('$authorizedGroups is set to "'.$groups.
                '", which means that nobody can log in.');
        }
    }

    public function getAuthorizedGroups() {
        return $this->authorizedGroups;
    }



    /**
     * @brief Try to connect to the database, but only once. On subsequent
     *        only the result of the first call is returned.
     *
     * @return TRUE or FALSE
     */
    private function connect() {
        if ($this->connected === NULL) {
            try {
                $this->db = new \PDO($this->dsn, $this->dbUser, $this->dbPassword);
                $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->connected = TRUE;
            }
            catch (\PDOException $e) {
                $this->warn('Unable to connect to database: '.
                    $this->db->connect_error);
                $this->connected = FALSE;
            }
        }
        return $this->connected;
    }
   
}
