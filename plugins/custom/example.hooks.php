<?php
/**
 * Hooks - Custom API Call - Example.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */
use marcocesarato\DatabaseAPI\API;
use marcocesarato\DatabaseAPI\Auth;
use marcocesarato\DatabaseAPI\Hooks;

$hooks = Hooks::getInstance();

/**
 * Endpoint example.
 *
 * @return mixed or die (with mixed return just skip to next action until 404 error)
 */
function action_endpoint_example()
{
    $user = Auth::getUser(); // User row
    $api = API::getInstance(); // PDO Object
    $db = API::getConnection('dataset'); // $db MUST NOT BE EMPTY or WILL CAUSE A LOOP

    // Example url: example.com/TOKEN/part_1/part_2/part_3.format
    $part_1 = $api->query['part_1'];
    $part_2 = $api->query['part_2'];
    $part_3 = $api->query['part_3'];

    // example.com/TOKEN/example/something.json
    /*if($part_1 == 'example') {
        $example       = new StdClass();
        $example->id   = '1';
        $example->desc = "Example custom call";
        $api->render(array($example, $example, $example));
    }*/
}

// Private
$hooks->add_action('endpoint', 'action_endpoint_example');
// Public
$hooks->add_action('public_endpoint', 'action_endpoint_example');
