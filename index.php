<?php
/**
 * Index
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

include dirname(__FILE__) . '/includes/loader.php';
$AUTH = Auth::getInstance();
$API = API::getInstance();
$query = $API->parse_params();
$results = $API->query();
$renderer = 'render_' . $query['format'];
$API->$renderer($results, $query);
