<?php
/**
 * Callbacks
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

/**
 * Add restriction on where conditions for each query
 * @param $table
 * @param $permission
 * @return null|string
 */
function callback_sql_restriction($table, $permission){

	 // All denied
	$sql = "'1' = '0'";
	// All allowed
	$sql = "'1' = '1'";

	$AUTH = Auth::getInstance();
	$user = $AUTH->getUser(); // User row
	$API = API::getInstance();
	$db = $API->connect(); // You can specify dataset. Return PDO Object
	switch ($permission){
		case 'READ':
		case 'WRITE':
		case 'EDIT':
		case 'DELETE':
			break;
	}
	return null; // Continue or return $sql
}

/**
 * Return if can select
 * @param $table
 * @return null|bool
 */
function callback_can_read($table){
	$AUTH = Auth::getInstance();
	$user = $AUTH->getUser(); // User row
	$API = API::getInstance();
	$db = $API->connect(); // You can specify dataset. Return PDO Object
	return null; // Continue or return bool
}

/**
 * Return if can insert
 * @param $table
 * @return null|bool
 */
function callback_can_write($table){
	$AUTH = Auth::getInstance();
	$user = $AUTH->getUser(); // User row
	$API = API::getInstance();
	$db = $API->connect(); // You can specify dataset. Return PDO Object
	return null; // Continue or return bool
}

/**
 * Return if can update
 * @param $table
 * @return null|bool
 */
function callback_can_edit($table){
	$AUTH = Auth::getInstance();
	$user = $AUTH->getUser(); // User row
	$API = API::getInstance();
	$db = $API->connect(); // You can specify dataset. Return PDO Object
	return null; // Continue or return bool
}

/**
 * Return if can delete
 * @param $table
 * @return null|bool
 */
function callback_can_delete($table){
	$AUTH = Auth::getInstance();
	$user = $AUTH->getUser(); // User row
	$API = API::getInstance();
	$db = $API->connect(); // You can specify dataset. Return PDO Object
	return null; // Continue or return bool
}