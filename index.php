<?php
/**
 * Index
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2019
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */

use marcocesarato\DatabaseAPI\API;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE');

include dirname(__FILE__) . '/includes/loader.php';
API::run();
