<?php
/**
 * Hooks - Actions
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

/**
 * Custom API Call
 * @param $query
 */
function action_custom_api_call() {
	$api                  = API::getInstance();
	$api->query['part_1'] = $api->query['db'];
	$api->query['part_2'] = $api->query['table'];
	$api->query['part_3'] = $api->query['id'];
}

/**
 * Login Custom
 * @param $query
 */
/*function login_custom($query){

	$auth = Auth::getInstance();
	$db = API::getConnection(); // PDO Object
	$api = API::getInstance();

	$user = strtolower($query['user_id']);

    //....

    if($login){
        // Login
        $results = array((object)array(
            "token" => $token,
            "id" => $user_row['id'],
            "is_admin" => false,
        ));
        $auth->logger->debug($results);
        $api->render($results);

    }
	Request::error("Invalid authentication!", 401);
}*/