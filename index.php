<?php
/**
 * Index
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2018
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */

include dirname(__FILE__) . '/includes/loader.php';
$AUTH = Auth::getInstance();
$API = API::getInstance();
$query = $API->parse_params();
$results = $API->query();
$renderer = 'render_' . $query['format'];
$API->$renderer($results, $query);
