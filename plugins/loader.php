<?php
/**
 * Hooks - Loader.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */
use marcocesarato\DatabaseAPI\Hooks;

require_once __API_DIR_PLUGINS__ . '/functions.php';
require_once __API_DIR_PLUGINS__ . '/filters.php';
require_once __API_DIR_PLUGINS__ . '/actions.php';

$hooks = Hooks::getInstance();

// Include plugins
$dir = new RecursiveDirectoryIterator(__API_DIR_PLUGINS__);
$ite = new RecursiveIteratorIterator($dir);
$pattern = '/^.+\/([^\/]+)\/([^\/]+)\.hooks\.php$/';
$files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);
foreach ($files as $file) {
    include_once $file[0];
}

// Register loaders
$hooks->add_filter('on_read', 'loader_on_read_tables', 25);
$hooks->add_filter('on_write', 'loader_on_write_tables', 25);
$hooks->add_filter('on_edit', 'loader_on_edit_tables', 25);

// Register filters and actions
$hooks->add_action('endpoint', 'action_endpoint', 1);
$hooks->add_action('public_endpoint', 'action_endpoint', 1);
$hooks->add_action('on_error', 'action_on_error');
$hooks->add_filter('request_input_query', 'filter_request_input_query');
$hooks->add_filter('sql_restriction', 'filter_sql_restriction');
$hooks->add_filter('can_read', 'filter_can_read');
$hooks->add_filter('can_write', 'filter_can_write');
$hooks->add_filter('can_edit', 'filter_can_edit');
$hooks->add_filter('can_delete', 'filter_can_delete');
$hooks->add_filter('on_read', 'filter_on_read');
$hooks->add_filter('on_write', 'filter_on_write');
$hooks->add_filter('on_write_exists', 'filter_on_write_exists');
$hooks->add_filter('on_edit', 'filter_on_edit');
$hooks->add_filter('auth_validate_user', 'filter_auth_validate_user');
$hooks->add_filter('auth_validate_token', 'filter_auth_validate_token');
$hooks->add_filter('auth_user_id', 'filter_auth_user_id');
$hooks->add_filter('auth_bypass', 'filter_auth_bypass');
$hooks->add_filter('auth_login', 'filter_auth_login');
$hooks->add_filter('render', 'filter_render');
$hooks->add_filter('get_query_table', 'filter_get_query_table');
$hooks->add_filter('selected_columns', 'filter_selected_columns');
$hooks->add_filter('get_query_additional_join_on', 'filter_get_query_additional_join_on');
$hooks->add_filter('get_where_values', 'filter_get_where_values');
$hooks->add_filter('get_patch_values', 'filter_patch_where_values');
$hooks->add_filter('get_delete_values', 'filter_delete_where_values');
$hooks->add_filter('get_query_additional_where', 'filter_get_query_additional_where');
