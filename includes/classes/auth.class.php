<?php

/**
 * Authentication Class
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2018
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */

class Auth
{

	public static $instance;
	public static $settings = null;
	public $user_id = null;
	public $is_admin = false;
	private $api;
	private $db;
	private $user = array();

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

			$sth = $this->db->prepare("SELECT * FROM $users_table WHERE $where_sql");
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
                    $this->is_admin = !empty($users_columns['admin']) ? $user_row[key(reset($users_columns['admin']))] : false;
                    // Render
                    $results = array((object) array(
                        "token" => $token,
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
				$sth = $this->db->prepare("SELECT * FROM $users_table WHERE ".$users_columns['id']." = :user_id");
				$sth->bindParam(':user_id', $token_row['user_id']);

				$sth->execute();
		        $user_row = $sth->fetch();

				if ($user_row) {
					$this->user = $user_row;
				    $this->user_id = $user_row[$users_columns['id']];
					$this->is_admin = (($user_row['is_admin'] == reset($users_columns['admin'])) ? true : false);
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
	public function sql_restriction($table, $permission) {

		// All allowed
		if ($this->is_admin == true)
			return "'1' = '1'";

		if(!empty(self::$settings['callbacks']) && self::$settings['callbacks']['sql_restriction']){
			$callback = call_user_func(self::$settings['callbacks']['sql_restriction'], $table, $permission);
			if(!empty($callback)) return $callback;
		}

		return "'1' = '1'";
	}

	/**
	 * Return if the user can read on this table
	 * @param $table
	 * @return bool
	 */
	public function can_read($table) {

        if(empty(self::$settings))
            return true;

		if ($this->is_admin == true)
			return true;

		if(!empty(self::$settings['callbacks']) && self::$settings['callbacks']['can_read']){
			$callback = call_user_func(self::$settings['callbacks']['can_read'], $table);
			if(!empty($callback)) return $callback;
		}

		return false;
	}

	/**
	 * Return if the user can insert on this table
	 * @param $table
	 * @return bool
	 */
	public function can_write($table) {

		if(empty(self::$settings))
			return false;

		if ($this->is_admin == true)
			return true;

		if(!empty(self::$settings['callbacks']) && self::$settings['callbacks']['can_write']){
			$callback = call_user_func(self::$settings['callbacks']['can_write'], $table);
			if(!empty($callback)) return $callback;
		}

		return false;
	}

	/**
	 * Return if the user can update on this table
	 * @param $table
	 * @return bool
	 */
	public function can_edit($table) {

		if(empty(self::$settings))
			return false;

		if ($this->is_admin == true)
			return true;

		if(!empty(self::$settings['callbacks']) && self::$settings['callbacks']['can_edit']){
			$callback = call_user_func(self::$settings['callbacks']['can_edit'], $table);
			if(!empty($callback)) return $callback;
		}

		return false;
	}

	/**
	 * Return if the user can delete on this table
	 * @param $table
	 * @return bool
	 */
	public function can_delete($table) {

		if(empty(self::$settings))
			return false;

		if ($this->is_admin == true)
			return true;

		if(!empty(self::$settings['callbacks']) && self::$settings['callbacks']['can_delete']){
			$callback = call_user_func(self::$settings['callbacks']['can_delete'], $table);
			if(!empty($callback)) return $callback;
		}

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

	public function getUser(){
		return $this->user;
	}
}
