<?php

namespace marcocesarato\DatabaseAPI;

use PDO;
use PDOException;
use SimpleXMLElement;
use stdClass;

/**
 * API Class.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
class API
{
    /**
     * @var self
     */
    public static $instance;
    /**
     * @var Auth
     */
    public $auth;
    /**
     * @var Hooks
     */
    public $hooks;
    /**
     * @var Logger
     */
    public $logger;
    /**
     * @var Request
     */
    public $request;
    public $dbs = [];
    public $db;
    public $dbh;
    public $query = [];
    public $ttl = 3600;
    public $cache = [];
    public $connections = [];

    private static $enable_errors = false;

    /**
     * Singleton constructor.
     */
    public function __construct()
    {
        self::$instance = &$this;
        $this->request = Request::getInstance();
        $this->logger = Logger::getInstance();
        $this->hooks = Hooks::getInstance();
        $this->auth = Auth::getInstance();
    }

    /**
     * Returns static reference to the class instance.
     *
     * @return self
     */
    public static function &getInstance()
    {
        return self::$instance;
    }

    /**
     * Run API.
     */
    public static function run()
    {
        $api = self::getInstance();
        $api->parseParams();
        $results = $api->query();
        $api->render($results);
    }

    /**
     * Returns a database connection.
     *
     * @param null $db
     *
     * @return \PDO
     */
    public static function getConnection($db = null)
    {
        return self::$instance->connect($db);
    }

    /**
     * Register dataset.
     *
     * @param $datasets
     */
    public static function registerDatasets($datasets)
    {
        $api = self::getInstance();
        foreach ($datasets as $db_name => $db_config) {
            $api->registerDatabase($db_name, $db_config);
        }
    }

    /**
     * Register a new database dataset.
     *
     * @param string $name the database dataset name
     * @param array  $args the database dataset properties
     */
    public function registerDatabase($name = null, $args = [])
    {
        $defaults = [
            'default' => false,
            'api' => true,
            'name' => null,
            'username' => 'root',
            'password' => 'root',
            'server' => 'localhost',
            'port' => 3306,
            'type' => 'mysql',
            'table_docs' => [],
            'table_blacklist' => [],
            'table_list' => [],
            'table_free' => [],
            'table_readonly' => [],
            'column_blacklist' => [],
            'column_list' => [],
            'ttl' => $this->ttl,
            'receivers' => [],
        ];

        $args = shortcode_atts($defaults, $args);
        $name = $this->slugify($name);

        $this->dbs[$name] = (object)$args;
    }

    /**
     * Parses rewrite and actual params var and sanitizes.
     *
     * @param null $parts
     *
     * @return array the params array parsed
     */
    public function parseParams($parts = null)
    {
        if ($parts == null) {
            $parts = $this->request->input;
        }

        $defaults = [
            'db' => null,
            'table' => null,
            'order_by' => null,
            'direction' => null,
            'docs' => null,
            'insert' => null,
            'update' => null,
            'limit' => null,
            'offset' => null,
            'format' => null,
            'callback' => null,
            'where' => null,
            'join' => null,
            'prefix' => null,
            'id' => null,
            'unique' => null,

            'client_id' => null,
            'referer' => null,

            'token' => null,
            'check_token' => null,
            'check_counter' => null,
            'force_validation' => null,
            'user_id' => null,
            'password' => null,
        ];

        $this->query = shortcode_atts($defaults, $parts);
        $this->query = $this->hooks->apply_filters('request_input_query', $this->query);

        if (!empty($this->query['db'])) {
            $db = $this->getSelectedDatabase($this->query['db']);
            $this->setDatabase($db);
        } else {
            $this->setDatabase();
        }

        if ($this->auth->validate($this->query)) {
            if ($this->query['db'] == null) {
                Response::error('Must select a Dataset', 400, true);
            }

            if (!in_array(strtoupper($this->query['direction']), ['ASC', 'DESC'])) {
                $this->query['direction'] = null;
            }

            if (!in_array($this->query['format'], ['html', 'xml', 'json'])) {
                $this->query['format'] = null;
            }

            if (!$db->api) {
                Response::error('Invalid Dataset', 404, true);
            }

            if (!empty($this->query['table']) && !$this->query['docs']) {
                $this->query['table'] = $this->hooks->apply_filters('get_query_table', $this->query['table']);
                if (!$this->auth->isAdmin()) {
                    if (in_array($this->query['table'], $db->table_blacklist)) {
                        Response::error('Invalid Entity', 404, true);
                    }
                    if (count($db->table_list) > 0 && !in_array($this->query['table'], $db->table_list)) {
                        Response::error('Invalid Entity', 404, true);
                    }
                }
                if (!$this->checkTable($this->query['table'], $db)) {
                    Response::error('Invalid Entity', 404, true);
                }
            }
        }

        return $this->query;
    }

    /**
     * Retrieves the selected database and its properties.
     *
     * @param string|object $db the DB slug (optional)
     *
     * @return object the database property array
     */
    public function getSelectedDatabase($db = null)
    {
        if ($db == null && !is_null($this->db)) {
            return $this->db;
        }

        if (is_object($db)) {
            foreach ($this->dbs as $key => $values) {
                if ($values->name == $db->name) {
                    $db = $key;
                    break;
                }
            }
        }

        if (empty($db)) {
            $db = $this->query['db'];
        }

        if (!array_key_exists($db, $this->dbs)) {
            Response::error('Invalid Dataset', 404, true);
        }

        return $this->dbs[$db];
    }

    /**
     * Retrieves a database and its properties.
     *
     * @param string|object $db the DB slug (optional)
     *
     * @return object the database property array
     */
    public function getDatabase($db = null)
    {
        if ($db == null && !is_null($this->db)) {
            return $this->db;
        }

        if (is_object($db)) {
            foreach ($this->dbs as $key => $values) {
                if ($values->name == $db->name) {
                    $db = $key;
                    break;
                }
            }
        }

        if (empty($db)) {
            $db = $this->query['db'];
        }

        if (!@array_key_exists($db, $this->dbs)) {
            foreach ($this->dbs as $key => $values) {
                if ($values->default) {
                    $db = $key;
                    break;
                }
            }
        }

        return $this->dbs[$db];
    }

    /**
     * Sets the current database.
     *
     * @param string|object $db the db slug
     *
     * @return bool success/fail
     */
    public function setDatabase($db = null)
    {
        if ($db == null) {
            $db = $this->query['db'];
        }

        if (is_object($db)) {
            $db = $this->getDatabase($db);
        }

        $db = $this->getDatabase($db);

        if (!$db) {
            return false;
        }

        $this->db = $db;

        return true;
    }

    /**
     * Establish a database connection.
     *
     * @param string|object $db the database slug
     *
     * @return \PDO
     *
     * @todo support port #s and test on each database
     */
    public function &connect($db = null)
    {
        // check for existing connection
        if (empty($db) && !empty($this->db->name) && !empty($this->connections[$this->db->name])) {
            $db = $this->db->name;

            return $this->connections[$db];
        }

        if (!empty($db) && is_string($db) && !empty($this->connections[$db])) {
            return $this->connections[$db];
        }

        $db = $this->getDatabase($db);

        try {
            if ($db->type === 'mysql') {
                $dbh = new PDO("mysql:host={$db->server};port={$db->port};dbname={$db->name}", $db->username, $db->password);
            } elseif ($db->type === 'pgsql') {
                $dbh = new PDO("pgsql:host={$db->server};port={$db->port};dbname={$db->name}", $db->username, $db->password);
            } elseif ($db->type === 'mssql') {
                $dbh = new PDO("sqlsrv:Server={$db->server},{$db->port};Database={$db->name}", $db->username, $db->password);
            } elseif ($db->type === 'sqlite') {
                $dbh = new PDO("sqlite:/{$db->name}");
            } elseif ($db->type === 'oracle') {
                $dbh = new PDO("oci:dbname={$db->name}");
            } elseif ($db->type === 'ibm') {
                // May require a specified port number as per http://php.net/manual/en/ref.pdo-ibm.connection.php.
                $dbh = new PDO("ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE={$db->name};PORT={$db->port};HOSTNAME={$db->server};PROTOCOL=TCPIP;", $db->username, $db->password);
            } elseif (($db->type === 'firebird') || ($db->type == 'interbase')) {
                $dbh = new PDO("firebird:dbname={$db->name};host={$db->server};port={$db->port};");
            } elseif ($db->type === '4D') {
                $dbh = new PDO("4D:host={$db->server};port={$db->port};dbname={$db->name}", $db->username, $db->password);
            } elseif ($db->type === 'informix') {
                $dbh = new PDO("informix:host={$db->server};port={$db->port};database={$db->name};server={$db->server}", $db->username, $db->password);
            } else {
                Response::error('Unknown database type.');
            }
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            Response::error($e);
        }

        // cache
        $this->connections[$db->type] = &$dbh;

        return $dbh;
    }

    /**
     * @section Queries
     */

    /**
     * Detect method of the request and execute the main database query.
     *
     * @param array $query the database query ASSUMES SANITIZED
     * @param null  $db
     *
     * @return array|bool
     */
    public function query($query = null, $db = null)
    {
        if ($query == null) {
            $query = $this->query;
        }
        $query['table'] = $this->hooks->apply_filters('get_query_table', $query['table']);
        switch (Request::method()) {
            case 'GET':
                if ($this->auth->canRead($query['table'])) {
                    if (!empty($query['docs'])) {
                        return $this->docs($query, $db);
                    }

                    return $this->get($query, $db);
                }
                break;
            case 'POST':
                return $this->post($query, $db);
                break;
            case 'PUT':
                return $this->put($query, $db);
                break;
            case 'PATCH':
                return $this->patch($query, $db);
                break;
            case 'DELETE':
                if ($this->auth->canDelete($query['table'])) {
                    return $this->delete($query, $db);
                }
                Response::noPermissions('- Can\'t delete on ' . $query['table']);
                break;
            default:
                Response::failed();

                return false;
                break;
        }
        Response::noPermissions();

        return false;
    }

    /**
     * Build and execute the docs query from the GET request.
     *
     * @param      $query
     * @param null $db
     *
     * @return array|bool|mixed|stdClass
     */
    private function docs($query, $db = null)
    {
        $final_result = [];

        $key = crc32(json_encode($query) . $this->getDatabase($db)->name . '_docs');

        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        try {
            $dbh = &$this->connect($db);
            // check table name
            if ($query['table'] === 'index' || empty($query['table'])) {
                $tables = $this->getTables($db);
                $url = build_base_url('/docs/openapi.json');
                $final_result = [
                    (object)[
                        'Entity' => '<b>OpenAPI Documentation</b>',
                        'Link' => '<a href="' . $url . '">Documentation (application/json)</a>',
                    ],
                ];
                foreach ($tables as $table) {
                    if ($this->checkTable($table)) {
                        $url = build_base_url('/docs/' . $table . '.' . $this->query['format']);
                        $final_result[] = (object)[
                            'Entity' => $table,
                            'Link' => '<a href="' . $url . '">Entity Details</a>',
                        ];
                    }
                }
            } elseif (($query['table'] === 'openapi' || $query['table'] === 'swagger') &&
                      $this->query['format'] === 'json') {
                // Open API
                $docs = Docs::getInstance();
                header('Content-type: application/json');
                echo json_encode($docs->generate());
                exit();
            } elseif (!$this->checkTable($query['table'])) {
                Response::error('Invalid Entity', 404);
            } else {
                $results = $this->getTableMeta($query['table'], $db);

                if (!empty($results)) {
                    foreach ($results as $column) {
                        if ($this->checkColumn($column['column_name'], $query['table'])) {
                            $docs_table = !empty($this->db->table_docs[$query['table']]) ? $this->db->table_docs[$query['table']] : null;
                            $docs_column = !empty($docs_table[$column['column_name']]) ? $docs_table[$column['column_name']] : null;

                            if (!empty($docs_table) && empty($docs_column)) {
                                continue;
                            }

                            $default = preg_replace("/'([^']*)'.*/si", '$1', $column['column_default']);
                            $tmp = [
                                'Column' => $column['column_name'],
                                'Type' => strtoupper($column['data_type'] . (!empty($column['character_maximum_length']) ? '(' . $column['character_maximum_length'] . ')' : '')),
                                'Optional' => strtoupper($column['is_nullable']),
                                'Default' => empty($default) ? '-' : $default,
                                'Description' => '',
                                'Example' => '',
                            ];
                            if (!empty($docs_column) && is_array($docs_column)) {
                                if (!empty($docs_column['description'])) {
                                    $tmp['Description'] = ucfirst($docs_column['description']);
                                }
                                if (!empty($docs_column['example'])) {
                                    $cleaned = trim($docs_column['example'], "'");
                                    $tmp['Example'] = !empty($cleaned) ? $cleaned : $docs_column['example'];
                                }
                            }
                            $final_result[] = (object)$tmp;
                        }
                    }
                } else {
                    Response::error('Documentation not implemented for this database type!', 501);
                }
            }
        } catch (PDOException $e) {
            Response::error($e);
        }

        $this->setCache($key, $final_result, $this->getDatabase($db)->ttl);

        return $final_result;
    }

    /**
     * Build and execute the SELECT query from the GET request.
     *
     * @param array $query the database query ASSUMES SANITIZED
     * @param null  $db
     *
     * @return array|object[]|void an array of results
     */
    public function get($query, $db = null)
    {
        $key = crc32(json_encode($query) . $this->getDatabase($db)->name);

        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        try {
            $dbh = &$this->connect($db);

            // check table name
            if ($query['table'] == null) {
                Response::error('Must select a Entity', 404);
            } elseif (!$this->checkTable($query['table'], $db)) {
                Response::error('Invalid Entity', 404);
            }

            // check WHERE
            if (!empty($query['where']) && is_array($query['where'])) {
                foreach ($query['where'] as $column => $value) {
                    $column_table = $query['table'];
                    $_split = explode('.', $column, 2);
                    if (count($_split) > 1) {
                        $column = $_split[1];
                        $column_table = $_split[0];
                    }
                    if (!$this->checkColumn($column, $column_table, $db)) {
                        Response::error('Invalid where condition ' . $column_table . '.' . $column, 404);
                    }
                }
            }

            // check id
            if (!empty($query['id'])) {
                $query['where'][$this->getFirstColumn($query['table'], $db)] = $query['id'];
            }

            $selected_columns = [];
            $columns = $this->getColumns($query['table'], $db);
            foreach ($columns as $column) {
                if ($this->checkColumn($column, $query['table'], $db)) {
                    $selected_columns[] = "{$query['table']}.{$column}";
                }
            }
            $selected_columns = $this->hooks->apply_filters('selected_columns', $selected_columns, $query['table']);

            $group_columns = $selected_columns;
            $select_columns = implode(', ', $selected_columns);

            $select_tables = [$query['table']];

            // build JOIN query
            $join_sql = '';
            if (!empty($query['join']) && is_array($query['join'])) {
                $methods_available = ['INNER', 'LEFT', 'RIGHT'];

                $join_values = [];
                foreach ($query['join'] as $table => $join) {
                    if (!is_array($join) || count($join) < 2) {
                        break;
                    }

                    // Table
                    if (!$this->checkTable($table, $db)) {
                        if (!self::$enable_errors) {
                            continue;
                        }
                        Response::error('Invalid Join Entity ' . $table, 404);
                    }
                    $select_tables[] = $table;

                    // Method
                    $join_method = '';
                    if (count($join) > 2) {
                        $join['method'] = strtoupper($join['method']);
                        if (in_array($join['method'], $methods_available)) {
                            $join_method = $join['method'];
                        }
                    }

                    // ON Column
                    $join_on_table = $table;
                    $join_on_column = $join['on'];
                    $join_on_expl = explode('.', $join['on'], 2);
                    if (count($join_on_expl) > 1) {
                        $join_on_table = $join_on_expl[0];
                        $join_on_column = $join_on_expl[1];
                    }
                    if (!$this->columnExists($join_on_column, $join_on_table, $db) || !$this->checkTable($join_on_table, $db)) {
                        if (!self::$enable_errors) {
                            continue;
                        }
                        Response::error('Invalid Join condition ' . $table . '.' . $join['on'], 404);
                    }

                    $join_sql .= " {$join_method} JOIN {$table} ON {$join_on_table}.{$join_on_column} = ";

                    // Value
                    $join_value_table = $query['table'];
                    $join_value_column = $join['value'];
                    $join_on_expl = explode('.', $join['value'], 2);
                    if (count($join_on_expl) > 1) {
                        $join_value_table = $join_on_expl[0];
                        $join_value_column = $join_on_expl[1];
                    }

                    if (!in_array($table, [$join_on_table, $join_value_table])) {
                        if (!self::$enable_errors) {
                            continue;
                        }
                        Response::error('Invalid Join Entities ' . $join_on_table . ', ' . $join_value_table, 404);
                    }

                    if (!$this->columnExists($join_value_column, $join_value_table, $db) || !$this->checkTable($join_value_table, $db)) {
                        $index_value = self::indexValue('join_', $join['on'], $join_values);
                        $join_values[$index_value] = $join['value'];
                        $join_sql .= ":{$index_value}";
                    } else {
                        $join_sql .= "{$join_value_table}.{$join_value_column}";
                    }

                    $join_on_additional = $this->hooks->apply_filters('get_query_additional_join_on', '', $join_on_table);
                    $join_sql .= (!empty($join_on_additional) ? ' AND (' . $join_on_additional . ') ' : '');
                }
                if (count($select_tables) > 1) {
                    $standard_columns = [];
                    $prefix_columns = [];
                    foreach ($select_tables as $table) {
                        $prefix = $table . '__';
                        $columns = $this->getColumns($table, $db);
                        foreach ($columns as $column) {
                            if ($this->checkColumn($column, $table, $db)) {
                                $standard_columns[] = "{$table}.{$column} AS {$column}";
                                $prefix_columns[] = "{$table}.{$column} AS {$prefix}{$column}";
                            }
                        }
                    }
                    if (!empty($query['prefix'])) {
                        $select_columns = implode(', ', $prefix_columns);
                    } else {
                        $select_columns = implode(', ', $standard_columns);
                    }
                }
                // Prefix table before column
            } elseif (!empty($query['prefix'])) {
                $prefix_columns = [];
                foreach ($select_tables as $table) {
                    $prefix = $table . '__';
                    $columns = $this->getColumns($table, $db);
                    foreach ($columns as $column) {
                        if ($this->checkColumn($column, $table, $db)) {
                            $prefix_columns[] = "{$table}.{$column} AS {$prefix}{$column}";
                        }
                    }
                }
                $group_columns = $selected_columns;
                $select_columns = implode(', ', $prefix_columns);
            }

            $select_columns = $this->hooks->apply_filters('get_select_' . strtolower($query['table']), $select_columns);
            $sql = 'SELECT ' . $select_columns . ' FROM ' . $query['table'] . ' ' . $join_sql;

            $where_exists = false;
            // build WHERE query
            $restriction = $this->auth->permissionSQL($query['table'], 'READ');

            // Do not put inside if, moved here to let plugins add WHERE clause if not specified by user
            $query['where'] = $this->hooks->apply_filters('get_where_' . strtolower($query['table']), !empty($query['where']) ? $query['where'] : []);
            if (!empty($query['where']) && is_array($query['where'])) {
                $where_parse = $this->parseWhere($query['table'], $query['where'], $sql);
                $where_values = $where_parse['values'];
                $where_exists = (!empty($where_values));
                $sql = $where_parse['sql'];
            }

            $where_values = $this->hooks->apply_filters('get_where_values', $where_values, $query['table']);
            $where_additional = $this->hooks->apply_filters('get_query_additional_where', '', $query['table']);
            $where_additional_table = $this->hooks->apply_filters('get_query_additional_where_' . strtolower($query['table']), '', $query['table']);

            $sql .= ($where_exists ? ' AND ' : ' WHERE ') . ' (' . $restriction . ') ';
            $sql .= (!empty($where_additional) ? ' AND (' . $where_additional . ') ' : '');
            $sql .= (!empty($where_additional_table) ? ' AND (' . $where_additional_table . ') ' : '');

            $sql = $this->hooks->apply_filters('get_query_prefilter_' . strtolower($query['table']), $sql, $group_columns);

            // build ORDER query
            if (!empty($query['order_by'])) {
                $order_by = $query['order_by'];
                if (!is_array($order_by)) {
                    $order_by = explode(',', $order_by);
                }

                $order_by = array_map('trim', $order_by);

                $order_query = [];
                foreach ($order_by as $column => $column_direction) {
                    $order_table = $query['table'];
                    $direction = '';
                    $type = '';
                    if (!is_int($column)) {
                        $column = trim($column);

                        // Type casting
                        $_split_type = explode('::', $column, 2);
                        if (count($_split_type) > 1) {
                            $column = $_split_type[0];
                            $_type = strtoupper($_split_type[1]);
                            if (in_array($_type, [
                                'VARCHAR',
                                'TEXT',
                                'CHAR',
                                'INT',
                                'INTEGER',
                                'FLOAT',
                                'DOUBLE',
                                'BIGINT',
                                'TINYINT',
                                'BOOL',
                                'BOOLEAN',
                                'DECIMAL',
                                'SMALLINT',
                                'REAL',
                                'DATE',
                                'DATETIME',
                                'TIMESTAMP',
                                'TIME',
                                'YEAR',
                            ])) {
                                $type = $_type;
                            }
                        }

                        $_split = array_map('trim', explode('.', $column, 2));
                        if (count($_split) > 1 && $this->checkColumn(@$_split[1], @$_split[0], $db)) {
                            $order_table = trim($_split[0]);
                            $column = trim($_split[1]);
                        } elseif (!$this->checkColumn($column, $order_table, $db)) {
                            if (!self::$enable_errors) {
                                continue;
                            }
                            if (count($_split) > 1) {
                                Response::error('Invalid order condition ' . $_split[0] . '.' . $_split[1], 404);
                            } else {
                                Response::error('Invalid order condition ' . $order_table . '.' . $column, 404);
                            }
                        }
                        $order_direction = trim($column_direction);
                        if (!empty($order_direction) && in_array(strtoupper($order_direction), ['ASC', 'DESC'])) {
                            $direction = $order_direction;
                        }
                    } else {
                        $_split = array_map('trim', explode('.', $column_direction, 2));
                        if (count($_split) > 1 && $this->checkColumn(@$_split[1], @$_split[0], $db)) {
                            $order_table = $_split[0];
                            $column = $_split[1];
                        } elseif ($this->checkColumn($column_direction, $order_table)) {
                            $column = $column_direction;
                        } else {
                            if (!self::$enable_errors) {
                                continue;
                            }
                            if (count($_split) > 1) {
                                Response::error('Invalid order condition ' . $_split[0] . '.' . $_split[1], 404);
                            } else {
                                Response::error('Invalid order condition ' . $order_table . '.' . $column_direction, 404);
                            }
                        }
                    }

                    if (!empty($type)) {
                        $type = '::' . $type;
                    }

                    $order_query[] = trim("{$order_table}.{$column}{$type} {$direction}");
                }
                if (empty($query['direction'])) {
                    $query['direction'] = '';
                }
                if (!empty($order_query)) {
                    $sql .= ' ORDER BY ' . implode(', ', $order_query) . " {$query['direction']}";
                }
            }

            // build LIMIT query
            if (!empty($query['limit']) && is_numeric($query['limit'])) {
                $sql .= ' LIMIT ' . (int)$query['limit'];
                if (!empty($query['offset']) && is_numeric($query['offset'])) {
                    $sql .= ' OFFSET ' . (int)$query['offset'];
                }
            }

            $sql = $this->hooks->apply_filters('get_query_' . strtolower($query['table']), $sql);
            $sql_compiled = $sql;

            $sth = $dbh->prepare($sql);

            // bind WHERE values
            if (!empty($where_values) && count($where_values) > 0) {
                foreach ($where_values as $key => $value) {
                    $value = $this->parseValue($value, $query['table'], $key, $db);
                    $type = self::detectPDOType($value);
                    $key = ':' . $key;
                    $sql_compiled = self::getPartialCompiledQuery($sql_compiled, $key, $value);
                    $sth->bindValue($key, $value, $type);
                }
            }

            // bind JOIN values
            if (!empty($join_values) && count($join_values) > 0) {
                foreach ($join_values as $key => $value) {
                    $value = $this->parseValue($value, $query['table'], $key, $db);
                    $type = self::detectPDOType($value);
                    $key = ':' . $key;
                    $sql_compiled = self::getPartialCompiledQuery($sql_compiled, $key, $value);
                    $sth->bindValue($key, $value, $type);
                }
            }

            $this->logger->debug($sql_compiled);

            $sth->execute();

            $results = $sth->fetchAll(PDO::FETCH_OBJ);

            // Sanitize encoding
            $results = $this->sanitizeResults($results, $query['table']);

            if (!empty($query['unique'])) {
                $results = array_unique($results, SORT_REGULAR);
                $results = array_filter($results);
            }
            $results = array_values($results);

            $results = $this->hooks->apply_filters('on_read', $results, $query['table']);
        } catch (PDOException $e) {
            Response::error($e);
        }

        $this->setCache($key, $results, $this->getDatabase($db)->ttl);

        return $results;
    }

    /**
     * Build and execute the INSERT query from the POST request.
     *
     * @param array $query the database query ASSUMES SANITIZED
     * @param null  $db
     * @param bool  $skipChecks
     *
     * @return array|object[]|void an array of results
     */
    public function post($query, $db = null, $skipChecks = false)
    {
        // check values
        if ((!empty($query['insert']) && !is_array($query['insert'])) || count($query['insert']) < 1) {
            Response::error('Invalid insert values', 400);
        }

        if (!empty($query['table']) && !is_multi_array($query['insert'])) {
            $query['insert'][$query['table']] = $query['insert'];
        }

        $counter = 0;
        foreach ($query['insert'] as $table => $values) {
            if (empty($table) || !$this->tableExists($table, $db)) {
                continue;
            }

            if (!$this->auth->canWrite($table)) {
                Response::noPermissions('- Can\'t write on ' . $table);
            }

            $columns = [];
            if (!$this->checkTable($table, $db)) {
                Response::error('Invalid Entity', 404);
            }

            $i = 0;
            // check columns name
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $column => $column_value) {
                        if (!$this->checkColumn($column, $table, $db)) {
                            if (!self::$enable_errors) {
                                continue;
                            }
                            Response::error('Invalid field. The field ' . $table . '.' . $column . ' not exists!', 404);
                        }
                        $columns[$i][$column] = $column_value;
                    }
                    $i++;
                } else {
                    if (!$this->checkColumn($key, $table, $db)) {
                        if (!self::$enable_errors) {
                            continue;
                        }
                        Response::error('Invalid field. The field ' . $table . '.' . $key . ' not exists!', 404);
                    }
                    $columns[$key] = $value;
                }
            }

            if (is_multi_array($columns)) {
                $all_columns = $columns;
                foreach ($all_columns as $columns) {
                    $this->executeInsert($table, $columns, $db);
                    $counter++;
                }
            } else {
                $this->executeInsert($table, $columns, $db);
                $counter++;
            }
        }

        if ($skipChecks || $counter > 0) {
            return Response::created();
        }

        return Response::failed();
    }

    /**
     * Build and execute the UPDATE or INSERT query from the PUT request.
     *
     * @param array $query the database query ASSUMES SANITIZED
     * @param null  $db
     *
     * @return array|object[]|void
     */
    public function put($query, $db = null)
    {
        $query = $this->parseUpdateQuery($query);

        if (empty($query['update']) || !is_array($query['update']) || count($query['update']) < 1) {
            Response::error('Invalid replace values', 400);
        }

        $counter = 0;
        foreach ($query['update'] as $table => $u) {
            if (empty($table) || !$this->tableExists($table, $db)) {
                continue;
            }

            if (!$this->auth->canEdit($table)) {
                Response::noPermissions('- Can\'t edit');
            }

            foreach ($u as $update) {
                if (!empty($update['where']) && is_array($update['where'])) {
                    foreach ($update['where'] as $column => $value) {
                        if (!$this->checkColumn($column, $table)) {
                            Response::error('Invalid where condition ' . $column, 404);
                        }
                    }

                    $check = [];
                    $check['table'] = $table;
                    $check['where'] = $update['where'];

                    $result = $this->get($check);

                    if (empty($result)) {
                        $insert = [];
                        $insert['insert'] = [];
                        $insert['insert'][$table] = array_merge($update['where'], $update['values']);
                        if ($this->auth->canWrite($table)) {
                            $this->post($insert, $db, true);
                        } else {
                            Response::noPermissions('- Can\'t write on ' . $table);
                        }
                    } else {
                        $new_update = $query;
                        $new_update['update'] = [];
                        $new_update['update'][$table] = $update;
                        $this->patch($new_update, $db, true);
                    }
                }
            }
        }

        if ($counter > 0) {
            // TODO: verify all put success or failed
            return Response::success();
        }

        return Response::failed();
    }

    /**
     * Build and execute the UPDATE query from the PATCH request.
     *
     * @param array $query the database query ASSUMES SANITIZED
     * @param null  $db
     * @param bool  $skipChecks
     *
     * @return array an array of results
     */
    public function patch($query, $db = null, $skipChecks = false)
    {
        $query = $this->parseUpdateQuery($query);

        if (empty($query['update']) || !is_array($query['update']) || count($query['update']) < 1) {
            Response::error('Invalid update values', 400);
        }

        try {
            $dbh = &$this->connect($db);

            $counter = 0;
            foreach ($query['update'] as $table => $update) {
                if (empty($table) || !$this->tableExists($table, $db)) {
                    continue;
                }

                if (!$this->auth->canEdit($table)) {
                    Response::noPermissions('- Can\'t edit on ' . $table);
                }

                foreach ($update as $values) {
                    if (empty($values['where']) || !is_array($values['where']) || count($values['where']) < 1) {
                        Response::error('Invalid conditions', 400);
                    }

                    if (empty($values['values']) || !is_array($values['values']) || count($values['values']) < 1) {
                        Response::error('Invalid values', 400);
                    }

                    $where = $values['where'];
                    $values = $values['values'];
                    $values_index = [];
                    $column_values = [];

                    // check columns name
                    foreach ($values as $key => $value) {
                        if (!$this->checkColumn($key, $table)) {
                            if (!self::$enable_errors) {
                                continue;
                            }
                            Response::error('Invalid field. The field ' . $table . '.' . $key . ' not exists!', 404);
                        }
                    }

                    $values = $this->hooks->apply_filters('on_edit', $values, $table);

                    if (!$this->checkTable($table)) {
                        Response::error('Invalid Entity', 404);
                    }

                    // build SET
                    foreach ($values as $key => $value) {
                        // check columns exists
                        if (!$this->columnExists($key, $table)) {
                            if (!self::$enable_errors) {
                                continue;
                            }
                            Response::error('Invalid field. The field ' . $table . '.' . $key . ' not exists!', 404);
                        }
                        $column_values[$key] = $value;
                        $values_index[] = $key . ' = :' . $key;
                    }
                    $sql = 'UPDATE ' . $table;
                    $sql .= ' SET ' . implode(', ', $values_index);

                    $where_exists = false;
                    // build WHERE query
                    $restriction = $this->auth->permissionSQL($table, 'EDIT');
                    if (is_array($where)) {
                        $where = $this->hooks->apply_filters('patch_where_' . strtolower($table), $where);
                        $where_parse = $this->parseWhere($table, $where, $sql);
                        $where_values = $where_parse['values'];
                        $where_exists = (!empty($where_values));
                        $sql = $where_parse['sql'];
                    }

                    $where_values = $this->hooks->apply_filters('patch_where_values', $where_values, $table);
                    $where_additional = $this->hooks->apply_filters('patch_query_additional_where', '', $table);
                    $where_additional_table = $this->hooks->apply_filters('patch_query_additional_where_' . strtolower($table), '', $table);

                    $sql .= ($where_exists ? ' AND ' : ' WHERE ') . ' (' . $restriction . ') ';
                    $sql .= (!empty($where_additional) ? ' AND (' . $where_additional . ') ' : '');
                    $sql .= (!empty($where_additional_table) ? ' AND (' . $where_additional_table . ') ' : '');

                    $sql = $this->hooks->apply_filters('patch_query_prefilter_' . strtolower($table), $sql);

                    $sth = $dbh->prepare($sql);
                    $sql_compiled = $sql;

                    // bind PUT values
                    foreach ($column_values as $key => $value) {
                        $value = $this->parseValue($value, $table, $key, $db);
                        $key = ':' . $key;
                        $sql_compiled = self::getPartialCompiledQuery($sql_compiled, $key, $value);
                        $sth->bindValue($key, $value);
                    }

                    // bind WHERE values
                    if (!empty($where_values) && count($where_values) > 0) {
                        foreach ($where_values as $key => $value) {
                            $value = $this->parseValue($value, $table, $key, $db);
                            $key = ':' . $key;
                            $sql_compiled = self::getPartialCompiledQuery($sql_compiled, $key, $value);
                            $sth->bindValue($key, $value);
                        }
                    }

                    $this->logger->debug($sql_compiled);

                    $sth->execute();

                    if ($sth->rowCount() < 1) {
                        Response::noPermissions();
                    }

                    $counter++;
                }
            }
        } catch (PDOException $e) {
            Response::error($e);
        }

        if ($skipChecks || $counter > 0) {
            return Response::success();
        }

        return Response::failed();
    }

    /**
     * Build and execute the DELETE query from the DELETE request.
     *
     * @param array $query the database query ASSUMES SANITIZED
     * @param null  $db
     *
     * @return array an array of results
     */
    public function delete($query, $db = null)
    {
        try {
            $dbh = &$this->connect($db);

            // check table name
            if (!$this->checkTable($query['table'])) {
                Response::error('Invalid Entity', 404);
            }

            // check ID
            if (!empty($query['id']) && !empty($query['id'])) {
                $query['where'][$this->getFirstColumn($query['table'])] = $query['id'];
            }

            $sql = 'DELETE FROM ' . $query['table'];

            $where_exists = false;
            // build WHERE query
            $restriction = $this->auth->permissionSQL($query['table'], 'DELETE');
            if (!empty($query['where']) && is_array($query['where'])) {
                $query['where'] = $this->hooks->apply_filters('delete_where_' . strtolower($query['table']), $query['where']);
                $where_parse = $this->parseWhere($query['table'], $query['where'], $sql);
                $where_values = $where_parse['values'];
                $where_exists = (!empty($where_values));
                $sql = $where_parse['sql'];
            }

            $where_values = $this->hooks->apply_filters('delete_where_values', $where_values, $query['table']);
            $where_additional = $this->hooks->apply_filters('delete_query_additional_where', '', $query['table']);
            $where_additional_table = $this->hooks->apply_filters('delete_query_additional_where_' . strtolower($query['table']), '', $query['table']);

            if (empty(trim($restriction))) {
                $restriction = "'1' = '1'";
            }

            $sql .= ($where_exists ? ' AND ' : ' WHERE ') . ' (' . $restriction . ') ';
            $sql .= (!empty($where_additional) ? ' AND (' . $where_additional . ') ' : '');
            $sql .= (!empty($where_additional_table) ? ' AND (' . $where_additional_table . ') ' : '');

            $sql = $this->hooks->apply_filters('delete_query_prefilter_' . strtolower($query['table']), $sql);

            $sth = $dbh->prepare($sql);
            $sql_compiled = $sql;

            // bind WHERE values
            if (!empty($where_values) && count($where_values) > 0) {
                foreach ($where_values as $key => $value) {
                    $value = $this->parseValue($value, $query['table'], $key, $db);
                    $type = self::detectPDOType($value);
                    $key = ':' . $key;
                    $sql_compiled = self::getPartialCompiledQuery($sql_compiled, $key, $value);
                    $sth->bindValue($key, $value, $type);
                }
            }

            $this->logger->debug($sql_compiled);
            //die($sql_compiled);

            $sth->execute();
        } catch (PDOException $e) {
            Response::error($e);
        }

        return Response::success();
    }

    /**
     * Execute an insert.
     *
     * @param      $table
     * @param      $columns
     * @param      $db
     * @param      $dbh
     */
    private function executeInsert($table, $columns, $db)
    {
        $dbh = &$this->connect($db);
        $columns = $this->hooks->apply_filters('on_write', $columns, $table);

        // Check if primary key already exists
        try {
            $keys = $this->getPrimaryKeys($table, $db);
            if (!empty($keys)) {
                $check = [];
                $check['table'] = $table;
                $check['where'] = [];
                $check['limit'] = 1;
                foreach ($keys as $key) {
                    $check['where'] = [$key => $columns[$key]];
                }
                $result = $this->get($check);
                if (!empty($result)) {
                    $item = reset($result);
                    // Default unique primary key check bypass is disabled
                    $bypassInsert = $this->hooks->apply_filters('on_write_exists', false, $item, $table);
                    if ($bypassInsert) {
                        return true;
                    }
                }
            }
        } catch (PDOException $e) {
            $log = Logger::getInstance();
            $log->error($e->getMessage());
        }

        try {
            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', array_keys($columns)) . ') VALUES (:' . implode(', :', array_keys($columns)) . ')';
            $sth = $dbh->prepare($sql);
            $sql_compiled = $sql;

            // bind POST values
            foreach ($columns as $column => $value) {
                $value = $this->parseValue($value, $table, $column);
                $column = ':' . $column;
                $sth->bindValue($column, $value);
                $sql_compiled = self::getPartialCompiledQuery($sql_compiled, $column, $value);
            }

            $this->logger->debug($sql_compiled);
            $sth->execute();
        } catch (PDOException $e) {
            Response::error($e);
        }
    }

    /**
     * @section Output Renders
     */

    /**
     * Output with autodetection.
     *
     * @param $data
     */
    public function render($data)
    {
        // Get hooks
        $hooks = Hooks::getInstance();

        // Clean
        ob_clean();

        // Render
        $default_format = Request::method() === 'GET' ? 'html' : 'json';
        $data = $hooks->apply_filters('render', $data, $this->query, Request::method());
        $renderer = 'render' . ucfirst(strtolower(!empty($this->query['format']) ? $this->query['format'] : $default_format));
        $this->$renderer($data);

        exit();
    }

    /**
     * Output JSON encoded data.
     *
     * @param $data
     * @param $simple_encode
     * @param $query
     */
    public function renderJson($data, $simple_encode = false, $complex = false)
    {
        if ($complex) {
            $output = $this->jsonStringify($data);
        } else {
            // TODO: replace with jsonStringify method, need to do some checks
            if (is_multi_array($data) && !$simple_encode) {
                $prefix = '';
                $output = '[';
                foreach ($data as $row) {
                    $output .= $prefix . json_encode($row);
                    $prefix = ',';
                }
                $output .= ']';
            } else {
                $output = json_encode($data);
            }
        }

        // Prepare a JSONP callback.
        $callback = jsonp_callback_filter($this->query['callback']);

        // Only send back JSONP if that's appropriate for the request.
        if ($callback) {
            $output = "{$callback}($output);";
        }

        $compressed = gzencode($output);

        ob_clean();

        @header('Content-Type: application/json');
        @header('Content-Encoding: gzip');
        @header('Content-Length: ' . strlen($compressed));

        exit($compressed);
    }

    /**
     * Encode large amount of data into JSON.
     *
     * @param mixed $data
     *
     * @return string
     */
    public function jsonStringify($data, $path = 'json')
    {
        if (is_array($data) || is_object($data)) {
            if (is_object($data)) {
                $isNamedKey = true;
            } else {
                $isNamedKey = (is_string(key($data)));
            }
            if ($isNamedKey) {
                $output = '{';
                $prefix = '';
                foreach ($data as $key => $value) {
                    $nextPath = $path . ".$key";
                    if (!is_numeric($key)) {
                        $key = '"' . $key . '"';
                    }
                    $stringify = $this->hooks->apply_filters('render_json_process', true, $nextPath);
                    if ($stringify) {
                        $content = $this->jsonStringify($value, $nextPath);
                        $content = $this->hooks->apply_filters('render_json_content', $content, $nextPath);
                    } else {
                        $content = $this->hooks->apply_filters('render_json_result', 'null', $nextPath);
                    }
                    $output .= $prefix . $key . ': ' . $content;
                    $prefix = ',';
                }
                $output .= '}';
            } else {
                $output = '[';
                $prefix = '';
                foreach ($data as $key => $value) {
                    $nextPath = $path . "[$key]";
                    $stringify = $this->hooks->apply_filters('render_json_process', true, $nextPath);
                    if ($stringify) {
                        $content = $this->jsonStringify($value, $nextPath);
                        $content = $this->hooks->apply_filters('render_json_content', $content, $nextPath);
                    } else {
                        $content = $this->hooks->apply_filters('render_json_result', 'null', $nextPath);
                    }
                    $output .= $prefix . $content;
                    $prefix = ',';
                }
                $output .= ']';
            }
        } else {
            $output = json_encode($data);
        }

        return (string)$output;
    }

    /**
     * Output data as an HTML table.
     *
     * @param $data
     */
    public function renderHtml($data)
    {
        include __API_ROOT__ . '/includes/template/header.php';
        //err out if no results
        if (empty($data)) {
            Response::error('No results found', 404);

            return;
        }
        //render readable array data serialized
        foreach ($data as $key => $result) {
            foreach ($result as $column => $value) {
                $value_parsed = @unserialize($value);
                if ($value_parsed !== false || $value === 'b:0;') {
                    if (is_array($data)) {
                        $data[$key]->$column = implode('|', $value_parsed);
                    }
                }
            }
        }
        //page title
        if (!empty($this->query['docs'])) {
            echo "<h2 class='text-center'>Documentation</h2>";
            echo '<h3>' . $this->query['table'] . '</h3><br>';
        } else {
            echo '<h2>Results</h2>';
        }
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
            echo '</tr>';
        }
        echo '</table>';
        include __API_ROOT__ . '/includes/template/footer.php';
        exit();
    }

    /**
     * Output data as XML.
     *
     * @param $data
     */
    public function renderXml($data)
    {
        header('Content-Type: text/xml');
        $xml = new SimpleXMLElement('<results></results>');
        $xml = object_to_xml($data, $xml);
        echo tidy_xml($xml);
        exit();
    }

    /**
     * @section Database data checks
     */

    /**
     * Verify a table exists, used to sanitize queries.
     *
     * @param string $query_table the table being queried
     * @param string|object $db          the database to check
     *
     * @return bool true if table exists, otherwise false
     */
    public function checkTable($query_table, $db = null)
    {
        if (!empty($db)) {
            $db = $this->getDatabase($db);
        } else {
            $db = $this->getDatabase();
        }

        if ($this->auth->isAuthenticated() && (!$this->auth->isAdmin())) {
            if (!empty($db->table_list) && is_array($db->table_list)) {
                $onColumnList = false;
                if (!empty($db->column_list) && is_array($db->column_list)) {
                    $onColumnList = array_key_exists($query_table, $db->column_list);
                }

                if (!in_array($query_table, $db->table_list) && !$onColumnList) {
                    return false;
                }
            }

            if (!empty($db->table_blacklist) && is_array($db->table_blacklist)) {
                if (in_array($query_table, $db->table_blacklist)) {
                    return false;
                }
            }
        }

        return $this->tableExists($query_table, $db);
    }

    /**
     * Verify a table exists, used to sanitize queries.
     *
     * @param string $query_table the table being queried
     * @param string $db          the database to check
     *
     * @return bool true if table exists, otherwise false
     */
    public function tableExists($query_table, $db = null)
    {
        $tables = $this->getTables($db);

        return in_array($query_table, $tables);
    }

    /**
     * Get tables.
     *
     * @param string $db the database to check
     *
     * @return array an array of the tables names
     */
    public function getTables($db = null)
    {
        $key = crc32($this->getDatabase($db)->name . '_tables');

        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        $dbh = &$this->connect($db);
        try {
            if ($this->getDatabase($db)->type === 'mysql') {
                $stmt = $dbh->query('SHOW TABLES');
            } else {
                $stmt = $dbh->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
            }
        } catch (PDOException $e) {
            Response::error($e);
        }

        $tables = [];
        while ($table = $stmt->fetch()) {
            $tables[] = $table[0];
        }
        $this->setCache($key, $tables, $this->getDatabase($db)->ttl);

        return $tables;
    }

    /**
     * Verify a column exists.
     *
     * @param string $column the column to check
     * @param string $table  the table to check
     * @param string|object $db     (optional) the db to check
     *
     * @return bool
     * @retrun bool true if exists, otherwise false
     */
    public function checkColumn($column, $table, $db = null)
    {
        if (!empty($db)) {
            $db = $this->getDatabase($db);
        } else {
            $db = $this->getDatabase();
        }

        if (!$this->auth->isAdmin() || Request::method() === 'PUT') {
            if (!empty($db->column_list[$table]) && is_array($db->column_list[$table])) {
                if (!in_array($column, $db->column_list[$table])) {
                    return false;
                }
            }

            if (!empty($db->column_blacklist[$table]) && is_array($db->column_blacklist[$table])) {
                if (in_array($column, $db->column_blacklist[$table])) {
                    return false;
                }
            }
        }

        return $this->columnExists($column, $table, $db);
    }

    /**
     * Get Table Meta.
     *
     * @param $table
     * @param $db
     *
     * @return array
     */
    public function getTableMeta($table, $db)
    {
        $db = $this->getDatabase($db);

        $key = crc32($db->name . '.' . $table . '.meta');

        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        switch ($db->type) {
            case 'pgsql':
                $sql = "SELECT c.column_name, c.udt_name as data_type, is_nullable, character_maximum_length, column_default
								FROM pg_catalog.pg_statio_all_tables AS st
								INNER JOIN pg_catalog.pg_description pgd ON (pgd.objoid=st.relid)
								RIGHT OUTER JOIN information_schema.columns c ON (pgd.objsubid=c.ordinal_position AND c.table_schema=st.schemaname AND c.table_name=st.relname)
								WHERE table_schema = 'public' AND c.table_name = :table;";
                break;
            case 'mysql':
                $sql = 'SELECT column_name, data_type, is_nullable, character_maximum_length, column_default FROM information_schema.columns WHERE table_name = :table;';
                break;
            default:
                return [];
        }

        $dbh = &$this->connect($db);

        $sth = $dbh->prepare($sql);
        $sth->bindValue(':table', $table);
        $sth->execute();

        $results = $sth->fetchAll();
        $this->setCache($key, $results, $db->ttl);

        return $results;
    }

    /**
     * Get Table Meta.
     *
     * @param $table
     * @param $db
     *
     * @return array
     */
    private function getTableColumnsMeta($table, $db = null)
    {
        $db = $this->getDatabase($db);

        $key = crc32($db->name . '.' . $table . '.meta_columns');

        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        $result = [];
        $columns = $this->getTableMeta($table, $db = null);
        foreach ($columns as $column) {
            $result[$column['column_name']] = $column;
        }

        $this->setCache($key, $result, $db->ttl);

        return $result;
    }

    /**
     * Clean Condition Value.
     *
     * @param $value
     * @param $table
     * @param $key
     * @param $db
     * @param $forceNullable
     *
     * @return mixed
     */
    public function parseValue($value, $table, $key, $db = null, $forceNullable = false)
    {
        if (is_array($value)) {
            $value = serialize($value);
        }
        if (is_string($value) && strtolower($value) === 'null') {
            $value = null;
        }
        if (trim($value) == '') {
            $column = self::getColumnNameFromIndex($key, $table);
            $metas = $this->getTableColumnsMeta($table, $db);
            $dataType = strtolower(preg_replace('/[\d]/', '', $metas[$column]['data_type']));
            if (!empty($metas[$column])) {
                $default = preg_replace("/'([^']*)'.*/si", '$1', $metas['column_default']);
                $isNullable = ($metas[$column]['is_nullable'] === 'YES' || strtolower(trim($default)) === 'null');
                $numericDataType = [
                    'int', 'smallint', 'tinyint', 'bigint', 'integer',
                    'decimal', 'float', 'double', 'double precision',
                    'numeric', 'real',
                    'serial', 'bigserial',
                ];
                if (in_array($dataType, $numericDataType)) {
                    $value = ($isNullable) ? null : (float)$default;
                } elseif ($forceNullable) {
                    $value = null;
                }
            }
        }

        return $value;
    }

    /**
     * Check if column exists.
     *
     * @param      $column
     * @param      $table
     * @param null $db
     *
     * @return bool
     */
    public function columnExists($column, $table, $db = null)
    {
        $columns = $this->getColumns($table, $db);

        if (!$columns) {
            return false;
        }

        return in_array($column, $columns);
    }

    /**
     * Get columns.
     *
     * @param string $table the table to check
     * @param string $db    the database to check
     *
     * @return array|bool an array of the column names
     */
    public function getColumns($table, $db = null)
    {
        if (!$this->tableExists($table, $db)) {
            return false;
        }

        $key = crc32($this->getDatabase($db)->name . '.' . $table . '_columns');

        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        $dbh = &$this->connect($db);

        try {
            if ($this->getDatabase($db)->type === 'mysql') {
                $q = $dbh->prepare("DESCRIBE {$table}");
            } else {
                $q = $dbh->prepare("SELECT column_name FROM information_schema.columns WHERE table_name ='{$table}'");
            }
            $q->execute();
            $columns = $q->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            Response::error($e);
        }

        $this->setCache($key, $columns, $this->getDatabase($db)->ttl);

        return $columns;
    }

    /**
     * Returns the first column in a table.
     *
     * @param string $table the table
     * @param string $db    the database slug
     *
     * @return string the column name
     */
    public function getFirstColumn($table, $db = null)
    {
        $columns = $this->getColumns($table, $db);
        if (in_array('id', $columns)) {
            return 'id';
        }

        return reset($columns);
    }

    /**
     * Returns the primary key columns in a table.
     *
     * @param string $table        the table
     * @param string|object $db    the database slug
     *
     * @return string[] the primary columns name
     */
    public function getPrimaryKeys($table, $db = null)
    {
        if (!$this->tableExists($table, $db)) {
            return [];
        }

        $db = $this->getDatabase($db);
        $key = crc32($db->name . '.' . $table . '.primary_keys');

        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        $results = [];
        $keys = [];

        switch ($db->type) {
            case 'pgsql':
                $sql = "SELECT a.attname AS name
							FROM pg_index i
							JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
							WHERE i.indrelid = '" . $table . "'::regclass
							AND i.indisprimary;";
                $dbh = &$this->connect($db);
                $sth = $dbh->query($sql);
                $results = $sth->fetchAll();
                break;
            case 'mysql':
                $sql = 'SHOW KEYS AS name FROM ' . $table . " WHERE Key_name = 'PRIMARY'";
                $dbh = &$this->connect($db);
                $sth = $dbh->query($sql);
                $results = $sth->fetchAll();
                break;
        }

        foreach ($results as $result) {
            $keys[] = $result['name'];
        }

        if (empty($keys)) {
            $keys = [$this->getFirstColumn($table, $db)];
        }

        $this->setCache($key, $keys, $db->ttl);

        return $keys;
    }

    /**
     * @section Database utils
     */

    /**
     * Build WHERE query and assign to an array the values to bind on the PDO obj.
     *
     * @param $main_table
     * @param $where
     * @param $sql
     *
     * @return array
     */
    private function parseWhere($main_table, $where, $sql)
    {
        $prefix = 'where_';
        $where_sql = [];
        $where_values = [];

        // column IN (args, ...)
        $cases_equal = [
            '=',
            'eq',
            'equal',
        ];
        // column NOT IN (args, ...)
        $cases_not_equal = [
            '!',
            '<>',
            '!=',
            'not',
            'neq',
            'notequal',
        ];
        $cases_special = [
            // column > args OR column > ...
            '>' => '>',
            'greater' => '>',
            'gt' => '>',
            // column < args OR column < ...
            '<' => '<',
            'lower' => '<',
            'lt' => '<',
            // column <= args OR column <= ...
            '<=' => '<=',
            'lte' => '<=',
            // column >= args OR column >= ...
            '>=' => '>=',
            'gte' => '>=',
            // column % args OR column % ...
            '%' => 'LIKE',
            'like' => 'LIKE',
        ];

        $sql .= ' WHERE ';

        foreach ($where as $column => $values_column) {
            $table = $main_table;
            $where_in = [];
            $_split = explode('.', $column, 2);
            if (count($_split) > 1) {
                $table = $_split[0];
                $column = $_split[1];
            }

            $ref = "COALESCE({$table}.{$column}::TEXT, '')";
            $column = self::getColumnNameFromIndex($column, $table);

            if (!is_array($values_column)) {
                $values_column = [$values_column];
            }

            foreach ($values_column as $condition => $value_condition) {
                if (array_key_exists($condition, $cases_special)) {
                    // Check special cases

                    $bind = $cases_special[$condition];
                    if (!is_array($value_condition) ||
                       count(array_intersect(array_keys($value_condition), $cases_equal)) > 0 ||
                       count(array_intersect(array_keys($value_condition), $cases_not_equal)) > 0) {
                        $value_condition = [$value_condition];
                    }
                    foreach ($value_condition as $value) {
                        $where_or = [];
                        if (!is_array($value)) {
                            $value = [$value];
                        }
                        foreach ($value as $k => $special_value) {
                            $operator = $bind;
                            if (!is_array($special_value)) {
                                $special_value = [$special_value];
                            }
                            foreach ($special_value as $v) {
                                $clean_value = $this->parseValue($v, $table, $column);
                                if ($clean_value === null) {
                                    $clean_value = ''; // Don't accept null
                                }
                                $_value_split = explode('.', $v, 2);
                                if (count($_value_split) > 1 && $this->checkColumn(@$_value_split[1], @$_value_split[0])) {
                                    $index_value = $_value_split[0] . '.' . $_value_split[1];
                                } else {
                                    $index_key = self::indexValue($prefix, $column, $where_values);
                                    $index_value = ' :' . $index_key;
                                    $where_values[$index_key] = $clean_value;
                                }
                                if (in_array($k, $cases_not_equal, true)) {
                                    // Not equal cases on or condition
                                    $operator = '!=';
                                } elseif (in_array($k, $cases_equal, true)) {
                                    // Equal cases on or condition
                                    $operator = '=';
                                }
                                $where_or[] = "{$ref} " . $operator . " {$index_value}";
                            }
                        }
                        if (count($where_or) > 0) {
                            $where_sql[] = '(' . implode(' OR ', $where_or) . ')';
                        }
                    }
                } elseif (in_array($condition, $cases_not_equal, true)) {
                    // Check unequal cases

                    $where_not = [];
                    if (!is_array($value_condition)) {
                        $value_condition = [$value_condition];
                    }

                    foreach ($value_condition as $value) {
                        $_value_split = explode('.', $value, 2);
                        if (count($_value_split) > 1 && $this->checkColumn(@$_value_split[1], @$_value_split[0])) {
                            $index_value = $_value_split[0] . '.' . $_value_split[1];
                        } else {
                            if (is_null($value)) {
                                $index_value = "''";
                            } else {
                                $clean_value = $this->parseValue($value, $table, $column);
                                if ($clean_value === null) {
                                    $clean_value = ''; // Don't accept null
                                }
                                $index_key = self::indexValue($prefix, $column, $where_values);
                                $index_value = ' :' . $index_key;
                                $where_values[$index_key] = $clean_value;
                            }
                        }
                        $where_not[] = "{$ref} != {$index_value}";
                    }

                    if (count($where_not) > 0) {
                        $where_sql[] = '(' . implode(' AND ', $where_not) . ')';
                    }
                } elseif (in_array($condition, $cases_equal, true) || is_int($condition)) {
                    // Check equal cases

                    if (!is_array($value_condition)) {
                        $value_condition = [$value_condition];
                    }

                    foreach ($value_condition as $value) {
                        $_value_split = explode('.', $value, 2);
                        if (count($_value_split) > 1 && $this->checkColumn(@$_value_split[1], @$_value_split[0])) {
                            $index_value = $_value_split[0] . '.' . $_value_split[1];
                        } else {
                            $clean_value = $this->parseValue($value, $table, $column);
                            if ($clean_value === null) {
                                $clean_value = ''; // Don't accept null
                            }
                            $index_key = self::indexValue($prefix, $column, $where_values);
                            $index_value = ' :' . $index_key;
                            $where_values[$index_key] = $clean_value;
                        }
                        $where_in[] = $index_value;
                    }
                }
            }

            if (count($where_in) > 0) {
                $where_sql[] = "{$ref} IN (" . implode(', ', $where_in) . ')';
            }
        }

        if (count($where_sql) > 0) {
            $sql .= implode(' AND ', $where_sql);
        }

        //die($sql);

        $result = [];
        $result['sql'] = $sql;
        $result['values'] = $where_values;

        return $result;
    }

    /**
     * Parse and verify query params.
     *
     * @param $query
     *
     * @return mixed
     */
    private function parseUpdateQuery($query)
    {
        $query = $this->reformatUpdateQuery($query);
        $first_col = $this->getFirstColumn($query['table']);
        // Check id
        if (!empty($query['table']) && !empty($query['id'])) {
            // Check WHERE
            if (!empty($query['where']) && is_array($query['where'])) {
                foreach ($query['where'] as $column => $value) {
                    $column_table = $query['table'];
                    $_split = explode('.', $column, 2);
                    if (count($_split) > 1) {
                        $column = $_split[1];
                        $column_table = $_split[0];
                    }
                    if (!$this->checkColumn($column, $column_table)) {
                        Response::error('Invalid where condition ' . $column_table . '.' . $column, 404);
                    }
                }
                foreach ($query['update'][$query['table']] as $key => $values) {
                    $query['update'][$query['table']][$key]['where'] = $query['where'];
                }
            }
            foreach ($query['update'][$query['table']] as $key => $values) {
                $query['update'][$query['table']][$key]['values'] = $query['update'];
                $query['update'][$query['table']][$key]['where'][$first_col] = $query['id'];
            }
        } elseif (empty($query['update']) && !is_array($query['update']) && count($query['update']) < 1) { // Check values
            Response::error('Invalid values', 400);
        } else {
            foreach ($query['update'] as $table => $u) {
                foreach ($u as $update) {
                    if (!empty($update['where']) && is_array($update['where'])) {
                        foreach ($update['where'] as $column => $value) {
                            if (!$this->checkColumn($column, $table)) {
                                Response::error('Invalid where condition ' . $column, 404);
                            }
                        }
                    }
                }
            }
        }

        return $query;
    }

    /**
     * Convert query values to update to multi array if needed.
     *
     * @param $query
     *
     * @return mixed
     */
    private function reformatUpdateQuery($query)
    {
        $update = [];
        foreach ($query['update'] as $table => $values) {
            if (!empty($values['values'])) {
                $update[$table][] = $values;
            } else {
                $update[$table] = $values;
            }
        }
        $query['update'] = $update;

        return $query;
    }

    /**
     * Change column value index name for bind value on PDO.
     *
     * @param string $prefix
     * @param        $column
     * @param        $array
     *
     * @return string
     */
    private static function indexValue($prefix, $column, $array)
    {
        $i = 1;
        $column = str_replace('.', '_', $column);
        $column = $prefix . $column;
        $index = $column;
        while (array_key_exists($index, $array)) {
            $i++;
            $index = $column . '_' . $i;
        }

        return $index;
    }

    /**
     * Reverse of Index Value.
     *
     * @param $key
     * @param $table
     *
     * @return string|string[]|null
     */
    private static function getColumnNameFromIndex($key, $table)
    {
        return preg_replace(['/^where\_/i', '/^join\_/i', '/^\_/i', '/\_[\d]+$/i', '/\_' . preg_quote($table, '/') . '/i'], '', $key);
    }

    /**
     * Detect the type of value and return the PDO::PARAM.
     *
     * @param $value
     *
     * @return int
     */
    private static function detectPDOType($value)
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return PDO::PARAM_BOOL;
        }
        if (filter_var($value, FILTER_VALIDATE_INT)) {
            return PDO::PARAM_INT;
        }
        if ($value === null) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_STR;
    }

    /**
     * Returns the partial emulated SQL string.
     *
     * @param $string
     * @param $key
     * @param $value
     *
     * @return string
     */
    public static function getPartialCompiledQuery($query, $key, $value)
    {
        $value = self::getCompiledParamValue($value);

        return preg_replace('/' . $key . "([,]|\s|$|\))/i", $value, $query);
    }

    /**
     * Returns the emulated SQL string.
     *
     * @param string $query
     * @param array $parameters
     *
     * @return string
     */
    public static function getCompiledQuery($query, $parameters = [])
    {
        $keys = [];
        $values = [];

        /**
         * Get longest keys first, so the regex replacement doesn't cut markers
         * (ex : replace ":username" with "marco.cesarato" if we have a param name :user ).
         */
        $isNamedMarkers = false;
        if (count($parameters) && is_string(key($parameters))) {
            uksort($parameters, function ($k1, $k2) {
                return strlen($k2) - strlen($k1);
            });
            $isNamedMarkers = true;
        }
        foreach ($parameters as $key => $value) {
            // Check if named parameters (':param') or anonymous parameters ('?') are used
            if (is_string($key)) {
                $keys[] = '/:' . ltrim($key, ':') . '/';
            } else {
                $keys[] = '/[?]/';
            }
            $values[] = self::getCompiledParamValue($value);
        }
        if ($isNamedMarkers) {
            return preg_replace($keys, $values, $query);
        }

        return preg_replace($keys, $values, $query, 1, $count);
    }

    /**
     * Bring parameter value into human-readable format.
     *
     * @param mixed $rawValue
     *
     * @return string
     */
    public static function getCompiledParamValue($rawValue)
    {
        if (is_string($rawValue)) {
            $value = "'" . addslashes($rawValue) . "'";
        } elseif (is_numeric($rawValue)) {
            $value = (string)$rawValue;
        } elseif (is_array($rawValue)) {
            $value = implode(',', $rawValue);
        } elseif (is_null($rawValue)) {
            $value = 'NULL';
        } elseif (is_bool($rawValue)) {
            $value = (string)($rawValue);
        } else {
            $value = (string)($rawValue);
        }

        return $value;
    }

    /**
     * @section Data manipulation
     */

    /**
     * Modifies a string to remove all non-ASCII characters and spaces.
     *
     * @param $text
     *
     * @return string|string[]|null
     */
    private function slugify($text)
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    /**
     * Detect if is UTF-8.
     */
    private static function isUTF8($string)
    {
        return preg_match('%(?:'
                          . '[\xC2-\xDF][\x80-\xBF]'                // non-overlong 2-byte
                          . '|\xE0[\xA0-\xBF][\x80-\xBF]'           // excluding overlongs
                          . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'    // straight 3-byte
                          . '|\xED[\x80-\x9F][\x80-\xBF]'           // excluding surrogates
                          . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'        // planes 1-3
                          . '|[\xF1-\xF3][\x80-\xBF]{3}'            // planes 4-15
                          . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'        // plane 16
                          . ')+%xs', $string);
    }

    /**
     * Sanitize encoding.
     *
     * @param array $results
     *
     * @return mixed
     */
    public function sanitizeResults($results, $table = null)
    {
        foreach ($results as $key => $result) {
            // Sanitize encoding
            foreach ($result as $column => $value) {
                // Remind: change LBL_CHARSET in include/language/[iso_lang]_[iso_lang].lang.php
                $results[$key]->$column = (self::isUTF8($value)) ? $value : utf8_encode($value);
            }
        }

        return $results;
    }

    /**
     * @section Cache
     */

    /**
     * Retrieve data from Alternative PHP Cache (APC).
     *
     * @param $key
     *
     * @return bool|mixed
     */
    private function getCache($key)
    {
        if (!extension_loaded('apc') || (ini_get('apc.enabled') != 1)) {
            if (!empty($this->cache[$key])) {
                return $this->cache[$key];
            }
        } else {
            return apc_fetch($key);
        }

        return false;
    }

    /**
     * Store data in Alternative PHP Cache (APC).
     *
     * @param      $key
     * @param      $value
     * @param null $ttl
     *
     * @return array|bool
     */
    private function setCache($key, $value, $ttl = null)
    {
        if ($ttl == null) {
            $ttl = (!empty($this->db->ttl)) ? $this->db->ttl : $this->ttl;
        }

        if (extension_loaded('apc') && (ini_get('apc.enabled') == 1)) {
            return apc_store($key, $value, $ttl);
        }

        $this->cache[$key] = $value;

        return true;
    }
}

$API = new API();
