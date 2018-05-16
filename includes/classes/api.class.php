<?php

/**
 * API Class
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

class API
{

	static $instance;
	public $dbs = array();
	public $db = null;
	public $dbh = null;
	public $query = array();
	public $ttl = 3600;
	public $cache = array();
	public $connections = array();
	public $request;

	/**
	 * Singleton constructor
	 */
	public function __construct() {
		self::$instance = &$this;
		$this->request = Request::get_instance();
		$this->auth = Auth::getInstance();
	}

	/**
	 * Returns static reference to the class instance
	 */
	public static function &getInstance() {
		return self::$instance;
	}

	/**
	 * Register a new dataset
	 * @param string $name the dataset name
	 * @param array $args the dataset properties
	 */
	public function register($name = null, $args = array()) {

		$defaults = array(
			'name' => null,
			'username' => 'root',
			'password' => 'root',
			'server' => 'localhost',
			'port' => 3306,
			'type' => 'mysql',
			'table_blacklist' => array(),
			'table_list' => array(),
			'column_blacklist' => array(),
			'column_list' => array(),
			'ttl' => $this->ttl,
		);

		$args = shortcode_atts($defaults, $args);
		$name = $this->slugify($name);

		$this->dbs[$name] = (object)$args;

	}

	/**
	 * Modifies a string to remove all non-ASCII characters and spaces.
	 * http://snipplr.com/view.php?codeview&id=22741
	 */
	private function slugify($text) {

		// replace non-alphanumeric characters with a hyphen
		$text = preg_replace('~[^\\pL\d]+~u', '-', $text);

		// trim off any trailing or leading hyphens
		$text = trim($text, '-');

		// transliterate from UTF-8 to ASCII
		if (function_exists('iconv')) {
			$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		}

		// lowercase
		$text = strtolower($text);

		// remove unwanted characters
		$text = preg_replace('~[^-\w]+~', '', $text);

		// ensure that this slug is unique
		$i = 1;
		while (array_key_exists($text, $this->dbs)) {
			$text .= "-$i";
			$i++;
		}

		return $text;
	}

	/**
	 * Parses rewrite and actual params var and sanitizes
	 * @return array the params array parsed
	 */
	public function parse_params($parts = null) {

		if ($parts == null)
			$parts = $this->request->input;

		$defaults = array(
			'db' => null,
			'table' => null,
			'order_by' => null,
			'direction' => null,
			'insert' => null,
			'update' => null,
			'limit' => null,
			'format' => 'json',
			'callback' => null,
			'where' => null,
			'join' => null,
			'id' => null,

			'token' => null,
			'check_token' => null,
			'user_id' => null,
			'password' => null,
		);

		//die(print_r($parts));
		$parts = shortcode_atts($defaults, $parts);
		//die(print_r($parts));

		if ($this->auth->validate($parts)) {
			if ($parts['db'] == null) {
				Request::error('Must select a database', 400);
			}

			$db = $this->get_db($parts['db']);

			if (!$this->auth->is_admin) {

				if (in_array($parts['table'], $db->table_blacklist)) {
					Request::error('Invalid table', 404);
				}

				if (count($db->table_list) > 0 && !in_array($parts['table'], $db->table_list)) {
					Request::error('Invalid table', 404);
				}

			}

			if (!in_array(strtoupper($parts['direction']), array('ASC', 'DESC'))) {
				$parts['direction'] = null;
			}

			if (!in_array($parts['format'], array('html', 'xml', 'json'))) {
				$parts['format'] = null;
			}

			$this->query = $parts;
			$this->set_db();
		}

		return $parts;
	}

	/**
	 * Retrieves a database and its properties
	 * @param string $db the DB slug (optional)
	 * @return array the database property array
	 */
	public function get_db($db = null) {

		if ($db == null && !is_null($this->db)) {
			return $this->db;
		}

		if (is_object($db)) {
			$db = $db->name;
		}


		if (!array_key_exists($db, $this->dbs)) {
			Request::error('Invalid Database', 404);
		}

		return $this->dbs[$db];

	}

	/**
	 * Sets the current database
	 * @param string $db the db slug
	 * @return bool success/fail
	 */
	public function set_db($db = null) {

		if ($db == null)
			$db = $this->query['db'];

		$db = $this->get_db($db);

		if (!$db) {
			return false;
		}

		$this->db = $db;

		return true;

	}

	/**
	 * Detect method of the request and execute the main database query
	 * @param array $query the database query ASSUMES SANITIZED
	 * @return array an array of results
	 */
	public function query($query = null, $db = null) {
		if ($query == null)
			$query = $this->query;
		switch (Request::method()) {
			case 'GET':
				if ($this->auth->can_read($query['table']))
					return $this->get_query($query, $db);
				break;
			case 'POST':
				if ($this->auth->can_write($query['table']))
					return $this->post_query($query, $db);
				break;
			case 'PUT':
				if ($this->auth->can_edit($query['table']))
					return $this->put_query($query, $db);
				break;
			case 'DELETE':
				if ($this->auth->can_delete($query['table']))
					return $this->delete_query($query, $db);
				break;
			default:
				return self::reponse_failed();
				break;
		}
		return self::no_permissions();
	}

	/**
	 * Build and execute the SELECT query from the GET request
	 * @param array $query the database query ASSUMES SANITIZED
	 * @return array an array of results
	 */
	private function get_query($query, $db = null) {

		$key = md5(serialize($query) . $this->get_db($db)->name);

		if ($cache = $this->cache_get($key)) {
			return $cache;
		}

		try {

			$dbh = &$this->connect($db);

			// check table name
			if ($query['table'] == null) {
				Request::error('Must select a table', 404);
			} elseif (!$this->verify_table($query['table'])) {
				Request::error('Invalid Table', 404);
			}

			// check WHERE
			if (isset($query['where']) && is_array($query['where'])) {
				foreach ($query['where'] as $column => $value) {
					$column_table = $query['table'];
					$_split = explode('.', $column,2);
					if(count($_split) > 1){
						$column = $_split[1];
						$column_table = $_split[0];
					}
					if (!$this->verify_column($column, $column_table)) {
						Request::error('Invalid WHERE column ' . $column_table . '.'. $column, 404);
					}
				}
			}

			// check id
			if (isset($query['id']) && !empty($query['id'])) {
				$query["where"][$this->get_first_column($query['table'])] = $query['id'];
			}

			$sql = 'SELECT * FROM ' . $query['table'];

			// build JOIN query
			if (isset($query['join']) && is_array($query['join'])) {

				$methods_available = array('INNER', 'LEFT', 'RIGHT');

				$join_values = array();
				foreach ($query['join'] as $table => $join) {
					if (!is_array($join) || count($join) < 2)
						break;
					$join_method = "";
					if (count($join) > 2) {
						$join['method'] = strtoupper($join['method']);
						if (in_array($join['method'], $methods_available))
							$join_method = $join['method'];
					}
					if (!$this->verify_table($query['table'])) {
						Request::error('Invalid Join table ' . $table, 404);
					}
					if (!$this->verify_column($join['on'], $table)) {
						Request::error('Invalid Join column ' . $table . '.' . $join['on'], 404);
					}

					$sql .= " {$join_method} JOIN {$table} ON {$table}.{$join['on']} = ";

					if (!$this->verify_column($join['value'], $table)) {
						$index_value = self::value_index("join_", $join['on'], $join_values);
						$join_values[$index_value] = $join['value'];
						$sql .= ":{$index_value}";
					} else {
						$sql .= "{$query['table']}.{$join['value']}";
					}
				}
			}

			// build WHERE query
			$restriction = $this->auth->sql_restriction($query['table'], 'READ');
			if (isset($query['where']) && is_array($query['where'])) {
				$where = $this->parse_where($query['table'], $query['where'], $sql);
				$sql = $where["sql"] . ' AND ' . $restriction;
				$where_values = $where["values"];
			} else if (!empty($restriction)) {
				$sql .= ' WHERE ' . $restriction;
			}

			// build ORDER query
			if (isset($query['order_by']) && !empty($query['order_by'])) {

				$order_by = $query['order_by'];
				if (!is_array($order_by))
					$order_by = explode(',', $order_by);

				$order_by = array_map('trim', $order_by);

				$order_query = array();
				foreach ($order_by as $column => $column_direction) {
					$order_table = $query['table'];
					$direction = '';
					if(!is_int($column)) {
						$column = trim($column);
						$_split = array_map('trim', explode('.', $column, 2));
						if (count($_split) > 1 && $this->verify_column(@$_split[1], @$_split[0])) {
							$order_table = trim($_split[0]);
							$column = trim($_split[1]);
						} else if (!$this->verify_column($column, $order_table)) {
							if (count($_split) > 1) {
								Request::error('Invalid order column ' . $_split[0] . '.' . $_split[1], 404);
							} else {
								Request::error('Invalid order column ' . $order_table . '.' . $column, 404);
							}
						}
						$order_direction = trim($column_direction);
						if (!empty($order_direction) && in_array(strtoupper($order_direction), array('ASC', 'DESC')))
							$direction = $order_direction;
					} else {
						$_split = array_map('trim', explode('.', $column_direction, 2));
						if (count($_split) > 1 && $this->verify_column(@$_split[1], @$_split[0])) {
							$order_table = $_split[0];
							$column = $_split[1];
						} else if ($this->verify_column($column_direction, $order_table)) {
							$column = $column_direction;
						} else {
							if (count($_split) > 1) {
								Request::error('Invalid order column ' . $_split[0] . '.' . $_split[1], 404);
							} else {
								Request::error('Invalid order column ' . $order_table . '.' . $column_direction, 404);
							}
						}
					}
					$order_query[] = "{$order_table}.{$column} {$direction}";
				}
				if (empty($query['direction'])) $query['direction'] = "";
				$sql .= " ORDER BY " . implode(",", $order_query) . " {$query['direction']}";
			}

			// build LIMIT query
			if (isset($query['limit']) && is_numeric($query['limit'])) {
				$sql .= " LIMIT " . (int)$query['limit'];
			}

			$sql_compiled = $sql;

			$sth = $dbh->prepare($sql);

			// bind WHERE values
			if (isset($where_values) && count($where_values) > 0) {
				foreach ($where_values as $key => $value) {
					$type = self::PDO_type($value);
					$key = ':' . $key;
					$sql_compiled = str_replace($key, "'" . $value . "'", $sql_compiled);
					$sth->bindValue($key, $value, $type);
				}
			}

			// bind JOIN values
			if (isset($join_values) && count($join_values) > 0) {
				foreach ($join_values as $key => $value) {
					$type = self::PDO_type($value);
					$key = ':' . $key;
					$sql_compiled = str_replace($key, "'" . $value . "'", $sql_compiled);
					$sth->bindValue($key, $value, $type);
				}
			}

			//die($sql_compiled);

			$sth->execute();

			$results = $sth->fetchAll(PDO::FETCH_OBJ);
			$results = $this->sanitize_results($query['table'], $results);

		} catch (PDOException $e) {
			Request::error($e);
		}

		$this->cache_set($key, $results, $this->get_db($db)->ttl);

		return $results;

	}

	/**
	 * Retrieve data from Alternative PHP Cache (APC).
	 */
	private function cache_get($key) {

		if (!extension_loaded('apc') || (ini_get('apc.enabled') != 1)) {
			if (isset($this->cache[$key])) {
				return $this->cache[$key];
			}
		} else {
			return apc_fetch($key);
		}

		return false;

	}

	/**
	 * Establish a database connection
	 * @param string $db the database slug
	 * @return object the PDO object
	 * @todo support port #s and test on each database
	 */
	public function &connect($db = null) {

		if ($db == null && !is_null($this->db)) {
			return $this->db;
		}

		// check for existing connection
		if (isset($this->connections[$db])) {
			return $this->connections[$db];
		}

		$db = $this->get_db($db);

		try {
			if ($db->type == 'mysql') {
				$dbh = new PDO("mysql:host={$db->server};dbname={$db->name}", $db->username, $db->password);
			} elseif ($db->type == 'pgsql') {
				$dbh = new PDO("pgsql:host={$db->server};dbname={$db->name}", $db->username, $db->password);
			} elseif ($db->type == 'mssql') {
				$dbh = new PDO("sqlsrv:Server={$db->server};Database={$db->name}", $db->username, $db->password);
			} elseif ($db->type == 'sqlite') {
				$dbh = new PDO("sqlite:/{$db->name}");
			} elseif ($db->type == 'oracle') {
				$dbh = new PDO("oci:dbname={$db->name}");
			} elseif ($db->type == 'ibm') {
				// May require a specified port number as per http://php.net/manual/en/ref.pdo-ibm.connection.php.
				$dbh = new PDO("ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE={$db->name};HOSTNAME={$db->server};PROTOCOL=TCPIP;", $db->username, $db->password);
			} elseif (($db->type == 'firebird') || ($db->type == 'interbase')) {
				$dbh = new PDO("firebird:dbname={$db->name};host={$db->server}");
			} elseif ($db->type == '4D') {
				$dbh = new PDO("4D:host={$db->server}", $db->username, $db->password);
			} elseif ($db->type == 'informix') {
				$dbh = new PDO("informix:host={$db->server}; database={$db->name}; server={$db->server}", $db->username, $db->password);
			} else {
				Request::error('Unknown database type.');
			}
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			Request::error($e);
		}

		// cache
		$this->connections[$db->type] = &$dbh;

		return $dbh;

	}

	/**
	 * Verify a table exists, used to sanitize queries
	 * @param string $query_table the table being queried
	 * @param string $db the database to check
	 * @param return bool true if table exists, otherwise false
	 */
	public function verify_table($query_table, $db = null) {

		if (!$this->auth->is_admin) {
			if (!empty($db->table_list) && is_array($db->table_list)) {
				if (!in_array($query_table, $db->table_list)) return false;
			}

			if (!empty($db->table_blacklist) && is_array($db->table_blacklist)) {
				if (in_array($query_table, $db->table_blacklist)) return false;
			}
		}

		$tables = $this->cache_get($this->get_db($db)->name . '_tables');

		if (!$tables) {

			$dbh = &$this->connect($db);
			try {
				if ($this->get_db($db)->type == "mysql")
					$stmt = $dbh->query("SHOW TABLES");
				else
					$stmt = $dbh->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
			} catch (PDOException $e) {
				Request::error($e);
			}

			$tables = array();
			while ($table = $stmt->fetch()) {
				$tables[] = $table[0];
			}

		}

		return in_array($query_table, $tables);

	}

	/**
	 * Returns the first column in a table
	 * @param string $table the table
	 * @param string $db the datbase slug
	 * @return string the column name
	 */
	public function get_first_column($table, $db = null) {

		$columns = $this->get_columns($table, $db);
		return reset($columns);

	}

	/**
	 * Returns an array of all columns in a table
	 * @param string $table the table to check
	 * @param string $db the database to check
	 * @return array an array of the column names
	 */
	public function get_columns($table, $db = null) {

		if (!$this->verify_table($table)) {
			return false;
		}

		$key = $this->get_db($db)->name . '.' . $table . '_columns';

		if ($cache = $this->cache_get($key)) {
			return $cache;
		}

		$dbh = &$this->connect($db);

		try {
			if ($this->get_db($db)->type == "mysql")
				$q = $dbh->prepare("DESCRIBE {$table}");
			else
				$q = $dbh->prepare("SELECT column_name FROM information_schema.columns WHERE table_name ='{$table}'");
			$q->execute();
			$columns = $q->fetchAll(PDO::FETCH_COLUMN);
		} catch (PDOException $e) {
			Request::error($e);
		}

		$this->cache_set($key, $columns, $this->get_db($db)->ttl);
		return $columns;
	}

	/**
	 * Store data in Alternative PHP Cache (APC).
	 */
	private function cache_set($key, $value, $ttl = null) {

		if ($ttl == null) {
			$ttl = (isset($this->db->ttl)) ? $this->db->ttl : $this->ttl;
		}

		$key = 'api_' . $key;

		if (extension_loaded('apc') && (ini_get('apc.enabled') == 1)) {
			return apc_store($key, $value, $ttl);
		}

		$this->cache[$key] = $value;


	}

	/**
	 * Verify a column exists
	 * @param string $column the column to check
	 * @param string $table the table to check
	 * @param string $db (optional) the db to check
	 * @retrun bool true if exists, otherwise false
	 */
	public function verify_column($column, $table, $db = null) {

		if (!$this->auth->is_admin) {
			if (!empty($db->column_list[$table]) && is_array($db->column_list[$table])) {
				if (!in_array($column, $db->column_list[$table])) return false;
			}

			if (!empty($db->column_blacklist[$table]) && is_array($db->column_blacklist[$table])) {
				if (in_array($column, $db->column_blacklist[$table])) return false;
			}
		}

		$columns = $this->get_columns($table, $db);
		return in_array($column, $columns);

	}

	/**
	 * Change column value index name for bind value on PDO
	 */
	private static function value_index($prefix = "_", $column, $array) {
		$i = 1;
		$column = $prefix . $column;
		$index = $column;
		while (array_key_exists($index, $array)) {
			$i++;
			$index = $column . "_" . $i;
		}
		return $index;
	}

	/**
	 * Build WHERE query and assign to an array the values to bind on the PDO obj
	 */
	private function parse_where($main_table, $where, $sql) {

		$prefix = "where_";
		$where_sql = array();
		$where_values = array();

		$cases = array(
			'>' => '>',
			'<' => '<',
			'%' => 'LIKE',
		);

		$sql .= " WHERE ";

		foreach ($where as $column => $values_column) {

			$table = $main_table;
			$_split = explode('.', $column,2);
			if(count($_split) > 1){
				$table = $_split[0];
				$column = $_split[1];
			}

			// Check equal case
			if (!is_array($values_column)) {
				$_value_split = explode('.', $values_column,2);
				if(count($_value_split) > 1 && $this->verify_column(@$_value_split[1], @$_value_split[0])){
					$index_value = $_value_split[0] . "." . $_value_split[1];
				} else {
					$index_key = self::value_index($prefix, $column, $where_values);
					$index_value = " :" . $index_key;
					$where_values[$index_key] = $values_column;
				}
				$where_sql[] = "{$table}.{$column} = {$index_value}";
			} else {

				$where_in = array();

				foreach ($values_column as $condition => $value_condition) {

					// Check special cases
					if (array_key_exists($condition, $cases)) {
						$bind = $cases[$condition];
						if (!is_array($value_condition)) {
							$_value_split = explode('.', $value_condition,2);
							if(count($_value_split) > 1 && $this->verify_column(@$_value_split[1], @$_value_split[0])){
								$index_value = $_value_split[0] . "." . $_value_split[1];
							} else {
								$index_key = self::value_index($prefix, $column, $where_values);
								$index_value = " :" . $index_key;
								$where_values[$index_key] = $value_condition;
							}
							$where_sql[] = "{$table}.{$column} " . $bind . " {$index_value}";
						} else {
							foreach ($value_condition as $value) {
								$index_key = self::value_index($prefix, $column, $where_values);
								$index_value = " :" . $index_key;
								$where_values[$index_key] = $value;
								$where_sql[] = "{$table}.{$column} " . $bind . " {$index_value}";
							}
						}

						// Check unequal cases
					} elseif ($condition === '!') {

						$where_not_in = array();
						if (!is_array($value_condition))
							$value_condition = array($value_condition);

						foreach ($value_condition as $value) {
							$_value_split = explode('.', $value,2);
							if(count($_value_split) > 1 && $this->verify_column(@$_value_split[1], @$_value_split[0])){
								$index_value = $_value_split[0] . "." . $_value_split[1];
							} else {
								$index_key = self::value_index($prefix, $column, $where_values);
								$index_value = " :" . $index_key;
								$where_values[$index_key] = $value;
							}
							$where_not_in[] = $index_value;
						}

						if (count($where_not_in) > 0)
							$where_sql[] = "{$table}.{$column} NOT IN (" . implode(", ", $where_not_in) . ")";

						// Check equal array cases
					} elseif (!is_array($value_condition) && is_int($condition) || $condition === '=') {
						$_value_split = explode('.', $value_condition,2);
						if(count($_value_split) > 1 && $this->verify_column(@$_value_split[1], @$_value_split[0])){
							$index_value = $_value_split[0] . "." . $_value_split[1];
						} else {
							$index_key = self::value_index($prefix, $column, $where_values);
							$index_value = " :" . $index_key;
							$where_values[$index_key] = $value_condition;
						}
						$where_in[] = $index_value;
					}
				}

				if (count($where_in) > 0)
					$where_sql[] = "{$table}.{$column} IN (" . implode(", ", $where_in) . ")";
			}
		}

		if (count($where_sql) > 0) {
			$sql .= implode(" AND ", $where_sql);
		}

		//die($sql);

		$result = array();
		$result["sql"] = $sql;
		$result["values"] = $where_values;

		return $result;
	}

	/**
	 * Detect the type of value and return the PDO::PARAM
	 */
	private static function PDO_type($value) {
		if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) return PDO::PARAM_BOOL;
		if (filter_var($value, FILTER_VALIDATE_INT)) return PDO::PARAM_INT;
		if (is_null($value)) return PDO::PARAM_NULL;
		return PDO::PARAM_STR;
	}

	/**
	 * Remove any blacklisted columns from the data set.
	 */
	private function sanitize_results($table, $results, $db = null) {

		$db = $this->get_db($db);

		foreach ($results as $key => $result) {

			if (!$this->auth->is_admin) {
				// blacklist
				if (!empty($db->column_blacklist[$table]) && is_array($db->column_blacklist[$table])) {
					foreach ($db->column_blacklist[$table] as $column) {
						unset($results[$key]->$column);
					}
				}

				// whitelist
				if (!empty($db->column_list[$table]) && is_array($db->column_list[$table])) {
					foreach ($result as $column => $value) {
						if (count($db->column_list[$table]) > 0) {
							if (!in_array($column, $db->column_list[$table])) {
								unset($results[$key]->$column);
							}
						}
					}
				}
			}
			// Sanitize encoding
			foreach ($result as $column => $value) {
				$results[$key]->$column = utf8_encode($value);
			}
		}

		return $results;

	}

	/**
	 * Build and execute the INSERT query from the POST request
	 * @param array $query the database query ASSUMES SANITIZED
	 * @return array an array of results
	 */
	private function post_query($query, $db = null) {

		try {

			$dbh = &$this->connect($db);

			// check values
			if (!isset($query['insert']) && !is_array($query['insert']) && count($query['insert']) <= 0) {
				Request::error('Invalid values', 400);
			}

			foreach ($query['insert'] as $table => $values) {
				$columns = array();
				if (!$this->verify_table($table)) {
					Request::error('Invalid Table', 404);
				}
				// check columns name
				foreach ($values as $key => $value) {
					if (!$this->verify_column($key, $table)) {
						Request::error('Invalid column. The column ' . $table . '.' . $key . ' not exists!', 404);
					}
					$columns[] = $key;
				}

				$sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')';

				$sth = $dbh->prepare($sql);

				// bind POST values
				foreach ($values as $key => $value) {
					if (is_array($value)) $value = serialize($value);
					$key = ':' . $key;
					$sth->bindValue($key, $value);
				}

				$sth->execute();
			}
		} catch (PDOException $e) {
			Request::error($e);
		}

		return self::reponse_success();
	}

	/**
	 * Return success reponse
	 */
	private static function reponse_success() {
		http_response_code(200);
		return array("response" => (object)array('status' => 200, 'message' => 'OK'));
	}

	/**
	 * Build and execute the UPDATE query from the PUT request
	 * @param array $query the database query ASSUMES SANITIZED
	 * @return array an array of results
	 */
	private function put_query($query, $db = null) {

		try {

			$dbh = &$this->connect($db);

			// Check id
			if (isset($query['table']) && !empty($query['table']) && isset($query['id']) && !empty($query['id'])) {
				// Check WHERE
				if (isset($query['where']) && is_array($query['where'])) {
					foreach ($query['where'] as $column => $value) {
						$column_table = $query['table'];
						$_split = explode('.', $column,2);
						if(count($_split) > 1){
							$column = $_split[1];
							$column_table = $_split[0];
						}
						if (!$this->verify_column($column, $column_table)) {
							Request::error('Invalid WHERE column ' . $column_table . '.'. $column, 404);
						}
					}
					$query['update'][$query['table']]['where'] = $query['where'];
					$query['update'][$query['table']]['where'][$this->get_first_column($query['table'])] = $query['id'];
				}
				$query['update'][$query['table']]['values'] = $query['update'];
				$query['update'][$query['table']]['where'][$this->get_first_column($query['table'])] = $query['id'];
				// Check values
			} elseif (!isset($query['update']) && !is_array($query['update']) && count($query['update']) <= 0) {
				Request::error('Invalid values', 400);
			} else {
				foreach ($query['update'] as $table => $update) {
					if (isset($update['where']) && is_array($update['where'])) {
						foreach ($update['where'] as $column => $value) {
							if (!$this->verify_column($column, $table)) {
								Request::error('Invalid Where column ' . $column, 404);
							}
						}
					}
				}
			}

			foreach ($query['update'] as $table => $values) {
				$where = $values['where'];
				$values = $values['values'];
				$values_index = array();

				if (!$this->verify_table($table)) {
					Request::error('Invalid Table', 404);
				}
				// check columns name
				foreach ($values as $key => $value) {
					if (!$this->verify_column($key, $table)) {
						Request::error('Invalid column. The column ' . $table . '.' . $key . ' not exists!', 404);
					}
					$values_index[] = $key . ' = :' . $key;
				}

				$sql = 'UPDATE ' . $table;
				$sql .= ' SET ' . implode(', ', $values_index);

				// build WHERE query
				$restriction = $this->auth->sql_restriction($query['table'], 'EDIT');
				if (is_array($where)) {
					$where_parse = $this->parse_where($table, $where, $sql);
					$sql = $where_parse["sql"] . ' AND ' . $restriction;
					$where_values = $where_parse["values"];
				} else if (!empty($restriction)) {
					$sql .= ' WHERE ' . $restriction;
				}


				$sth = $dbh->prepare($sql);
				$sql_compiled = $sql;

				// bind PUT values
				foreach ($values as $key => $value) {
					if (is_array($value)) $value = serialize($value);
					$key = ':' . $key;
					$sql_compiled = str_replace($key, "'" . $value . "'", $sql_compiled);
					$sth->bindValue($key, $value);
				}

				// bind WHERE values
				if (isset($where_values) && count($where_values) > 0) {
					foreach ($where_values as $key => $value) {
						$key = ':' . $key;
						$sql_compiled = str_replace($key, "'" . $value . "'", $sql_compiled);
						$sth->bindValue($key, $value);
					}
				}

				//die($sql_compiled);

				$sth->execute();
			}
		} catch (PDOException $e) {
			Request::error($e);
		}

		return self::reponse_success();
	}

	/**
	 * Build and execute the DELETE query from the DELETE request
	 * @param array $query the database query ASSUMES SANITIZED
	 * @return array an array of results
	 */
	private function delete_query($query, $db = null) {

		try {

			$dbh = &$this->connect($db);
			$values = array();
			$column_id = $this->get_first_column($query['table']);

			// check table name
			if (!$this->verify_table($query['table'])) {
				Request::error('Invalid Table', 404);
			}

			// check ID
			if (isset($query['id']) && !empty($query['id'])) {
				$query["where"][$this->get_first_column($query['table'])] = $query['id'];
			}

			$sql = 'DELETE FROM ' . $query['table'];

			// build WHERE query
			$restriction = $this->auth->sql_restriction($query['table'], 'DELETE');
			if (isset($query['where']) && is_array($query['where'])) {
				$where = $this->parse_where($query['table'], $query['where'], $sql);
				$sql = $where["sql"] . ' AND ' . $restriction;
				$where_values = $where["values"];
			} else if (!empty($restriction)) {
				$sql .= ' WHERE ' . $restriction;
			}

			$sth = $dbh->prepare($sql);
			$sql_compiled = $sql;

			// bind WHERE values
			if (isset($where_values) && count($where_values) > 0) {
				foreach ($where_values as $key => $value) {
					$type = self::PDO_type($value);
					$key = ':' . $key;
					$sql_compiled = str_replace($key, "'" . $value . "'", $sql_compiled);
					$sth->bindValue($key, $value, $type);
				}
			}

			//die($sql_compiled);

			$sth->execute();
		} catch (PDOException $e) {
			Request::error($e);
		}

		return self::reponse_success();
	}

	/**
	 * Return failed reponse
	 */
	private static function reponse_failed() {
		Request::error("Bad request", 400);
	}

	/**
	 * Return failed reponse
	 */
	private static function no_permissions() {
		Request::error("No permissions", 403);
	}

	/**
	 * Output JSON encoded data.
	 * @todo Support JSONP, with callback filtering.
	 */
	public function render_json($data, $query) {

		header('Content-type: application/json');

		$output = json_encode($data);
		//ie(var_dump(json_last_error()));

		// Prepare a JSONP callback.
		$callback = $this->jsonp_callback_filter($query['callback']);

		// Only send back JSONP if that's appropriate for the request.
		if ($callback) {
			echo "{$callback}($output);";
			return;
		}

		// If not JSONP, send back the data.
		echo $output;

	}

	/**
	 * Prevent malicious callbacks from being used in JSONP requests.
	 */
	private function jsonp_callback_filter($callback) {

		// As per <http://stackoverflow.com/a/10900911/1082542>.
		if (preg_match('/[^0-9a-zA-Z\$_]|^(abstract|boolean|break|byte|case|catch|char|class|const|continue|debugger|default|delete|do|double|else|enum|export|extends|false|final|finally|float|for|function|goto|if|implements|import|in|instanceof|int|interface|long|native|new|null|package|private|protected|public|return|short|static|super|switch|synchronized|this|throw|throws|transient|true|try|typeof|var|volatile|void|while|with|NaN|Infinity|undefined)$/', $callback)) {
			return false;
		}

		return $callback;

	}

	/**
	 * Output data as an HTML table.
	 */
	public function render_html($data) {

		require_once(__ROOT__ . '/includes/template/header.php');

		//err out if no results
		if (empty($data)) {
			Request::error('No results found', 404);
			return;
		}

		//render readable array data serialized
		foreach ($data as $key => $result) {
			foreach ($result as $column => $value) {
				$value_parsed = @unserialize($value);
				if ($value_parsed !== false || $value === 'b:0;') {
					if (is_array($data)) {
						$data[$key]->$column = implode("|", $value_parsed);
					}
				}
			}
		}

		//page title
		echo "<h2>Results</h2>";

		//render table headings
		echo "<table class='table table-striped'>\n<thead>\n<tr>\n";

		foreach (array_keys(get_object_vars(reset($data))) as $heading) {
			echo "\t<th>$heading</th>\n";
		}

		echo "</tr>\n</thead>\n";

		//loop data and render
		foreach ($data as $row) {
			echo "<tr>\n";
			foreach ($row as $cell) {
				echo "\t<td>$cell</td>\n";
			}
			echo "</tr>";
		}

		echo "</table>";

		require_once(__ROOT__ . '/includes/template/footer.php');

	}

	/**
	 * Output data as XML.
	 */
	public function render_xml($data) {

		header("Content-Type:text/xml");
		$xml = new SimpleXMLElement('<results></results>');
		$xml = $this->object_to_xml($data, $xml);
		echo $this->tidy_xml($xml);

	}

	/**
	 * Recusively travserses through an array to propegate SimpleXML objects
	 * @param array $array the array to parse
	 * @param object $xml the Simple XML object (must be at least a single empty node)
	 * @return object the Simple XML object (with array objects added)
	 */
	private function object_to_xml($array, $xml) {

		//array of keys that will be treated as attributes, not children
		$attributes = array('id');

		//recursively loop through each item
		foreach ($array as $key => $value) {

			//if this is a numbered array,
			//grab the parent node to determine the node name
			if (is_numeric($key))
				$key = 'result';

			//if this is an attribute, treat as an attribute
			if (in_array($key, $attributes)) {
				$xml->addAttribute($key, $value);

				//if this value is an object or array, add a child node and treat recursively
			} else if (is_object($value) || is_array($value)) {
				$child = $xml->addChild($key);
				$child = $this->object_to_xml($value, $child);

				//simple key/value child pair
			} else {
				$xml->addChild($key, $value);
			}

		}

		return $xml;

	}

	/**
	 * Clean up XML domdocument formatting and return as string
	 */
	private function tidy_xml($xml) {

		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($xml->asXML());
		return $dom->saveXML();

	}
}