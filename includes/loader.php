<?php
/**
 * Loader
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2019
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */

use marcocesarato\DatabaseAPI\API;
use marcocesarato\DatabaseAPI\Logger;

define("__API_ROOT__", realpath(dirname(__FILE__) . '/..'));

ini_set("zend.ze1_compatibility_mode", 0);
ini_set('memory_limit', '512M');
set_time_limit(3600);

// Compatibility
require_once(__API_ROOT__ . '/includes/compatibility.php');
require_once(__API_ROOT__ . '/includes/functions.php');

disable_php_errors();

// Classes
require_once(__API_ROOT__ . '/includes/classes/Hooks.php');
require_once(__API_ROOT__ . '/includes/classes/Logger.php');
require_once(__API_ROOT__ . '/includes/classes/DatabaseErrorParser.php');
require_once(__API_ROOT__ . '/includes/classes/Request.php');
require_once(__API_ROOT__ . '/includes/classes/Auth.php');
require_once(__API_ROOT__ . '/includes/classes/API.php');

// Hooks
require_once(__API_ROOT__ . '/hooks/loader.hooks.php');
require_once(__API_ROOT__ . '/hooks.php');

// Config
require_once(__API_ROOT__ . '/config.php');

Logger::getInstance()->setLog(__API_ROOT__ . "/logs/", "log-" . date("Y-m-d") . ".log");

API::registerDatasets(unserialize(__API_DATASETS__));
