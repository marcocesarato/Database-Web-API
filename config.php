<?php
/**
 * Config
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2018
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */

require_once(__ROOT__ . '/docs.php');

define("__API_NAME__", "Database Web API"); // API Name

$users_table = 'users'; // Table where users are stored

// REMOVE COMMENT FOR ENABLE TOKEN AUTHENTICATION
/*define("__AUTH__",  serialize(array( // Set null for disable authentication
    'sqlite' => false, // Enabled save token on SQLite file
    'sqlite_database' => 'api_token', // SQLite filename (only with sqlite = true)
    'api_database' => 'dataset', // Authentication database
    'api_table' => 'api_authentications', // API token table name
    'users' => array(
        'database' => 'dataset', // Database where users are stored
        'table' => $users_table, // Table where users are stored
        'columns' => array(
            'id' => 'user_id', // Id column name
            'username' => 'user_name', // Username column name
            'password' => 'password', // Password column name
            'admin' => array('is_admin' => 1) // Admin bypass condition. With this condition true API bypass all black/whitelists and permissions. Set NULL for disable
        ),
        'search' => array('user_id', 'email', 'username'), // Search user by these fields
        'check' => array('active' => 1) // Some validation checks. In this case if the column 'active' with value '1'. Set NULL for disable
    ),
)));*/

define("__DATASETS__", serialize(array(
	'dataset' => array(
		'name'             => 'database_name',
		'username'         => 'root', // root is default
		'password'         => 'root', // root is default
		'server'           => 'localhost',  // localhost default
		'port'             => 3306, // 3306 is default
		'type'             => 'mysql', // mysql is default
		'table_docs'       => $docs['dataset'],
		'table_list'       => array(), // Tables's whitelist (Allow only the tables in this list, if empty allow all)
		'table_blacklist'  => array(/*blacklist users table*/
			$users_table
		), // Tables's blacklist
		'column_list'      => array(), // Columns's whitelist (Allow only the columns in this list, if empty allow all)
		'column_blacklist' => array(), // Columns's blacklist
	),
)));
