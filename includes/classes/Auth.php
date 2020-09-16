<?php

namespace marcocesarato\DatabaseAPI;

use PDO;
use PDOException;

/**
 * Authentication Class.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2019
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
class Auth
{
    public static $instance;
    public static $settings = null;
    public static $api_table = 'api_auth';

    public $user = [];
    public $user_id = null;
    public $is_admin = false;
    public $authenticated = false;
    public $can_values = ['All', 'Owner', 'Teams'];
    private $api;
    private $db;
    private $table_free = [];
    private $table_readonly = [];
    private $query = [];

    /**
     * Singleton constructor.
     */
    public function __construct()
    {
        self::$instance = &$this;
        $this->logger = Logger::getInstance();
        $this->hooks = Hooks::getInstance();
    }

    /**
     * Get user row.
     *
     * @return array
     */
    public static function getUser()
    {
        return self::getInstance()->user;
    }

    /**
     * Returns static reference to the class instance.
     */
    public static function &getInstance()
    {
        return self::$instance;
    }

    /**
     * Returns if authentication is valid.
     *
     * @param $query
     *
     * @return bool
     */
    public function validate($query)
    {
        $this->api = API::getInstance();

        if (!empty($this->query['db'])) {
            $this->api->setDatabase();
            $db_settings = $this->api->getDatabase();
            $this->table_free = $db_settings->table_free;
            $this->table_readonly = $db_settings->table_readonly;
        }

        $this->query = $query;

        if (defined('__API_AUTH__')) {
            self::$settings = unserialize(__API_AUTH__);
            if (!empty(self::$settings['api_table'])) {
                self::$api_table = preg_replace('/\s+/', '', self::$settings['api_table']);
            }
        } else {
            return true;
        }

        if (!$this->api->tableExists(self::$api_table, self::$settings['api_database'])) {
            $this->createAPITable(); //create the table
        } else {
            $this->checkAPITable();
        }

        if (!empty($this->query['check_counter']) && $this->validateToken($this->query['token']) && $this->is_admin) {
            $this->checkCounter();
        } elseif (!empty($this->query['check_token']) && $this->validateToken($this->query['check_token'])) {
            $this->checkToken();
        } elseif (!empty($this->query['token']) && $this->validateToken($this->query['token'])) {
            return true;
        } elseif (($login_action = $this->hooks->apply_filters('auth_login_request', false, $this->query)) && $this->hooks->has_action($login_action)) {
            // Login custom
            $this->hooks->do_action($login_action);
        } elseif (!empty($this->query['user_id']) && !empty($this->query['password'])) {
            $bind_values = [];

            $users_table = self::$settings['users']['table'];
            $users_columns = self::$settings['users']['columns'];

            $user = strtolower($query['user_id']);

            $where = [];
            foreach (self::$settings['users']['search'] as $col) {
                $bind_values[$col] = $user;
                $where[$col] = "$col = :$col";
            }
            $where_sql = implode(' OR ', $where);

            if (!empty(self::$settings['users']['check'])) {
                $where = [];
                foreach (self::$settings['users']['check'] as $col => $value) {
                    $bind_values[$col] = $value;
                    $where[$col] = "$col = :$col";
                }
                $where_sql = (!empty($where_sql) ? " ($where_sql) AND " : '') . implode(' OR ', $where);
            }

            $this->api = API::getInstance();
            $this->users_db = $this->getUsersDatabase();

            $sth = $this->users_db->prepare("SELECT * FROM $users_table WHERE $where_sql");
            foreach ($bind_values as $col => $value) {
                $sth->bindParam(":$col", $value);
            }

            $sth->execute();

            $user_row = $sth->fetch();

            $is_valid = $this->hooks->apply_filters('auth_validate_token', !empty($user_row), $user_row);

            if ($is_valid) {
                $password = $query['password'];
                if ($user_row[$users_columns['password']] == $password) {
                    $token = $this->generateToken($user_row[$users_columns['id']], $user_row[$users_columns['username']]);
                    $this->user_id = $user_row[$users_columns['id']];
                    $this->is_admin = !empty($users_columns['admin']) ? $user_row[key(reset($users_columns['admin']))] : false;
                    // Render
                    $results = [
                        (object)[
                            'token' => $token,
                        ],
                    ];
                    $results = $this->hooks->apply_filters('auth_login', $results);
                    die($this->api->render($results));
                }
            }
            Response::error('Invalid authentication!', 401);
        }
        Response::error('Forbidden!', 403);

        return false;
    }

    /**
     * Get API Database.
     *
     * @return PDO
     */
    public function getAPIDatabase()
    {
        if (self::$settings['sqlite']) {
            return new PDO('sqlite:' . self::$settings['sqlite_filename'] . '.sqlite');
        }

        return $this->api->connect(self::$settings['api_database']);
    }

    /**
     * Get Users database.
     *
     * @return mixed
     */
    public function getUsersDatabase()
    {
        return $this->api->connect(self::$settings['users']['database']);
    }

    /**
     * Create database table.
     */
    private function createAPITable()
    {
        try {
            $this->db->exec('
            CREATE TABLE ' . self::$api_table . ' (
                token CHAR(32) PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                user_name VARCHAR(255) NOT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                counter INT DEFAULT 0 NOT NULL,
                date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
        } catch (PDOException $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Check database table.
     */
    private function checkAPITable()
    {
        $this->db = $this->getAPIDatabase();
        try {
            $date = date('Y-m-d H:i:s', strtotime('-1 month'));
            $this->db->exec('DELETE FROM ' . self::$api_table . " WHERE last_access != date_created AND last_access < '" . $date . "'");
        } catch (PDOException $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Validate Token.
     *
     * @param $token
     *
     * @return bool
     */
    private function validateToken($token)
    {
        if (empty(self::$settings)) {
            return true;
        }

        $users_table = self::$settings['users']['table'];
        $users_columns = self::$settings['users']['columns'];

        $this->db = $this->getAPIDatabase();

        try {
            $sth = $this->db->prepare('SELECT * FROM ' . self::$api_table . ' WHERE token = :token');
            $sth->bindParam(':token', $token);
            $sth->execute();
            $token_row = $sth->fetch();

            $exists = $this->hooks->apply_filters('auth_validate_token', !empty($token_row), $token);

            $auth_bypass = false;
            $auth_bypass = $this->hooks->apply_filters('auth_bypass', $auth_bypass);

            // Bypass
            if (!$exists && $auth_bypass && empty($this->query['force_validation'])) {
                $exists = true;
                $token_row = [];
                $token_row['user_id'] = '1';
                $token_row['counter'] = 0;
            }

            if ($exists) {
                $this->api = API::getInstance();
                $this->users_db = $this->getUsersDatabase();
                $sth = $this->users_db->prepare("SELECT * FROM $users_table WHERE " . $users_columns['id'] . ' = :user_id');
                $sth->bindParam(':user_id', $token_row['user_id']);

                $sth->execute();
                $user_row = $sth->fetch();

                if (!empty($user_row)) {
                    $this->db = $this->getAPIDatabase();
                    $sth = $this->db->prepare('UPDATE ' . self::$api_table . ' SET last_access = :last_access, counter = :counter WHERE token = :token');
                    $last_access = date('Y-m-d H:i:s');
                    $counter = $this->needIncrementCounter() ? intval($token_row['counter']) + 1 : intval($token_row['counter']);
                    $sth->bindParam(':counter', $counter);
                    $sth->bindParam(':last_access', $last_access);
                    $sth->bindParam(':token', $token);
                    $sth->execute();

                    $this->user = $user_row;
                    $this->user_id = $user_row['id'];
                    if (!empty($users_columns['admin'])) {
                        $this->is_admin = (($user_row[key($users_columns['admin'])] == reset($users_columns['admin'])) ? true : false);
                    }
                    $this->authenticated = true;

                    return true;
                }
            }

            return false;
        } catch (PDOException $e) {
            Response::error($e->getMessage(), 500);

            return false;
        }
    }

    /**
     * Check counter.
     */
    private function checkCounter()
    {
        $this->db = $this->getAPIDatabase();
        try {
            $sth = $this->db->prepare('SELECT user_id, user_name, SUM(counter) as counter FROM ' . self::$api_table . ' GROUP BY user_id, user_name');
            $sth->execute();
            $results = $sth->fetchAll(PDO::FETCH_OBJ);
            $this->api->render($results);
        } catch (PDOException $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Check token.
     */
    private function checkToken()
    {
        try {
            $results = [
                'user' => (object)[
                    'id' => $this->user_id,
                    'is_admin' => $this->is_admin,
                ],
                'response' => (object)['status' => 200, 'message' => 'OK'],
            ];

            $this->logger->debug($results);
            $results = $this->hooks->apply_filters('auth_token_check', $results);
            $this->api->render($results);
        } catch (PDOException $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Generate Token.
     *
     * @param $user_id
     * @param $user_name
     *
     * @return string|null
     */
    public function generateToken($user_id, $user_name)
    {
        $this->db = $this->getAPIDatabase();
        try {
            $token = md5(uniqid(rand(), true));
            $sth = $this->db->prepare('INSERT INTO ' . self::$api_table . ' (token,user_id,user_name,user_agent) VALUES (:token,:user_id,:user_name,:user_agent)');
            $sth->bindParam(':token', $token);
            $sth->bindParam(':user_name', $user_name);
            $sth->bindParam(':user_id', $user_id);
            $sth->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
            if ($sth->execute()) {
                return $token;
            }

            return null;
        } catch (PDOException $e) {
            Response::error($e->getMessage(), 500);
        }

        return null;
    }

    /**
     * Generate restricted condition for sql queries.
     *
     * @param $table
     * @param $permission
     *
     * @return string
     */
    public function permissionSQL($table, $permission)
    {
        $sql = '';

        // All allowed
        if ($this->is_admin == true) {
            $sql = "'1' = '1'";
        }

        $sql = $this->hooks->apply_filters('sql_restriction', $sql, $table, $permission);

        return $sql;
    }

    // Tables with no permission required (readonly)

    /**
     * Can Read.
     *
     * @param $table
     *
     * @return bool
     */
    public function canRead($table)
    {
        $result = true;

        if (empty(self::$settings)) {
            $result = true;
        } else {
            if (in_array($table, $this->table_free)) {
                $result = true;
            }
        }

        $result = $this->hooks->apply_filters('can_read', $result, $table);

        return $result;
    }

    /**
     * Can Write.
     *
     * @param $table
     *
     * @return bool
     */
    public function canWrite($table)
    {
        $result = false;

        if (in_array($table, $this->table_readonly)) {
            $result = false;
        } else {
            if (empty(self::$settings)) {
                $result = false;
            } else {
                if (in_array($table, $this->table_free)) {
                    $result = true;
                }
            }
        }

        $result = $this->hooks->apply_filters('can_write', $result, $table);

        return $result;
    }

    /**
     * Can edit.
     *
     * @param $table
     *
     * @return bool
     */
    public function canEdit($table)
    {
        $result = false;

        if (in_array($table, $this->table_readonly)) {
            $result = false;
        } else {
            if (empty(self::$settings)) {
                $result = false;
            } else {
                if (in_array($table, $this->table_free)) {
                    $result = true;
                }
            }
        }

        $result = $this->hooks->apply_filters('can_edit', $result, $table);

        return $result;
    }

    /**
     * Can delete.
     *
     * @param $table
     *
     * @return bool
     */
    public function canDelete($table)
    {
        $result = false;

        if (in_array($table, $this->table_readonly)) {
            $result = false;
        } else {
            if (empty(self::$settings)) {
                $result = false;
            } else {
                if (in_array($table, $this->table_free)) {
                    $result = true;
                }
            }
        }

        $result = $this->hooks->apply_filters('can_delete', $result, $table);

        return $result;
    }

    /**
     * Increment counter.
     *
     * @return bool
     */
    private function needIncrementCounter()
    {
        return !(!empty($this->query['docs']) || !empty($this->query['check_token']) || !empty($this->query['check_counter']) || !empty($this->query['user_id']) && !empty($this->query['password']));
    }
}

$AUTH = new Auth();
