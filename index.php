<?php
/**
 * Index.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
use marcocesarato\DatabaseAPI\API;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE');

include __DIR__ . '/includes/loader.php';
API::run();
