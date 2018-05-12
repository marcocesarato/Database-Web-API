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
	public static function &get_instance() {
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

			$this->api = API::get_instance();
			$results = array(
				"user" => (object)array(
					"id" => $this->user_id,
					"role_id" => $this->role_id,
					"is_admin" => $this->is_admin
				),
				"response" => (object)array('status' => 200, 'message' => 'OK')
			);

			$renderer = 'render_' . $query['format'];
			die($this->api->$renderer($results, $query));

		} elseif (isset($query['user_id']) && isset($query['password'])) {

            return true; // <==== REMOVE

            // TODO based your dataset

            /** @example

			$user = strtolower($query['user_id']);

			$this->api = API::get_instance();
			$this->db = &$this->api->connect('database');

			$sth = $this->db->prepare("SELECT id, first_name, last_name, role_id, is_admin, user_hash FROM users WHERE (id = :user_id OR user_name = :username OR email1 = :email)");
			$sth->bindParam(':user_id', $user);
			$sth->bindParam(':username', $user);
			$sth->bindParam(':email', $user);

			$sth->execute();
			$user_row = $sth->fetch();

			if ($user_row) {

				$password = strtolower($query['password']);

				if ($user_row['user_hash'] == $password) {
					$token = $this->generateToken($user_row['id']);
					$this->user_id = $user_row['id'];
					$this->role_id = $user_row['role_id'];
					$this->is_admin = $user_row['is_admin'];
					// Render
					$results = array((object)array(
						"token" => $token,
						"id" => $user_row['id'],
						"first_name" => $user_row['first_name'],
						"last_name" => $user_row['last_name'],
						"role_id" => $user_row['role_id'],
						"is_admin" => (($user_row['is_admin'] == 'on') ? true : false),
					));
					$renderer = 'render_' . $query['format'];

					die($this->api->$renderer($results, $query));
				}
			}
			Request::error("Invalid authentication!", 401);
             */
		}
		Request::error("Forbidden!", 403);
		return false;
	}

	private function validateToken($token) {

	    return true; // <==== REMOVE

        // TODO based your dataset
        /** @example

		try {
			$sth = $this->sqlite_db->prepare("SELECT * FROM tokens WHERE token = :token");
			$sth->bindParam(':token', $token);
			$sth->execute();
			$token_row = $sth->fetch();

			if ($token_row) {

				$this->api = API::get_instance();
				$this->db = &$this->api->connect('database');

				$sth = $this->db->prepare("SELECT id, role_id, is_admin  FROM users WHERE id = :user_id");
				$sth->bindParam(':user_id', $token_row['user_id']);

				$sth->execute();
				$user_row = $sth->fetch();

				if ($user_row) {
					$this->user_id = $user_row['id'];
					$this->role_id = $user_row['role_id'];
					$this->is_admin = (($user_row['is_admin'] == 'on') ? true : false);
					return true;
				}

			}
			return false;
		} catch (PDOException $e) {
			Request::error($e->getMessage(), 500);
		}
         */
	}

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

	public function sql_restriction($table, $permission /*(READ|MODIFY|DELETE)*/) {

		// All allowed
		if ($this->is_admin == true || $this->role_id == '')
			return "'1' = '1'";

		/*// All denied (default)
		$sql = "'1' = '0'";
		$value = 'None';*/

		// TODO based your need and dataset

		$sql = "'1' = '1'";
	
		return $sql;
	}

	public function can_read($table) {

		if (in_array($table, $this->table_free_access)) {
			$this->bypass_access = true;
			return true;
		}

		if ($this->is_admin == true)
			return true;

        // TODO based your need and dataset

		return false;
	}

	public function can_write($table) {

		if (in_array($table, $this->table_readonly_access))
			return false;

		if ($this->is_admin == true)
			return true;

        // TODO based your need and dataset

		return false;
	}

	public function can_modify($table) {

		if (in_array($table, $this->table_readonly_access))
			return false;

		if ($this->is_admin == true)
			return true;

        // TODO based your need and dataset

		return false;
	}

	public function can_delete($table) {

		if (in_array($table, $this->table_readonly_access))
			return false;

		if ($this->is_admin == true)
			return true;

        // TODO based your need and dataset

		return false;
	}
}
$AUTH = new Auth();