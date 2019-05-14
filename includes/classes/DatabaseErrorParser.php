<?php

namespace marcocesarato\DatabaseAPI;

/**
 * Database error parser Class
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2019
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */

class DatabaseErrorParser {
	public function __construct() {
	}

	public static function errorMessage($error) {

		$code    = $error->getCode();
		$message = $error->getMessage();

		$error = "($code) Oops something's gone wrong!";

		$logger = Logger::getInstance();
		$logger->error("($code) $message");

		return $error;
	}
}