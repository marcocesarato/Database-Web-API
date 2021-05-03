<?php

namespace marcocesarato\DatabaseAPI;

/**
 * Response Class.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
class Response
{
    public static $instance;
    public $input;

    /**
     * Return success response.
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
     *
     * @return array
     */
    public static function failed()
    {
        $message = 'Bad request';
        $code = 400;

        self::error($message, $code);

        return ['response' => (object)['status' => $code, 'message' => $message]];
    }

    /**
     * Return failed response.
     *
     * @param string $msg
     *
     * @return array
     */
    public static function noPermissions($msg = '')
    {
        $message = 'No permissions ' . $msg;
        $code = 403;

        self::error($message, $code);

        return ['response' => (object)['status' => $code, 'message' => $message]];
    }

    /**
     * Halt the program with an "Internal server error" and the specified message.
     *
     * @param string|object $error the error or a (PDO) exception object
     * @param int           $code  (optional) the error code with which to respond
     * @param bool          $internal if true will fallback to the first endpoint available
     *
     * @return array
     */
    public static function error($error, $code = 500, $internal = false)
    {
        $hooks = Hooks::getInstance();
        if ($internal) {
            $hooks->do_action('endpoint');
        }
        $hooks->do_action('public_endpoint');
        $hooks->do_action('on_error', $error, $code);

        $api = API::getInstance();
        $logger = Logger::getInstance();
        if (is_object($error) && method_exists($error, 'getMessage') && method_exists($error, 'getCode')) {
            $message = DatabaseErrors::errorMessage($error);
            $results = [
                'response' => (object)['status' => 400, 'message' => $message],
            ];
            $logger->error('ERROR_' . $code . ': ' . $error);
            $api->render($results);
        }
        http_response_code($code);
        $error = trim($error);
        $logger->error('ERROR_' . $code . ': ' . $error);
        $results = [
            'response' => (object)['status' => $code, 'message' => Request::sanitizeHtmlentities($error)],
        ];
        $api->render($results);

        return ['response' => (object)['status' => $code, 'message' => $error]];
    }
}
