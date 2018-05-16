<?php
/**
 * Config
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

define("__API_NAME__", "Database Web API"); // API Name
define("__BASE_DIR__", ""); // Dir relative to the http root where is located

define("__AUTH__",  serialize(array( // Set null for disable authentication
    'database' => 'dataset',
    'users' => array(
    	'table' => 'users', // Table where users are stored
        'columns' => array(
            'id' => 'user_id',
            'password' => 'password',
            'admin' => array('is_admin' => 1) // Admin bypass all black/whitelists and permissions. Set NULL for disable
        ),
        'search' => array('user_id', 'email', 'username'), // Search user by these fields
        'check' => array('active' => 1) // Some validation checks. In this case if the column 'active' with value '1'. Set NULL for disable
    ),
	'callbacks' => array( // Set NULL for disable (readonly)
		'sql_restriction' => 'callback_sql_restriction',
		'can_read' => 'callback_can_read',
		'can_write' => 'callback_can_write',
		'can_edit' => 'callback_can_edit',
		'can_delete' => 'callback_can_delete',
	),
)));

define("__DATASETS__", serialize(array(
	'dataset' => array(
		'name' => 'database_name',
		'username' => 'root', // root is default
		'password' => 'root', // root is default
		'server' => 'localhost',  // localhost default
		'port' => 3306, // 3306 is default
		'type' => 'mysql', // mysql is default
		'table_list' => array(), // Tables's whitelist (Allow only the tables in this list, if empty allow all)
		'table_blacklist' => array(), // Tables's blacklist
		'column_list' => array(), // Columns's whitelist (Allow only the columns in this list, if empty allow all)
		'column_blacklist' => array(), // Columns's blacklist
	),
)));