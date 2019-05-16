<?php
/**
 * Hooks - Register
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

use marcocesarato\DatabaseAPI\Hooks;

require_once(__API_ROOT__ . '/hooks/utils.hooks.php');
require_once(__API_ROOT__ . '/hooks/filters.hooks.php');
require_once(__API_ROOT__ . '/hooks/actions.hooks.php');

$hooks = Hooks::getInstance();

// Include tables filters
foreach(glob(__API_ROOT__ . "/hooks/{tables,custom,helpers}/*.hooks.php", GLOB_BRACE) as $file) {
	include_once($file);
}

/**
 * On read loader hooks
 * @param $data
 * @param $table
 * @return mixed
 */
function loader_on_read_tables($data, $table) {
	$hooks = Hooks::getInstance();
	$data  = $hooks->apply_filters('on_read_' . $table, $data, $table);

	return $data;
}

$hooks->add_filter('on_read', 'loader_on_read_tables', 25);

/**
 * On write loader hooks
 * @param $data
 * @param $table
 * @return mixed
 */
function loader_on_write_tables($data, $table) {
	$hooks = Hooks::getInstance();
	$data  = $hooks->apply_filters('on_write_' . $table, $data, $table);

	return $data;
}

$hooks->add_filter('on_write', 'loader_on_write_tables', 25);


/**
 * On edit loader hooks
 * @param $data
 * @param $table
 * @return mixed
 */
function loader_on_edit_tables($data, $table) {
	$hooks = Hooks::getInstance();
	$data  = $hooks->apply_filters('on_edit_' . $table, $data, $table);

	return $data;
}

$hooks->add_filter('on_edit', 'loader_on_edit_tables', 25);