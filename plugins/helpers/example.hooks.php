<?php

/**
 * Hooks - Helpers.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */
use marcocesarato\DatabaseAPI\API;
use marcocesarato\DatabaseAPI\Auth;
use marcocesarato\DatabaseAPI\Hooks;
use marcocesarato\DatabaseAPI\Response;

$hooks = Hooks::getInstance();

/**
 * On write helper hooks.
 *
 * @param $data
 * @param $table
 *
 * @return mixed
 */
function helper_on_write($data, $table)
{
    $user = Auth::getUser(); // User row
    $api = API::getInstance();

    /*if($api->checkColumn('date_entered', $table)) {
        $data['date_entered'] = date('Y-m-d');
    }
    if($api->checkColumn('assigned_user_id', $table)) {
        $data['assigned_user_id'] = $user['id'];
    };
    if($api->checkColumn('created_by', $table)) {
        $data['created_by'] = $user['id'];
    }
    if($api->checkColumn('modified_user_id', $table)) {
        $data['modified_user_id'] = $user['id'];
    }
    if($api->checkColumn('date_modified', $table)) {
        $data['date_modified'] = date('Y-m-d');
    }*/

    return $data;
}

$hooks->add_filter('on_write', 'helper_on_write', 100);

/**
 * On edit helper hooks.
 *
 * @param $data
 * @param $table
 *
 * @return mixed
 */
function helper_on_edit($data, $table)
{
    $user = Auth::getUser(); // User row
    $api = API::getInstance();

    /*if($api->checkColumn('modified_user_id', $table)) {
        $data['modified_user_id'] = $user['id'];
    }
    if($api->checkColumn('date_modified', $table)) {
        $data['date_modified'] = date('Y-m-d');
    }*/

    return $data;
}

$hooks->add_filter('on_edit', 'helper_on_edit', 100);

/**
 * Patch where values.
 *
 * @param $where_values
 * @param $table
 *
 * @return mixed
 */
function helper_patch_where_values($where_values, $table)
{
    if (empty($where_values)) {
        Response::error('Invalid update condition', 404);
    }

    return $where_values;
}

$hooks->add_filter('patch_where_values', 'helper_patch_where_values', 100);

/**
 * Delete where values.
 *
 * @param $where_values
 * @param $table
 *
 * @return mixed
 */
function helper_delete_where_values($where_values, $table)
{
    if (empty($where_values)) {
        Response::error('Invalid delete condition', 404);
    }

    return $where_values;
}

$hooks->add_filter('delete_where_values', 'helper_delete_where_values', 100);
