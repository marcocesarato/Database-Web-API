<?php
/**
 * Loader
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2018
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */

// PHP Errors

ini_set("zend.ze1_compatibility_mode", "0");
ini_set('memory_limit', '512M');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0); // E_ALL
set_time_limit(3600);

define("__ROOT__", realpath(dirname(__FILE__) . '/..'));

require_once(__ROOT__ . '/config.php');
require_once(__ROOT__ . '/includes/compatibility.php');
require_once(__ROOT__ . '/includes/functions.php');
if(!class_exists('PDO'))
	require_once(__ROOT__ . '/includes/classes/PDO/PDO.class.php');
require_once(__ROOT__ . '/includes/classes/hooks.class.php');
require_once(__ROOT__ . '/includes/classes/logger.class.php');
require_once(__ROOT__ . '/includes/classes/dberrorparser.class.php');
require_once(__ROOT__ . '/includes/classes/request.class.php');
require_once(__ROOT__ . '/includes/classes/auth.class.php');
require_once(__ROOT__ . '/includes/classes/api.class.php');
require_once(__ROOT__ . '/hooks.php');

Logger::getInstance()->setLog(__ROOT__."/logs/","log-".date("Y-m-d").".log");

API::registerDatasets(unserialize(__DATASETS__));
