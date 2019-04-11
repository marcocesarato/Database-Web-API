<?php
/**
 * Hooks - Filters
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

/**
 * Add restriction on where conditions for each query
 * @param $restriction
 * @param $table
 * @param $permission
 * @return mixed
 */
function filter_sql_restriction($restriction, $table, $permission) {
	return $restriction; // Continue or return $sql
}

/**
 * Return if can select
 * @param $permission
 * @param $table
 * @return mixed
 */
function filter_can_read($permission, $table) {
	$user = Auth::getUser(); // User row
	$db   = API::getConnection(); // PDO Object

	return $permission;
}

/**
 * Return if can insert
 * @param $permission
 * @param $table
 * @return mixed
 */
function filter_can_write($permission, $table) {
	$user = Auth::getUser(); // User row
	$db   = API::getConnection(); // PDO Object

	return $permission;
}

/**
 * Return if can update
 * @param $permission
 * @param $table
 * @return mixed
 */
function filter_can_edit($permission, $table) {
	$user = Auth::getUser(); // User row
	$db   = API::getConnection(); // PDO Object

	return $permission;
}

/**
 * Return if can delete
 * @param $permission
 * @param $table
 * @return mixed
 */
function filter_can_delete($permission, $table) {
	$user       = Auth::getUser(); // User row
	$db         = API::getConnection(); // PDO Object
	$permission = false;

	return $permission;
}

/**
 * On read
 * @param $data
 * @param $table
 * @return mixed
 */
function filter_on_read($data, $table) {
	$user = Auth::getUser(); // User row
	$db   = API::getConnection(); // PDO Object

	return $data;
}

/**
 * On write
 * @param $data
 * @param $table
 * @return mixed
 */
function filter_on_write($data, $table) {
	$user = Auth::getUser(); // User row
	$db   = API::getConnection(); // PDO Object

	return $data;
}

/**
 * On edit
 * @param $data
 * @param $table
 * @return mixed
 */
function filter_on_edit($data, $table) {
	$user = Auth::getUser(); // User row
	$db   = API::getConnection(); // PDO Object

	return $data;
}

/**
 * Validate token
 * @param $is_valid
 * @param $token
 * @return bool
 */
function filter_auth_validate_token($is_valid, $token) {
	//return ($token == "51cc155a75a5cf79a4b28b9bf93cc680");
	return $is_valid;
}

/**
 * Filter user auth login
 * @param $user_id
 * @return string
 */
function filter_auth_user_id($user_id) {
	//return '78365125-d663-fd41-1bcd-5c8b7fde1a33';
	return $user_id;
}

/**
 * Bypass authentication
 * @param $bypass
 * @return bool
 */
function filter_auth_bypass($bypass) {
	$ip = Request::getIPAddress();

	//return in_array($ip, array('heartquarter' => '0.0.0.0'));
	return $bypass;
}

/**
 * Check if is a login request and return login action
 * @param $is_valid_request
 * @param $query
 * @return string|false
 */
function filter_auth_login_request($is_valid_request, $query) {
	$hooks = Hooks::getInstance();

	/*if(isset($query['user_id']) && $query['user_id'] != 'admin' && isset($query['password']) && !empty($query['client_id']) && $query['referer'] == "login_custom") {
		$hooks->add_action('login_custom','action_login_custom');
		return "login_custom";
	}*/

	return $is_valid_request;
}

/**
 * Login data result
 * @param $data
 * @return string|false
 */
function filter_auth_login($data) {
	$user = Auth::getUser(); // User row
	$db   = API::getConnection(); // PDO Object
	return $data;
}

/**
 * Token check data result
 * @param $data
 * @return string|false
 */
function filter_auth_token_check($data) {
	$user = Auth::getUser(); // User row
	$db   = API::getConnection(); // PDO Object
	return $data;
}