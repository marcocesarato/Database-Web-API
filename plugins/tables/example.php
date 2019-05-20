<?php
/**
 * Hooks - example
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

use marcocesarato\DatabaseAPI\API;
use marcocesarato\DatabaseAPI\Hooks;

$hooks = Hooks::getInstance();

/**
 * On write example table (POST/PUT request)
 * @param $data
 * @return mixed
 */
function filter_on_write_example($data, $table) {
	$db = API::getConnection(); // PDO Object
	/*
	 $data['uuid'] = uniqid();
	 $data['timestamp'] = time();
	*/

	return $data;
}

$hooks->add_filter('on_write_example', 'filter_on_write_example');