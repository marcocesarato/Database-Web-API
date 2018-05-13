<?php
/**
 * Config
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

define("__API_NAME__", "Database Web API");
define("__BASE_DIR__", "");
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