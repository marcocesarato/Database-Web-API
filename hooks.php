<?php
/**
 * Hooks - Register
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

require_once(__API_ROOT__ . '/hooks/loader.hooks.php');

$hooks = Hooks::getInstance();

/**
 * Custom API Call
 * @return mixed or die (with mixed return just skip to next action until 404 error)
 */
$hooks->add_action('custom_api_call', 'action_custom_api_call', 1);

/**
 * On error
 * @param $message
 * @param $code
 */
$hooks->add_action('on_error', 'action_on_error');

/**
 * Add restriction on where conditions for each query
 * @param $restriction
 * @param $table
 * @param $permission
 * @return mixed
 */
$hooks->add_filter('sql_restriction', 'filter_sql_restriction');

/**
 * Return if can select
 * @param $permission
 * @param $table
 * @return mixed
 */
$hooks->add_filter('can_read', 'filter_can_read');

/**
 * Return if can insert
 * @param $permission
 * @param $table
 * @return mixed
 */
$hooks->add_filter('can_write', 'filter_can_write');

/**
 * Return if can update
 * @param $permission
 * @param $table
 * @return mixed
 */
$hooks->add_filter('can_edit', 'filter_can_edit');

/**
 * Return if can delete
 * @param $permission
 * @param $table
 * @return mixed
 */
$hooks->add_filter('can_delete', 'filter_can_delete');

/**
 * On read
 * @param $data
 * @param $table
 * @return mixed
 */
$hooks->add_filter('on_read', 'filter_on_read');

/**
 * On write
 * @param $data
 * @param $table
 * @return mixed
 */
$hooks->add_filter('on_write', 'filter_on_write');

/**
 * On edit
 * @param $data
 * @param $table
 * @return mixed
 */
$hooks->add_filter('on_edit', 'filter_on_edit');

/**
 * Validate Authentication
 * @param $is_valid
 * @param $user_row
 * @return mixed
 */
$hooks->add_filter('auth_validate_user', 'filter_auth_validate_user');

/**
 * Validate token
 * @param $is_valid
 * @param $token
 * @return bool
 */
$hooks->add_filter('auth_validate_token', 'filter_auth_validate_token');


/**
 * Filter user auth login
 * @param $user_id
 * @return string
 */
$hooks->add_filter('auth_user_id', 'filter_auth_user_id');

/**
 * Bypass authentication
 * @param $bypass
 * @return bool
 */
$hooks->add_filter('auth_bypass', 'filter_auth_bypass');

/**
 * Login data result
 * @param $data
 * @return string|false
 */
$hooks->add_filter('auth_login', 'filter_auth_login');

/**
 * Check if is a login request
 * @param $is_valid_request
 * @param $query
 * @return string|false
 */
$hooks->add_filter('auth_login_request', 'filter_auth_login_request');

/**
 * Token check data result
 * @param $data
 * @return string|false
 */
$hooks->add_filter('auth_token_check', 'filter_auth_token_check');

/**
 * Render
 * @param $data
 * @param $query
 * @param $method
 * @return array
 */
$hooks->add_filter('render', 'filter_render');
