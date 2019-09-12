<?php
/**
 * Loader.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2019
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */

namespace marcocesarato\DatabaseAPI;

define('__API_ROOT__', realpath(dirname(__FILE__) . '/..'));
define('__API_DIR_PLUGINS__', realpath(__API_ROOT__ . '/plugins/'));
define('__API_DIR_INCLUDES__', realpath(__API_ROOT__ . '/includes/'));
define('__API_DIR_CLASSES__', realpath(__API_DIR_INCLUDES__ . '/classes/'));
define('__API_DIR_LIBS__', realpath(__API_DIR_CLASSES__ . '/libs/'));
define('__API_DIR_LOGS__', realpath(__API_ROOT__ . '/logs/'));

ini_set('zend.ze1_compatibility_mode', 0);
ini_set('memory_limit', '512M');
set_time_limit(3600);

// Compatibility
require_once __API_DIR_INCLUDES__ . '/compatibility.php';
require_once __API_DIR_INCLUDES__ . '/functions.php';

disable_php_errors();

// Libs
require_once __API_DIR_CLASSES__ . '/Hooks.php';

// Classes
require_once __API_DIR_CLASSES__ . '/Logger.php';
require_once __API_DIR_CLASSES__ . '/DatabaseErrors.php';
require_once __API_DIR_CLASSES__ . '/Request.php';
require_once __API_DIR_CLASSES__ . '/Response.php';
require_once __API_DIR_CLASSES__ . '/Auth.php';
require_once __API_DIR_CLASSES__ . '/API.php';
require_once __API_DIR_CLASSES__ . '/Dump.php';

// Hooks
require_once __API_DIR_PLUGINS__ . '/loader.php';

// Config
require_once __API_ROOT__ . '/config.php';

Logger::getInstance()->setLog(__API_DIR_LOGS__ . '/', 'log-' . date('Y-m-d') . '.log');

API::registerDatasets(unserialize(__API_DATASETS__));
