<?php
/**
 * Hooks - Actions.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */
use marcocesarato\DatabaseAPI\API;
use marcocesarato\DatabaseAPI\Response;

/**
 * Custom API Call.
 *
 * @param $query
 */
function action_custom_api_call($query)
{
    $api = API::getInstance();
    $api->query['part_1'] = $api->query['db'];
    $api->query['part_2'] = (!empty($api->query['limit']) ? $api->query['limit'] : $api->query['table']);
    $api->query['part_3'] = (!empty($api->query['id']) ? $api->query['id'] : $api->query['table']);
    $api->query['part_4'] = null;

    if (!empty($api->query['where']) && count($api->query['where']) == 1) {
        $api->query['part_4'] = reset($api->query['where']);
        $api->query['part_3'] = key($api->query['where']);
    }
}

/**
 * On error.
 *
 * @param $message
 * @param $code
 */
function action_on_error($message, $code)
{
}

/**
 * Login Custom.
 *
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
    Response::error("Invalid authentication!", 401);
}*/
