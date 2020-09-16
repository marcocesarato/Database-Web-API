<?php

namespace marcocesarato\DatabaseAPI;

/**
 * Response Class.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2019
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
class Response
{
    public static $instance;
    public $input;

    public function __construct()
    {
    }

    /**
     * Return success reponse.
     *
     * @return array
     */
    public static function success()
    {
        http_response_code(200);

        return ['response' => (object)['status' => 200, 'message' => 'OK']];
    }

    /**
     * Return created with success response.
     *
     * @return array
     */
    public static function created()
    {
        http_response_code(201);

        return ['response' => (object)['status' => 201, 'message' => 'OK']];
    }

    /**
     * Return failed response.
     */
    public static function failed()
    {
        self::error('Bad request', 400);
    }

    /**
     * Return failed response.
     *
     * @param string $msg
     */
    public static function noPermissions($msg = '')
    {
        self::error('No permissions ' . $msg, 403);
    }

    /**
     * Halt the program with an "Internal server error" and the specified message.
     *
     * @param string|object $error the error or a (PDO) exception object
     * @param int           $code  (optional) the error code with which to respond
     * @param bool          $custom_call
     */
    public static function error($error, $code = 500, $custom_call = false)
    {
        $hooks = Hooks::getInstance();
        if ($custom_call) {
            $hooks->do_action('custom_api_call');
        }
        $hooks->do_action('on_error', $error, $code);

        $api = API::getInstance();
        $logger = Logger::getInstance();
        if (is_object($error) && method_exists($error, 'getMessage') && method_exists($error, 'getCode')) {
            $message = DatabaseErrors::errorMessage($error);
            $results = [
                'response' => (object)['status' => 400, 'message' => $message],
            ];
            $logger->error($code . ' - ' . $error);
            $api->render($results);
        }
        http_response_code($code);
        $error = trim($error);
        $logger->error($code . ' - ' . $error);
        $results = [
            'response' => (object)['status' => $code, 'message' => Request::sanitizeHtmlentities($error)],
        ];
        $api->render($results);
    }
}
