<?php

/**
 * Authentication Class
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

class Auth
{

	public static $instance;
	public static $settings = null;
	public $user_id = null;
	public $role_id = null;
	public $is_admin = false;
	private $api;
	private $db;
	private $bypass_access = false;
	private $sqlite_db;
	private $table_free_access = array();
	private $table_readonly_access = array();

	/**
	 * Singleton constructor
	 */
	public function __construct() {
		self::$instance = &$this;
		try {
			//open the database
			$this->sqlite_db = new PDO('sqlite:sessions.sqlite');
		} catch (PDOException $e) {
			Request::error($e->getMessage(), 500);
		}

		//create the database
		$this->createTokensDatabase();

        if(defined('__AUTH__')) {
            self::$settings = unserialize(__AUTH__);
        }

	}

	/**
	 * Create database for tokens
	 */
	private function createTokensDatabase() {
		try {
			$this->sqlite_db->exec("
            CREATE TABLE IF NOT EXISTS tokens (
                token CHAR(32) PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
		} catch (PDOException $e) {
			Request::error($e->getMessage(), 500);
		}
	}

	/**
	 * Returns static reference to the class instance
	 */
	public static function &getInstance() {
		return self::$instance;
	}

	/**
	 * Returns if authentication is valid
	 * @param $query
	 * @return bool
	 */
	public function validate($query) {
		if (isset($query['token']) && $this->validateToken($query['token'])) {
			return true;
		} elseif (isset($query['check_token']) && $this->validateToken($query['check_token'])) {

			$this->api = API::getInstance();
			$results = array(
				"user" => (object)array(
					"id" => $this->user_id,
					"role_id" => $this->role_id,
					"is_admin" => $this->is_admin
				),
				"response" => (object) array('status' => 200, 'message' => 'OK')
			);

			$renderer = 'render_' . $query['format'];
			die($this->api->$renderer($results, $query));

		} elseif (isset($query['user_id']) && isset($query['password'])) {

			if(empty(self::$settings))
			    return true;

            $bind_values = array();

			$users_table = self::$settings['users']['table'];
            $users_columns = self::$settings['users']['columns'];

            $user = strtolower($query['user_id']);

            $where = array();
            foreach(self::$settings['users']['search'] as $col){
                $bind_values[$col] = $user;
                $where[$col] = "$col = :$col";
            }
            $where_sql = implode(" OR ", $where);

            if(!empty(self::$settings['users']['check'])) {
                $where = array();
                foreach (self::$settings['users']['check'] as $col => $value) {
                    $bind_values[$col] = $value;
                    $where[$col] = "$col = :$col";
                }
                $where_sql = (!empty($where_sql) ? " ($where_sql) AND " : "") . implode(" OR ", $where);
            }

			$this->api = API::getInstance();
			$this->db = &$this->api->connect(self::$settings['database']);

			$super_admin = !empty($users_columns['super_admin']) ? ', ' . $users_columns['super_admin'] : '';

			$sth = $this->db->prepare("SELECT ".$users_columns['id'].", ".$users_columns['username'].", ".$users_columns['role'].", ".$users_columns['password']." $super_admin FROM $users_table WHERE $where_sql");
			foreach($bind_values as $col => $value){
                $sth->bindParam(":$col", $value);
            }

			$sth->execute();
			$user_row = $sth->fetch();

			if ($user_row) {
				$password = strtolower($query['password']);
                if ($user_row[$users_columns['password']] == $password) {
                    $token = $this->generateToken($user_row['id']);
                    $this->user_id = $user_row[$users_columns['id']];
                    $this->role_id = $user_row[$users_columns['role']];
                    $this->is_admin = !empty($users_columns['super_admin']) ? $user_row[key(reset($users_columns['super_admin']))] : false;
                    // Render
                    $results = array((object) array(
                        "token" => $token,
                        "id" => $user_row['id'],
                        "role_id" => $user_row['role_id'],
                        "is_admin" => (($user_row['is_admin'] == reset($users_columns['super_admin'])) ? true : false),
                    ));
                    $renderer = 'render_' . $query['format'];
                    die($this->api->$renderer($results, $query));
                }
            }
			Request::error("Invalid authentication!", 401);
		}

        if(empty(self::$settings))
            return true;

		Request::error("Forbidden!", 403);
		return false;
	}

	/**
	 * Validate token
	 * @param $token
	 * @return bool
	 */
	private function validateToken($token) {

        if(empty(self::$settings))
            return true;

        $users_table = self::$settings['users']['table'];
        $users_columns = self::$settings['users']['columns'];

		try {
			$sth = $this->sqlite_db->prepare("SELECT * FROM tokens WHERE token = :token");
			$sth->bindParam(':token', $token);
			$sth->execute();
			$token_row = $sth->fetch();

			if ($token_row) {

				$this->api = API::get_instance();
				$this->db = &$this->api->connect(self::$settings['database']);
				$sth = $this->db->prepare("SELECT id, role_id, is_admin FROM $users_table WHERE ".$users_columns['id']." = :user_id");
				$sth->bindParam(':user_id', $token_row['user_id']);

				$sth->execute();
		        $user_row = $sth->fetch();

				if ($user_row) {
				    $this->user_id = $user_row[$users_columns['id']];
					$this->role_id = $user_row[$users_columns['role']];
					$this->is_admin = (($user_row['is_admin'] == reset($users_columns['super_admin'])) ? true : false);
					return true;
				}

			}
		    return false;
		} catch (PDOException $e) {
			Request::error($e->getMessage(), 500);
		}
	}

	/**
	 * Add at the end of SELECT, UPDATE and DELETE queries some restriction based on permissions (you can do a subquery with the user/role id)
	 * @param $table
	 * @param $permission
	 * @return string
	 */
	public function sql_restriction($table, $permission /*(READ|EDIT|DELETE)*/) {

		// All allowed
		if ($this->is_admin == true || $this->role_id == '')
			return "'1' = '1'";

		/* REMOVE COMMENTS
		// All denied (default)
		$sql = "'1' = '0'";
		$value = 'None';
		*/

		// TODO based your need and dataset

		$sql = "'1' = '1'";

		return $sql;
	}

	/**
	 * Return if the user can read on this table
	 * @param $table
	 * @return bool
	 */
	public function can_read($table) {

        if(empty(self::$settings))
            return true;

		if (in_array($table, $this->table_free_access)) {
			$this->bypass_access = true;
			return true;
		}

		if ($this->is_admin == true)
			return true;

		// TODO based your need and dataset

		return false;
	}

	/**
	 * Return if the user can insert on this table
	 * @param $table
	 * @return bool
	 */
	public function can_write($table) {

        if(empty(self::$settings))
            return true;

		if (in_array($table, $this->table_readonly_access))
			return false;

		if ($this->is_admin == true)
			return true;

		// TODO based your need and dataset

		return false;
	}

	/**
	 * Return if the user can update on this table
	 * @param $table
	 * @return bool
	 */
	public function can_edit($table) {

        if(empty(self::$settings))
            return true;

		if (in_array($table, $this->table_readonly_access))
			return false;

		if ($this->is_admin == true)
			return true;

		// TODO based your need and dataset

		return false;
	}

	/**
	 * Return if the user can delete on this table
	 * @param $table
	 * @return bool
	 */
	public function can_delete($table) {

		return false; // <==== REMOVE

		if (in_array($table, $this->table_readonly_access))
			return false;

		if ($this->is_admin == true)
			return true;

		// TODO based your need and dataset

		return false;
	}

	/**
	 * Token generator
	 * @param $user_id
	 * @return null|string
	 */
	private function generateToken($user_id) {
		try {
			$token = md5(uniqid(rand(), true));
			$sth = $this->sqlite_db->prepare("INSERT INTO tokens (token,user_id,user_agent) VALUES (:token,:user_id,:user_agent)");
			$sth->bindParam(':token', $token);
			$sth->bindParam(':user_id', $user_id);
			$sth->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
			if ($sth->execute())
				return $token;
			return null;
		} catch (PDOException $e) {
			Request::error($e->getMessage(), 500);
		}
	}
}
