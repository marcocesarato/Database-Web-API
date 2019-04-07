<?php
/**
 * Hooks - example
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

$hooks = Hooks::getInstance();

/**
 * On write doc testate
 * @param $data
 * @return mixed
 */
function filter_on_write_example($data, $table){
	$db = API::getConnection(); // PDO Object
    /*
     $data['uuid'] = uniqid();
    */
	return $data;
}
$hooks->add_filter('on_write_example', 'filter_on_write_example');