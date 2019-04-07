<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
$method = $_SERVER['REQUEST_METHOD'];

// Parse GET params
$source = $_SERVER['QUERY_STRING'];

parse_str($source, $params);


// Parse POST, PUT, DELETE params
if ($method != 'GET' && $method != 'DELETE') {
	$source_input = file_get_contents("php://input");
	parse_str($source_input, $params_input);
	$params = array_merge($params, $params_input);
}

$params['__METHOD__'] = $method;

switch ($method) {
	case 'PUT':
		echo json_encode($params);
		break;
	case 'POST':
		echo json_encode($params);
		break;
	case 'GET':
		echo json_encode($params);
		break;
	default:
		echo json_encode($params);
		break;
}
