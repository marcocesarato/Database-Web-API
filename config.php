<?php
/**
 * Config
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

define("__API_NAME__", "Database Web API");
define("__BASE_DIR__", "");
define("__AUTH__",  serialize(array(
    'database' => 'dataset',
    'users' => array(
        'table' => 'users',
        'columns' => array(
            'id' => 'user_id',
            'email' => 'email',
            'role' => 'role_id',
            'username' => 'username',
            'password' => 'password',
            'super_admin' => array(
                'is_admin' => 'on'  // Super admin bypass all black/whitelists. Set NULL for disable
            )
        ),
        'search' => array('user_id', 'email', 'username'), // Search user by these fields
        'check' => array(
            'active' => 1  // Check if the user is active the have the column 'active' with value '1'
        )
    ),
    'roles' => array(
        'table' => 'roles',
        'columns' => array(
            'id' => 'role_id',
            'data' => 'table', // Table name with te permissions
            'can_read' => array(
                'read' => 1
            ),
            'can_write' => array(
                'write' => 1
            ),
            'can_edit' => array(
                'edit' => 1
            ),
            'can_delete' => array(
                'delete' => 1
            ),
        )
    ),
    'callbacks' => array(),
)));

define("__DATASETS__", serialize(array(
	'dataset' => array(
		'name' => 'database_name',
		'username' => 'username',
		'password' => 'password',
		'server' => 'localhost',
		'port' => 3306,
		'type' => 'mysql',
		'table_list' => array(
			/** @example
				'users'
			 **/
		), // Whitelist (Allow only the tables in this list, if empty allow all)
		'table_blacklist' => array(
			/** @example
				'passwords'
			 **/
		),
		'column_list' => array(
			/** @example
				'users' => array(
					'username',
					'name',
					'surname'
				)
			 **/
		),  // Whitelist  (Allow only the columns in this list, if empty allow all)
		'column_blacklist' => array(
			/** @example
				'users' => array(
					'password',
				)
			 **/
		),
	),
)));