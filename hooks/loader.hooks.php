<?php
/**
 * Hooks - Register
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

require_once(__ROOT__ . '/hooks/utils.hooks.php');
require_once(__ROOT__ . '/hooks/filters.hooks.php');
require_once(__ROOT__ . '/hooks/actions.hooks.php');

$hooks = Hooks::getInstance();

// Include tables filters
foreach(glob(__ROOT__ . "/hooks/{tables,custom}/*.hooks.php", GLOB_BRACE) as $file) {
	include_once($file);
}

/**
 * On write helper hooks
 * @param $data
 * @param $table
 * @return mixed
 */
function helper_on_write($data, $table) {
	$user = Auth::getUser(); // User row
	$api  = API::getInstance();

	/*
	if($api->checkColumn('date_entered', $table)) {
		$data['date_entered'] = date('Y-m-d');
	}
	if($api->checkColumn('created_by', $table)) {
		$data['created_by'] = $user['id'];
	}
	*/

	return $data;
}

$hooks->add_filter('on_write', 'helper_on_write', 100);


/**
 * On write helper hooks
 * @param $data
 * @param $table
 * @return mixed
 */
function helper_on_write_tables($data, $table) {
	$hooks = Hooks::getInstance();
	$data  = $hooks->apply_filters('on_write_' . $table, $data, $table);

	return $data;
}

$hooks->add_filter('on_write', 'helper_on_write_tables', 25);

/**
 * On edit helper hooks
 * @param $data
 * @param $table
 * @return mixed
 */
function helper_on_edit($data, $table) {
	$user = Auth::getUser(); // User row
	$api  = API::getInstance();
	/*if($api->checkColumn('modified_user_id', $table)) {
		$data['modified_user_id'] = $user['id'];
	}
	if($api->checkColumn('date_modified', $table)) {
		$data['date_modified'] = date('Y-m-d');
	}*/
	unset($data['deleted']);

	return $data;
}

$hooks->add_filter('on_edit', 'helper_on_edit', 100);


/**
 * On edit helper hooks
 * @param $data
 * @param $table
 * @return mixed
 */
function helper_on_edit_tables($data, $table) {
	$hooks = Hooks::getInstance();
	$data  = $hooks->apply_filters('on_edit_' . $table, $data, $table);

	return $data;
}

$hooks->add_filter('on_edit', 'helper_on_edit_tables', 25);